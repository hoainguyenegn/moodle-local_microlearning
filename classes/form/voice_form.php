<?php
namespace local_microlearning\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class voice_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $m->setType('courseid', PARAM_INT);
        $m->addElement('text', 'name', 'Tên giọng', ['maxlength' => 200]);
        $m->setType('name', PARAM_TEXT);
        $m->addRule('name', null, 'required');
        $m->addElement('filepicker', 'sample', 'Bản thu WAV', null,
            ['accepted_types' => ['.wav'], 'maxbytes' => 5 * 1024 * 1024]);
        $m->addRule('sample', null, 'required');
        $m->addElement('static', 'hint', '',
            'WAV PCM 16-bit, 3–30 giây, tối đa 5 MB. Thu một giọng, không nhạc nền.');
        $m->addElement('advcheckbox', 'consent', 'Cho phép sử dụng',
            'Tôi đồng ý dùng giọng của mình để tạo lời giảng.');
        $this->add_action_buttons(false, 'Lưu giọng');
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['consent'])) {
            $errors['consent'] = 'Cần xác nhận quyền sử dụng giọng.';
        }
        if (trim($data['name']) === '') {
            $errors['name'] = 'Nhập tên giọng.';
        }
        return $errors;
    }
}
