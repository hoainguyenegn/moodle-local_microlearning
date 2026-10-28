<?php
namespace local_microlearning\external;
defined('MOODLE_INTERNAL') || die();

/** Controller contract. Implement business rules in material_service. */
class material_api extends base_api {
    public static function upload_material_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'draftitemid' => new \external_value(PARAM_INT, 'draftitemid; see API contract'),
            'title' => new \external_value(PARAM_TEXT, 'title; see API contract'),
            'materialtype' => new \external_value(PARAM_ALPHA, 'materialtype; see API contract'),
        ]);
    }

    public static function upload_material(int $courseid, int $draftitemid, string $title, string $materialtype): array {
        $params = self::validate_parameters(self::upload_material_parameters(), compact('courseid', 'draftitemid', 'title', 'materialtype'));
        self::authorise($params['courseid'], 'uploadmaterial');
        $service = new \local_microlearning\services\material_service();
        $result = $service->upload_material($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function upload_material_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function get_material_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
        ]);
    }

    public static function get_material(int $courseid, int $docid): array {
        $params = self::validate_parameters(self::get_material_parameters(), compact('courseid', 'docid'));
        self::authorise($params['courseid'], 'uploadmaterial');
        $service = new \local_microlearning\services\material_service();
        $result = $service->get_material($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_material_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function delete_material_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
        ]);
    }

    public static function delete_material(int $courseid, int $docid): array {
        $params = self::validate_parameters(self::delete_material_parameters(), compact('courseid', 'docid'));
        self::authorise($params['courseid'], 'uploadmaterial');
        $service = new \local_microlearning\services\material_service();
        $result = $service->delete_material($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function delete_material_returns(): \external_single_structure {
        return self::response_schema();
    }

}
