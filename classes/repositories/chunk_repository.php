<?php
namespace local_microlearning\repositories;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for chunks, summaries, quizzes, questions, answers, and flashcards generated from a document.
 */
class chunk_repository {

    /**
     * Load document-scoped chunks and generated children, preserving 1-based source positions.
     *
     * @param array $criteria ['docid' => int]
     * @return array Workspace tree ['chunks' => [...], 'flashcard_sets' => [...]]
     */
    public function get_workspace(array $criteria): array {
        global $DB;
        $docid = (int) $criteria['docid'];
        $chunkrecords = $DB->get_records('local_microlearning_chunks', ['docid' => $docid], 'position ASC, id ASC');
        $chunks = [];

        foreach ($chunkrecords as $ch) {
            $sum = $DB->get_record('local_microlearning_sums', ['chunkid' => $ch->id]);
            $quizrecords = $DB->get_records('local_microlearning_quizzes', ['chunkid' => $ch->id], 'id ASC');
            $quizzes = [];
            foreach ($quizrecords as $qz) {
                $questrecords = $DB->get_records('local_microlearning_quests', ['quizid' => $qz->id], 'position ASC');
                $questions = [];
                foreach ($questrecords as $qt) {
                    $ansrecords = $DB->get_records('local_microlearning_ans', ['questid' => $qt->id], 'position ASC');
                    $answers = [];
                    foreach ($ansrecords as $ans) {
                        $answers[] = [
                            'id' => (int) $ans->id,
                            'position' => (int) $ans->position,
                            'answer' => (string) $ans->answer,
                            'iscorrect' => (bool) $ans->iscorrect,
                        ];
                    }
                    $questions[] = [
                        'id' => (int) $qt->id,
                        'position' => (int) $qt->position,
                        'question' => (string) $qt->question,
                        'explanation' => (string) $qt->explanation,
                        'answers' => $answers,
                    ];
                }
                $quizzes[] = [
                    'id' => (int) $qz->id,
                    'title' => (string) $qz->title,
                    'status' => (string) $qz->status,
                    'questions' => $questions,
                ];
            }

            $chunks[] = [
                'id' => (int) $ch->id,
                'taskid' => (int) $ch->taskid,
                'position' => (int) $ch->position,
                'content' => (string) $ch->content,
                'sourcestart' => (int) $ch->sourcestart,
                'sourceend' => (int) $ch->sourceend,
                'status' => (string) $ch->status,
                'summary' => $sum ? [
                    'id' => (int) $sum->id,
                    'content' => (string) $sum->content,
                    'status' => (string) $sum->status,
                ] : null,
                'quizzes' => $quizzes,
            ];
        }

        $fsets = [];
        $fsetrecords = $DB->get_records('local_microlearning_fsets', ['docid' => $docid], 'id ASC');
        foreach ($fsetrecords as $fs) {
            $cardrecords = $DB->get_records('local_microlearning_fcards', ['fsetid' => $fs->id], 'position ASC');
            $cards = [];
            foreach ($cardrecords as $card) {
                $cards[] = [
                    'id' => (int) $card->id,
                    'position' => (int) $card->position,
                    'front' => (string) $card->front,
                    'back' => (string) $card->back,
                ];
            }
            $fsets[] = [
                'id' => (int) $fs->id,
                'taskid' => (int) $fs->taskid,
                'title' => (string) $fs->title,
                'status' => (string) $fs->status,
                'cards' => $cards,
            ];
        }

        return [
            'chunks' => $chunks,
            'flashcard_sets' => $fsets,
        ];
    }

    /**
     * Persist validated AI task output atomically and prevent duplicate child records on retry.
     * Caller MUST execute inside a delegated DB transaction.
     *
     * @param array $criteria ['docid' => int, 'taskid' => int, 'validated' => array]
     * @return array Summary counts ['chunks' => int, 'summaries' => int, 'quizzes' => int, 'questions' => int, 'flashcards' => int]
     */
    public function replace_task_output(array $criteria): array {
        global $DB;
        $docid = (int) $criteria['docid'];
        $taskid = (int) $criteria['taskid'];
        $validated = $criteria['validated'];
        $now = time();

        // Clean any partial draft records previously written for this taskid so retries are idempotent.
        $this->delete_by_task($taskid);

        $counts = [
            'chunks' => 0,
            'summaries' => 0,
            'quizzes' => 0,
            'questions' => 0,
            'flashcards' => 0,
        ];

        foreach ($validated['chunks'] as $chunkdata) {
            $chunkid = (int) $DB->insert_record('local_microlearning_chunks', (object) [
                'docid' => $docid,
                'taskid' => $taskid,
                'position' => (int) $chunkdata['position'],
                'content' => (string) $chunkdata['content'],
                'sourcestart' => (int) $chunkdata['sourcestart'],
                'sourceend' => (int) $chunkdata['sourceend'],
                'status' => 'DRAFT',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $counts['chunks']++;

            if (!empty($chunkdata['summary'])) {
                $DB->insert_record('local_microlearning_sums', (object) [
                    'chunkid' => $chunkid,
                    'content' => (string) $chunkdata['summary']['content'],
                    'status' => 'DRAFT',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $counts['summaries']++;
            }

            if (!empty($chunkdata['quiz'])) {
                $quizid = (int) $DB->insert_record('local_microlearning_quizzes', (object) [
                    'chunkid' => $chunkid,
                    'title' => (string) $chunkdata['quiz']['title'],
                    'status' => 'DRAFT',
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $counts['quizzes']++;

                foreach ($chunkdata['quiz']['questions'] as $qdata) {
                    $questid = (int) $DB->insert_record('local_microlearning_quests', (object) [
                        'quizid' => $quizid,
                        'position' => (int) $qdata['position'],
                        'question' => (string) $qdata['question'],
                        'explanation' => (string) $qdata['explanation'],
                        'timecreated' => $now,
                        'timemodified' => $now,
                    ]);
                    $counts['questions']++;

                    foreach ($qdata['options'] as $opt) {
                        $DB->insert_record('local_microlearning_ans', (object) [
                            'questid' => $questid,
                            'position' => (int) $opt['position'],
                            'answer' => (string) $opt['answer'],
                            'iscorrect' => (int) $opt['iscorrect'],
                            'timecreated' => $now,
                            'timemodified' => $now,
                        ]);
                    }
                }
            }
        }

        if (!empty($validated['flashcards']) && !empty($validated['flashcards']['cards'])) {
            $fsetid = (int) $DB->insert_record('local_microlearning_fsets', (object) [
                'docid' => $docid,
                'taskid' => $taskid,
                'title' => (string) $validated['flashcards']['title'],
                'status' => 'DRAFT',
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            foreach ($validated['flashcards']['cards'] as $card) {
                $DB->insert_record('local_microlearning_fcards', (object) [
                    'fsetid' => $fsetid,
                    'position' => (int) $card['position'],
                    'front' => (string) $card['front'],
                    'back' => (string) $card['back'],
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $counts['flashcards']++;
            }
        }

        return $counts;
    }

    /**
     * Delete any existing chunks, summaries, quizzes, questions, answers, and flashcards created by a given taskid.
     *
     * @param int $taskid
     * @return void
     */
    private function delete_by_task(int $taskid): void {
        global $DB;
        $chunkids = $DB->get_fieldset_select('local_microlearning_chunks', 'id', 'taskid = :taskid', ['taskid' => $taskid]);
        if (!empty($chunkids)) {
            [$chunksql, $chunkparams] = $DB->get_in_or_equal($chunkids, SQL_PARAMS_NAMED);
            $quizids = $DB->get_fieldset_select('local_microlearning_quizzes', 'id', "chunkid $chunksql", $chunkparams);
            if (!empty($quizids)) {
                [$quizsql, $quizparams] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED);
                $questids = $DB->get_fieldset_select('local_microlearning_quests', 'id', "quizid $quizsql", $quizparams);
                if (!empty($questids)) {
                    [$questsql, $questparams] = $DB->get_in_or_equal($questids, SQL_PARAMS_NAMED);
                    $DB->delete_records_select('local_microlearning_ans', "questid $questsql", $questparams);
                }
                $DB->delete_records_select('local_microlearning_quests', "quizid $quizsql", $quizparams);
                $DB->delete_records_select('local_microlearning_quizzes', "id $quizsql", $quizparams);
            }
            $DB->delete_records_select('local_microlearning_sums', "chunkid $chunksql", $chunkparams);
            $DB->delete_records_select('local_microlearning_chunks', "id $chunksql", $chunkparams);
        }

        $fsetids = $DB->get_fieldset_select('local_microlearning_fsets', 'id', 'taskid = :taskid', ['taskid' => $taskid]);
        if (!empty($fsetids)) {
            [$fsetsql, $fsetparams] = $DB->get_in_or_equal($fsetids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_microlearning_fcards', "fsetid $fsetsql", $fsetparams);
            $DB->delete_records_select('local_microlearning_fsets', "id $fsetsql", $fsetparams);
        }
    }
}
