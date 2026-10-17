<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_microlearning_upload_material' => [
        'classname'   => 'local_microlearning\external\material_api',
        'methodname'  => 'upload_material',
        'classpath'   => 'local/microlearning/classes/external/material_api.php',
        'description' => 'Upload PDF or video lecture file',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'local_microlearning_dispatch_ai' => [
        'classname'   => 'local_microlearning\external\ai_task_api',
        'methodname'  => 'dispatch_ai',
        'classpath'   => 'local/microlearning/classes/external/ai_task_api.php',
        'description' => 'Trigger AI engine for document parsing and generation',
        'type'        => 'write',
        'ajax'        => true,
    ],
];

$services = [
    'Microlearning API Service' => [
        'functions' => [
            'local_microlearning_upload_material',
            'local_microlearning_dispatch_ai',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
    ],
];