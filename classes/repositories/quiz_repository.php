<?php
namespace local_microlearning\repositories;
defined('MOODLE_INTERNAL') || die();

class quiz_repository {
    public function get_published(array $criteria): array {
        // TODO: Query quiz/questions/options through a published document in the requested course.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'quiz.get_published');
    }

    public function save_attempt_once(array $criteria): array {
        // TODO: Insert attempt/details under the service transaction and user/request-key uniqueness constraint.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'quiz.save_attempt_once');
    }

}
