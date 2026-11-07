<?php
defined('MOODLE_INTERNAL') || die();

function local_microlearning_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/microlearning:uploadmaterial', $context)) {
        $navigation->add(get_string('narration', 'local_microlearning'),
            new moodle_url('/local/microlearning/narration.php', ['courseid' => $course->id]),
            navigation_node::TYPE_CUSTOM);
    }
}

function local_microlearning_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;
    if ($context->contextlevel !== CONTEXT_COURSE || !in_array($filearea, ['narration', 'voice'])) {
        return false;
    }
    require_login($course);
    require_capability('local/microlearning:uploadmaterial', $context);
    $id = (int)array_shift($args);
    $table = $filearea === 'voice' ? 'local_microlearning_voices' : 'local_microlearning_narrjobs';
    $record = $DB->get_record($table, ['id' => $id, 'courseid' => $course->id, 'userid' => $USER->id]);
    if (!$record || count($args) !== 1) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, 'local_microlearning', $filearea, $id, '/', $args[0]);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
