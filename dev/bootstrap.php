<?php
// Local development bootstrap: run only from the Moodle container CLI.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');
$admin = get_admin();
\core\session\manager::set_user($admin);
set_config('speechurl', 'http://speech:8000', 'local_microlearning');
$env = parse_ini_file('/var/www/html/local/microlearning/engine/.env', false, INI_SCANNER_RAW);
set_config('speechtoken', $env['SPEECH_TOKEN'], 'local_microlearning');
$course = $DB->get_record('course', ['shortname' => 'MICRO-DEMO']);
if (!$course) {
    $categories = $DB->get_records('course_categories', null, 'id ASC', '*', 0, 1);
    $category = reset($categories);
    $course = create_course((object)[
        'fullname' => 'Phòng thử nghiệm bài giảng AI', 'shortname' => 'MICRO-DEMO',
        'category' => $category->id, 'format' => 'topics',
        'summary' => 'Chọn AI lecture narration trong menu khóa học để thử tạo lời giảng tiếng Việt.',
    ]);
}
echo 'COURSE_ID=' . $course->id . PHP_EOL;
echo 'NARRATION_URL=' . $CFG->wwwroot . '/local/microlearning/narration.php?courseid=' . $course->id . PHP_EOL;
