<?php
namespace local_microlearning\task;
defined('MOODLE_INTERNAL') || die();

/** Queue only after task metadata commits; task ID goes in custom_data. */
class process_material extends \core\task\adhoc_task {
    public function execute() {
        // TODO: Claim task with Moodle Lock API; call worker with bounded timeout.
        // TODO: Persist validated output and terminal task/document states in one transaction.
        // TODO: Retry only transient failures with bounded attempts; redact provider errors.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'process_material');
    }
}
