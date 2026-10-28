# API Contract

APIs are registered in `db/services.php`. Controllers validate parameters and course capabilities; services still return `notimplemented` until their TODOs are completed.

## Access

Use core/ajax inside Moodle. REST clients POST to `<MOODLE_URL>/webservice/rest/server.php` with these body fields:

```text
wstoken=<authorised token>
moodlewsrestformat=json
wsfunction=local_microlearning_<method>
courseid=<course ID>
```

An administrator must enable REST access first. Identity comes from Moodle. Capability names below use `local/microlearning:`.

| Method | Capability | Parameters besides courseid |
| --- | --- | --- |
| upload_material | uploadmaterial | draftitemid:int, title:string, materialtype:string |
| get_material | uploadmaterial | docid:int |
| delete_material | uploadmaterial | docid:int |
| dispatch_ai | uploadmaterial | docid:int, requestkey:string, outputs:string |
| get_task | uploadmaterial | taskid:int |
| get_workspace | reviewcontent | docid:int |
| save_review | reviewcontent | docid:int, revision:int, contentjson:string |
| publish_material | reviewcontent | docid:int, revision:int |
| get_feed | viewfeed | afterid:int, limit:int |
| get_content | viewfeed | docid:int |
| get_quiz | viewfeed | quizid:int |
| submit_quiz | viewfeed | quizid:int, requestkey:string, answersjson:string |
| get_attempt | viewfeed | attemptid:int |

## Payloads

Success uses `{ "datajson": "<JSON resource>" }`. Material responses contain metadata and revision; tasks contain status and timing; workspaces contain ordered chunks, summaries and quizzes; attempts contain score and answer details. Feed responses use `{items,nextafterid}`. Do not expose storage paths, or correctness/explanations before quiz submission. Inspect exception/errorcode even when HTTP status is 200.

- materialtype: PDF, AUDIO or VIDEO.
- outputs: CSV without duplicates, such as `SUMMARY,QUIZ`; FLASHCARDS is supported by the proposed contract.
- requestkey: 1–64 ASCII letters, digits, hyphens or underscores. Same key/payload returns the original task/attempt; changed payload is a conflict.
- contentjson: `{"chunks":[...]}`. Edit existing text only; validate ownership and expected revision. Increment revision on save/publish.
- answersjson: `[{"questid":12,"ansid":43}]`. Require one valid option per question; grade one point per question server-side. Save results/details together and freeze quizzes with attempts.
- Pagination: ascending document IDs, afterid=0 initially, limit=1..100. Other IDs are positive; trimmed titles are 1..255 characters.

## AI engine

The engine is separate. TODO: Agree on authenticated transport and versioned input/output schemas using taskid/docid. Return ordered chunks, source markers and requested summaries/quizzes/flashcards. PDF markers are inclusive 1-based pages; audio/video markers are milliseconds [start,end).

TODO: Validate output, require at least two options and one correct answer per question, then commit content/task/document states atomically. Bound retries, prevent duplicate output and keep AI calls outside database transactions.

## Remaining TODOs

Implement resource ownership, validation, repositories, typed responses and sanitised errors. Add material listing/editing, task/attempt history, teacher reports and flashcard access/review. Agree on upload/output limits and error codes before integration.

See [EERD Design](eerd-design.md) for the schema mapping.
