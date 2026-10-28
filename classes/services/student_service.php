<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

/** See docs/implementation-plan.md for invariants and implementation order. */
class student_service {
    public function get_feed(array $params, int $actorid): array {
        // TODO: Check course access and filter PUBLISHED material by visibility.
        // TODO: Apply ascending document-ID pagination with limit 1..100; return the feed DTO.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_feed');
    }

    public function get_content(array $params, int $actorid): array {
        // TODO: Resolve the published document within courseid and enforce visibility.
        // TODO: Return student-safe content without answer correctness or pre-submission explanations.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_content');
    }

}
