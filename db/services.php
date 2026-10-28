<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_microlearning_upload_material' => [
        'classname' => 'local_microlearning\\external\\material_api',
        'methodname' => 'upload_material',
        'description' => 'Skeleton: upload_material',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/microlearning:uploadmaterial',
    ],
    'local_microlearning_get_material' => [
        'classname' => 'local_microlearning\\external\\material_api',
        'methodname' => 'get_material',
        'description' => 'Skeleton: get_material',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:uploadmaterial',
    ],
    'local_microlearning_delete_material' => [
        'classname' => 'local_microlearning\\external\\material_api',
        'methodname' => 'delete_material',
        'description' => 'Skeleton: delete_material',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/microlearning:uploadmaterial',
    ],
    'local_microlearning_dispatch_ai' => [
        'classname' => 'local_microlearning\\external\\ai_task_api',
        'methodname' => 'dispatch_ai',
        'description' => 'Skeleton: dispatch_ai',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/microlearning:uploadmaterial',
    ],
    'local_microlearning_get_task' => [
        'classname' => 'local_microlearning\\external\\ai_task_api',
        'methodname' => 'get_task',
        'description' => 'Skeleton: get_task',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:uploadmaterial',
    ],
    'local_microlearning_get_workspace' => [
        'classname' => 'local_microlearning\\external\\review_api',
        'methodname' => 'get_workspace',
        'description' => 'Skeleton: get_workspace',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:reviewcontent',
    ],
    'local_microlearning_save_review' => [
        'classname' => 'local_microlearning\\external\\review_api',
        'methodname' => 'save_review',
        'description' => 'Skeleton: save_review',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/microlearning:reviewcontent',
    ],
    'local_microlearning_publish_material' => [
        'classname' => 'local_microlearning\\external\\review_api',
        'methodname' => 'publish_material',
        'description' => 'Skeleton: publish_material',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/microlearning:reviewcontent',
    ],
    'local_microlearning_get_feed' => [
        'classname' => 'local_microlearning\\external\\student_api',
        'methodname' => 'get_feed',
        'description' => 'Skeleton: get_feed',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:viewfeed',
    ],
    'local_microlearning_get_content' => [
        'classname' => 'local_microlearning\\external\\student_api',
        'methodname' => 'get_content',
        'description' => 'Skeleton: get_content',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:viewfeed',
    ],
    'local_microlearning_get_quiz' => [
        'classname' => 'local_microlearning\\external\\quiz_api',
        'methodname' => 'get_quiz',
        'description' => 'Skeleton: get_quiz',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:viewfeed',
    ],
    'local_microlearning_submit_quiz' => [
        'classname' => 'local_microlearning\\external\\quiz_api',
        'methodname' => 'submit_quiz',
        'description' => 'Skeleton: submit_quiz',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/microlearning:viewfeed',
    ],
    'local_microlearning_get_attempt' => [
        'classname' => 'local_microlearning\\external\\quiz_api',
        'methodname' => 'get_attempt',
        'description' => 'Skeleton: get_attempt',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/microlearning:viewfeed',
    ],
];

// Administrators explicitly enable and authorise REST access after implementation.
$services = [
    'Microlearning API Service' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 1,
        'enabled' => 0,
    ],
];
