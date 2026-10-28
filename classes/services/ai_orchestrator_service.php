<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

/** See docs/api-contract.md and docs/eerd-design.md for implementation requirements. */
class ai_orchestrator_service {
    public function dispatch_ai(array $params, int $actorid): array {
        // TODO: Validate document ownership/course, requested outputs and canonical request hash.
        // TODO: Lock the document; reuse matching request keys and reject payload conflicts.
        // TODO: Persist a QUEUED task and schedule processing after commit.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'dispatch_ai');
    }

    public function get_task(array $params, int $actorid): array {
        // TODO: Resolve task to document and course; enforce uploader/reviewer access.
        // TODO: Return sanitised task status without raw provider errors or credentials.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_task');
    }

}
