<?php
namespace local_microlearning\task;
defined('MOODLE_INTERNAL') || die();

use local_microlearning\services\speech_client;
use local_microlearning\services\narration_service;

class generate_narration extends \core\task\adhoc_task {
    public function execute() {
        global $DB;
        $id = (int)$this->get_custom_data()->id;
        $lock = \core\lock\lock_config::get_lock_factory('local_microlearning')->get_lock('narration-' . $id, 0);
        if (!$lock) {
            return;
        }
        $temp = null;
        try {
            $job = $DB->get_record('local_microlearning_narrjobs', ['id' => $id], '*', MUST_EXIST);
            if (in_array($job->status, ['done', 'failed'])) {
                return;
            }
            $context = \context_course::instance($job->courseid);
            if (!has_capability('local/microlearning:uploadmaterial', $context, $job->userid)
                    || time() - $job->timecreated > 86400) {
                $job->status = 'failed';
                $DB->update_record('local_microlearning_narrjobs', $job);
                return;
            }
            if (!$job->engineid) {
                $payload = json_decode($job->payload, true);
                $payload['requestkey'] = 'moodle-' . $job->requestkey;
                if ($job->voiceid) {
                    $DB->get_record('local_microlearning_voices', ['id' => $job->voiceid,
                        'userid' => $job->userid, 'courseid' => $job->courseid], '*', MUST_EXIST);
                    $files = get_file_storage()->get_area_files($context->id, 'local_microlearning',
                        'voice', $job->voiceid, 'id', false);
                    if (!$files) {
                        throw new \moodle_exception('speechunavailable', 'local_microlearning');
                    }
                    $payload['reference'] = base64_encode(reset($files)->get_content());
                    $payload['consent'] = true;
                }
                $reply = speech_client::request('/v1/jobs', $payload);
                if (!preg_match('/^[a-f0-9]{32}$/', $reply['id'] ?? '')) {
                    throw new \moodle_exception('speechunavailable', 'local_microlearning');
                }
                $job->engineid = $reply['id'];
            }
            $result = speech_client::request('/v1/jobs/' . $job->engineid);
            if (!in_array($result['status'] ?? '', ['queued', 'running', 'done', 'failed'])) {
                throw new \moodle_exception('speechunavailable', 'local_microlearning');
            }
            $job->status = $result['status'];
            if ($job->status === 'done') {
                $temp = tempnam(make_temp_directory('local_microlearning'), 'speech');
                speech_client::request('/v1/jobs/' . $job->engineid . '/audio', null, $temp);
                $handle = fopen($temp, 'rb');
                $header = fread($handle, 12);
                fclose($handle);
                if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
                    throw new \moodle_exception('speechunavailable', 'local_microlearning');
                }
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'local_microlearning', 'narration', $id);
                $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'local_microlearning',
                    'filearea' => 'narration', 'itemid' => $id, 'filepath' => '/', 'filename' => 'audio.wav',
                    'userid' => $job->userid, 'mimetype' => 'audio/wav'], $temp);
                $job->manifest = json_encode($result['result']);
            }
            $job->attempts = 0;
            $job->timemodified = time();
            $tx = $DB->start_delegated_transaction();
            $DB->update_record('local_microlearning_narrjobs', $job);
            if (in_array($job->status, ['queued', 'running'])) {
                narration_service::queue($id, 30);
            }
            $tx->allow_commit();
        } catch (\Exception $e) {
            if (!empty($job)) {
                $job->attempts++;
                $job->status = $job->attempts >= 5 ? 'failed' : 'queued';
                $job->timemodified = time();
                $DB->update_record('local_microlearning_narrjobs', $job);
                if ($job->status !== 'failed') {
                    narration_service::queue($id, min(600, 30 * (2 ** $job->attempts)));
                }
            } else {
                throw $e;
            }
        } finally {
            if ($temp && file_exists($temp)) {
                unlink($temp);
            }
            $lock->release();
        }
    }
}
