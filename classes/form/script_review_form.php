<?php
namespace local_microlearning\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class script_review_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $m->setType('courseid', PARAM_INT);
        $m->addElement('text', 'title', 'Tên bài giảng', ['maxlength' => 200]);
        $m->setType('title', PARAM_TEXT);
        $m->addRule('title', null, 'required');
        $m->addElement('textarea', 'script', 'Kịch bản', ['rows' => 18, 'cols' => 85]);
        $m->setType('script', PARAM_TEXT);
        $m->addRule('script', null, 'required');
        $m->addElement('static', 'separator', '', 'Ngăn cách slide bằng dòng ---.');
        if ($this->_customdata['enabled']) {
            $m->addElement('text', 'audience', 'Đối tượng người học', ['maxlength' => 500]);
            $m->setType('audience', PARAM_TEXT);
            $m->setDefault('audience', 'Sinh viên mới bắt đầu');
            $m->addElement('textarea', 'objectives', 'Mục tiêu bài học', ['rows' => 3]);
            $m->setType('objectives', PARAM_TEXT);
            $m->addElement('static', 'externalnotice', '',
                'Gemini nhận kịch bản, đối tượng và mục tiêu; không nhận file gốc hoặc mẫu giọng. Tối đa 15 slide / 15000 ký tự.');
            $m->addElement('advcheckbox', 'consent', 'Gửi tới Google Gemini',
                'Tôi đồng ý gửi nội dung tới Google Gemini và chi phí API có thể phát sinh.');
            $m->addElement('submit', 'explain', 'Soạn lời giải thích');
        } else {
            $m->addElement('static', 'disabled', '',
                'AI soạn lời giảng chưa được cấu hình. Bạn có thể chỉnh sửa kịch bản trực tiếp.');
        }
        $m->addElement('submit', 'submitbutton', 'Tiếp tục tạo giọng');
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $parts = preg_split('/^---\s*$/m', trim($data['script']));
        $limit = empty($data['explain']) ? 50000 : 15000;
        $count = empty($data['explain']) ? 50 : 15;
        if (\core_text::strlen($data['script']) > $limit || count($parts) > $count) {
            $errors['script'] = "Tối đa {$count} slide / {$limit} ký tự cho thao tác này.";
        }
        foreach ($parts as $part) {
            if (!trim($part) || \core_text::strlen($part) > 10000) {
                $errors['script'] = 'Mỗi slide cần 1–10000 ký tự.';
            }
        }
        if (\core_text::strlen($data['objectives'] ?? '') > 2000) {
            $errors['objectives'] = 'Tối đa 2000 ký tự.';
        }
        if (!empty($data['explain']) && (empty($data['consent']) || !$this->_customdata['enabled'])) {
            $errors['consent'] = 'Cần bật cấu hình AI và đồng ý gửi nội dung.';
        }
        return $errors;
    }
}
