<?php
namespace local_microlearning\services;

defined('MOODLE_INTERNAL') || die();

use local_microlearning\ai\output_validator;
use local_microlearning\repositories\ai_task_repository;
use local_microlearning\repositories\material_repository;
use local_microlearning\task\process_material;

/**
 * Service coordinating asynchronous AI processing tasks for uploaded source materials.
 *
 * See docs/api-contract.md and docs/eerd-design.md for implementation requirements.
 */
class ai_orchestrator_service {
    /** @var material_repository */
    private material_repository $materials;

    /** @var ai_task_repository */
    private ai_task_repository $tasks;

    /**
     * @param material_repository|null $materials
     * @param ai_task_repository|null $tasks
     */
    public function __construct(?material_repository $materials = null, ?ai_task_repository $tasks = null) {
        $this->materials = $materials ?? new material_repository();
        $this->tasks = $tasks ?? new ai_task_repository();
    }

    /**
     * Validate request, enforce idempotency on (actorid, requestkey), persist a QUEUED task,
     * and schedule the process_material adhoc task after transaction commit.
     *
     * @param array $params ['courseid' => int, 'docid' => int, 'requestkey' => string, 'outputs' => string]
     * @param int $actorid Authenticated Moodle user ID
     * @return array Sanitised AI Task DTO
     */
    public function dispatch_ai(array $params, int $actorid): array {
        global $DB;

        $courseid = (int) $params['courseid'];
        $docid = (int) $params['docid'];
        $requestkey = trim((string) $params['requestkey']);
        $outputsraw = (string) $params['outputs'];

        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $requestkey)) {
            throw new \moodle_exception('invalidrequestkey', 'local_microlearning');
        }
        [$canonicaloutputs] = output_validator::parse_outputs_csv($outputsraw);

        $doc = $this->materials->get_for_course($docid, $courseid);
        if (!$doc) {
            throw new \moodle_exception('materialnotfound', 'local_microlearning');
        }
        if ($doc->materialtype !== 'PDF') {
            throw new \moodle_exception('unsupportedmaterialtype', 'local_microlearning', '', $doc->materialtype);
        }
        if ($doc->status === 'PUBLISHED') {
            throw new \moodle_exception('cannotreprocesspublished', 'local_microlearning');
        }

        // Canonical request hash ensures identical requestkey with altered parameters is rejected as conflict.
        $inputhash = hash('sha256', implode(':', [
            $courseid,
            $docid,
            (int) $doc->fileid,
            $canonicaloutputs,
        ]));

        $lockfactory = \core\lock\lock_config::get_lock_factory('local_microlearning_dispatch');
        $lock = $lockfactory->get_lock('doc_' . $docid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'local_microlearning');
        }

        $creatednew = false;
        try {
            // NOTE (Team Lead - User Entity Injection): requestedby stores $actorid.
            $existing = $this->tasks->find_by_request_key([
                'requestedby' => $actorid,
                'requestkey' => $requestkey,
            ]);
            if (!empty($existing)) {
                if ($existing['inputhash'] !== $inputhash) {
                    throw new \moodle_exception('requestkeyconflict', 'local_microlearning');
                }
                return $this->format_task_dto((object) $existing, $courseid);
            }

            $transaction = $DB->start_delegated_transaction();
            $taskarr = $this->tasks->create_queued([
                'docid' => $docid,
                'requestedby' => $actorid,
                'requestkey' => $requestkey,
                'inputhash' => $inputhash,
                'outputs' => $canonicaloutputs,
            ]);
            $this->materials->update_status($docid, 'PROCESSING');
            $this->tasks->log_event((int) $taskarr['id'], 'INFO', 'task_queued');
            $transaction->allow_commit();
            $creatednew = true;
        } finally {
            $lock->release();
        }

        if ($creatednew) {
            $adhoctask = new process_material();
            $adhoctask->set_custom_data((object) [
                'taskid' => (int) $taskarr['id'],
                'docid' => $docid,
                'courseid' => $courseid,
            ]);
            $adhoctask->set_userid($actorid);
            \core\task\manager::queue_adhoc_task($adhoctask, true);
        }

        return $this->format_task_dto((object) $taskarr, $courseid);
    }

    /**
     * Resolve task to document and course, verify access, and return sanitised task status.
     *
     * @param array $params ['courseid' => int, 'taskid' => int]
     * @param int $actorid Authenticated Moodle user ID
     * @return array Sanitised AI Task DTO
     */
    public function get_task(array $params, int $actorid): array {
        $courseid = (int) $params['courseid'];
        $taskid = (int) $params['taskid'];

        $task = $this->tasks->get_by_id($taskid);
        if (!$task) {
            throw new \moodle_exception('tasknotfound', 'local_microlearning');
        }
        $doc = $this->materials->get_for_course((int) $task->docid, $courseid);
        if (!$doc) {
            throw new \moodle_exception('tasknotfound', 'local_microlearning');
        }

        return $this->format_task_dto($task, $courseid);
    }

    /**
     * Build public Task DTO without leaking provider credentials or internal stack traces.
     *
     * @param \stdClass $task
     * @param int $courseid
     * @return array
     */
    private function format_task_dto(\stdClass $task, int $courseid): array {
        return [
            'taskid' => (int) $task->id,
            'docid' => (int) $task->docid,
            'courseid' => $courseid,
            'requestedby' => (int) $task->requestedby,
            'requestkey' => (string) $task->requestkey,
            'outputs' => (string) $task->outputs,
            'status' => (string) $task->status,
            'attempts' => (int) $task->attempts,
            'errorcode' => (string) $task->errorcode,
            'startedat' => (int) $task->startedat,
            'finishedat' => (int) $task->finishedat,
            'timecreated' => (int) $task->timecreated,
            'timemodified' => (int) $task->timemodified,
        ];
    }
}
