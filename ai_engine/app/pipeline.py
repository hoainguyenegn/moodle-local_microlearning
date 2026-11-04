"""
pipeline.py - Dieu phoi toan bo luong xu ly PDF (thay the benchmark.py cua Process-PDF).

    PDF bytes
      -> [1] Extract   (PyMuPDF + loc header/footer/logo + Selective OCR)
      -> [2] Classify  (TEXT_HEAVY / VISUAL_HEAVY / MIXED)
      -> [3] Vision    (chi trang VISUAL/MIXED; Inclusive: anh + text da trich xuat)
      -> [4] Chunk     (gop noi dung theo thu tu trang -> micro-lessons co moc trang)
      -> [5] Generate  (1 lan goi LLM / chunk: Summary + Quiz + Flashcards)
      -> [6] Normalize (chuan hoa & loc cau hoi loi truoc khi tra cho Moodle)

Engine KHONG ghi DB. Moodle la noi duy nhat luu du lieu (xem process_material adhoc task).
"""

from __future__ import annotations

import logging
import math
import time

from app.config import Settings, settings
from app.core.classifier import classify_document, routing_summary
from app.core.text_chunking import Chunk, chunk_pages
from app.core.vision_cv import preprocess_for_vision
from app.extractors.pdf_parser import PdfError, extract_pages, get_metadata, open_pdf
from app.llm.ai_router import LLMClient, LLMError
from app.llm.prompts import build_lesson_prompt, build_vision_prompt
from app.schemas import (
    AnswerOption, ChunkOut, DocumentReport, Flashcard, FlashcardSet, PageReport,
    ProcessResponse, Question, Quiz, Summary,
)

logger = logging.getLogger(__name__)

OPTION_LETTERS = ("A", "B", "C", "D", "E", "F")


# ---------------------------------------------------------------------------
# [3] Vision - Inclusive Routing
# ---------------------------------------------------------------------------
def _merge_visual(page_number: int, text: str, analysis: dict) -> str:
    """Ghep text goc + phan tich Vision. Text goc LUON duoc giu (Zero Data Loss)."""
    description = str(analysis.get("page_description", "")).strip()
    points = [str(p).strip() for p in analysis.get("key_points", []) or [] if str(p).strip()]
    if not description and not points:
        return text
    visual = [f"[Visual content - page {page_number}]"]
    if description:
        visual.append(description)
    visual.extend(f"- {p}" for p in points)
    return (text.strip() + "\n\n" if text.strip() else "") + "\n".join(visual)


# ---------------------------------------------------------------------------
# [6] Normalize LLM output
# ---------------------------------------------------------------------------
def _clean_list(values) -> list[str]:
    if not isinstance(values, list):
        return []
    return [str(v).strip() for v in values if str(v).strip()]


def _normalize_summary(raw) -> Summary | None:
    if not isinstance(raw, dict):
        return None
    title = str(raw.get("title", "")).strip()
    points = _clean_list(raw.get("points") or raw.get("summary_points"))
    concepts = _clean_list(raw.get("key_concepts"))
    if not points:
        return None
    title = title or points[0][:120]
    lines = [f"## {title}", ""] + [f"- {p}" for p in points]
    if concepts:
        lines += ["", "**Key concepts:** " + ", ".join(concepts)]
    return Summary(title=title[:255], points=points, key_concepts=concepts, markdown="\n".join(lines))


def _normalize_question(raw) -> Question | None:
    """Chap nhan options dang dict {A: ..} hoac list; dung 1 dap an dung, >= 2 lua chon."""
    if not isinstance(raw, dict):
        return None
    text = str(raw.get("question", "")).strip()
    options_raw = raw.get("options")
    correct = str(raw.get("correct_answer", "")).strip()
    if isinstance(options_raw, dict):
        items = [(str(k).strip().upper(), str(v).strip()) for k, v in options_raw.items()]
    elif isinstance(options_raw, list):
        items = [(OPTION_LETTERS[i] if i < len(OPTION_LETTERS) else str(i), str(v).strip())
                 for i, v in enumerate(options_raw)]
    else:
        return None
    items = [(key, value) for key, value in items if value]
    if not text or len(items) < 2:
        return None
    correct_key = correct.upper().rstrip(").:")
    options = [AnswerOption(text=value, is_correct=(key == correct_key or value == correct)) for key, value in items]
    if sum(o.is_correct for o in options) != 1:
        return None
    return Question(question=text, explanation=str(raw.get("explanation", "")).strip(), options=options)


def _normalize_quiz(raw, title: str) -> tuple[Quiz | None, int]:
    """Tra ve (quiz, so cau bi loai)."""
    if isinstance(raw, dict):
        raw = raw.get("questions")
    if not isinstance(raw, list):
        return None, 0
    questions = [q for q in (_normalize_question(item) for item in raw) if q is not None]
    dropped = len(raw) - len(questions)
    if not questions:
        return None, dropped
    return Quiz(title=f"Quiz: {title}"[:255], questions=questions), dropped


def _normalize_flashcards(raw) -> list[Flashcard]:
    if not isinstance(raw, list):
        return []
    cards = []
    for item in raw:
        if isinstance(item, dict):
            front, back = str(item.get("front", "")).strip(), str(item.get("back", "")).strip()
            if front and back:
                cards.append(Flashcard(front=front, back=back))
    return cards


# ---------------------------------------------------------------------------
# [5] Generate
# ---------------------------------------------------------------------------
def _generate_lesson(llm: LLMClient, chunk: Chunk, outputs: set[str], cfg: Settings) -> dict | None:
    """1 lan goi LLM cho 1 chunk; neu JSON loi -> thu lai voi it cau hoi & input ngan hon."""
    attempts = (
        (cfg.llm_input_chars, cfg.questions_per_chunk, cfg.flashcards_per_chunk),
        (min(cfg.llm_input_chars, 5000), 3, 3),
    )
    for max_chars, num_questions, num_cards in attempts:
        prompt = build_lesson_prompt(chunk.content[:max_chars], chunk.source_start, chunk.source_end,
                                     outputs, num_questions, num_cards)
        result = llm.complete_json(prompt)
        if result is not None:
            return result
        logger.warning("Chunk %d: invalid JSON, retrying with fewer items", chunk.position)
    return None


def _effective_chunk_size(page_contents: list[tuple[int, str]], cfg: Settings) -> int:
    """Tai lieu qua dai -> tang kich thuoc chunk thay vi cat bo noi dung (khong mat du lieu)."""
    total = sum(len(c) for _, c in page_contents)
    needed = math.ceil(total / max(cfg.max_chunks, 1)) + cfg.chunk_overlap
    return max(cfg.chunk_size, needed)


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------
def process_pdf(pdf_bytes: bytes, *, task_id: int, doc_id: int, outputs: list[str], title: str = "",
                llm: LLMClient | None = None, cfg: Settings = settings) -> ProcessResponse:
    started = time.monotonic()
    llm = llm or LLMClient(cfg)
    requested = set(outputs)

    # [1] Extract
    doc = open_pdf(pdf_bytes)
    try:
        metadata = get_metadata(doc)
        if metadata["total_pages"] == 0:
            raise PdfError("pdf_empty")
        if metadata["total_pages"] > cfg.max_pages:
            raise PdfError("too_many_pages")
        pages, warnings = extract_pages(doc, cfg)
    finally:
        doc.close()

    # [2] Classify
    classified = classify_document(pages, cfg)

    # [3] Vision (Inclusive) -> noi dung hop nhat cua tung trang
    page_contents: list[tuple[int, str]] = []
    page_reports: list[PageReport] = []
    vision_pages = 0
    for cp in classified:
        page = cp.page
        content = page.text
        analyzed, blur_score = False, None
        if cp.needs_vision:
            image = page.page_image or (page.images[0] if page.images else None)
            if image is not None and vision_pages < cfg.max_vision_pages:
                cv_result = preprocess_for_vision(image, cfg.blur_threshold)
                blur_score = cv_result["blur_score"]
                analysis = llm.complete_json(build_vision_prompt(page.text[:cfg.llm_input_chars]),
                                             image_png=cv_result["processed_image"])
                vision_pages += 1
                if analysis:
                    content = _merge_visual(page.page_number, page.text, analysis)
                    analyzed = True
                else:
                    warnings.append(f"vision_invalid_output:page_{page.page_number}")
            elif image is not None:
                warnings.append(f"vision_limit_reached:page_{page.page_number}")
        page_contents.append((page.page_number, content))
        page_reports.append(PageReport(page_number=page.page_number, page_type=cp.page_type.value,
                                       ocr_applied=page.ocr_applied, vision_analyzed=analyzed,
                                       blur_score=blur_score))

    # [4] Chunk
    chunk_size = _effective_chunk_size(page_contents, cfg)
    if chunk_size > cfg.chunk_size:
        warnings.append("chunk_size_increased")
    chunks = chunk_pages(page_contents, chunk_size, cfg.chunk_overlap)
    if not chunks:
        raise PdfError("no_extractable_content")

    # [5] + [6] Generate & normalize
    chunk_outputs: list[ChunkOut] = []
    all_cards: list[Flashcard] = []
    generated_any = False
    for chunk in chunks:
        if len(chunk.content) > cfg.llm_input_chars:
            warnings.append(f"generation_input_truncated:chunk_{chunk.position}")
        raw = _generate_lesson(llm, chunk, requested, cfg)
        summary = quiz = None
        if raw is None:
            warnings.append(f"lesson_invalid_output:chunk_{chunk.position}")
        else:
            generated_any = True
            if "SUMMARY" in requested:
                summary = _normalize_summary(raw.get("summary"))
            if "QUIZ" in requested:
                lesson_title = summary.title if summary else f"{title or 'Lesson'} - {chunk.position}"
                quiz, dropped = _normalize_quiz(raw.get("quiz"), lesson_title)
                if dropped:
                    warnings.append(f"questions_dropped:chunk_{chunk.position}:{dropped}")
            if "FLASHCARDS" in requested:
                all_cards.extend(_normalize_flashcards(raw.get("flashcards")))
        chunk_outputs.append(ChunkOut(position=chunk.position, source_start=chunk.source_start,
                                      source_end=chunk.source_end, content=chunk.content,
                                      summary=summary, quiz=quiz))

    if not generated_any:
        raise LLMError("llm_invalid_output", "LLM returned no usable JSON for any chunk", retryable=True)

    flashcards = None
    if "FLASHCARDS" in requested and all_cards:
        flashcards = FlashcardSet(title=(title or metadata["title"] or "Flashcards")[:255], cards=all_cards)

    return ProcessResponse(
        task_id=task_id,
        doc_id=doc_id,
        outputs=[o for o in ("SUMMARY", "QUIZ", "FLASHCARDS") if o in requested],
        document=DocumentReport(
            total_pages=metadata["total_pages"],
            ocr_pages=sum(1 for p in pages if p.ocr_applied),
            vision_pages=vision_pages,
            routing=routing_summary(classified),
            pages=page_reports,
        ),
        chunks=chunk_outputs,
        flashcards=flashcards,
        usage=llm.usage.as_dict(),
        warnings=warnings,
        processing_time_ms=int((time.monotonic() - started) * 1000),
    )

