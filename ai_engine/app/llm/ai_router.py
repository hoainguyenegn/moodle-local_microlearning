"""
ai_router.py - Goi LLM qua LiteLLM voi co che chiu loi (port & nang cap tu Process-PDF).

Giu nguyen:
- Exponential Backoff cho loi 503 / qua tai; doc so giay "retry in Xs" cua Google khi gap 429.
- Parse JSON phong ho (bo ```json fences).

Nang cap khi dua vao repo nhom:
- Vong lap thay de quy; KHONG retry loi xac thuc / request sai (401/403/400) vi retry vo ich.
- Het retry -> nem LLMError(retryable=...) de API tra ma loi ro rang cho Moodle
  (Moodle adhoc task se tu len lich chay lai neu retryable).
- Bat che do JSON cua provider (response_format) + drop_params cho model khong ho tro.
- Dem so lan goi va token de Moodle ghi vao bang local_microlearning_logs.
"""

from __future__ import annotations

import base64
import json
import logging
import os
import re
import time
from dataclasses import dataclass, field

# Su dung bang gia model local cua LiteLLM de khong bi treo/cham khi khoi dong offline.
os.environ.setdefault("LITELLM_LOCAL_MODEL_COST_MAP", "True")

import litellm

from app.config import Settings, settings

logger = logging.getLogger(__name__)
litellm.drop_params = True          # Bo qua tham so model khong ho tro thay vi bao loi
litellm.suppress_debug_info = True

MAX_WAIT_SECONDS = 60


class LLMError(Exception):
    def __init__(self, code: str, message: str, retryable: bool):
        super().__init__(message)
        self.code = code
        self.retryable = retryable


@dataclass
class Usage:
    calls: int = 0
    tokens: int = 0
    latency_ms: int = 0
    models: set[str] = field(default_factory=set)

    def as_dict(self) -> dict:
        return {"llm_calls": self.calls, "tokens": self.tokens, "latency_ms": self.latency_ms,
                "models": sorted(self.models)}


def parse_json_response(raw: str | None) -> dict | None:
    """Parse JSON tu output LLM. Tra None neu khong cuu duoc (de caller quyet dinh retry)."""
    if not raw:
        return None
    text = raw.strip()
    text = re.sub(r"^```(?:json)?\s*", "", text)
    text = re.sub(r"\s*```$", "", text)
    for candidate in (text, _outermost_object(text)):
        if not candidate:
            continue
        try:
            value = json.loads(candidate)
            return value if isinstance(value, dict) else None
        except json.JSONDecodeError:
            continue
    return None


def _outermost_object(text: str) -> str | None:
    start, end = text.find("{"), text.rfind("}")
    return text[start:end + 1] if 0 <= start < end else None


def _classify_error(exc: Exception) -> tuple[str, bool]:
    """Tra ve (error_code, retryable)."""
    non_retryable = (
        (getattr(litellm, "AuthenticationError", None), "llm_auth_failed"),
        (getattr(litellm, "PermissionDeniedError", None), "llm_auth_failed"),
        (getattr(litellm, "NotFoundError", None), "llm_model_not_found"),
        (getattr(litellm, "ContextWindowExceededError", None), "llm_context_exceeded"),
        (getattr(litellm, "BadRequestError", None), "llm_bad_request"),
    )
    rate_limit_type = getattr(litellm, "RateLimitError", None)
    if rate_limit_type is not None and isinstance(exc, rate_limit_type):
        return "llm_rate_limited", True
    for exc_type, code in non_retryable:
        if exc_type is not None and isinstance(exc, exc_type):
            return code, False
    message = str(exc).lower()
    if "401" in message or "403" in message or "api key" in message:
        return "llm_auth_failed", False
    if "429" in message or "quota" in message or "rate limit" in message:
        return "llm_rate_limited", True
    return "llm_unavailable", True


def _wait_seconds(code: str, message: str, attempt: int) -> int:
    if code == "llm_rate_limited":
        match = re.search(r"retry in ([\d.]+)s", message.lower())
        wait = int(float(match.group(1))) + 2 if match else 25   # goi Free ~15 RPM
    else:
        wait = (2 ** attempt) * 2                                 # 2s, 4s, 8s ...
    return min(wait, MAX_WAIT_SECONDS)


class LLMClient:
    """Lop bao goi LiteLLM. Pipeline nhan client qua tham so -> test co the thay bang Fake."""

    def __init__(self, cfg: Settings = settings):
        self.cfg = cfg
        self.usage = Usage()

    def _complete(self, model: str, messages: list[dict]) -> str:
        last_code, last_message = "llm_unavailable", ""
        for attempt in range(self.cfg.llm_max_retries + 1):
            started = time.monotonic()
            try:
                response = litellm.completion(
                    model=model,
                    messages=messages,
                    temperature=self.cfg.llm_temperature,
                    max_tokens=self.cfg.max_output_tokens,
                    response_format={"type": "json_object"},
                )
                self.usage.calls += 1
                self.usage.latency_ms += int((time.monotonic() - started) * 1000)
                self.usage.models.add(model)
                usage = getattr(response, "usage", None)
                self.usage.tokens += int(getattr(usage, "total_tokens", 0) or 0)
                return response.choices[0].message.content or ""
            except Exception as exc:  # noqa: BLE001 - LiteLLM nem nhieu loai exception
                last_code, retryable = _classify_error(exc)
                last_message = str(exc)
                if not retryable:
                    raise LLMError(last_code, last_message[:300], retryable=False) from exc
                if attempt >= self.cfg.llm_max_retries:
                    break
                wait = _wait_seconds(last_code, last_message, attempt)
                logger.warning("LLM %s (attempt %d), backing off %ss", last_code, attempt + 1, wait)
                time.sleep(wait)
        raise LLMError(last_code, last_message[:300], retryable=True)

    def complete_json(self, prompt: str, image_png: bytes | None = None, model: str | None = None) -> dict | None:
        if image_png is None:
            messages = [{"role": "user", "content": prompt}]
            model = model or self.cfg.text_model
        else:
            b64 = base64.b64encode(image_png).decode("ascii")
            messages = [{"role": "user", "content": [
                {"type": "text", "text": prompt},
                {"type": "image_url", "image_url": {"url": f"data:image/png;base64,{b64}"}},
            ]}]
            model = model or self.cfg.vision_model
        return parse_json_response(self._complete(model, messages))
