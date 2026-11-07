<?php
defined('MOODLE_INTERNAL') || die();

/** Shared presentation for the lecture workspace. */
function local_microlearning_workspace_start(int $courseid, string $active): void {
    echo html_writer::start_div('ml-workspace');
    echo html_writer::start_tag('nav', ['class' => 'ml-tabs', 'aria-label' => 'Bài giảng']);
    foreach (['import_slides' => 'Nhập tài liệu', 'narration' => 'Tạo lời giảng', 'voices' => 'Giọng của tôi'] as $page => $label) {
        $attributes = ['class' => $page === $active ? 'ml-tab is-active' : 'ml-tab'];
        if ($page === $active) {
            $attributes['aria-current'] = 'page';
        }
        echo html_writer::link(new moodle_url('/local/microlearning/' . $page . '.php', ['courseid' => $courseid]),
            $label, $attributes);
    }
    echo html_writer::end_tag('nav');
}

function local_microlearning_panel_start(string $title): void {
    echo html_writer::start_tag('section', ['class' => 'ml-panel']);
    echo html_writer::tag('h2', $title, ['class' => 'ml-panel-title']);
}
