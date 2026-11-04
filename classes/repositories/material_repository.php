<?php
namespace local_microlearning\repositories;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository for local_microlearning_docs (PDF / Audio / Video source materials).
 */
class material_repository {

    /**
     * Insert a new document record into the XMLDB table.
     *
     * @param \stdClass $data
     * @return int Record ID
     */
    public function create(\stdClass $data): int {
        global $DB;
        $now = time();
        $data->timecreated = $data->timecreated ?? $now;
        $data->timemodified = $data->timemodified ?? $now;
        $data->status = $data->status ?? 'READY';
        $data->visibility = $data->visibility ?? 'CLASS_ONLY';
        $data->revision = $data->revision ?? 1;
        return (int) $DB->insert_record('local_microlearning_docs', $data);
    }

    /**
     * Retrieve document by its primary key ID.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public function get_by_id(int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_microlearning_docs', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Retrieve document scoped to a course ID.
     *
     * @param int $docid
     * @param int $courseid
     * @return \stdClass|null
     */
    public function get_for_course(int $docid, int $courseid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_microlearning_docs', [
            'id' => $docid,
            'courseid' => $courseid,
        ]);
        return $record ?: null;
    }

    /**
     * Update document lifecycle status (READY, PROCESSING, READY_FOR_REVIEW, PUBLISHED, FAILED).
     *
     * @param int $docid
     * @param string $status
     * @param int|null $revision Optional new revision number
     * @return void
     */
    public function update_status(int $docid, string $status, ?int $revision = null): void {
        global $DB;
        $update = (object) [
            'id' => $docid,
            'status' => $status,
            'timemodified' => time(),
        ];
        if ($revision !== null) {
            $update->revision = $revision;
        }
        $DB->update_record('local_microlearning_docs', $update);
    }

    /**
     * Check whether the document has any active (QUEUED or RUNNING) AI tasks.
     *
     * @param int $docid
     * @return bool
     */
    public function has_active_tasks(int $docid): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {local_microlearning_tasks}
                 WHERE docid = :docid
                   AND status IN ('QUEUED', 'RUNNING')";
        return $DB->record_exists_sql($sql, ['docid' => $docid]);
    }

    /**
     * Check whether any student quiz attempts exist for this document.
     *
     * @param int $docid
     * @return bool
     */
    public function has_quiz_attempts(int $docid): bool {
        global $DB;
        $sql = "SELECT 1
                  FROM {local_microlearning_qres} qr
                  JOIN {local_microlearning_quizzes} qz ON qz.id = qr.quizid
                  JOIN {local_microlearning_chunks} ch ON ch.id = qz.chunkid
                 WHERE ch.docid = :docid";
        return $DB->record_exists_sql($sql, ['docid' => $docid]);
    }

    /**
     * Delete a document and all dependent draft records (tasks, logs, chunks, summaries, quizzes, flashcards).
     * Caller must run inside a delegated transaction and clean up Moodle File API records afterwards.
     *
     * @param int $docid
     * @return void
     */
    public function delete_cascade(int $docid): void {
        global $DB;

        $chunkids = $DB->get_fieldset_select('local_microlearning_chunks', 'id', 'docid = :docid', ['docid' => $docid]);
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

        $fsetids = $DB->get_fieldset_select('local_microlearning_fsets', 'id', 'docid = :docid', ['docid' => $docid]);
        if (!empty($fsetids)) {
            [$fsetsql, $fsetparams] = $DB->get_in_or_equal($fsetids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_microlearning_fcards', "fsetid $fsetsql", $fsetparams);
            $DB->delete_records_select('local_microlearning_fsets', "id $fsetsql", $fsetparams);
        }

        $taskids = $DB->get_fieldset_select('local_microlearning_tasks', 'id', 'docid = :docid', ['docid' => $docid]);
        if (!empty($taskids)) {
            [$tasksql, $taskparams] = $DB->get_in_or_equal($taskids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_microlearning_logs', "taskid $tasksql", $taskparams);
            $DB->delete_records_select('local_microlearning_tasks', "id $tasksql", $taskparams);
        }

        $DB->delete_records('local_microlearning_docs', ['id' => $docid]);
    }
}
