# API Contract — Skeleton v1

Use Moodle External Functions through core/ajax (session/sesskey), or POST to `/webservice/rest/server.php` with `wsfunction=local_microlearning_<method>`, `moodlewsrestformat=json` and an administrator-issued token. REST service access is disabled/restricted by default. Do not place tokens in URLs.

Every method accepts courseid:int. The actor comes from the Moodle session/token. Controllers validate parameter types, login/context and capabilities. TODO: Implement resource ownership, course scope and business validation in services. Services currently throw `notimplemented` deliberately.

| Method | Capability | Parameters besides courseid | Intended result |
| --- | --- | --- | --- |
| upload_material | uploadmaterial | draftitemid:int, title:string, materialtype:PDF/AUDIO/VIDEO | Material |
| get_material | uploadmaterial | docid:int | Material |
| delete_material | uploadmaterial | docid:int | {deleted:true,docid} |
| dispatch_ai | uploadmaterial | docid:int, requestkey:string, outputs:string | Task |
| get_task | uploadmaterial | taskid:int | Task |
| get_workspace | reviewcontent | docid:int | Workspace |
| save_review | reviewcontent | docid:int, revision:int, contentjson:string | Workspace |
| publish_material | reviewcontent | docid:int, revision:int | Material |
| get_feed | viewfeed | afterid:int (initially 0), limit:int (1..100) | {items:Material[],nextafterid:int} |
| get_content | viewfeed | docid:int | PublishedContent |
| get_quiz | viewfeed | quizid:int | StudentQuiz |
| submit_quiz | viewfeed | quizid:int, requestkey:string, answersjson:string | Attempt |
| get_attempt | viewfeed | attemptid:int | Attempt owned by the caller |

The intended success envelope is `{ "datajson": "<JSON resource>" }`. Moodle REST may return exceptions with HTTP 200, so inspect exception/errorcode. TODO: Define typed external return structures after agreeing on UI payloads.

outputs is a CSV list without duplicates: SUMMARY/QUIZ/FLASHCARDS. requestkey is 1..64 ASCII alphanumeric/hyphen/underscore characters. TODO: Store a canonical payload hash in inputhash, reject matching keys with different payloads, trim titles to 1..255 characters and enforce positive identifiers. Feed ordering is ascending document ID.

## Resource shapes

- Material: `{id,courseid,title,materialtype,status,visibility,revision,timecreated,timemodified}`; no storage URLs/paths.
- Task: `{id,docid,status,outputs,attempts,errorcode,startedat,finishedat}`.
- Workspace: `{document:Material,chunks:[{id,position,content,sourcestart,sourceend,summary:{id,content}|null,quizzes:[TeacherQuiz]}]}`.
- TeacherQuiz: `{id,title,questions:[{id,position,question,explanation,answers:[{id,position,answer,iscorrect}]}]}`.
- PublishedContent: `{document:Material,chunks:[{id,position,content,sourcestart,sourceend,summary:{id,content}|null,quizids:[int]}]}`.
- StudentQuiz: `{id,title,questions:[{id,position,question,answers:[{id,position,answer}]}]}`; omit explanation/iscorrect before submission.
- Attempt: `{id,quizid,score,maxscore,details:[{questid,ansid,iscorrect,explanation}],timecreated}`.

contentjson = `{ "chunks": [...] }`, following Workspace.chunks. The proposed MVP only edits existing text; it cannot add/delete/reparent IDs or change owner/course/task/status. TODO: Validate nested ownership and expected revision, then increment revision atomically on save/publication.

answersjson = `[{"questid":12,"ansid":43}]`. Each question requires one option; the option must belong to the question and the question to the published quiz. TODO: Grade one point per question on the server and commit attempts/details atomically. Repeating the same key/hash returns the original attempt. Freeze quizzes with existing attempts.

## Worker v1

Proposed transport input: schema_version=1, taskid, docid, materialtype, outputs and a file supplied through authenticated transport. The external engine is maintained separately. TODO: Agree on version/document mapping and executable JSON schemas between the plugin and external engine.

Expected output shape: `{schema_version:1,taskid,docid,chunks:[{position,content,sourcestart,sourceend,summary,quiz:{title,questions:[{position,question,explanation,answers:[{position,answer,iscorrect}]}]},flashcards:[]}]}`. TODO: Validate task/document/source hash, RUNNING state and schema before writing. SUMMARY requires a summary; QUIZ requires at least one question with at least two options and exactly one correct option; FLASHCARDS requires nonempty cards. Unrequested outputs must be null/empty. PDF offsets are inclusive 1-based pages; audio/video offsets are milliseconds [start,end).

TODO: Commit all output, task SUCCEEDED and document REVIEW in one transaction. Retrying a SUCCEEDED task must not duplicate output. Do not hold transactions during AI calls. Agree on provider, transport and resource limits before integration.

TODO: Implement sanitised errors: invalidmaterial, invalidpayload, resourcenotfound, requestconflict, staleversion, invalidstate, processingfailed. Never expose SQL, tokens, stack traces or provider secrets.
