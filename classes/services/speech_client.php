<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

class speech_client {
    public static function request(string $path, ?array $payload = null, ?string $download = null) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $url = rtrim((string)get_config('local_microlearning', 'speechurl'), '/');
        $token = (string)get_config('local_microlearning', 'speechtoken');
        if (!$url || strlen($token) < 32) {
            throw new \moodle_exception('speechunavailable', 'local_microlearning');
        }
        // Only administrators configure this trusted internal endpoint; no user-supplied URLs.
        $parts = parse_url($url);
        if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https']) || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
                || !preg_match('~^/v1/(health|voices|validate-reference|documents/(?:config|extract|explain)|jobs(?:/[a-f0-9]{32}(?:/audio)?)?)$~D', $path)) {
            throw new \moodle_exception('speechunavailable', 'local_microlearning');
        }
        $curl = new \curl(['securityhelper' => new speech_security($url . $path)]);
        $curl->setHeader(['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
        $options = ['CURLOPT_CONNECTTIMEOUT' => 10, 'CURLOPT_TIMEOUT' => $download ? 120 : ($path === '/v1/documents/explain' ? 75 : 30),
                    'CURLOPT_FOLLOWLOCATION' => false];
        $handle = null;
        if ($download) {
            $handle = fopen($download, 'wb');
            $options['CURLOPT_FILE'] = $handle;
        }
        try {
            $body = $payload === null ? $curl->get($url . $path, [], $options)
                : $curl->post($url . $path, json_encode($payload), $options);
            $info = $curl->get_info();
            if (($info['http_code'] ?? 0) === 422) {
                throw new \moodle_exception('speechinvalid', 'local_microlearning');
            }
            if ($curl->get_errno() || ($info['http_code'] ?? 0) < 200 || ($info['http_code'] ?? 0) >= 300) {
                throw new \moodle_exception('speechunavailable', 'local_microlearning');
            }
            if ($download) {
                return null;
            }
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                throw new \moodle_exception('speechunavailable', 'local_microlearning');
            }
            return $decoded;
        } finally {
            if ($handle) {
                fclose($handle);
            }
        }
    }
}
