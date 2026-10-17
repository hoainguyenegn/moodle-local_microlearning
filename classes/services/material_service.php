<?php
namespace local_microlearning\services;

use local_microlearning\repositories\material_repository;

defined('MOODLE_INTERNAL') || die();

class material_service {

    private material_repository $materialRepo;

    public function __construct() {
        $this->materialRepo = new material_repository();
    }

    /**
     * Process and store metadata for uploaded source materials.
     *
     * @param int $courseId Course ID context
     * @param int $userId Uploader user ID
     * @param string $title Material title
     * @param string $fileUrl Physical file path or storage URL
     * @param string $type Material type (PDF or VIDEO)
     * @param string $visibility Visibility level (PUBLIC, CLASS_ONLY, PRIVATE)
     * @return int Inserted document ID
     */
    public function processUpload(int $courseId, int $userId, string $title, string $fileUrl, string $type, string $visibility): int {
        // TODO: Validate file extension, file size, and storage accessibility.
        
        $data = new \stdClass();
        $data->courseid = $courseId;
        $data->userid = $userId;
        $data->title = $title;
        $data->fileurl = $fileUrl;
        $data->materialtype = $type;
        $data->status = 'UPLOADING';
        $data->visibility = $visibility;

        return $this->materialRepo->create($data);
    }
}