# EERD Design

The diagram shows how courses, lecture materials, generated content and student results fit together.

![AI Microlearning EERD](images/EERD.png)

[Open the full-size diagram](images/EERD.png).

## Main relationships

A course contains documents. Each document can be processed by several AI tasks and split into chunks. Chunks hold summaries and quizzes; flashcard sets belong to documents. Students submit quizzes, and each result stores their selected answers.

PDFs use page markers. Audio and video use timestamps so students can return to a specific part of the source.

## Mapping to Moodle

`db/install.xml` defines the plugin tables below. Moodle adds the site's configured table prefix automatically.

| Diagram entity | Plugin table |
| --- | --- |
| DOCUMENT | local_microlearning_docs |
| AI_TASK | local_microlearning_tasks |
| MATERIAL_CHUNK | local_microlearning_chunks |
| SUMMARY | local_microlearning_sums |
| FLASHCARD_SET / FLASHCARD | local_microlearning_fsets / local_microlearning_fcards |
| QUIZ / QUESTION / ANSWER | local_microlearning_quizzes / local_microlearning_quests / local_microlearning_ans |
| QUIZ_RESULT / RESULT_DETAIL | local_microlearning_qres / local_microlearning_rdet |
| CONFIG / AI_LOG | local_microlearning_cfgs / local_microlearning_logs |

The image is a conceptual model, not an exact copy of the schema:

- Moodle manages users, courses, enrolments and course roles. The plugin does not create separate lecturer/student accounts or store passwords.
- Source files use Moodle File API and docs.fileid rather than a public file_url. PDF, AUDIO and VIDEO share docs.materialtype; there are no separate source subtype tables.
- The current schema does not include every diagram attribute, such as quiz duration, source page count or separate prompt/completion token counts.
- A summary is optional until generated and unique per chunk. Parent-child checks and publication rules still need service validation.

## TODO

- Confirm the remaining diagram-to-schema differences before implementation.
- Implement course/owner checks, task locking, revision checks and atomic publication.
- Validate that selected answers belong to the submitted quiz; save results and details together.
- Design scripts, voice profiles, TTS and library search separately before extending this schema.

See [API Contract](api-contract.md) for the current endpoints.
