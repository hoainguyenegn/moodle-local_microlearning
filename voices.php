<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ui.php');
$courseid = required_param('courseid', PARAM_INT);
$context = \local_microlearning\services\narration_service::authorise($courseid);
$url = new moodle_url('/local/microlearning/voices.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/microlearning/workspace.css'));
$PAGE->set_title('Giọng của tôi');
$PAGE->set_heading('Giọng của tôi');
$form = new \local_microlearning\form\voice_form($url, ['courseid' => $courseid]);
if ($data = $form->get_data()) {
    $raw = $form->get_file_content('sample');
    // Validate PCM WAV duration/channels with the local engine before retaining a voice.
    \local_microlearning\services\speech_client::request('/v1/validate-reference',
        ['reference' => base64_encode($raw)]);
    $tx = $DB->start_delegated_transaction();
    $id = $DB->insert_record('local_microlearning_voices', (object)[
        'courseid' => $courseid, 'userid' => $USER->id, 'name' => $data->name, 'timecreated' => time()]);
    if (!$form->save_stored_file('sample', $context->id, 'local_microlearning', 'voice', $id, '/', 'reference.wav')) {
        throw new moodle_exception('speechinvalid', 'local_microlearning');
    }
    $tx->allow_commit();
    redirect($url);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('deletevoice', 0, PARAM_INT)) {
    require_sesskey();
    $id = required_param('deletevoice', PARAM_INT);
    $DB->get_record('local_microlearning_voices', ['id' => $id, 'userid' => $USER->id, 'courseid' => $courseid], '*', MUST_EXIST);
    if ($DB->record_exists_select('local_microlearning_narrjobs',
            'voiceid = :voice AND status IN (:queued, :running)',
            ['voice' => $id, 'queued' => 'queued', 'running' => 'running'])) {
        throw new moodle_exception('voicebusy', 'local_microlearning');
    }
    get_file_storage()->delete_area_files($context->id, 'local_microlearning', 'voice', $id);
    $DB->delete_records('local_microlearning_voices', ['id' => $id]);
    redirect($url);
}
echo $OUTPUT->header();
local_microlearning_workspace_start($courseid, 'voices');
local_microlearning_panel_start('Thêm giọng');
$form->display();
echo html_writer::end_tag('section');
echo html_writer::tag('h2', 'Giọng đã lưu', ['class' => 'ml-panel-title']);
if (!$DB->record_exists('local_microlearning_voices', ['courseid' => $courseid, 'userid' => $USER->id])) {
    echo html_writer::tag('p', 'Chưa có giọng riêng.', ['class' => 'ml-empty']);
}
foreach ($DB->get_records('local_microlearning_voices', ['courseid' => $courseid, 'userid' => $USER->id]) as $voice) {
    echo html_writer::start_tag('article', ['class' => 'ml-record']);
    echo html_writer::tag('h3', s($voice->name));
    $src = moodle_url::make_pluginfile_url($context->id, 'local_microlearning', 'voice', $voice->id, '/', 'reference.wav');
    echo html_writer::tag('audio', '', ['controls' => 'controls', 'src' => $src->out(false)]);
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    foreach (['sesskey' => sesskey(), 'deletevoice' => $voice->id] as $name => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    echo html_writer::tag('button', 'Xóa mẫu giọng', ['type' => 'submit', 'class' => 'btn btn-outline-danger']);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('article');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
