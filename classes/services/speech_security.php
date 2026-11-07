<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

/** Allow only the exact trusted speech request, without changing site-wide URL policy. */
class speech_security extends \core\files\curl_security_helper {
    private $expected;
    public function __construct(string $expected) {
        $this->expected = $expected;
    }
    public function url_is_blocked($url, $notused = 0) {
        return $url !== $this->expected;
    }
    public function get_blocked_url_string() {
        return 'Unexpected speech destination.';
    }
    public function get_resolve_info(): array {
        return [];
    }
}
