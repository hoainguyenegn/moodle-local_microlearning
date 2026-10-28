<?php
namespace local_microlearning\external;
defined('MOODLE_INTERNAL') || die();

/** Controller contract. Implement business rules in ai_orchestrator_service. */
class ai_task_api extends base_api {
    public static function dispatch_ai_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'docid' => new \external_value(PARAM_INT, 'docid; see API contract'),
            'requestkey' => new \external_value(PARAM_ALPHANUMEXT, 'requestkey; see API contract'),
            'outputs' => new \external_value(PARAM_TEXT, 'outputs; see API contract'),
        ]);
    }

    public static function dispatch_ai(int $courseid, int $docid, string $requestkey, string $outputs): array {
        $params = self::validate_parameters(self::dispatch_ai_parameters(), compact('courseid', 'docid', 'requestkey', 'outputs'));
        self::authorise($params['courseid'], 'uploadmaterial');
        $service = new \local_microlearning\services\ai_orchestrator_service();
        $result = $service->dispatch_ai($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function dispatch_ai_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function get_task_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'taskid' => new \external_value(PARAM_INT, 'taskid; see API contract'),
        ]);
    }

    public static function get_task(int $courseid, int $taskid): array {
        $params = self::validate_parameters(self::get_task_parameters(), compact('courseid', 'taskid'));
        self::authorise($params['courseid'], 'uploadmaterial');
        $service = new \local_microlearning\services\ai_orchestrator_service();
        $result = $service->get_task($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_task_returns(): \external_single_structure {
        return self::response_schema();
    }

}
