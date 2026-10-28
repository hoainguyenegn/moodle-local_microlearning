<?php
namespace local_microlearning\external;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/** Moodle 4.0-compatible external API support. */
abstract class base_api extends \external_api {
    protected static function authorise(int $courseid, string $capability): void {
        $course = get_course($courseid);
        require_login($course);
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/microlearning:' . $capability, $context);
    }

    protected static function response_schema(): \external_single_structure {
        // TODO: Replace the generic JSON envelope with agreed typed resource return schemas.
        return new \external_single_structure([
            'datajson' => new \external_value(PARAM_RAW, 'JSON resource; see docs/api-contract.md'),
        ]);
    }
}
