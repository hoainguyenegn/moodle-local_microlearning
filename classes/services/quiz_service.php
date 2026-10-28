<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

/** See docs/implementation-plan.md for invariants and implementation order. */
class quiz_service {
    public function get_quiz(array $params, int $actorid): array {
        // TODO: Resolve quiz through chunk/document to the authorised course.
        // TODO: Require published content and return only StudentQuiz fields.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_quiz');
    }

    public function submit_quiz(array $params, int $actorid): array {
        // TODO: Validate one answer per question, option ownership and published quiz access.
        // TODO: Lock/check the user request key and canonical payload hash for idempotency.
        // TODO: Grade on the server; commit attempt/details atomically and return the original attempt on retry.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'submit_quiz');
    }

    public function get_attempt(array $params, int $actorid): array {
        // TODO: Resolve attempt through quiz/document/course and require the actor owns it.
        // TODO: Return stored score/details with post-submission explanations.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_attempt');
    }

}
