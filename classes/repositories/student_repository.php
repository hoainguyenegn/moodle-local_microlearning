<?php
namespace local_microlearning\repositories;
defined('MOODLE_INTERNAL') || die();

class student_repository {
    public function get_published_feed(array $criteria): array {
        // TODO: Query only visible published course documents with stable cursor pagination.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'student.get_published_feed');
    }

}
