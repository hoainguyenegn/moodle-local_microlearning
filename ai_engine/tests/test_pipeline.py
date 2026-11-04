"""
Offline tests cho pipeline (khong goi LLM that, khong can API key).

    cd ai_engine
    python -m pytest -q
"""

from __future__ import annotations

import sys
from dataclasses import replace
from pathlib import Path

import numpy as np
import pymupdf as fitz
import pytest

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.config import settings  # noqa: E402
from app.core.text_chunking import chunk_pages  # noqa: E402
from app.llm.ai_router import LLMError, Usage, parse_json_response  # noqa: E402
from app.pipeline import _normalize_question, process_pdf  # noqa: E402

CFG = replace(settings, ocr_enabled=False, chunk_size=1500, chunk_overlap=100, max_chunks=40)

LESSON = {
    "summary": {"title": "Design process", "points": ["Understand users", "Prototype early"], "key_concepts": ["UX"]},
    "quiz": [
        {"question": "First step?", "options": {"A": "Research", "B": "Code", "C": "Deploy", "D": "Sell"},
         "correct_answer": "A", "explanation": "Research comes first."},
        {"question": "Broken (2 correct)?", "options": ["x", "x"], "correct_answer": "x"},
    ],
    "flashcards": [{"front": "UX", "back": "User experience"}],
}


class FakeLLM:
    def __init__(self, lesson=LESSON, vision=None, fail_lessons: int = 0):
        self.usage = Usage()
        self.lesson, self.vision = lesson, vision or {"page_description": "A flow diagram", "key_points": ["Step 1"]}
        self.fail_lessons = fail_lessons
        self.prompts: list[tuple[str, bool]] = []

    def complete_json(self, prompt, image_png=None, model=None):
        self.prompts.append((prompt, image_png is not None))
        self.usage.calls += 1
        if image_png is not None:
            return self.vision
        if self.fail_lessons > 0:
            self.fail_lessons -= 1
            return None
        return self.lesson


def _paragraph(seed: str) -> str:
    return " ".join(f"{seed} sentence number {i} explains mobile application design." for i in range(25))


def _text_pdf(pages: int = 3) -> bytes:
    doc = fitz.open()
    for n in range(pages):
        page = doc.new_page()
        page.insert_textbox(fitz.Rect(72, 120, 520, 700), _paragraph(f"Page{n + 1}"), fontsize=10)
    data = doc.tobytes()
    doc.close()
    return data


def _noise_png(size: int = 400) -> bytes:
    """Anh nhieu ngau nhien (> 5KB, > 150px) -> vuot qua luoi loc anh rac."""
    rng = np.random.default_rng(42)
    raw = rng.integers(0, 255, size * size * 3, dtype=np.uint8).tobytes()
    return fitz.Pixmap(fitz.csRGB, size, size, raw, False).tobytes("png")


def _mixed_pdf() -> bytes:
    doc = fitz.open()
    page = doc.new_page()
    page.insert_textbox(fitz.Rect(72, 100, 520, 300), "Chapter 2 - User research helps the team understand "
                        "experiences. Evidence should be evaluated before product decisions. " * 3, fontsize=10)
    page.insert_image(fitz.Rect(72, 320, 472, 720), stream=_noise_png())
    data = doc.tobytes()
    doc.close()
    return data


def test_text_pdf_produces_lessons_with_page_markers():
    llm = FakeLLM()
    result = process_pdf(_text_pdf(3), task_id=7, doc_id=3, outputs=["SUMMARY", "QUIZ", "FLASHCARDS"],
                         title="Demo", llm=llm, cfg=CFG)
    assert result.schema_version == "1.0" and result.task_id == 7 and result.doc_id == 3
    assert result.document.total_pages == 3 and result.document.routing["text_heavy"] == 3
    assert [c.position for c in result.chunks] == list(range(1, len(result.chunks) + 1))
    assert result.chunks[0].source_start == 1 and result.chunks[-1].source_end == 3
    assert all(c.source_start <= c.source_end for c in result.chunks)
    first = result.chunks[0]
    assert first.summary.markdown.startswith("## Design process")
    assert len(first.quiz.questions) == 1                      # cau co 2 dap an dung bi loai
    assert sum(o.is_correct for o in first.quiz.questions[0].options) == 1
    assert any(w.startswith("questions_dropped") for w in result.warnings)
    assert result.flashcards is not None and result.flashcards.cards[0].front == "UX"
    assert not any(is_vision for _, is_vision in llm.prompts)  # trang text khong ton Vision


def test_inclusive_routing_keeps_text_and_adds_vision():
    llm = FakeLLM()
    result = process_pdf(_mixed_pdf(), task_id=1, doc_id=1, outputs=["SUMMARY"], llm=llm, cfg=CFG)
    vision_prompts = [p for p, is_vision in llm.prompts if is_vision]
    assert len(vision_prompts) == 1
    assert "User research helps the team" in vision_prompts[0]     # text duoc gui kem anh
    content = result.chunks[0].content
    assert "User research helps the team" in content               # Zero Data Loss
    assert "A flow diagram" in content
    assert result.document.vision_pages == 1


def test_outputs_are_respected():
    llm = FakeLLM()
    result = process_pdf(_text_pdf(1), task_id=1, doc_id=1, outputs=["QUIZ"], llm=llm, cfg=CFG)
    assert all(c.summary is None for c in result.chunks)
    assert result.flashcards is None
    assert '"summary"' not in llm.prompts[0][0]


def test_invalid_json_is_retried_then_succeeds():
    llm = FakeLLM(fail_lessons=1)
    single_chunk_cfg = replace(CFG, chunk_size=5000)
    result = process_pdf(_text_pdf(1), task_id=1, doc_id=1, outputs=["SUMMARY"], llm=llm, cfg=single_chunk_cfg)
    assert result.chunks[0].summary is not None
    assert len(llm.prompts) == 2


def test_all_lessons_failing_raises_retryable_error():
    llm = FakeLLM(fail_lessons=99)
    with pytest.raises(LLMError) as info:
        process_pdf(_text_pdf(1), task_id=1, doc_id=1, outputs=["SUMMARY"], llm=llm, cfg=CFG)
    assert info.value.retryable


def test_normalize_question_variants():
    good = _normalize_question({"question": "Q?", "options": ["a", "b", "c"], "correct_answer": "B"})
    assert [o.is_correct for o in good.options] == [False, True, False]
    assert _normalize_question({"question": "Q?", "options": {"A": "a"}, "correct_answer": "A"}) is None
    assert _normalize_question({"question": "Q?", "options": {"A": "a", "B": "b"}, "correct_answer": "Z"}) is None


def test_chunk_pages_maps_sources():
    chunks = chunk_pages([(1, "alpha " * 200), (2, ""), (3, "beta " * 200)], chunk_size=500, chunk_overlap=50)
    assert chunks[0].source_start == 1
    assert chunks[-1].source_end == 3
    assert all(c.source_start in (1, 3) and c.source_end in (1, 3) for c in chunks)


def test_parse_json_response_handles_fences_and_garbage():
    assert parse_json_response('```json\n{"a": 1}\n```') == {"a": 1}
    assert parse_json_response('Sure! {"a": 2} hope it helps') == {"a": 2}
    assert parse_json_response("not json") is None
