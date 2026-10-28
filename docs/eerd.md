# EERD — AI Microlearning

The relational diagram maps directly to db/install.xml: thirteen plugin tables plus Moodle course/user/files. Moodle determines the actual database prefix; do not hard-code mdl_.

## Enhanced entity specialization

SOURCE MATERIAL uses total, disjoint specialization into PDF, AUDIO and VIDEO, implemented by docs.materialtype. All subtypes share ownership, tasks, chunks, quizzes and summaries. PDF uses parsing/OCR; AUDIO uses speech recognition; VIDEO extracts audio before transcription.

TODO: Validate supported discriminator values in services; the schema alone does not enforce this specialization.

```mermaid
classDiagram
    Material <|-- PDF
    Material <|-- Audio
    Material <|-- Video
    class Material {
        materialtype PDF_OR_AUDIO_OR_VIDEO
        courseid
        userid
        fileid
    }
```

## Relationships and keys

```mermaid
erDiagram
    docs {
        int id PK
        int courseid FK
        int userid FK
        char title
        char materialtype
        int fileid FK
        char status
        char visibility
        int timecreated
        int timemodified
        int revision
    }
    course ||--o{ docs : courseid
    user ||--o{ docs : userid
    files ||--o{ docs : fileid
    tasks {
        int id PK
        int docid FK
        int requestedby FK
        char requestkey
        char inputhash
        char outputs
        char status
        int attempts
        char errorcode
        int startedat
        int finishedat
        int timecreated
        int timemodified
    }
    docs ||--o{ tasks : docid
    user ||--o{ tasks : requestedby
    chunks {
        int id PK
        int docid FK
        int taskid FK
        int position
        text content
        int sourcestart
        int sourceend
        char status
        int timecreated
        int timemodified
    }
    docs ||--o{ chunks : docid
    tasks ||--o{ chunks : taskid
    sums {
        int id PK
        int chunkid FK
        text content
        char status
        int timecreated
        int timemodified
    }
    chunks ||--o{ sums : chunkid
    fsets {
        int id PK
        int docid FK
        int taskid FK
        char title
        char status
        int timecreated
        int timemodified
    }
    docs ||--o{ fsets : docid
    tasks ||--o{ fsets : taskid
    fcards {
        int id PK
        int fsetid FK
        int position
        text front
        text back
        int timecreated
        int timemodified
    }
    fsets ||--o{ fcards : fsetid
    quizzes {
        int id PK
        int chunkid FK
        char title
        char status
        int timecreated
        int timemodified
    }
    chunks ||--o{ quizzes : chunkid
    quests {
        int id PK
        int quizid FK
        int position
        text question
        text explanation
        int timecreated
        int timemodified
    }
    quizzes ||--o{ quests : quizid
    ans {
        int id PK
        int questid FK
        int position
        text answer
        int iscorrect
        int timecreated
        int timemodified
    }
    quests ||--o{ ans : questid
    qres {
        int id PK
        int quizid FK
        int userid FK
        char requestkey
        char inputhash
        number score
        number maxscore
        int timecreated
        int timemodified
    }
    quizzes ||--o{ qres : quizid
    user ||--o{ qres : userid
    rdet {
        int id PK
        int qresid FK
        int questid FK
        int ansid FK
        int iscorrect
        int timecreated
        int timemodified
    }
    qres ||--o{ rdet : qresid
    quests ||--o{ rdet : questid
    ans ||--o{ rdet : ansid
    cfgs {
        int id PK
        char name
        text value
        int timecreated
        int timemodified
    }
    logs {
        int id PK
        int taskid FK
        char level
        char eventcode
        int tokens
        int latencyms
        int timecreated
        int timemodified
    }
    tasks ||--o{ logs : taskid
```

Unique constraints include one summary per chunk, a task request key per requester, a quiz submission key per user, positions within a task/set/quiz/question, and one answer detail per question/attempt.

TODO: Validate that ansid belongs to questid, questid belongs to the attempt's quiz, and the caller has course/resource access. Foreign keys alone do not enforce these business rules.

The diagram shows general relationship cardinalities. The proposed MVP uses one source file per document and File API cleanup on deletion. A document may have historical tasks, but only one may write output at a time. A chunk has at most one summary; the schema permits multiple quizzes, while the proposed MVP generates one quiz per chunk. Chunks store transcript text and source offsets rather than a separate transcript table. cfgs contains non-sensitive prompts/parameters; credentials belong in Moodle configuration.

## Team decisions and TODOs

- TODO: Confirm plugin-owned quizzes versus Moodle question bank/quiz activities. Gradebook integration is not implemented.
- TODO: Add flashcard access/review contracts if retaining the original thirteen-table scope; no dedicated flashcard endpoint exists yet.
- TODO: Agree on retention/privacy, AI/OCR/ASR providers and source limits.
- TODO: Update this model before adding scripts, voice profiles, generated speech or library/search modules.
