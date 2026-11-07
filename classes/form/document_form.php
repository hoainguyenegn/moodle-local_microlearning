<?php
namespace local_microlearning\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');

class document_form extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $m->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $m->setType('courseid', PARAM_INT);
        $m->addElement('filepicker', 'document', 'File PDF / PowerPoint', null,
            ['accepted_types' => ['.pdf', '.pptx'], 'maxbytes' => 5 * 1024 * 1024]);
        $m->addRule('document', null, 'required');
        $m->addElement('static', 'notice', '',
            'PDF / PPTX, tối đa 5 MB và 50 slide. Chỉ đọc chữ; chưa hỗ trợ ảnh quét, biểu đồ.');
        $this->add_action_buttons(false, 'Nhập tài liệu');
    }
}
