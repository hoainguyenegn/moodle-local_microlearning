<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Micro-learning';
$string['microlearning:uploadmaterial'] = 'Upload source materials';
$string['microlearning:viewfeed'] = 'View micro-learning feeds';
$string['microlearning:reviewcontent'] = 'Review and publish generated content';
$string['notimplemented'] = 'This feature is a development skeleton: {$a}';

// Admin settings.
$string['setting_engineurl'] = 'AI Engine base URL';
$string['setting_engineurl_desc'] = 'Base URL of the standalone Python PDF Processing & AI Routing service (e.g. http://127.0.0.1:8000).';
$string['setting_enginekey'] = 'AI Engine shared secret key';
$string['setting_enginekey_desc'] = 'Must match ENGINE_API_KEY in ai_engine/.env. Sent via the X-Engine-Key header.';
$string['setting_enginetimeout'] = 'AI Engine request timeout (seconds)';
$string['setting_enginetimeout_desc'] = 'Maximum HTTP timeout when waiting for the PDF pipeline to finish.';
$string['setting_maxpdfbytes'] = 'Maximum PDF size (bytes)';
$string['setting_maxpdfbytes_desc'] = 'Maximum allowed upload size for PDF source materials (default 50 MB = 52428800 bytes).';

// Material & AI pipeline errors.
$string['aiengineerror'] = 'AI processing engine error ({$a})';
$string['cannotdeleteactivetask'] = 'Cannot delete material while an AI processing task is queued or running.';
$string['cannotdeletepublished'] = 'Cannot delete a published material.';
$string['cannotdeletewithattempts'] = 'Cannot delete material because student quiz attempts already exist.';
$string['cannotreprocesspublished'] = 'Cannot dispatch AI processing on a published material.';
$string['emptyfile'] = 'The uploaded file is empty.';
$string['filetoolarge'] = 'The uploaded PDF exceeds the maximum allowed file size.';
$string['invalidmaterialtype'] = 'Invalid material type. Allowed values: PDF, AUDIO, VIDEO.';
$string['invalidoutputs'] = 'Invalid outputs list. Allowed CSV values without duplicates: SUMMARY, QUIZ, FLASHCARDS.';
$string['invalidparameter'] = 'Invalid request parameter.';
$string['invalidpdffile'] = 'The uploaded file is not a valid PDF document.';
$string['invalidrequestkey'] = 'Invalid requestkey (must be 1-64 ASCII letters, digits, hyphens, or underscores).';
$string['invalidtitle'] = 'Document title must be between 1 and 255 characters.';
$string['locktimeout'] = 'Could not acquire processing lock. Please try again.';
$string['materialnotfound'] = 'Source material not found in this course.';
$string['nopermission'] = 'You do not have permission to perform this action on the material.';
$string['requestkeyconflict'] = 'A task with this requestkey already exists with different parameters.';
$string['singlesourcefilerequired'] = 'Exactly one file must be uploaded in the draft area.';
$string['tasknotfound'] = 'AI processing task not found in this course.';
$string['unsupportedmaterialtype'] = 'AI processing for material type {$a} is not yet supported by the PDF engine.';
