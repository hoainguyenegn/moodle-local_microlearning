<?php
// This file is part of Moodle - http://moodle.org/

namespace local_microlearning\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Validates the JSON payload returned by the Python AI Engine before any database write.
 *
 * Enforces the contract in docs/api-contract.md and docs/ai-engine-contract.md:
 * - schema_version === "1.0", matching task_id and doc_id
 * - ordered 1-based chunks with inclusive 1-based PDF page markers (source_start <= source_end)
 * - at least two options and exactly one correct answer per quiz question
 * - non-empty front/back for flashcards
 */
class output_validator {
    /** Supported contract version */
    public const SCHEMA_VERSION = '1.0';

    /** Allowed output types */
    public const ALLOWED_OUTPUTS = ['SUMMARY', 'QUIZ', 'FLASHCARDS'];

    /**
     * Validate and normalise a raw decoded payload from the AI engine.
     *
     * @param array $payload Decoded JSON payload from POST /v1/process
     * @param int $expectedtaskid Expected task ID
     * @param int $expecteddocid Expected document ID
     * @param array $requestedoutputs Requested output types (e.g. ['SUMMARY', 'QUIZ'])
     * @return array Validated & normalised structure ready for chunk_repository::replace_task_output
     * @throws engine_exception
     */
    public static function validate(
        array $payload,
        int $expectedtaskid,
        int $expecteddocid,
        array $requestedoutputs
    ): array {
        if (($payload['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
            throw new engine_exception('invalid_schema_version', false);
        }
        if ((int) ($payload['task_id'] ?? 0) !== $expectedtaskid || (int) ($payload['doc_id'] ?? 0) !== $expecteddocid) {
            throw new engine_exception('task_doc_mismatch', false);
        }
        if (empty($payload['chunks']) || !is_array($payload['chunks'])) {
            throw new engine_exception('empty_chunks', true);
        }

        $wanted = array_fill_keys($requestedoutputs, true);
        $totalpages = (int) ($payload['document']['total_pages'] ?? 0);
        $chunks = [];
        $expectedpos = 1;

        foreach ($payload['chunks'] as $rawchunk) {
            if (!is_array($rawchunk)) {
                throw new engine_exception('invalid_chunk_item', true);
            }
            $position = (int) ($rawchunk['position'] ?? 0);
            $sourcestart = (int) ($rawchunk['source_start'] ?? 0);
            $sourceend = (int) ($rawchunk['source_end'] ?? 0);
            $content = trim((string) ($rawchunk['content'] ?? ''));

            if ($position !== $expectedpos) {
                throw new engine_exception('chunk_position_sequence', true);
            }
            if ($content === '') {
                throw new engine_exception('empty_chunk_content', true);
            }
            if ($sourcestart < 1 || $sourceend < $sourcestart || ($totalpages > 0 && $sourceend > $totalpages)) {
                throw new engine_exception('invalid_page_markers', true);
            }

            $summary = null;
            if (!empty($wanted['SUMMARY']) && isset($rawchunk['summary']) && is_array($rawchunk['summary'])) {
                $markdown = trim((string) ($rawchunk['summary']['markdown'] ?? ''));
                if ($markdown !== '') {
                    $summary = ['content' => $markdown];
                }
            }

            $quiz = null;
            if (!empty($wanted['QUIZ']) && isset($rawchunk['quiz']) && is_array($rawchunk['quiz'])) {
                $quiz = self::validate_quiz($rawchunk['quiz'], $position);
            }

            $chunks[] = [
                'position' => $position,
                'sourcestart' => $sourcestart,
                'sourceend' => $sourceend,
                'content' => $content,
                'summary' => $summary,
                'quiz' => $quiz,
            ];
            $expectedpos++;
        }

        $flashcards = null;
        if (!empty($wanted['FLASHCARDS']) && isset($payload['flashcards']) && is_array($payload['flashcards'])) {
            $flashcards = self::validate_flashcards($payload['flashcards']);
        }

        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];

        return [
            'chunks' => $chunks,
            'flashcards' => $flashcards,
            'usage' => [
                'tokens' => max(0, (int) ($usage['tokens'] ?? 0)),
                'latency_ms' => max(0, (int) ($payload['processing_time_ms'] ?? ($usage['latency_ms'] ?? 0))),
            ],
        ];
    }

    /**
     * Validate a quiz block: >=1 question, each question >=2 options and ==1 correct option.
     *
     * @param array $rawquiz
     * @param int $chunkpos
     * @return array|null
     * @throws engine_exception
     */
    private static function validate_quiz(array $rawquiz, int $chunkpos): ?array {
        $rawquestions = $rawquiz['questions'] ?? [];
        if (!is_array($rawquestions) || empty($rawquestions)) {
            return null;
        }
        $title = trim((string) ($rawquiz['title'] ?? ''));
        if ($title === '') {
            $title = 'Quiz ' . $chunkpos;
        }

        $questions = [];
        $qpos = 1;
        foreach ($rawquestions as $rawq) {
            if (!is_array($rawq)) {
                throw new engine_exception('invalid_question', true);
            }
            $qtext = trim((string) ($rawq['question'] ?? ''));
            $explanation = trim((string) ($rawq['explanation'] ?? ''));
            $rawopts = $rawq['options'] ?? [];
            if ($qtext === '' || !is_array($rawopts) || count($rawopts) < 2) {
                throw new engine_exception('invalid_question_options', true);
            }

            $options = [];
            $correctcount = 0;
            $apos = 1;
            foreach ($rawopts as $rawopt) {
                if (!is_array($rawopt)) {
                    throw new engine_exception('invalid_answer_option', true);
                }
                $atext = trim((string) ($rawopt['text'] ?? ''));
                if ($atext === '') {
                    throw new engine_exception('empty_answer_option', true);
                }
                $iscorrect = !empty($rawopt['is_correct']) ? 1 : 0;
                $correctcount += $iscorrect;
                $options[] = [
                    'position' => $apos++,
                    'answer' => $atext,
                    'iscorrect' => $iscorrect,
                ];
            }

            if ($correctcount !== 1) {
                throw new engine_exception('question_requires_single_correct_option', true);
            }

            $questions[] = [
                'position' => $qpos++,
                'question' => $qtext,
                'explanation' => $explanation,
                'options' => $options,
            ];
        }

        return [
            'title' => substr($title, 0, 255),
            'questions' => $questions,
        ];
    }

    /**
     * Validate flashcard set.
     *
     * @param array $rawset
     * @return array|null
     * @throws engine_exception
     */
    private static function validate_flashcards(array $rawset): ?array {
        $rawcards = $rawset['cards'] ?? [];
        if (!is_array($rawcards) || empty($rawcards)) {
            return null;
        }
        $title = trim((string) ($rawset['title'] ?? ''));
        if ($title === '') {
            $title = 'Flashcards';
        }
        $cards = [];
        $pos = 1;
        foreach ($rawcards as $card) {
            if (!is_array($card)) {
                throw new engine_exception('invalid_flashcard', true);
            }
            $front = trim((string) ($card['front'] ?? ''));
            $back = trim((string) ($card['back'] ?? ''));
            if ($front === '' || $back === '') {
                throw new engine_exception('empty_flashcard_side', true);
            }
            $cards[] = [
                'position' => $pos++,
                'front' => $front,
                'back' => $back,
            ];
        }
        return [
            'title' => substr($title, 0, 255),
            'cards' => $cards,
        ];
    }

    /**
     * Parse and validate the CSV outputs parameter (e.g. "SUMMARY,QUIZ").
     *
     * @param string $outputsraw
     * @return array{0:string, 1:array<int,string>} [canonical_csv, list_of_outputs]
     * @throws \moodle_exception
     */
    public static function parse_outputs_csv(string $outputsraw): array {
        $parts = array_map('trim', explode(',', strtoupper($outputsraw)));
        $parts = array_values(array_filter($parts, static fn(string $p): bool => $p !== ''));
        if (empty($parts) || count($parts) !== count(array_unique($parts))) {
            throw new \moodle_exception('invalidoutputs', 'local_microlearning');
        }
        foreach ($parts as $part) {
            if (!in_array($part, self::ALLOWED_OUTPUTS, true)) {
                throw new \moodle_exception('invalidoutputs', 'local_microlearning');
            }
        }
        // Preserve canonical ordering matching ALLOWED_OUTPUTS so inputhash is deterministic.
        $ordered = array_values(array_intersect(self::ALLOWED_OUTPUTS, $parts));
        return [implode(',', $ordered), $ordered];
    }
}

