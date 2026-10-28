<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

/** See docs/implementation-plan.md for invariants and implementation order. */
class review_service {
    public function get_workspace(array $params, int $actorid): array {
        // TODO: Load draft chunks, summaries and quizzes for the authorised document/course.
        // TODO: Return the current revision and teacher-only correctness/explanation fields.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_workspace');
    }

    public function save_review(array $params, int $actorid): array {
        // TODO: Validate contentjson, nested IDs, editable fields and expected document revision.
        // TODO: Lock the draft, update existing content and increment revision in one transaction.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'save_review');
    }

    public function publish_material(array $params, int $actorid): array {
        // TODO: Lock the document and reject stale revisions or invalid lifecycle states.
        // TODO: Validate complete content and publish document/children atomically.
        // TODO: Freeze quiz content once student attempts exist.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'publish_material');
    }

}
