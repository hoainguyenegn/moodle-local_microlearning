"""Tests cho HTTP API (can fastapi + python-multipart)."""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

pytest.importorskip("fastapi")
pytest.importorskip("multipart")

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from dataclasses import replace  # noqa: E402

from fastapi.testclient import TestClient  # noqa: E402

import app.main as main  # noqa: E402
import app.pipeline as pipeline  # noqa: E402
from tests.test_pipeline import CFG, FakeLLM, _text_pdf  # noqa: E402

KEY = "test-key"


@pytest.fixture()
def client(monkeypatch):
    monkeypatch.setattr(main, "settings", replace(main.settings, engine_api_key=KEY))
    real = pipeline.process_pdf

    def fake_process(data, **kwargs):
        return real(data, llm=FakeLLM(), cfg=CFG, **kwargs)

    monkeypatch.setattr(main, "process_pdf", fake_process)
    return TestClient(main.app)


def _post(client, data: bytes, key: str | None = KEY, outputs: str = "SUMMARY,QUIZ"):
    headers = {"X-Engine-Key": key} if key else {}
    return client.post("/v1/process", headers=headers,
                       data={"task_id": "5", "doc_id": "9", "outputs": outputs, "title": "Lecture"},
                       files={"file": ("lecture.pdf", data, "application/pdf")})


def test_health(client):
    assert client.get("/health").json()["status"] == "ok"


def test_rejects_missing_or_wrong_key(client):
    assert _post(client, _text_pdf(1), key=None).status_code == 401
    response = _post(client, _text_pdf(1), key="wrong")
    assert response.status_code == 401 and response.json()["error"]["code"] == "unauthorized"


def test_rejects_invalid_outputs(client):
    response = _post(client, _text_pdf(1), outputs="SUMMARY,SUMMARY")
    assert response.status_code == 422 and response.json()["error"]["code"] == "invalid_outputs"


def test_rejects_non_pdf(client):
    response = _post(client, b"hello world")
    assert response.status_code == 422
    assert response.json()["error"] == {"code": "not_a_pdf", "message": "The PDF cannot be processed",
                                        "retryable": False}


def test_success_contract(client):
    response = _post(client, _text_pdf(2))
    assert response.status_code == 200
    body = response.json()
    assert body["schema_version"] == "1.0" and body["task_id"] == 5 and body["doc_id"] == 9
    assert body["outputs"] == ["SUMMARY", "QUIZ"]
    assert body["chunks"][0]["summary"]["markdown"]
    assert body["chunks"][0]["quiz"]["questions"][0]["options"][0].keys() == {"text", "is_correct"}

