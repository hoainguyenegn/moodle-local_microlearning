"""
classifier.py - Phan loai trang PDF: TEXT_HEAVY / VISUAL_HEAVY / MIXED (port tu Process-PDF).

- Trang da OCR thanh cong -> TEXT_HEAVY (dung Text LLM re thay vi Vision).
- Anh nao song sot qua luoi loc rac (pdf_parser) deu duoc xem la so do / bieu do quan trong.
"""

from __future__ import annotations

from dataclasses import dataclass
from enum import Enum

from app.config import Settings, settings
from app.extractors.pdf_parser import PageData


class PageType(str, Enum):
    TEXT_HEAVY = "text_heavy"
    VISUAL_HEAVY = "visual_heavy"
    MIXED = "mixed"


@dataclass
class ClassifiedPage:
    page: PageData
    page_type: PageType
    reason: str

    @property
    def needs_vision(self) -> bool:
        return self.page_type in (PageType.VISUAL_HEAVY, PageType.MIXED)


def classify_page(page: PageData, cfg: Settings = settings) -> ClassifiedPage:
    text_len = len(page.text)
    num_images = len(page.images)

    if page.ocr_applied and page.has_text:
        return ClassifiedPage(page, PageType.TEXT_HEAVY, f"scanned page recovered by OCR ({text_len} chars)")

    if not page.has_text:
        return ClassifiedPage(page, PageType.VISUAL_HEAVY, f"almost no text ({text_len} chars)")

    if num_images > 0:
        if text_len < cfg.visual_text_threshold:
            return ClassifiedPage(page, PageType.VISUAL_HEAVY, f"{num_images} significant image(s), {text_len} chars")
        return ClassifiedPage(page, PageType.MIXED, f"{text_len} chars + {num_images} image(s)")

    return ClassifiedPage(page, PageType.TEXT_HEAVY, f"{text_len} chars, no significant image")


def classify_document(pages: list[PageData], cfg: Settings = settings) -> list[ClassifiedPage]:
    return [classify_page(p, cfg) for p in pages]


def routing_summary(classified: list[ClassifiedPage]) -> dict:
    summary = {"text_heavy": 0, "visual_heavy": 0, "mixed": 0}
    for cp in classified:
        summary[cp.page_type.value] += 1
    return summary

