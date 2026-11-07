<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

class narration_service {
    public static function authorise(int $courseid): \context_course {
        require_login(get_course($courseid));
        $context = \context_course::instance($courseid);
        require_capability('local/microlearning:uploadmaterial', $context);
        return $context;
    }

    public static function create(int $courseid, \stdClass $data): int {
        global $DB, $USER;
        // Forms have already initialised the page theme: do not reset the course here.
        require_login();
        $context = \context_course::instance($courseid);
        require_capability('local/microlearning:uploadmaterial', $context);
        $slides = [];
        foreach (preg_split('/^---\s*$/m', trim($data->script)) as $i => $text) {
            $text = trim($text);
            if ($text === '' || \core_text::strlen($text) > 10000) {
                throw new \invalid_parameter_exception('Each slide needs 1–10000 characters.');
            }
            $slides[] = ['title' => 'Slide ' . ($i + 1), 'text' => $text];
        }
        if (count($slides) > 50 || \core_text::strlen($data->script) > 50000) {
            throw new \invalid_parameter_exception('Maximum 50 slides / 50000 characters.');
        }
        $speed = (float)$data->speed;
        $pitch = (float)$data->pitch;
        if ($speed < .8 || $speed > 1.2 || $pitch < -3 || $pitch > 3) {
            throw new \invalid_parameter_exception('Invalid speech controls.');
        }
        $voiceid = 0;
        if (strpos($data->voice, 'custom:') === 0) {
            $voiceid = (int)substr($data->voice, 7);
            $DB->get_record('local_microlearning_voices',
                ['id' => $voiceid, 'userid' => $USER->id, 'courseid' => $courseid], '*', MUST_EXIST);
        }
        $payload = ['slides' => $slides, 'voice' => $voiceid ? 'custom' : $data->voice,
                    'speed' => $speed, 'pitch' => $pitch, 'owner' => (int)$USER->id];
        // Lock duplicate browser submissions before persisting and queueing atomically.
        $factory = \core\lock\lock_config::get_lock_factory('local_microlearning');
        $lock = $factory->get_lock('speech-' . $USER->id . '-' . $data->requestkey, 5);
        if (!$lock) {
            throw new \moodle_exception('speechunavailable', 'local_microlearning');
        }
        try {
            $existing = $DB->get_record('local_microlearning_narrjobs',
                ['userid' => $USER->id, 'requestkey' => $data->requestkey]);
            if ($existing) {
                if ((int)$existing->courseid !== $courseid || (int)$existing->voiceid !== $voiceid
                        || $existing->payload !== json_encode($payload) || $existing->title !== $data->title) {
                    throw new \invalid_parameter_exception('Conflicting duplicate request.');
                }
                return (int)$existing->id;
            }
            $tx = $DB->start_delegated_transaction();
            $id = $DB->insert_record('local_microlearning_narrjobs', (object)[
                'courseid' => $courseid, 'userid' => $USER->id, 'requestkey' => $data->requestkey,
                'title' => $data->title, 'payload' => json_encode($payload), 'voiceid' => $voiceid,
                'engineid' => '', 'status' => 'queued', 'manifest' => '[]', 'attempts' => 0,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
            self::queue($id);
            $tx->allow_commit();
            return $id;
        } finally {
            $lock->release();
        }
    }

    public static function queue(int $id, int $delay = 0): void {
        $task = new \local_microlearning\task\generate_narration();
        $task->set_custom_data(['id' => $id]);
        $task->set_next_run_time(time() + $delay);
        \core\task\manager::queue_adhoc_task($task);
    }
}
