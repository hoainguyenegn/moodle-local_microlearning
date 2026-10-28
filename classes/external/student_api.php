<?php
namespace local_microlearning\external;
defined('MOODLE_INTERNAL') || die();

/** Controller contract. Implement business rules in student_service. */
class student_api extends base_api {
    public static function get_feed_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'afterid' => new \external_value(PARAM_INT, 'afterid; see API contract'),
            'limit' => new \external_value(PARAM_INT, 'limit; see API contract'),
        ]);
    }

    public static function get_feed(int $courseid, int $afterid, int $limit): array {
        $params = self::validate_parameters(self::get_feed_parameters(), compact('courseid', 'afterid', 'limit'));
        self::authorise($params['courseid'], 'viewfeed');
        $service = new \local_microlearning\services\student_service();
        $result = $service->get_feed($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_feed_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function get_content_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
        ]);
    }

    public static function get_content(int $courseid, int $docid): array {
        $params = self::validate_parameters(self::get_content_parameters(), compact('courseid', 'docid'));
        self::authorise($params['courseid'], 'viewfeed');
        $service = new \local_microlearning\services\student_service();
        $result = $service->get_content($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_content_returns(): \external_single_structure {
        return self::response_schema();
    }

}
