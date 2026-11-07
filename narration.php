<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ui.php');
$courseid = required_param('courseid', PARAM_INT);
$context = \local_microlearning\services\narration_service::authorise($courseid);
$url = new moodle_url('/local/microlearning/narration.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/microlearning/workspace.css'));
$PAGE->set_title('Tạo lời giảng');
$PAGE->set_heading('Tạo lời giảng');
$voices = [];
$unavailable = false;
try {
    foreach (\local_microlearning\services\speech_client::request('/v1/voices') as $voice) {
        $voices[$voice['id']] = trim(preg_replace('/[\x{2600}-\x{27BF}\x{1F000}-\x{1FAFF}\x{FE0F}]/u', '', $voice['label']));
    }
} catch (Exception $e) {
    $unavailable = true;
}
foreach ($DB->get_records('local_microlearning_voices', ['courseid' => $courseid, 'userid' => $USER->id]) as $voice) {
    $voices['custom:' . $voice->id] = 'Giọng của tôi: ' . $voice->name;
}
$form = new \local_microlearning\form\narration_form($url, ['courseid' => $courseid, 'voices' => $voices]);
if ($_SERVER['REQUEST_METHOD'] === 'GET' && optional_param('imported', 0, PARAM_BOOL)) {
    $draft = $SESSION->microlearning_import ?? null;
    if ($draft && $draft->courseid == $courseid && $draft->timecreated >= time() - 7200) {
        $form->set_data(['title' => $draft->title, 'script' => $draft->script]);
    }
}
if ($data = $form->get_data()) {
    \local_microlearning\services\narration_service::create($courseid, $data);
    redirect($url);
}
$jobs = $DB->get_records('local_microlearning_narrjobs',
    ['courseid' => $courseid, 'userid' => $USER->id], 'id DESC', '*', 0, 20);
echo $OUTPUT->header();
local_microlearning_workspace_start($courseid, 'narration');

if ($unavailable) {
    echo $OUTPUT->notification(get_string('speechunavailable', 'local_microlearning'), 'warning');
}
local_microlearning_panel_start('Kịch bản và giọng đọc');
$form->display();
echo html_writer::end_tag('section');
echo html_writer::start_div('ml-section-heading');
echo html_writer::tag('h2', 'Bài giảng gần đây');
echo html_writer::link($url, 'Cập nhật', ['class' => 'btn btn-secondary']);
echo html_writer::end_div();
if (!$jobs) {
    echo html_writer::tag('p', 'Chưa có bài giảng.', ['class' => 'ml-empty']);
}
$labels = ['queued' => 'Đang chờ', 'running' => 'Đang tạo', 'done' => 'Hoàn thành', 'failed' => 'Thất bại — hãy kiểm tra dịch vụ rồi tạo lại'];
foreach ($jobs as $job) {
    echo html_writer::start_tag('article', ['class' => 'ml-record']);
    echo html_writer::tag('h3', s($job->title));
    echo html_writer::tag('p', s($labels[$job->status] ?? $job->status), ['class' => 'ml-status']);
    if ($job->status !== 'done') {
        echo html_writer::end_tag('article');
        continue;
    }
    $src = moodle_url::make_pluginfile_url($context->id, 'local_microlearning', 'narration', $job->id, '/', 'audio.wav');
    echo html_writer::tag('audio', '', ['id' => 'audio-' . $job->id, 'controls' => 'controls',
        'preload' => 'none', 'src' => $src->out(false)]);
    echo html_writer::link($src, 'Tải WAV', ['download' => 'audio.wav', 'class' => 'ml-2']);
    echo html_writer::start_tag('details');
    echo html_writer::tag('summary', 'Kịch bản theo slide');
    $payload = json_decode($job->payload, true);
    foreach (json_decode($job->manifest, true) as $slide) {
        echo html_writer::tag('button', s($slide['title']), ['type' => 'button', 'class' => 'btn btn-outline-primary ml-1 speech-seek',
            'data-audio' => 'audio-' . $job->id, 'data-start' => $slide['start']]);
        echo html_writer::tag('p', nl2br(s($payload['slides'][$slide['index']]['text'])),
            ['class' => 'speech-slide', 'data-audio' => 'audio-' . $job->id,
             'data-start' => $slide['start'], 'data-end' => $slide['end']]);
    }
    echo html_writer::end_tag('details');
    echo html_writer::end_tag('article');
}
$PAGE->requires->js(new moodle_url('/local/microlearning/narration.js'));
echo html_writer::end_div();
echo $OUTPUT->footer();
