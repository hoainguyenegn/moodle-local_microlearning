<?php
namespace local_microlearning\task;

defined('MOODLE_INTERNAL') || die();

use local_microlearning\ai\engine_exception;
use local_microlearning\ai\output_validator;
use local_microlearning\ai\pdf_engine_client;
use local_microlearning\repositories\ai_task_repository;
use local_microlearning\repositories\chunk_repository;
use local_microlearning\repositories\material_repository;

/**
 * Adhoc background worker that sends a queued PDF document to the Python AI Engine,
 * validates the structured JSON output, and atomically persists chunks, summaries,
 * quizzes, and flashcards.
 *
 * Queue only after task metadata commits; task ID goes in custom_data.
 */
class process_material extends \core\task\adhoc_task {
    /** Maximum task execution attempts before permanent FAILED status */
    public const MAX_ATTEMPTS = 3;

    /**
     * Execute the background PDF processing job.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $taskid = (int) ($data->taskid ?? 0);
        if ($taskid <= 0) {
            return;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_microlearning_task');
        $lock = $lockfactory->get_lock('task_' . $taskid, 30);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'local_microlearning');
        }

        $tasksrepo = new ai_task_repository();
        $materialsrepo = new material_repository();
        $chunksrepo = new chunk_repository();

        try {
            $claimed = $tasksrepo->claim_queued(['taskid' => $taskid]);
            if (empty($claimed)) {
                // Already RUNNING, DONE, or FAILED.
                return;
            }

            $docid = (int) $claimed['docid'];
            $attempts = (int) $claimed['attempts'];
            [, $requestedoutputs] = output_validator::parse_outputs_csv((string) $claimed['outputs']);

            $doc = $materialsrepo->get_by_id($docid);
            if (!$doc) {
                $this->fail_permanently($tasksrepo, $materialsrepo, $taskid, 0, 'material_missing');
                return;
            }

            $fs = get_file_storage();
            $storedfile = $fs->get_file_by_id((int) $doc->fileid);
            if (!$storedfile || $storedfile->is_directory()) {
                $this->fail_permanently($tasksrepo, $materialsrepo, $taskid, $docid, 'source_file_missing');
                return;
            }

            // Keep external AI engine call strictly OUTSIDE any database transaction.
            $client = new pdf_engine_client();
            $rawresponse = $client->process_pdf(
                $storedfile->get_content(),
                $storedfile->get_filename(),
                $taskid,
                $docid,
                (string) $claimed['outputs'],
                (string) $doc->title
            );

            $validated = output_validator::validate($rawresponse, $taskid, $docid, $requestedoutputs);

            // Commit chunks, summaries, quizzes, flashcards, and terminal statuses atomically.
            $transaction = $DB->start_delegated_transaction();
            $chunksrepo->replace_task_output([
                'docid' => $docid,
                'taskid' => $taskid,
                'validated' => $validated,
            ]);
            $tasksrepo->finish_once([
                'taskid' => $taskid,
                'status' => 'DONE',
                'errorcode' => '',
            ]);
            $materialsrepo->update_status($docid, 'READY_FOR_REVIEW');
            $tasksrepo->log_event(
                $taskid,
                'INFO',
                'task_completed',
                (int) $validated['usage']['tokens'],
                (int) $validated['usage']['latency_ms']
            );
            $transaction->allow_commit();
        } catch (engine_exception $e) {
            $this->handle_failure(
                $tasksrepo,
                $materialsrepo,
                $taskid,
                (int) ($claimed['docid'] ?? 0),
                (int) ($claimed['attempts'] ?? 1),
                $e->engineerrorcode,
                $e->retryable,
                $e
            );
        } catch (\Throwable $e) {
            $this->handle_failure(
                $tasksrepo,
                $materialsrepo,
                $taskid,
                (int) ($claimed['docid'] ?? 0),
                (int) ($claimed['attempts'] ?? 1),
                'unexpected_worker_error',
                false,
                $e
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * Handle transient vs permanent failures with bounded retry count.
     *
     * @param ai_task_repository $tasksrepo
     * @param material_repository $materialsrepo
     * @param int $taskid
     * @param int $docid
     * @param int $attempts
     * @param string $errorcode
     * @param bool $retryable
     * @param \Throwable $cause
     * @return void
     */
    private function handle_failure(
        ai_task_repository $tasksrepo,
        material_repository $materialsrepo,
        int $taskid,
        int $docid,
        int $attempts,
        string $errorcode,
        bool $retryable,
        \Throwable $cause
    ): void {
        if ($retryable && $attempts < self::MAX_ATTEMPTS) {
            $tasksrepo->requeue_for_retry($taskid, $errorcode);
            $tasksrepo->log_event($taskid, 'WARN', 'retry:' . $errorcode);
            // Rethrow so Moodle's adhoc_task runner schedules a backoff retry.
            throw $cause;
        }
        $this->fail_permanently($tasksrepo, $materialsrepo, $taskid, $docid, $errorcode);
    }

    /**
     * Mark task and document as FAILED in a single transaction.
     *
     * @param ai_task_repository $tasksrepo
     * @param material_repository $materialsrepo
     * @param int $taskid
     * @param int $docid
     * @param string $errorcode
     * @return void
     */
    private function fail_permanently(
        ai_task_repository $tasksrepo,
        material_repository $materialsrepo,
        int $taskid,
        int $docid,
        string $errorcode
    ): void {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $tasksrepo->finish_once([
            'taskid' => $taskid,
            'status' => 'FAILED',
            'errorcode' => $errorcode,
        ]);
        if ($docid > 0) {
            $materialsrepo->update_status($docid, 'FAILED');
        }
        $tasksrepo->log_event($taskid, 'ERROR', $errorcode);
        $transaction->allow_commit();
    }
}
