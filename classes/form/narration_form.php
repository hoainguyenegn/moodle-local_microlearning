<?php
namespace local_microlearning\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class narration_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $m->setType('courseid', PARAM_INT);
        $m->addElement('hidden', 'requestkey', bin2hex(random_bytes(24)));
        $m->setType('requestkey', PARAM_ALPHANUM);
        $m->addElement('text', 'title', 'Tên bài giảng', ['maxlength' => 200]);
        $m->setType('title', PARAM_TEXT);
        $m->addRule('title', null, 'required');
        $m->addElement('textarea', 'script', 'Kịch bản theo slide', ['rows' => 12, 'cols' => 80]);
        $m->setType('script', PARAM_TEXT);
        $m->addRule('script', null, 'required');
        $m->addElement('static', 'instructions', '',
            'Ngăn cách slide bằng dòng ---. Tối đa 50 slide.');
        $m->addElement('select', 'voice', 'Giọng đọc', $this->_customdata['voices']);
        $m->addRule('voice', null, 'required');
        $m->addElement('select', 'speed', 'Tốc độ', ['0.8' => 'Chậm', '1' => 'Bình thường', '1.2' => 'Nhanh']);
        $m->setDefault('speed', '1');
        $m->addElement('select', 'pitch', 'Cao độ', ['-3' => 'Thấp', '0' => 'Tự nhiên', '3' => 'Cao']);
        $m->setDefault('pitch', '0');
        $m->addElement('advcheckbox', 'approved', 'Xác nhận', 'Tôi đã duyệt kịch bản.');
        $this->add_action_buttons(false, 'Tạo giọng đọc');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['approved'])) {
            $errors['approved'] = 'Cần duyệt kịch bản trước khi tạo âm thanh.';
        }
        if (trim($data['title']) === '') {
            $errors['title'] = 'Nhập tên bài giảng.';
        }
        if (!isset($this->_customdata['voices'][$data['voice']])) {
            $errors['voice'] = 'Chọn giọng đọc hợp lệ.';
        }
        if (\core_text::strlen($data['script']) > 50000) {
            $errors['script'] = 'Tối đa 50000 ký tự.';
        }
        return $errors;
    }
}
