<?php
namespace local_microlearning\repositories;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for local_microlearning_tasks and local_microlearning_logs.
 */
class ai_task_repository {

    /**
     * Find an existing task by (requestedby, requestkey) for idempotency and conflict detection.
     *
     * @param array $criteria ['requestedby' => int, 'requestkey' => string]
     * @return array Empty array if not found, or associative task record
     */
    public function find_by_request_key(array $criteria): array {
        global $DB;
        $record = $DB->get_record('local_microlearning_tasks', [
            'requestedby' => (int) $criteria['requestedby'],
            'requestkey' => (string) $criteria['requestkey'],
        ]);
        return $record ? (array) $record : [];
    }

    /**
     * Retrieve task record by primary key ID.
     *
     * @param int $taskid
     * @return \stdClass|null
     */
    public function get_by_id(int $taskid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_microlearning_tasks', ['id' => $taskid]);
        return $record ?: null;
    }

    /**
     * Insert a new QUEUED AI processing task.
     *
     * @param array $data ['docid' => int, 'requestedby' => int, 'requestkey' => string, 'inputhash' => string, 'outputs' => string]
     * @return array Created task associative array
     */
    public function create_queued(array $data): array {
        global $DB;
        $now = time();
        $record = (object) [
            'docid' => (int) $data['docid'],
            'requestedby' => (int) $data['requestedby'],
            'requestkey' => (string) $data['requestkey'],
            'inputhash' => (string) $data['inputhash'],
            'outputs' => (string) $data['outputs'],
            'status' => 'QUEUED',
            'attempts' => 0,
            'errorcode' => '',
            'startedat' => 0,
            'finishedat' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = (int) $DB->insert_record('local_microlearning_tasks', $record);
        return (array) $record;
    }

    /**
     * Transition a task from QUEUED to RUNNING under service lock and increment attempts.
     *
     * @param array $criteria ['taskid' => int]
     * @return array Updated task associative array, or empty array if not in QUEUED state
     */
    public function claim_queued(array $criteria): array {
        global $DB;
        $taskid = (int) $criteria['taskid'];
        $task = $DB->get_record('local_microlearning_tasks', ['id' => $taskid]);
        if (!$task || $task->status !== 'QUEUED') {
            return [];
        }
        $now = time();
        $task->status = 'RUNNING';
        $task->attempts = (int) $task->attempts + 1;
        $task->errorcode = '';
        if ((int) $task->startedat === 0) {
            $task->startedat = $now;
        }
        $task->timemodified = $now;
        $DB->update_record('local_microlearning_tasks', $task);
        return (array) $task;
    }

    /**
     * Return a RUNNING task back to QUEUED so Moodle adhoc scheduler can retry a transient failure.
     *
     * @param int $taskid
     * @param string $errorcode Sanitised transient error code
     * @return void
     */
    public function requeue_for_retry(int $taskid, string $errorcode): void {
        global $DB;
        $DB->update_record('local_microlearning_tasks', (object) [
            'id' => $taskid,
            'status' => 'QUEUED',
            'errorcode' => substr($errorcode, 0, 100),
            'timemodified' => time(),
        ]);
    }

    /**
     * Persist terminal state (DONE or FAILED) once for a RUNNING task.
     *
     * @param array $criteria ['taskid' => int, 'status' => 'DONE'|'FAILED', 'errorcode' => string]
     * @return array Updated task associative array
     */
    public function finish_once(array $criteria): array {
        global $DB;
        $taskid = (int) $criteria['taskid'];
        $status = (string) $criteria['status'];
        $errorcode = substr((string) ($criteria['errorcode'] ?? ''), 0, 100);
        $task = $DB->get_record('local_microlearning_tasks', ['id' => $taskid], '*', MUST_EXIST);
        if (in_array($task->status, ['DONE', 'FAILED'], true)) {
            return (array) $task;
        }
        $now = time();
        $task->status = $status;
        $task->errorcode = $status === 'DONE' ? '' : $errorcode;
        $task->finishedat = $now;
        $task->timemodified = $now;
        $DB->update_record('local_microlearning_tasks', $task);
        return (array) $task;
    }

    /**
     * Append an operational log entry to local_microlearning_logs.
     *
     * @param int $taskid
     * @param string $level INFO, WARN, or ERROR
     * @param string $eventcode Short event/error code
     * @param int $tokens Total LLM tokens used
     * @param int $latencyms Processing duration in milliseconds
     * @return void
     */
    public function log_event(
        int $taskid,
        string $level,
        string $eventcode,
        int $tokens = 0,
        int $latencyms = 0
    ): void {
        global $DB;
        $now = time();
        $DB->insert_record('local_microlearning_logs', (object) [
            'taskid' => $taskid,
            'level' => substr(strtoupper($level), 0, 10),
            'eventcode' => substr($eventcode, 0, 100),
            'tokens' => max(0, $tokens),
            'latencyms' => max(0, $latencyms),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
