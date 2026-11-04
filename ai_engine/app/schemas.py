"""
schemas.py - Hop dong du lieu (contract) Engine -> Moodle, phien ban 1.0.

Moodle (classes/ai/output_validator.php) kiem tra lai toan bo cau truc nay truoc khi
ghi vao DB, nen moi thay doi o day PHAI tang schema_version va cap nhat
docs/ai-engine-contract.md.
"""

from __future__ import annotations

from pydantic import BaseModel, Field

SCHEMA_VERSION = "1.0"
ALLOWED_OUTPUTS = ("SUMMARY", "QUIZ", "FLASHCARDS")


class AnswerOption(BaseModel):
    text: str
    is_correct: bool


class Question(BaseModel):
    question: str
    explanation: str = ""
    options: list[AnswerOption] = Field(min_length=2)


class Quiz(BaseModel):
    title: str
    questions: list[Question]


class Summary(BaseModel):
    title: str
    points: list[str]
    key_concepts: list[str]
    markdown: str                 # Ban Markdown de luu vao local_microlearning_sums.content


class ChunkOut(BaseModel):
    position: int                 # 1-based
    source_start: int             # Trang dau, 1-based, inclusive
    source_end: int               # Trang cuoi, 1-based, inclusive
    content: str                  # Noi dung goc cua micro-lesson (text + phan tich Vision)
    summary: Summary | None = None
    quiz: Quiz | None = None


class Flashcard(BaseModel):
    front: str
    back: str


class FlashcardSet(BaseModel):
    title: str
    cards: list[Flashcard]


class PageReport(BaseModel):
    page_number: int
    page_type: str
    ocr_applied: bool
    vision_analyzed: bool
    blur_score: float | None = None


class DocumentReport(BaseModel):
    total_pages: int
    ocr_pages: int
    vision_pages: int
    routing: dict[str, int]
    pages: list[PageReport]


class ProcessResponse(BaseModel):
    schema_version: str = SCHEMA_VERSION
    task_id: int
    doc_id: int
    outputs: list[str]
    document: DocumentReport
    chunks: list[ChunkOut]
    flashcards: FlashcardSet | None = None
    usage: dict
    warnings: list[str]
    processing_time_ms: int


class ErrorBody(BaseModel):
    code: str
    message: str
    retryable: bool


class ErrorResponse(BaseModel):
    error: ErrorBody

