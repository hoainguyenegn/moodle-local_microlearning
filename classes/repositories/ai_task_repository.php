<?php
namespace local_microlearning\repositories;
defined('MOODLE_INTERNAL') || die();

class ai_task_repository {
    public function find_by_request_key(array $criteria): array {
        // TODO: Query by requestedby/requestkey and return the stored input hash for conflict checks.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'ai_task.find_by_request_key');
    }

    public function claim_queued(array $criteria): array {
        // TODO: Update QUEUED to RUNNING only under the service lock; persist attempts/start time.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'ai_task.claim_queued');
    }

    public function finish_once(array $criteria): array {
        // TODO: Persist terminal state once; coordinate successful output writes with the service transaction.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'ai_task.finish_once');
    }

}
