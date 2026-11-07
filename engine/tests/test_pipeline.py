import base64
import io
import wave
import pytest
from app.pipeline import split_text, validate_reference


def reference(seconds=4, amplitude=1000):
    buf = io.BytesIO()
    with wave.open(buf, 'wb') as wav:
        wav.setparams((1, 2, 16000, 0, 'NONE', 'not compressed'))
        wav.writeframes(int(amplitude).to_bytes(2, 'little', signed=True) * int(16000 * seconds))
    return base64.b64encode(buf.getvalue()).decode()


def test_chunking_preserves_words_and_limits():
    text = 'Xin chào các bạn. ' * 100
    parts = split_text(text, 80)
    assert ' '.join(parts) == text.strip()
    assert all(len(p) <= 80 for p in parts)


def test_empty_and_oversized_word_rejected():
    for text in (' ', 'x' * 351):
        with pytest.raises(ValueError):
            split_text(text)


def test_reference_rejects_silence_and_bad_duration():
    validate_reference(reference())
    for clip in (reference(.5), reference(31), reference(4, 0), 'bad'):
        with pytest.raises(Exception):
            validate_reference(clip)


def test_pipeline_reuses_cache_and_builds_real_wav(tmp_path, monkeypatch):
    from app.pipeline import Pipeline
    import shutil
    calls = []
    class FakeTTS:
        def infer(self, text, **kwargs):
            calls.append(text)
            return b'\x10\x00' * 48000
        def save(self, audio, path):
            with wave.open(path, 'wb') as wav:
                wav.setparams((1, 2, 48000, 0, 'NONE', 'not compressed'))
                wav.writeframes(audio)
    def convert(args, **kwargs):
        shutil.copyfile(args[args.index('-i') + 1], args[-1])
    monkeypatch.setattr('app.pipeline.subprocess.run', convert)
    pipeline = Pipeline.__new__(Pipeline)
    pipeline.tts, pipeline.root = FakeTTS(), tmp_path
    payload = {'owner': 1, 'voice': 'sample', 'speed': 1, 'pitch': 0,
               'slides': [{'title': 'One', 'text': 'Xin chào.'}, {'title': 'Two', 'text': 'Bài học.'}]}
    manifest = pipeline.run('first', payload)
    assert manifest[0]['start'] == 0
    assert manifest[1]['start'] == manifest[0]['end'] == 1.2
    assert manifest[1]['end'] == 2.4
    pipeline.run('second', payload)
    assert len(calls) == 2
    with wave.open(str(tmp_path / 'second' / 'audio.wav')) as wav:
        assert wav.getnframes() == 115200
    payload['owner'] = 2
    pipeline.run('third', payload)
    assert len(calls) == 4  # Never share a teacher's generated audio cache with another owner.


def test_onnx_cache_materialization_preserves_bytes_and_rejects_escape(tmp_path):
    from app.pipeline import materialize_model_cache
    root = tmp_path / 'hub'
    blob = root / 'blobs' / 'weight'
    blob.parent.mkdir(parents=True)
    blob.write_bytes(b'model-weights')
    snapshot = root / 'models--demo' / 'snapshots' / 'revision'
    snapshot.mkdir(parents=True)
    linked = snapshot / 'weights.data'
    linked.symlink_to(blob)
    materialize_model_cache(root)
    assert not linked.is_symlink()
    assert linked.read_bytes() == blob.read_bytes()
    outside = tmp_path / 'outside'
    outside.write_bytes(b'not-a-model')
    (snapshot / 'bad').symlink_to(outside)
    with pytest.raises(ValueError):
        materialize_model_cache(root)
