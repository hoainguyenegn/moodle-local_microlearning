<?php
namespace local_microlearning\external;
defined('MOODLE_INTERNAL') || die();

/** Controller contract. Implement business rules in quiz_service. */
class quiz_api extends base_api {
    public static function get_quiz_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'quizid' => new \external_value(PARAM_INT, 'quizid; see API contract'),
        ]);
    }

    public static function get_quiz(int $courseid, int $quizid): array {
        $params = self::validate_parameters(self::get_quiz_parameters(), compact('courseid', 'quizid'));
        self::authorise($params['courseid'], 'viewfeed');
        $service = new \local_microlearning\services\quiz_service();
        $result = $service->get_quiz($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_quiz_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function submit_quiz_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'quizid' => new \external_value(PARAM_INT, 'quizid; see API contract'),
            'requestkey' => new \external_value(PARAM_ALPHANUMEXT, 'requestkey; see API contract'),
            'answersjson' => new \external_value(PARAM_RAW, 'answersjson; see API contract'),
        ]);
    }

    public static function submit_quiz(int $courseid, int $quizid, string $requestkey, string $answersjson): array {
        $params = self::validate_parameters(self::submit_quiz_parameters(), compact('courseid', 'quizid', 'requestkey', 'answersjson'));
        self::authorise($params['courseid'], 'viewfeed');
        $service = new \local_microlearning\services\quiz_service();
        $result = $service->submit_quiz($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function submit_quiz_returns(): \external_single_structure {
        return self::response_schema();
    }

    public static function get_attempt_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'courseid; see API contract'),
            'attemptid' => new \external_value(PARAM_INT, 'attemptid; see API contract'),
        ]);
    }

    public static function get_attempt(int $courseid, int $attemptid): array {
        $params = self::validate_parameters(self::get_attempt_parameters(), compact('courseid', 'attemptid'));
        self::authorise($params['courseid'], 'viewfeed');
        $service = new \local_microlearning\services\quiz_service();
        $result = $service->get_attempt($params, (int) $GLOBALS['USER']->id);
        return ['datajson' => json_encode($result, JSON_THROW_ON_ERROR)];
    }

    public static function get_attempt_returns(): \external_single_structure {
        return self::response_schema();
    }

}
