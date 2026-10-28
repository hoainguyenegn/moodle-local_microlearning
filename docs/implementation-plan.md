# Implementation Handoff

This scaffold covers PDF/audio input -> AI summaries/quizzes -> teacher review -> publication -> student assessment. It does not implement the full extended feature set. Read system-architecture.md, eerd.md and api-contract.md before implementing a module.

## Module ownership

Plugin paths are relative to classes/. The external AI engine will be maintained separately.

| Module | Starting files | Team implementation TODO |
| --- | --- | --- |
| Material and tasks | external/material_api.php, external/ai_task_api.php; services/material_service.php, services/ai_orchestrator_service.php; repositories/material_repository.php, repositories/ai_task_repository.php; task/process_material.php | Implement draft/File API handling, course/owner validation, MIME/size limits, idempotency, queue/locks/retries and worker transport. |
| Review | external/review_api.php; services/review_service.php; repositories/review_repository.php, repositories/chunk_repository.php | Implement workspace queries, nested-ID validation, revision conflicts and atomic publication. |
| Student and quiz | external/student_api.php, external/quiz_api.php; services/student_service.php, services/quiz_service.php; repositories/student_repository.php, repositories/quiz_repository.php | Implement the published feed, answer redaction, server-side grading, attempt ownership and atomic/idempotent submissions. |
| External AI engine | Separate repository; Moodle integration starts in task/process_material.php | TODO: Implement PDF parsing/OCR, transcription, generation and output validation in the external engine; agree on its API with the plugin team. |

Controllers already call services. Service-to-repository wiring is intentionally TODO. material_repository retains the original create/get_by_id primitives; other repositories are unimplemented stubs. Use draftitemid rather than the original processUpload(fileUrl) design.

## Remaining scaffold TODOs

- TODO: Add list_materials, update_material, list_tasks, list_attempts, get_quiz_report and flashcard access/review contracts and method stubs.
- TODO: Specify repository arguments/results for list/update/delete and course-scoped queries; replace generic array contracts with agreed DTOs where appropriate.
- TODO: Add versioned worker JSON schemas and authenticated transport interfaces.
- TODO: Add settings.php, an authorised lib.php file callback, a privacy provider, and UI pages/templates/AMD modules.
- TODO: Design the extended script/TTS/voice/library/search modules described in scope-update.md before adding their schema or APIs.

## Integration order

1. TODO: Validate XMLDB, install/upgrade the plugin on a Moodle test site, and confirm the API registry resolves every controller.
2. TODO: Implement PDF/audio upload from Moodle drafts, resource ownership checks, and authorised file delivery.
3. TODO: Connect cron tasks to an authenticated worker with bounded retries/timeouts, locking and output validation.
4. TODO: Implement draft editing and revision-based atomic publication; reject incomplete output.
5. TODO: Implement published student content and quiz submissions; reveal answers only after submission and avoid duplicate attempts on retries.
6. TODO: Build the UI and Moodle tests for cross-course/owner access, MIME/size validation, scanned PDFs, silent audio, worker errors, concurrent operations and rollback.

## Verification

This repository contains the Moodle plugin scaffold only. The worker and scaffold test directories are excluded. The Moodle processing task remains as an integration stub for the external AI engine.

TODO: Run PHP lint, XMLDB editor validation, Moodle install/upgrade, PHPUnit and end-to-end tests on the team's runtime. PHP/Moodle is not available in the current execution environment.
