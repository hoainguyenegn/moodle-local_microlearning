<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/ui.php');
$courseid = required_param('courseid', PARAM_INT);
$context = \local_microlearning\services\narration_service::authorise($courseid);
$url = new moodle_url('/local/microlearning/import_slides.php', ['courseid' => $courseid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->requires->css(new moodle_url('/local/microlearning/workspace.css'));
$PAGE->set_title('Nhập slide và soạn lời giảng');
$PAGE->set_heading('Nhập slide và soạn lời giảng');
$client = '\\local_microlearning\\services\\speech_client';
$error = '';
$config = ['enabled' => false];
try {
    $config = $client::request('/v1/documents/config');
} catch (Exception $e) {
    $error = 'Dịch vụ đọc tài liệu chưa sẵn sàng. Kiểm tra cấu hình máy chủ.';
}
$upload = new \local_microlearning\form\document_form($url, ['courseid' => $courseid]);
if ($data = $upload->get_data()) {
    try {
        $name = $upload->get_new_filename('document');
        $kind = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($kind, ['pdf', 'pptx'])) {
            throw new invalid_parameter_exception('PDF/PPTX required');
        }
        $result = $client::request('/v1/documents/extract',
            ['kind' => $kind, 'content' => base64_encode($upload->get_file_content('document'))]);
        $parts = [];
        foreach ($result['slides'] as $slide) {
            // Prefer authored speaker notes; preserve source text below for teacher inspection.
            $parts[] = trim($slide['notes'] ?: $slide['text']);
        }
        $SESSION->microlearning_import = (object)[
            'courseid' => $courseid, 'timecreated' => time(),
            'title' => \core_text::substr(pathinfo($name, PATHINFO_FILENAME), 0, 200),
            'script' => implode("\n---\n", $parts), 'source' => $result['slides'], 'warnings' => $result['warnings'],
        ];
        redirect($url);
    } catch (Exception $e) {
        $error = 'Không đọc được tài liệu. Chỉ nhận PDF/PPTX tối đa 5 MB, 50 slide, không có mật khẩu. Hãy kiểm tra dịch vụ rồi thử lại.';
    }
}
$draft = $SESSION->microlearning_import ?? null;
if ($draft && ($draft->courseid != $courseid || $draft->timecreated < time() - 7200)) {
    $draft = null;
}
$review = null;
if ($draft) {
    $review = new \local_microlearning\form\script_review_form($url,
        ['courseid' => $courseid, 'enabled' => !empty($config['enabled'])]);
    $review->set_data(['title' => $draft->title, 'script' => $draft->script]);
    if ($data = $review->get_data()) {
        $draft->title = $data->title;
        $draft->script = $data->script;
        $draft->timecreated = time();
        if (!empty($data->explain)) {
            try {
                if (empty($data->consent) || empty($config['enabled'])) {
                    throw new invalid_parameter_exception('AI not authorised');
                }
                $slides = [];
                foreach (preg_split('/^---\s*$/m', trim($data->script)) as $i => $text) {
                    $slides[] = ['title' => 'Slide ' . ($i + 1), 'text' => trim($text), 'notes' => ''];
                }
                $request = ['slides' => $slides, 'audience' => $data->audience,
                    'objectives' => $data->objectives, 'consent' => true];
                $cachekey = hash('sha256', json_encode([$request, $config['model'] ?? '']));
                if (($draft->aicachekey ?? '') === $cachekey) {
                    $result = $draft->aicacheresult;
                } else {
                    $result = $client::request('/v1/documents/explain', $request);
                    $draft->aicachekey = $cachekey;
                    $draft->aicacheresult = $result;
                }
                $draft->script = implode("\n---\n", array_column($result['slides'], 'text'));
                $draft->warnings = array_merge(['Kịch bản AI: cần kiểm tra tính đúng đắn trước khi đọc.'], $result['warnings']);
                $SESSION->microlearning_import = $draft;
                redirect($url);
            } catch (Exception $e) {
                $error = 'Chưa lấy được kịch bản AI. Nội dung đang sửa vẫn được giữ. Kiểm tra khóa, mô hình, hạn mức hoặc mạng; hệ thống không tự gửi lại để tránh tính phí hai lần.';
            }
        } else {
            $SESSION->microlearning_import = $draft;
            redirect(new moodle_url('/local/microlearning/narration.php', ['courseid' => $courseid, 'imported' => 1]));
        }
    }
}
echo $OUTPUT->header();
local_microlearning_workspace_start($courseid, 'import_slides');

if ($error) {
    echo $OUTPUT->notification($error, 'warning');
}
local_microlearning_panel_start('Tài liệu bài giảng');
$upload->display();
echo html_writer::end_tag('section');
if ($review) {
    foreach ($draft->warnings as $warning) {
        echo $OUTPUT->notification(s($warning), 'warning');
    }
    local_microlearning_panel_start('Duyệt kịch bản');
    $review->display();
    echo html_writer::start_tag('details', ['class' => 'ml-source']);
    echo html_writer::tag('summary', 'Nội dung gốc');
    foreach ($draft->source as $slide) {
        echo html_writer::tag('h3', s($slide['title']));
        echo html_writer::tag('pre', s($slide['text']));
        if ($slide['notes']) {
            echo html_writer::tag('p', 'Ghi chú giảng viên:');
            echo html_writer::tag('pre', s($slide['notes']));
        }
    }
    echo html_writer::end_tag('details');
    echo html_writer::end_tag('section');
}
echo html_writer::end_div();
echo $OUTPUT->footer();
