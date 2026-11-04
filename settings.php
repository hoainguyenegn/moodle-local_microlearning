<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_microlearning', get_string('pluginname', 'local_microlearning'));

    $settings->add(new admin_setting_configtext(
        'local_microlearning/engineurl',
        get_string('setting_engineurl', 'local_microlearning'),
        get_string('setting_engineurl_desc', 'local_microlearning'),
        'http://127.0.0.1:8000',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_microlearning/enginekey',
        get_string('setting_enginekey', 'local_microlearning'),
        get_string('setting_enginekey_desc', 'local_microlearning'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_microlearning/enginetimeout',
        get_string('setting_enginetimeout', 'local_microlearning'),
        get_string('setting_enginetimeout_desc', 'local_microlearning'),
        '300',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_microlearning/maxpdfbytes',
        get_string('setting_maxpdfbytes', 'local_microlearning'),
        get_string('setting_maxpdfbytes_desc', 'local_microlearning'),
        '52428800',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}

