"""Local synthesis. No hosted speech API or remote reference URLs."""
import base64
import hashlib
import io
import json
import os
import re
import subprocess
import wave
from pathlib import Path


def split_text(text, limit=350):
    words = re.sub(r'\s+', ' ', text).strip().split(' ')
    parts, current = [], ''
    for word in words:
        if len(word) > limit:
            raise ValueError('word_too_long')
        if len(current) + len(word) + 1 > limit and current:
            parts.append(current)
            current = ''
        current = (current + ' ' + word).strip()
        if current and current[-1] in '.!?;':
            parts.append(current)
            current = ''
    if current:
        parts.append(current)
    if not parts:
        raise ValueError('empty_text')
    return parts


def validate_reference(encoded):
    raw = base64.b64decode(encoded, validate=True)
    if len(raw) > 5 * 1024 * 1024:
        raise ValueError('reference_too_large')
    with wave.open(io.BytesIO(raw), 'rb') as wav:
        duration = wav.getnframes() / wav.getframerate()
        if not 3 <= duration <= 30 or wav.getnchannels() not in (1, 2) or wav.getsampwidth() != 2:
            raise ValueError('reference_requires_3_to_30_seconds_pcm16_wav')
        samples = wav.readframes(wav.getnframes())
        import array
        values = array.array('h', samples)
        if not values or max(abs(v) for v in values) < 100:
            raise ValueError('reference_silent')
    return raw


def materialize_model_cache(root):
    """Keep ONNX graph and external weights together without disabling path validation.

    Recent hub caches use symlinks to sharded blobs. Hardlinks preserve bytes and
    disk space, but give ONNX a real file in the snapshot directory.
    """
    root = Path(root).resolve()
    for path in root.glob('models--*/snapshots/**/*'):
        if not path.is_symlink() or not path.is_file():
            continue
        source = path.resolve()
        if root not in source.parents:
            raise ValueError('model_cache_target_outside_cache')
        temporary = path.with_name(path.name + '.materializing')
        temporary.unlink(missing_ok=True)
        os.link(source, temporary)
        temporary.replace(path)


class Pipeline:
    def __init__(self, root):
        from vieneu import Vieneu
        for attempt in range(4):
            try:
                self.tts = Vieneu(mode='v3turbo', backend='onnx')
                break
            except Exception as error:
                if 'External data path escapes model directory' not in str(error) or attempt == 3:
                    raise
                materialize_model_cache(Path(os.environ.get('HF_HOME', Path.home() / '.cache/huggingface')) / 'hub')
        self.root = Path(root)

    def voices(self):
        return [{'label': label, 'id': ident} for label, ident in self.tts.list_preset_voices()]

    def run(self, jobid, payload):
        folder = self.root / jobid
        folder.mkdir(exist_ok=True)
        reference = None
        if payload.get('reference'):
            reference = folder / 'reference.wav'
            reference.write_bytes(validate_reference(payload['reference']))
        cache = self.root / 'cache' / str(payload['owner'])
        cache.mkdir(parents=True, exist_ok=True)
        chunks, manifest, offset = [], [], 0
        try:
            for index, slide in enumerate(payload['slides']):
                start = offset
                for text in split_text(slide['text']):
                    config = {k: payload[k] for k in ('voice', 'speed', 'pitch')}
                    config.update(text=text, model='vieneu-3.8.3-v3turbo',
                                  reference=hashlib.sha256(reference.read_bytes()).hexdigest() if reference else '')
                    key = hashlib.sha256(json.dumps(config, sort_keys=True).encode()).hexdigest()
                    target = cache / (key + '.wav')
                    if not target.exists():
                        rawpath = folder / 'raw.wav'
                        kwargs = {'ref_audio': str(reference)} if reference else {'voice': payload['voice']}
                        audio = self.tts.infer(text, **kwargs)
                        self.tts.save(audio, str(rawpath))
                        factor = 2 ** (payload['pitch'] / 12)
                        filters = f'asetrate=48000*{factor},aresample=48000,atempo={payload["speed"] / factor}'
                        temp = folder / 'converted.wav'
                        subprocess.run(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-i', str(rawpath),
                                        '-af', filters, '-ar', '48000', '-ac', '1', '-c:a', 'pcm_s16le',
                                        str(temp)], check=True, timeout=120, capture_output=True)
                        temp.replace(target)
                    with wave.open(str(target), 'rb') as wav:
                        if wav.getnchannels() != 1 or wav.getsampwidth() != 2 or wav.getframerate() != 48000:
                            raise ValueError('invalid_cached_audio')
                        offset += wav.getnframes()
                        chunks.append(target)
                    chunks.append(None)
                    offset += 9600
                manifest.append({'index': index, 'title': slide['title'],
                                 'start': start / 48000, 'end': offset / 48000})
            with wave.open(str(folder / 'audio.wav'), 'wb') as wav:
                wav.setparams((1, 2, 48000, 0, 'NONE', 'not compressed'))
                for part in chunks:
                    if part is None:
                        wav.writeframes(b'\0\0' * 9600)
                    else:
                        with wave.open(str(part), 'rb') as source:
                            while True:
                                data = source.readframes(48000)
                                if not data:
                                    break
                                wav.writeframes(data)
            return manifest
        finally:
            for name in ('reference.wav', 'raw.wav', 'converted.wav'):
                (folder / name).unlink(missing_ok=True)
