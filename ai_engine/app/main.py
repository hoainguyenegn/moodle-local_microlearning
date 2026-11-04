"""
main.py - HTTP API cua AI Engine (FastAPI).

Endpoints:
    GET  /health       -> kiem tra song + OCR co san khong
    POST /v1/process   -> nhan PDF (multipart) tu Moodle adhoc task, tra JSON contract v1.0

Bao mat: Moodle gui header `X-Engine-Key` = ENGINE_API_KEY (so sanh hang thoi gian).
Engine fail-closed: chua cau hinh ENGINE_API_KEY thi tu choi moi request xu ly.

Chay local:
    uvicorn app.main:app --host 127.0.0.1 --port 8000
"""

from __future__ import annotations

import hmac
import logging

from fastapi import FastAPI, File, Form, Header, Request, UploadFile
from fastapi.responses import JSONResponse

from app.config import settings
from app.extractors.pdf_parser import PdfError, tesseract_available
from app.llm.ai_router import LLMError
from app.pipeline import process_pdf
from app.schemas import ALLOWED_OUTPUTS, SCHEMA_VERSION, ErrorResponse, ProcessResponse

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
logger = logging.getLogger("ai_engine")

app = FastAPI(title="Microlearning AI Engine", version=SCHEMA_VERSION)


class EngineError(Exception):
    def __init__(self, status: int, code: str, message: str, retryable: bool = False):
        super().__init__(message)
        self.status, self.code, self.message, self.retryable = status, code, message, retryable


@app.exception_handler(EngineError)
async def _engine_error_handler(_request: Request, exc: EngineError) -> JSONResponse:
    body = {"error": {"code": exc.code, "message": exc.message, "retryable": exc.retryable}}
    return JSONResponse(status_code=exc.status, content=body)


def _check_key(provided: str | None) -> None:
    if not settings.engine_api_key:
        raise EngineError(500, "engine_misconfigured", "ENGINE_API_KEY is not configured")
    if not provided or not hmac.compare_digest(provided.encode(), settings.engine_api_key.encode()):
        raise EngineError(401, "unauthorized", "Invalid engine key")


def _parse_outputs(raw: str) -> list[str]:
    values = [v.strip().upper() for v in raw.split(",") if v.strip()]
    if not values or len(values) != len(set(values)) or any(v not in ALLOWED_OUTPUTS for v in values):
        raise EngineError(422, "invalid_outputs", f"outputs must be a CSV subset of {','.join(ALLOWED_OUTPUTS)}")
    return values


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "schema_version": SCHEMA_VERSION, "ocr_available": tesseract_available(settings)}


@app.post("/v1/process", response_model=ProcessResponse,
          responses={401: {"model": ErrorResponse}, 413: {"model": ErrorResponse},
                     422: {"model": ErrorResponse}, 502: {"model": ErrorResponse}, 503: {"model": ErrorResponse}})
def process(
    file: UploadFile = File(..., description="Source PDF"),
    task_id: int = Form(..., gt=0),
    doc_id: int = Form(..., gt=0),
    outputs: str = Form("SUMMARY,QUIZ"),
    title: str = Form(""),
    x_engine_key: str | None = Header(default=None),
) -> ProcessResponse:
    # Ham `def` (khong async): FastAPI chay trong threadpool vi pipeline la CPU/IO blocking.
    _check_key(x_engine_key)
    requested = _parse_outputs(outputs)

    limit = settings.max_upload_mb * 1024 * 1024
    data = file.file.read(limit + 1)
    if len(data) > limit:
        raise EngineError(413, "file_too_large", f"PDF exceeds {settings.max_upload_mb} MB")

    logger.info("task=%s doc=%s outputs=%s size=%d bytes", task_id, doc_id, requested, len(data))
    try:
        result = process_pdf(data, task_id=task_id, doc_id=doc_id, outputs=requested, title=title.strip())
    except PdfError as exc:
        raise EngineError(422, str(exc), "The PDF cannot be processed", retryable=False) from exc
    except LLMError as exc:
        logger.warning("task=%s LLM failure %s: %s", task_id, exc.code, exc)
        raise EngineError(503 if exc.retryable else 502, exc.code, "AI provider error", exc.retryable) from exc
    except Exception as exc:  # noqa: BLE001 - khong lo stack trace / secret ra ngoai
        logger.exception("task=%s unexpected failure", task_id)
        raise EngineError(500, "engine_internal_error", "Unexpected engine error", retryable=False) from exc

    logger.info("task=%s done: %d chunks, %d LLM calls, %d ms", task_id, len(result.chunks),
                result.usage.get("llm_calls", 0), result.processing_time_ms)
    return result
