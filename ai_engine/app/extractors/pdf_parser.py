"""
pdf_parser.py - Trich xuat text va anh tu file PDF (port tu Process-PDF).

Cac ky thuat giu nguyen tu ban goc:
1. Doc van ban theo Block & ho tro da cot (get_text("blocks", sort=True)).
2. Loc Header/Footer theo toa do Y (cat 10% le tren va 10% le duoi).
3. Loc anh rac theo kich thuoc + diet logo lap lai (duplicate size hashing).
4. Selective OCR: chi chay Tesseract tren trang KHONG co text (trang scan).

Thay doi khi tich hop vao repo nhom:
- Nhan input la bytes (file upload tu Moodle) thay vi duong dan tren dia.
- Trang co anh quan trong duoc render nguyen trang (page_image) de Vision LLM
  nhin thay bo cuc day du chu khong chi mot anh nhung roi rac.
- Dung logging thay vi print; nguong cau hinh qua app.config.
"""

from __future__ import annotations

import logging
import shutil
from dataclasses import dataclass, field
from io import BytesIO
from pathlib import Path

import pymupdf as fitz

from app.config import Settings, settings

logger = logging.getLogger(__name__)

try:  # OCR la tuy chon: thieu Tesseract thi pipeline van chay, chi tat OCR.
    import pytesseract
    from PIL import Image

    HAS_PYTESSERACT = True
except ImportError:  # pragma: no cover - phu thuoc moi truong
    HAS_PYTESSERACT = False

_WINDOWS_TESSERACT_PATHS = (
    r"C:\Program Files\Tesseract-OCR\tesseract.exe",
    r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
)


class PdfError(Exception):
    """PDF khong hop le / khong mo duoc (loi phia nguoi dung, khong retry)."""


@dataclass
class PageData:
    page_number: int                      # 1-based
    text: str
    images: list[bytes] = field(default_factory=list)
    page_image: bytes | None = None       # Anh render ca trang (PNG) neu can Vision/OCR
    has_text: bool = False
    ocr_applied: bool = False


def tesseract_available(cfg: Settings) -> bool:
    """Tim tesseract.exe: bien moi truong -> duong dan Windows mac dinh -> PATH."""
    if not (cfg.ocr_enabled and HAS_PYTESSERACT):
        return False
    candidates = [cfg.tesseract_cmd] if cfg.tesseract_cmd else []
    candidates += list(_WINDOWS_TESSERACT_PATHS)
    for candidate in candidates:
        if candidate and Path(candidate).exists():
            pytesseract.pytesseract.tesseract_cmd = candidate
            return True
    return shutil.which("tesseract") is not None


def _run_ocr(image_bytes: bytes, cfg: Settings) -> str:
    """Chay Tesseract tren anh trang; fallback sang 'eng' neu thieu goi ngon ngu."""
    try:
        img = Image.open(BytesIO(image_bytes))
        try:
            text = pytesseract.image_to_string(img, lang=cfg.ocr_lang)
        except pytesseract.TesseractError:
            text = pytesseract.image_to_string(img, lang="eng")
        return text.strip()
    except Exception as exc:  # OCR loi khong duoc lam sap ca pipeline
        logger.warning("OCR failed: %s", exc)
        return ""


def open_pdf(pdf_bytes: bytes) -> fitz.Document:
    if not pdf_bytes.startswith(b"%PDF-"):
        raise PdfError("not_a_pdf")
    try:
        doc = fitz.open(stream=pdf_bytes, filetype="pdf")
    except Exception as exc:
        raise PdfError("pdf_unreadable") from exc
    if doc.needs_pass:
        doc.close()
        raise PdfError("pdf_encrypted")
    return doc


def get_metadata(doc: fitz.Document) -> dict:
    meta = doc.metadata or {}
    return {
        "title": meta.get("title", "") or "",
        "author": meta.get("author", "") or "",
        "total_pages": len(doc),
    }


def _extract_text(page: fitz.Page, cfg: Settings) -> str:
    """Doc text theo block, sap xep da cot, bo block nam trong vung le header/footer."""
    height = page.rect.height
    top = height * cfg.margin_ratio
    bottom = height * (1 - cfg.margin_ratio)
    parts: list[str] = []
    for block in page.get_text("blocks", sort=True):
        x0, y0, x1, y1, block_text, _block_no, block_type = block[:7]
        if block_type == 0 and y0 >= top and y1 <= bottom:
            stripped = block_text.strip()
            if stripped:
                parts.append(stripped)
    return "\n".join(parts)


def _extract_images(doc: fitz.Document, page: fitz.Page, seen_sizes: set[int], cfg: Settings) -> list[bytes]:
    """Lay anh nhung, loai icon/logo nho va logo lap lai giua cac trang."""
    images: list[bytes] = []
    for img_info in page.get_images(full=True):
        try:
            base = doc.extract_image(img_info[0])
        except Exception:
            continue
        if not base or not base.get("image"):
            continue
        data = base["image"]
        if base.get("width", 0) < cfg.min_image_width or base.get("height", 0) < cfg.min_image_height:
            continue
        size = len(data)
        if size < cfg.min_image_bytes:
            continue
        if size < cfg.max_duplicate_bytes:
            if size in seen_sizes:      # Cung kich thuoc byte -> logo lap lai -> bo
                continue
            seen_sizes.add(size)
        images.append(data)
    return images


def extract_pages(doc: fitz.Document, cfg: Settings = settings) -> tuple[list[PageData], list[str]]:
    """
    Trich xuat toan bo trang. Tra ve (pages, warnings).

    Quy trinh Selective OCR:
    1. Boc text bang PyMuPDF (nhanh, mien phi).
    2. Trang < 30 ky tu -> trang scan -> render anh -> chay Tesseract (CPU local, 0 dong).
    3. OCR ra du chu -> trang chuyen ve nhanh Text LLM (re). Neu khong -> giu cho Vision.
    """
    warnings: list[str] = []
    ocr_available = tesseract_available(cfg)
    if cfg.ocr_enabled and not ocr_available:
        warnings.append("ocr_unavailable")
        logger.warning("Tesseract not found; scanned pages will go to Vision LLM.")

    pages: list[PageData] = []
    seen_sizes: set[int] = set()

    for index in range(len(doc)):
        page = doc[index]
        text = _extract_text(page, cfg)
        images = _extract_images(doc, page, seen_sizes, cfg)
        has_text = len(text) > cfg.ocr_min_text_length
        page_image: bytes | None = None
        ocr_applied = False

        if not has_text:
            page_image = page.get_pixmap(dpi=cfg.ocr_dpi).tobytes("png")
            if ocr_available:
                ocr_text = _run_ocr(page_image, cfg)
                if len(ocr_text) > cfg.ocr_min_text_length:
                    text, has_text, ocr_applied = ocr_text, True, True
                    logger.info("Page %d: OCR recovered %d chars", index + 1, len(ocr_text))
        elif images:
            # Trang co chu + anh quan trong: render ca trang cho Vision (Inclusive Routing).
            page_image = page.get_pixmap(dpi=cfg.render_dpi).tobytes("png")

        pages.append(PageData(
            page_number=index + 1,
            text=text,
            images=images,
            page_image=page_image,
            has_text=has_text,
            ocr_applied=ocr_applied,
        ))

    return pages, warnings
