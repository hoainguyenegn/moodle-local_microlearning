<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_microlearning', get_string('pluginname', 'local_microlearning'));
    $ADMIN->add('localplugins', $settings);
    $settings->add(new admin_setting_configtext('local_microlearning/speechurl',
        'Local speech server URL', 'Private server running engine/compose.yaml.', 'http://127.0.0.1:8010', PARAM_URL));
    $settings->add(new admin_setting_configpasswordunmask('local_microlearning/speechtoken',
        'Speech server token', 'Must match SPEECH_TOKEN on the private server.', ''));
}
