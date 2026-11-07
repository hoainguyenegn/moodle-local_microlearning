import os
import tempfile
os.environ['SPEECH_TOKEN'] = 'test-secret-' * 4
os.environ['SPEECH_DATA'] = tempfile.mkdtemp(prefix='speech-tests-')
from fastapi.testclient import TestClient
from app import main
from test_pipeline import reference

client = TestClient(main.app)  # No lifespan: never loads/downloads the real model.
headers = {'Authorization': 'Bearer ' + os.environ['SPEECH_TOKEN']}


def payload(key='one'):
    return {'requestkey': key, 'owner': 1, 'slides': [{'title': 'Slide 1', 'text': 'Xin chào.'}],
            'voice': 'test', 'speed': 1, 'pitch': 0}


def test_auth_required():
    assert client.get('/v1/health').status_code == 401
    assert client.post('/v1/jobs', json=payload()).status_code == 401


def test_idempotency_and_conflict():
    body = payload()
    response = client.post('/v1/jobs', json=body, headers=headers)
    assert response.status_code == 202
    assert client.post('/v1/jobs', json=body, headers=headers).json() == response.json()
    body['slides'][0]['text'] = 'Đã thay đổi.'
    assert client.post('/v1/jobs', json=body, headers=headers).status_code == 409
    ident = response.json()['id']
    assert client.get('/v1/jobs/' + ident, headers=headers).json()['status'] == 'queued'
    assert client.get('/v1/jobs/' + ident + '/audio', headers=headers).status_code == 409


def test_clone_requires_consent_and_valid_audio():
    body = payload('clone')
    body['reference'] = reference()
    assert client.post('/v1/jobs', json=body, headers=headers).status_code == 422
    body['consent'] = True
    assert client.post('/v1/jobs', json=body, headers=headers).status_code == 202
    body.update(requestkey='bad-clone', reference='invalid')
    assert client.post('/v1/jobs', json=body, headers=headers).status_code == 422


def test_bounds_and_unknown_job():
    body = payload('bounds')
    body['pitch'] = 100
    assert client.post('/v1/jobs', json=body, headers=headers).status_code == 422
    assert client.get('/v1/jobs/unknown', headers=headers).status_code == 404


def test_worker_persists_output_without_retaining_reference(monkeypatch):
    import threading
    import time
    class FakePipeline:
        def run(self, ident, data):
            return [{'index': 0, 'title': 'Slide 1', 'start': 0, 'end': 1}]
    monkeypatch.setattr(main, 'PIPE', FakePipeline())
    ident = client.post('/v1/jobs', json=payload('worker'), headers=headers).json()['id']
    stop = threading.Event()
    thread = threading.Thread(target=main.worker, args=(stop,))
    thread.start()
    try:
        for _ in range(100):
            if main.getjob(ident)['status'] == 'done':
                break
            time.sleep(.02)
        row = main.getjob(ident)
        assert row['status'] == 'done'
        assert row['payload'] == '{}'
    finally:
        stop.set()
        thread.join(2)


def test_oversized_request_rejected_before_json_parsing():
    response = client.post('/v1/jobs', content=b'x' * (8 * 1024 * 1024 + 1), headers=headers)
    assert response.status_code == 413
