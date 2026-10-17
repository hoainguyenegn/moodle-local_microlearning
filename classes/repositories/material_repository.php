<?php
namespace local_microlearning\repositories;

defined('MOODLE_INTERNAL') || die();

class material_repository {

    /**
     * Insert a new document record into the XMLDB table.
     *
     * @param \stdClass $data
     * @return int Record ID
     */
    public function create(\stdClass $data): int {
        global $DB;
        $data->timecreated = time();
        $data->timemodified = time();
        return $DB->insert_record('local_microlearning_docs', $data);
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
}