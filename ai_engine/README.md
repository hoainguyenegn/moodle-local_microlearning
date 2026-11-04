# Microlearning PDF Processing & AI Routing Engine (`ai_engine`)

Standalone Python microservice implementing **Module 4 (External AI Engine Worker)** of `local_microlearning`. Ported and upgraded from the `Process-PDF` research repository.

---

## 1. Architecture & Pipeline (6 Stages)

1. **Extract (`app/extractors/pdf_parser.py`)**
   - Multi-column block reading via PyMuPDF (`get_text("blocks", sort=True)`).
   - 10% top/bottom margin cropping to strip repeating headers, footers, and page numbers.
   - Junk image filtering (`<150px` or `<5KB`) + duplicate byte-size hashing to remove repeating university logos.
   - **Hybrid Selective Local OCR:** Pages with `<=30` characters are rendered at 200 DPI and passed through local Tesseract OCR (`vie+eng`). Pages where OCR recovers meaningful text are routed to the inexpensive Text LLM branch instead of Vision LLM.
2. **Classify (`app/core/classifier.py`)**
   - Classifies each page into `TEXT_HEAVY`, `VISUAL_HEAVY`, or `MIXED`.
3. **Vision Preprocessing & Inclusive Routing (`app/core/vision_cv.py`, `app/pipeline.py`)**
   - OpenCV Laplacian variance blur detection, Non-Local Means denoising (for blurry scans), and CLAHE contrast enhancement in LAB color space.
   - **Inclusive Data Merging (Zero Data Loss):** For `VISUAL_HEAVY` and `MIXED` pages, both the rendered page image and the extracted text are sent together to the Vision LLM, and the resulting visual analysis is merged with the original page text.
4. **Chunking (`app/core/text_chunking.py`)**
   - Splits merged page contents in reading order using `RecursiveCharacterTextSplitter` (`add_start_index=True`) and maps each chunk to inclusive 1-based source page markers (`source_start..source_end`).
5. **Generation & Fault-Tolerant Routing (`app/llm/prompts.py`, `app/llm/ai_router.py`)**
   - Language-agnostic prompts, anti-hallucination guardrails for unsolved math/physics problems, and raw backslash suppression to prevent LaTeX JSON breakage.
   - Exponential backoff and Google `retry in Xs` rate-limit parsing for HTTP `429`/`503`.
6. **Normalization & Validation (`app/pipeline.py`, `app/schemas.py`)**
   - Drops malformed questions (must have `>=2` options and `==1` correct answer) and outputs strict Schema `v1.0` JSON for Moodle.

---

## 2. Quick Start

### Install Dependencies
```bash
cd ai_engine
pip install -r requirements.txt
cp .env.example .env
# Edit .env and set ENGINE_API_KEY and GEMINI_API_KEY
```

### Run HTTP Service (for Moodle Integration)
```bash
uvicorn app.main:app --host 127.0.0.1 --port 8000
```

### Run CLI on Local PDFs (Standalone Benchmark Mode)
```bash
python scripts/run_local.py path/to/lecture.pdf --outputs SUMMARY,QUIZ,FLASHCARDS --out outputs/
```

### Run Offline Unit Tests
```bash
pytest -q
```

