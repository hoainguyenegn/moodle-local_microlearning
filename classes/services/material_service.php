<?php
namespace local_microlearning\services;
defined('MOODLE_INTERNAL') || die();

/** See docs/api-contract.md and docs/eerd-design.md for implementation requirements. */
class material_service {
    public function upload_material(array $params, int $actorid): array {
        // TODO: Verify course access, draft ownership, a single source file, MIME/type and size limits.
        // TODO: Copy the source through Moodle File API and persist document metadata via material_repository.
        // TODO: Coordinate database rollback with file cleanup; return the Material DTO.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'upload_material');
    }

    public function get_material(array $params, int $actorid): array {
        // TODO: Load the document within courseid and enforce uploader/reviewer access.
        // TODO: Return a Material DTO without raw storage URLs or paths.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'get_material');
    }

    public function delete_material(array $params, int $actorid): array {
        // TODO: Reject published documents, active processing tasks and existing student attempts.
        // TODO: Delete dependent records and File API content consistently through repository methods.
        throw new \moodle_exception('notimplemented', 'local_microlearning', '', 'delete_material');
    }

}
