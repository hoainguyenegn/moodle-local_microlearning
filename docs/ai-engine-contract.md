# AI Engine Contract (v1.0)

This document defines the HTTP and JSON contract between the Moodle plugin (`local_microlearning`) and the standalone Python PDF Processing & AI Routing service (`ai_engine/`).

---

## 1. Transport & Authentication

- **Base URL:** Configured in Moodle under `Site administration -> Plugins -> Local plugins -> AI Micro-learning` (`local_microlearning/engineurl`, default `http://127.0.0.1:8000`).
- **Authentication:** Every request to `/v1/process` must include the header:
  ```text
  X-Engine-Key: <ENGINE_API_KEY>
  ```
  The engine compares the key using constant-time `hmac.compare_digest` and rejects missing/invalid keys with HTTP `401`.

---

## 2. Endpoints

### `GET /health`
Returns service readiness and whether local Tesseract OCR is available:
```json
{
  "status": "ok",
  "schema_version": "1.0",
  "ocr_available": true
}
```

### `POST /v1/process` (`multipart/form-data`)
 invoked by `\local_microlearning\task\process_material` via `\local_microlearning\ai\pdf_engine_client` **outside** any database transaction.

#### Form Fields
| Field | Type | Required | Description |
| :--- | :--- | :---: | :--- |
| `file` | binary (`application/pdf`) | Yes | Raw PDF file stream from Moodle File Storage |
| `task_id` | `int (>0)` | Yes | `local_microlearning_tasks.id` |
| `doc_id` | `int (>0)` | Yes | `local_microlearning_docs.id` |
| `outputs` | `string` | Yes | Canonical CSV subset of `SUMMARY,QUIZ,FLASHCARDS` |
| `title` | `string` | No | Document title |

#### Success Response (`200 OK`)
```json
{
  "schema_version": "1.0",
  "task_id": 12,
  "doc_id": 5,
  "outputs": ["SUMMARY", "QUIZ", "FLASHCARDS"],
  "document": {
    "total_pages": 10,
    "ocr_pages": 2,
    "vision_pages": 1,
    "routing": {
      "text_heavy": 9,
      "visual_heavy": 0,
      "mixed": 1
    },
    "pages": [
      {
        "page_number": 1,
        "page_type": "text_heavy",
        "ocr_applied": false,
        "vision_analyzed": false,
        "blur_score": null
      }
    ]
  },
  "chunks": [
    {
      "position": 1,
      "source_start": 1,
      "source_end": 3,
      "content": "Merged page text and visual analysis...",
      "summary": {
        "title": "Lesson Title",
        "points": ["Key point 1", "Key point 2"],
        "key_concepts": ["Concept A", "Concept B"],
        "markdown": "## Lesson Title\n\n- Key point 1\n- Key point 2\n\n**Key concepts:** Concept A, Concept B"
      },
      "quiz": {
        "title": "Quiz: Lesson Title",
        "questions": [
          {
            "question": "Question stem?",
            "explanation": "Short explanation.",
            "options": [
              {"text": "Option A", "is_correct": true},
              {"text": "Option B", "is_correct": false},
              {"text": "Option C", "is_correct": false},
              {"text": "Option D", "is_correct": false}
            ]
          }
        ]
      }
    }
  ],
  "flashcards": {
    "title": "Lecture Flashcards",
    "cards": [
      {"front": "Term", "back": "Definition"}
    ]
  },
  "usage": {
    "llm_calls": 4,
    "tokens": 3120,
    "latency_ms": 8450,
    "models": ["gemini/gemini-3.5-flash-lite"]
  },
  "warnings": [],
  "processing_time_ms": 9120
}
```

#### Error Response (`401` / `413` / `422` / `500` / `502` / `503`)
```json
{
  "error": {
    "code": "llm_rate_limited",
    "message": "AI provider error",
    "retryable": true
  }
}
```
- When `retryable` is `true` and `attempts < 3`, Moodle's `process_material` task logs a warning, returns the task to `QUEUED`, and rethrows so Moodle's adhoc scheduler retries with backoff.
- When `retryable` is `false` (e.g., `not_a_pdf`, `pdf_encrypted`, `llm_auth_failed`) or max attempts are reached, the task and document are marked `FAILED`.

