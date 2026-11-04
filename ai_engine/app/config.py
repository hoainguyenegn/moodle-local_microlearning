"""
config.py - Cau hinh AI Engine, doc tu bien moi truong (.env).

Moi nguong (threshold) cua pipeline PDF deu nam o day de nhom co the tinh chinh
ma khong can sua code. Gia tri mac dinh duoc giu nguyen tu repo Process-PDF
(da benchmark tren 8 file dataset).
"""

from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

from dotenv import load_dotenv

# .env nam canh thu muc ai_engine/ (KHONG commit file nay).
_ENV_PATH = Path(__file__).resolve().parent.parent / ".env"
load_dotenv(_ENV_PATH, override=False)


def _int(name: str, default: int) -> int:
    try:
        return int(os.getenv(name, default))
    except (TypeError, ValueError):
        return default


def _bool(name: str, default: bool) -> bool:
    value = os.getenv(name)
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


@dataclass(frozen=True)
class Settings:
    # --- Bao mat giua Moodle <-> Engine ---
    engine_api_key: str = field(default_factory=lambda: os.getenv("ENGINE_API_KEY", ""))

    # --- LLM (LiteLLM) ---
    text_model: str = field(default_factory=lambda: os.getenv("TEXT_MODEL", "gemini/gemini-3.5-flash-lite"))
    vision_model: str = field(default_factory=lambda: os.getenv("VISION_MODEL", "gemini/gemini-3.5-flash-lite"))
    max_output_tokens: int = field(default_factory=lambda: _int("MAX_OUTPUT_TOKENS", 8192))
    llm_max_retries: int = field(default_factory=lambda: _int("LLM_MAX_RETRIES", 3))
    llm_temperature: float = 0.3

    # --- Gioi han dau vao ---
    max_upload_mb: int = field(default_factory=lambda: _int("MAX_UPLOAD_MB", 50))
    max_pages: int = field(default_factory=lambda: _int("MAX_PAGES", 300))

    # --- Trich xuat PDF (pdf_parser) ---
    margin_ratio: float = 0.10            # Cat 10% le tren / duoi (header, footer, so trang)
    min_image_width: int = 150            # Loc icon / logo nho
    min_image_height: int = 150
    min_image_bytes: int = 5000
    max_duplicate_bytes: int = 50000      # Anh nho hon nguong nay ma lap lai -> logo -> bo
    render_dpi: int = field(default_factory=lambda: _int("RENDER_DPI", 150))

    # --- Selective OCR ---
    ocr_enabled: bool = field(default_factory=lambda: _bool("OCR_ENABLED", True))
    ocr_min_text_length: int = 30         # Trang co <= 30 ky tu -> coi la trang scan
    ocr_lang: str = field(default_factory=lambda: os.getenv("OCR_LANG", "vie+eng"))
    ocr_dpi: int = 200
    tesseract_cmd: str = field(default_factory=lambda: os.getenv("TESSERACT_CMD", ""))

    # --- Classifier ---
    visual_text_threshold: int = 300      # Co anh + text < 300 ky tu -> VISUAL_HEAVY

    # --- Vision ---
    max_vision_pages: int = field(default_factory=lambda: _int("MAX_VISION_PAGES", 20))
    blur_threshold: float = 50.0

    # --- Chunking thanh bai hoc vi mo (1 chunk = 1 micro-lesson) ---
    chunk_size: int = field(default_factory=lambda: _int("CHUNK_SIZE", 6000))
    chunk_overlap: int = field(default_factory=lambda: _int("CHUNK_OVERLAP", 300))
    max_chunks: int = field(default_factory=lambda: _int("MAX_CHUNKS", 40))

    # --- Sinh noi dung ---
    questions_per_chunk: int = field(default_factory=lambda: _int("QUESTIONS_PER_CHUNK", 5))
    flashcards_per_chunk: int = field(default_factory=lambda: _int("FLASHCARDS_PER_CHUNK", 4))
    llm_input_chars: int = 8000           # Cat bot input moi lan goi LLM


settings = Settings()

# LiteLLM doc key Gemini tu GEMINI_API_KEY; giu tuong thich voi .env cu (GOOGLE_API_KEY).
if os.getenv("GEMINI_API_KEY") and not os.getenv("GOOGLE_API_KEY"):
    os.environ["GOOGLE_API_KEY"] = os.environ["GEMINI_API_KEY"]

