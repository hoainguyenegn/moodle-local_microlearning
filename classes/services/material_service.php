<?php
namespace local_microlearning\services;

defined('MOODLE_INTERNAL') || die();

use local_microlearning\repositories\material_repository;

/**
 * Service handling source document upload (PDF/Audio/Video), retrieval, and deletion.
 *
 * See docs/api-contract.md and docs/eerd-design.md for implementation requirements.
 */
class material_service {
    /** Allowed source material types */
    public const ALLOWED_TYPES = ['PDF', 'AUDIO', 'VIDEO'];

    /** Default max PDF size (50 MB) */
    public const DEFAULT_MAX_PDF_BYTES = 52428800;

    /** @var material_repository */
    private material_repository $materials;

    /**
     * @param material_repository|null $materials
     */
    public function __construct(?material_repository $materials = null) {
        $this->materials = $materials ?? new material_repository();
    }

    /**
     * Validate and store an uploaded source material from the user's Moodle draft filearea.
     *
     * @param array $params ['courseid' => int, 'draftitemid' => int, 'title' => string, 'materialtype' => string]
     * @param int $actorid Authenticated Moodle user ID
     * @return array Material DTO
     */
    public function upload_material(array $params, int $actorid): array {
        global $DB;

        $courseid = (int) $params['courseid'];
        $draftitemid = (int) $params['draftitemid'];
        $title = trim((string) $params['title']);
        $materialtype = strtoupper(trim((string) $params['materialtype']));

        if ($courseid <= 0 || $draftitemid <= 0 || $actorid <= 0) {
            throw new \moodle_exception('invalidparameter', 'local_microlearning');
        }
        if ($title === '' || \core_text::strlen($title) > 255) {
            throw new \moodle_exception('invalidtitle', 'local_microlearning');
        }
        if (!in_array($materialtype, self::ALLOWED_TYPES, true)) {
            throw new \moodle_exception('invalidmaterialtype', 'local_microlearning');
        }

        $fs = get_file_storage();
        $usercontext = \context_user::instance($actorid);
        $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id ASC', false);
        if (count($draftfiles) !== 1) {
            throw new \moodle_exception('singlesourcefilerequired', 'local_microlearning');
        }

        /** @var \stored_file $draftfile */
        $draftfile = reset($draftfiles);
        $this->validate_source_file($draftfile, $materialtype);

        $coursecontext = \context_course::instance($courseid);
        $storedfile = null;
        $transaction = $DB->start_delegated_transaction();

        try {
            // NOTE (Team Lead - User Entity Injection): $actorid is stored as docs.userid.
            $docrecord = (object) [
                'courseid' => $courseid,
                'userid' => $actorid,
                'title' => $title,
                'materialtype' => $materialtype,
                'fileid' => 0,
                'status' => 'READY',
                'visibility' => 'CLASS_ONLY',
                'revision' => 1,
            ];
            $docid = $this->materials->create($docrecord);

            $filerecord = [
                'contextid' => $coursecontext->id,
                'component' => 'local_microlearning',
                'filearea' => 'source',
                'itemid' => $docid,
                'filepath' => '/',
                'filename' => clean_param($draftfile->get_filename(), PARAM_FILE) ?: 'source.pdf',
                'userid' => $actorid,
            ];
            $storedfile = $fs->create_file_from_storedfile($filerecord, $draftfile);
            $DB->set_field('local_microlearning_docs', 'fileid', (int) $storedfile->get_id(), ['id' => $docid]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            if ($storedfile instanceof \stored_file) {
                $storedfile->delete();
            }
            throw $e;
        }

        $saved = $this->materials->get_by_id($docid);
        return $this->format_material_dto($saved);
    }

    /**
     * Load a document within courseid and return its sanitised DTO.
     *
     * @param array $params ['courseid' => int, 'docid' => int]
     * @param int $actorid Authenticated user ID
     * @return array Material DTO without raw storage paths
     */
    public function get_material(array $params, int $actorid): array {
        $courseid = (int) $params['courseid'];
        $docid = (int) $params['docid'];
        $doc = $this->require_course_document($docid, $courseid, $actorid);
        return $this->format_material_dto($doc);
    }

    /**
     * Delete an unpublished source material and its draft children when no active tasks or student attempts exist.
     *
     * @param array $params ['courseid' => int, 'docid' => int]
     * @param int $actorid Authenticated user ID
     * @return array ['deleted' => true, 'docid' => int]
     */
    public function delete_material(array $params, int $actorid): array {
        global $DB;

        $courseid = (int) $params['courseid'];
        $docid = (int) $params['docid'];
        $doc = $this->require_course_document($docid, $courseid, $actorid, true);

        if ($doc->status === 'PUBLISHED') {
            throw new \moodle_exception('cannotdeletepublished', 'local_microlearning');
        }
        if ($this->materials->has_active_tasks($docid)) {
            throw new \moodle_exception('cannotdeleteactivetask', 'local_microlearning');
        }
        if ($this->materials->has_quiz_attempts($docid)) {
            throw new \moodle_exception('cannotdeletewithattempts', 'local_microlearning');
        }

        $coursecontext = \context_course::instance($courseid);
        $transaction = $DB->start_delegated_transaction();
        $this->materials->delete_cascade($docid);
        $transaction->allow_commit();

        $fs = get_file_storage();
        $fs->delete_area_files($coursecontext->id, 'local_microlearning', 'source', $docid);

        return [
            'deleted' => true,
            'docid' => $docid,
        ];
    }

    /**
     * Verify the document exists in the course and check actor ownership/access.
     *
     * @param int $docid
     * @param int $courseid
     * @param int $actorid
     * @param bool $requireowner
     * @return \stdClass
     */
    public function require_course_document(
        int $docid,
        int $courseid,
        int $actorid,
        bool $requireowner = false
    ): \stdClass {
        $doc = $this->materials->get_for_course($docid, $courseid);
        if (!$doc) {
            throw new \moodle_exception('materialnotfound', 'local_microlearning');
        }
        // NOTE (Team Lead - User Entity Injection): Centralised ownership check for $actorid.
        if ($requireowner && (int) $doc->userid !== $actorid) {
            $context = \context_course::instance($courseid);
            if (!has_capability('local/microlearning:reviewcontent', $context, $actorid)) {
                throw new \moodle_exception('nopermission', 'local_microlearning');
            }
        }
        return $doc;
    }

    /**
     * Validate MIME type, header magic bytes, and size limits for the uploaded source file.
     *
     * @param \stored_file $file
     * @param string $materialtype
     * @return void
     */
    private function validate_source_file(\stored_file $file, string $materialtype): void {
        $size = (int) $file->get_filesize();
        if ($size <= 0) {
            throw new \moodle_exception('emptyfile', 'local_microlearning');
        }

        if ($materialtype === 'PDF') {
            $maxbytes = (int) get_config('local_microlearning', 'maxpdfbytes');
            if ($maxbytes <= 0) {
                $maxbytes = self::DEFAULT_MAX_PDF_BYTES;
            }
            if ($size > $maxbytes) {
                throw new \moodle_exception('filetoolarge', 'local_microlearning');
            }
            $mimetype = strtolower((string) $file->get_mimetype());
            if ($mimetype !== 'application/pdf') {
                throw new \moodle_exception('invalidpdffile', 'local_microlearning');
            }
            $handle = $file->get_content_file_handle();
            $header = is_resource($handle) ? fread($handle, 5) : '';
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($header !== '%PDF-') {
                throw new \moodle_exception('invalidpdffile', 'local_microlearning');
            }
        }
    }

    /**
     * Map DB record to public Material DTO without exposing internal file storage paths.
     *
     * @param \stdClass $doc
     * @return array
     */
    private function format_material_dto(\stdClass $doc): array {
        return [
            'docid' => (int) $doc->id,
            'courseid' => (int) $doc->courseid,
            'userid' => (int) $doc->userid,
            'title' => (string) $doc->title,
            'materialtype' => (string) $doc->materialtype,
            'status' => (string) $doc->status,
            'visibility' => (string) $doc->visibility,
            'revision' => (int) $doc->revision,
            'timecreated' => (int) $doc->timecreated,
            'timemodified' => (int) $doc->timemodified,
        ];
    }
}
