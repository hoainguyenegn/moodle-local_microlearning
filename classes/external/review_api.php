<?php
namespace local_microlearning\external;
defined('MOODLE_INTERNAL') || die();

/** Controller contract. Implement business rules in review_service. */
class review_api extends base_api {
    public static function get_workspace_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
        ]);
    }

    public static function get_workspace(int $courseid, int $docid): array {
        $params = self::validate_parameters(self::get_workspace_parameters(), compact('courseid', 'docid'));
        self::authorise($params['courseid'], 'reviewcontent');
        $service = new \local_microlearning\services\review_service();
        $result = $service->get_workspace($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_workspace_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function save_review_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
            'revision' => new \external_value(PARAM_INT, 'revision; see API contract'),
            'contentjson' => new \external_value(PARAM_RAW, 'contentjson; see API contract'),
        ]);
    }

    public static function save_review(int $courseid, int $docid, int $revision, string $contentjson): array {
        $params = self::validate_parameters(self::save_review_parameters(), compact('courseid', 'docid', 'revision', 'contentjson'));
        self::authorise($params['courseid'], 'reviewcontent');
        $service = new \local_microlearning\services\review_service();
        $result = $service->save_review($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function save_review_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function publish_material_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
            'revision' => new \external_value(PARAM_INT, 'revision; see API contract'),
        ]);
    }

    public static function publish_material(int $courseid, int $docid, int $revision): array {
        $params = self::validate_parameters(self::publish_material_parameters(), compact('courseid', 'docid', 'revision'));
        self::authorise($params['courseid'], 'reviewcontent');
        $service = new \local_microlearning\services\review_service();
        $result = $service->publish_material($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function publish_material_returns(): \external_single_structure {
        return self::response_schema();
    }

}
