<?php
// This file is part of Moodle - http://moodle.org/

namespace local_microlearning\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP client for the standalone Python PDF Processing & AI Routing Engine (ai_engine/).
 *
 * Keep all calls to this client OUTSIDE database transactions (see docs/api-contract.md).
 */
class pdf_engine_client {
    /** @var string Base URL of the Python FastAPI service */
    private string $baseurl;

    /** @var string Shared secret sent in X-Engine-Key header */
    private string $apikey;

    /** @var int Request timeout in seconds */
    private int $timeout;

    /**
     * @param string|null $baseurl Override engine URL (defaults to plugin config)
     * @param string|null $apikey Override engine API key (defaults to plugin config)
     * @param int|null $timeout Override timeout in seconds (defaults to plugin config)
     */
    public function __construct(?string $baseurl = null, ?string $apikey = null, ?int $timeout = null) {
        $cfgurl = function_exists('get_config') ? (string) get_config('local_microlearning', 'engineurl') : '';
        $cfgkey = function_exists('get_config') ? (string) get_config('local_microlearning', 'enginekey') : '';
        $cfgtimeout = function_exists('get_config') ? (int) get_config('local_microlearning', 'enginetimeout') : 0;

        $this->baseurl = rtrim($baseurl ?? ($cfgurl !== '' ? $cfgurl : 'http://127.0.0.1:8000'), '/');
        $this->apikey = $apikey ?? $cfgkey;
        $this->timeout = $timeout ?? ($cfgtimeout > 0 ? $cfgtimeout : 300);
    }

    /**
     * Send a PDF binary payload to POST /v1/process and return the decoded response array.
     *
     * @param string $pdfbytes Raw PDF file content
     * @param string $filename Original filename
     * @param int $taskid local_microlearning_tasks.id
     * @param int $docid local_microlearning_docs.id
     * @param string $outputs Canonical CSV of requested outputs (e.g. "SUMMARY,QUIZ")
     * @param string $title Document title
     * @return array Decoded JSON response matching schema_version 1.0
     * @throws engine_exception
     */
    public function process_pdf(
        string $pdfbytes,
        string $filename,
        int $taskid,
        int $docid,
        string $outputs,
        string $title = ''
    ): array {
        if ($this->apikey === '') {
            throw new engine_exception('engine_key_missing', false, 0);
        }
        if ($pdfbytes === '' || strncmp($pdfbytes, '%PDF-', 5) !== 0) {
            throw new engine_exception('not_a_pdf', false, 0);
        }

        $tmpfile = tempnam(sys_get_temp_dir(), 'mlpdf_');
        if ($tmpfile === false || file_put_contents($tmpfile, $pdfbytes) === false) {
            throw new engine_exception('temp_file_failed', true, 0);
        }

        try {
            $postfields = [
                'file' => new \CURLFile($tmpfile, 'application/pdf', $filename !== '' ? $filename : 'document.pdf'),
                'task_id' => (string) $taskid,
                'doc_id' => (string) $docid,
                'outputs' => $outputs,
                'title' => $title,
            ];
            [$httpstatus, $rawbody, $curlerr] = $this->send_multipart($this->baseurl . '/v1/process', $postfields);
        } finally {
            @unlink($tmpfile);
        }

        if ($curlerr !== '') {
            throw new engine_exception('engine_unreachable', true, 0, $curlerr);
        }

        $decoded = json_decode($rawbody, true);
        if ($httpstatus !== 200) {
            $errcode = 'engine_http_' . $httpstatus;
            $retryable = in_array($httpstatus, [408, 429, 502, 503, 504], true);
            if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
                if (!empty($decoded['error']['code']) && is_string($decoded['error']['code'])) {
                    $errcode = $decoded['error']['code'];
                }
                if (array_key_exists('retryable', $decoded['error'])) {
                    $retryable = (bool) $decoded['error']['retryable'];
                }
            }
            throw new engine_exception($errcode, $retryable, $httpstatus);
        }

        if (!is_array($decoded)) {
            throw new engine_exception('engine_invalid_json', true, $httpstatus);
        }

        return $decoded;
    }

    /**
     * Execute multipart POST request using cURL.
     *
     * @param string $url Target endpoint
     * @param array $postfields Multipart form fields
     * @return array{0:int, 1:string, 2:string} [httpstatus, body, error]
     */
    protected function send_multipart(string $url, array $postfields): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-Engine-Key: ' . $this->apikey,
            ],
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = $errno !== 0 ? curl_error($ch) : '';
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, is_string($body) ? $body : '', $error];
    }
}

