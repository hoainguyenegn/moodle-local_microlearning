"""One durable SQLite queue and one inference worker per installation."""
import base64
import hashlib
import hmac
import json
import os
import sqlite3
import threading
import time
from contextlib import asynccontextmanager, contextmanager
from pathlib import Path
from uuid import uuid4

from fastapi import Depends, FastAPI, HTTPException, Request
from fastapi.responses import FileResponse
from pydantic import BaseModel, Field
from .pipeline import Pipeline, validate_reference

ROOT = Path(os.environ.get('SPEECH_DATA', './data'))
ROOT.mkdir(parents=True, exist_ok=True)
TOKEN = os.environ.get('SPEECH_TOKEN', '')
if len(TOKEN) < 32:
    raise RuntimeError('Set SPEECH_TOKEN to a random secret of at least 32 characters')
PIPE = None
MODEL_LOCK = threading.Lock()


@contextmanager
def db():
    conn = sqlite3.connect(ROOT / 'jobs.sqlite', timeout=30)
    conn.row_factory = sqlite3.Row
    conn.execute('PRAGMA secure_delete=ON')
    try:
        with conn:
            yield conn
    finally:
        conn.close()


with db() as conn:
    conn.execute('CREATE TABLE IF NOT EXISTS jobs (id TEXT PRIMARY KEY, requestkey TEXT UNIQUE, '
                 'digest TEXT, payload TEXT, status TEXT, result TEXT, created REAL)')


def worker(stop):
    global PIPE
    with db() as conn:
        conn.execute("UPDATE jobs SET status='queued' WHERE status='running'")
    while not stop.is_set():
        with db() as conn:
            row = conn.execute("SELECT * FROM jobs WHERE status='queued' ORDER BY created LIMIT 1").fetchone()
            if row:
                conn.execute("UPDATE jobs SET status='running' WHERE id=?", (row['id'],))
        if not row:
            stop.wait(1)
            continue
        try:
            with MODEL_LOCK:
                if PIPE is None:
                    PIPE = Pipeline(ROOT)
                result = PIPE.run(row['id'], json.loads(row['payload']))
            status = 'done'
        except Exception:
            # Do not return model exceptions, source text or filesystem paths to clients.
            result, status = {'error': 'synthesis_failed'}, 'failed'
        with db() as conn:
            conn.execute('UPDATE jobs SET status=?,result=?,payload=? WHERE id=?',
                         (status, json.dumps(result), '{}', row['id']))


@asynccontextmanager
async def lifespan(app):
    stop = threading.Event()
    thread = threading.Thread(target=worker, args=(stop,), daemon=True)
    thread.start()
    yield
    stop.set()
    thread.join(timeout=5)


app = FastAPI(lifespan=lifespan, docs_url=None, redoc_url=None)


class LimitBody:
    """Bound buffering before FastAPI parses base64 JSON uploads."""
    def __init__(self, app):
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope['type'] != 'http':
            return await self.app(scope, receive, send)
        messages, total = [], 0
        while True:
            message = await receive()
            if message['type'] == 'http.disconnect':
                return
            total += len(message.get('body', b''))
            if total > 8 * 1024 * 1024:
                await send({'type': 'http.response.start', 'status': 413, 'headers': []})
                await send({'type': 'http.response.body', 'body': b'request_too_large'})
                return
            messages.append(message)
            if not message.get('more_body', False):
                break
        async def replay():
            if messages:
                return messages.pop(0)
            return await receive()
        await self.app(scope, replay, send)


app.add_middleware(LimitBody)


async def auth(request: Request):
    if not hmac.compare_digest(request.headers.get('Authorization', ''), 'Bearer ' + TOKEN):
        raise HTTPException(401, 'unauthorized')


class Slide(BaseModel):
    title: str = Field(min_length=1, max_length=200)
    text: str = Field(min_length=1, max_length=10000)


class Job(BaseModel):
    requestkey: str = Field(pattern=r'^[a-zA-Z0-9_-]{1,100}$')
    owner: int = Field(gt=0)
    slides: list[Slide] = Field(min_length=1, max_length=50)
    voice: str = Field(min_length=1, max_length=200)
    speed: float = Field(default=1, ge=0.8, le=1.2)
    pitch: float = Field(default=0, ge=-3, le=3)
    reference: str = Field(default='', max_length=7000000)
    consent: bool = False


@app.get('/v1/health', dependencies=[Depends(auth)])
def health():
    return {'status': 'ok', 'model_loaded': PIPE is not None}


@app.get('/v1/voices', dependencies=[Depends(auth)])
def voices():
    global PIPE
    if PIPE is None:
        with MODEL_LOCK:
            if PIPE is None:
                PIPE = Pipeline(ROOT)
    return PIPE.voices()


@app.post('/v1/jobs', dependencies=[Depends(auth)], status_code=202)
def submit(job: Job):
    if sum(len(s.text) for s in job.slides) > 50000:
        raise HTTPException(422, 'script_too_large')
    if any(not s.text.strip() or not s.title.strip() for s in job.slides):
        raise HTTPException(422, 'empty_slide')
    if job.reference:
        if not job.consent:
            raise HTTPException(422, 'consent_required')
        try:
            validate_reference(job.reference)
        except Exception:
            raise HTTPException(422, 'invalid_reference_wav')
    payload = job.model_dump_json()
    digest = hashlib.sha256(payload.encode()).hexdigest()
    with db() as conn:
        conn.execute('BEGIN IMMEDIATE')
        old = conn.execute('SELECT id,digest FROM jobs WHERE requestkey=?', (job.requestkey,)).fetchone()
        if old:
            if old['digest'] != digest:
                raise HTTPException(409, 'request_conflict')
            return {'id': old['id']}
        if conn.execute("SELECT COUNT(*) FROM jobs WHERE status IN ('queued','running')").fetchone()[0] >= 100:
            raise HTTPException(429, 'queue_full')
        ident = uuid4().hex
        conn.execute('INSERT INTO jobs VALUES (?,?,?,?,?,?,?)',
                     (ident, job.requestkey, digest, payload, 'queued', 'null', time.time()))
    return {'id': ident}


def getjob(ident):
    with db() as conn:
        row = conn.execute('SELECT * FROM jobs WHERE id=?', (ident,)).fetchone()
    if row is None:
        raise HTTPException(404, 'not_found')
    return row


@app.get('/v1/jobs/{ident}', dependencies=[Depends(auth)])
def status(ident: str):
    row = getjob(ident)
    return {'id': ident, 'status': row['status'], 'result': json.loads(row['result'])}


@app.get('/v1/jobs/{ident}/audio', dependencies=[Depends(auth)])
def audio(ident: str):
    row = getjob(ident)
    if row['status'] != 'done':
        raise HTTPException(409, 'not_ready')
    return FileResponse(ROOT / row['id'] / 'audio.wav', media_type='audio/wav')


class Reference(BaseModel):
    reference: str = Field(max_length=7000000)


@app.post('/v1/validate-reference', dependencies=[Depends(auth)])
def validate(reference: Reference):
    try:
        validate_reference(reference.reference)
    except Exception:
        raise HTTPException(422, 'invalid_reference_wav')
    return {'valid': True}


class DocumentUpload(BaseModel):
    kind: str = Field(pattern=r'^(pdf|pptx)$')
    content: str = Field(max_length=7000000)


class ExplainSlide(BaseModel):
    title: str = Field(max_length=200)
    text: str = Field(max_length=10000)
    notes: str = Field(default='', max_length=10000)


class ExplainRequest(BaseModel):
    slides: list[ExplainSlide] = Field(min_length=1, max_length=15)
    audience: str = Field(default='Sinh viên mới bắt đầu', max_length=500)
    objectives: str = Field(default='', max_length=2000)
    consent: bool = False


@app.get('/v1/documents/config', dependencies=[Depends(auth)])
def document_config():
    from .documents import ai_configuration
    return ai_configuration()


@app.post('/v1/documents/extract', dependencies=[Depends(auth)])
def extract_document(document: DocumentUpload):
    from .documents import extract_isolated
    try:
        return extract_isolated(document.content, document.kind)
    except Exception:
        raise HTTPException(422, 'Không đọc được PDF/PPTX. Tối đa 50 slide, 5 MB; không hỗ trợ tài liệu mã hóa.')


@app.post('/v1/documents/explain', dependencies=[Depends(auth)])
def explain_document(request: ExplainRequest):
    from .documents import explain
    try:
        return explain([s.model_dump() for s in request.slides], request.audience, request.objectives, request.consent)
    except ValueError as error:
        raise HTTPException(422, str(error))
    except Exception:
        raise HTTPException(502, 'Không gọi được Gemini. Kiểm tra khóa, tên mô hình, hạn mức và mạng. Không tự động gửi lại.')
