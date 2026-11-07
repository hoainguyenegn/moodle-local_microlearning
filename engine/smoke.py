"""Run actual local preset synthesis, without Moodle or paid APIs."""
import json
import os
from pathlib import Path
from app.pipeline import Pipeline

root = Path(os.environ.get('SPEECH_DATA', '/data'))
root.mkdir(parents=True, exist_ok=True)
pipeline = Pipeline(root)
voices = pipeline.voices()
if not voices:
    raise RuntimeError('No built-in voices available')
payload = {'owner': 1, 'voice': voices[0]['id'], 'speed': 1, 'pitch': 0,
           'slides': [{'title': 'Slide 1', 'text': 'Xin chào các bạn. Hôm nay chúng ta cùng học một bài học mới.'}]}
manifest = pipeline.run('smoke-preset', payload)
print(json.dumps({'voices': len(voices), 'manifest': manifest}, ensure_ascii=False), flush=True)
# Cloning must be tested with a consenting teacher's own sample; do not use a stranger's recording.
print('Preset test finished. Test a teacher-provided reference through the Moodle voices page.', flush=True)
