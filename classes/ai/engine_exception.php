<?php
// This file is part of Moodle - http://moodle.org/

namespace local_microlearning\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Sanitised exception raised when the external PDF AI Engine fails or returns invalid data.
 *
 * Provider secrets, stack traces, and internal URLs are stripped so error codes can be safely
 * persisted in local_microlearning_tasks.errorcode.
 */
class engine_exception extends \moodle_exception {
    /** @var string Machine-readable error code for local_microlearning_tasks.errorcode */
    public string $engineerrorcode;

    /** @var bool Whether the adhoc task should be retried */
    public bool $retryable;

    /** @var int HTTP status returned by the engine (0 on transport failure) */
    public int $httpstatus;

    /**
     * @param string $engineerrorcode Short ASCII error code (max 100 chars)
     * @param bool $retryable True if transient failure (rate limit, 503, timeout)
     * @param int $httpstatus HTTP status code or 0
     * @param string $debuginfo Optional internal debug note (not exposed to end users)
     */
    public function __construct(
        string $engineerrorcode,
        bool $retryable = false,
        int $httpstatus = 0,
        string $debuginfo = ''
    ) {
        $clean = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $engineerrorcode) ?: 'engine_error';
        $this->engineerrorcode = substr($clean, 0, 100);
        $this->retryable = $retryable;
        $this->httpstatus = $httpstatus;
        parent::__construct('aiengineerror', 'local_microlearning', '', $this->engineerrorcode, $debuginfo);
    }
}

