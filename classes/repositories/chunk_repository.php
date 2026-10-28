<?php
namespace local_microlearning\repositories;
defined('MOODLE_INTERNAL') || die();

class chunk_repository {
    public function get_workspace(array $criteria): array {
        // TODO: Load document-scoped chunks and generated children, preserving source positions.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'chunk.get_workspace');
    }

    public function replace_task_output(array $criteria): array {
        // TODO: Persist validated task output atomically and prevent duplicate/reparented child records.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'chunk.replace_task_output');
    }

}
