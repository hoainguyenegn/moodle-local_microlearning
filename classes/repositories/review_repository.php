<?php
namespace local_microlearning\repositories;
defined('MOODLE_INTERNAL') || die();

class review_repository {
    public function save_draft_revision(array $criteria): array {
        // TODO: Update authorised draft records using expected revision; reject stale writes.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'review.save_draft_revision');
    }

    public function publish_revision(array $criteria): array {
        // TODO: Update document and child publication states atomically after service validation.
        // TODO: Agree on criteria/result DTOs; use parameterised Moodle DML and service-owned locks/transactions.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'review.publish_revision');
    }

}
