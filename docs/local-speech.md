# Local Vietnamese lecture narration

## Technology and network boundary

The implementation uses VieNeu-TTS **v3 Turbo**, pinned through `vieneu==3.8.3`,
with the ONNX CPU backend, FastAPI, SQLite, FFmpeg and Moodle ad-hoc tasks.
It does not use VieNeu's proprietary v4 API or any hosted speech-generation API.
The upstream SDK is Apache-2.0: https://github.com/pnnbao97/VieNeu-TTS .
Dependencies and model assets have their own licenses; retain their notices when distributing.

Installation downloads packages and model weights from their distribution hosts.
Inference runs locally. Moodle sends scripts and optional reference recordings to
**your configured private speech server**, authenticated by a shared secret.
Never configure a third-party URL if recordings must remain on your infrastructure.
This avoids per-request fees; it does not eliminate hardware, storage or electricity costs.

## Start the engine

Requires Docker Engine/Compose (or Python 3.11 plus FFmpeg and libsndfile).
From `engine/`:

```sh
cp .env.example .env
# Replace SPEECH_TOKEN in .env with a randomly generated secret, e.g.:
openssl rand -hex 32
docker compose up --build -d
docker compose logs -f speech
```

Do not paste the secret into Git. The server binds to localhost port 8010.
First model initialization can take longer than a Moodle HTTP request. Warm it up:

```sh
docker compose exec speech python -c 'from app.pipeline import Pipeline; p=Pipeline("/data"); print(p.voices())'
```

Test a preset and a 3–30 second PCM16 WAV reference before offline operation.
Then set `HF_HUB_OFFLINE=1` in `.env` and recreate the container:

```sh
docker compose up -d --force-recreate
```

For a strict no-internet deployment, also deny outbound internet at the container
network/firewall after provisioning. HF_HUB_OFFLINE controls Hugging Face access;
it is not a general network firewall. Keep model volumes across restarts.
Run **one** Uvicorn process/replica: the durable queue is designed for one worker.

## Moodle setup

1. Install this repository at `local/microlearning` inside a compatible Moodle site.
2. Run Moodle's normal database upgrade (version `2026110600`).
3. In Site administration → Plugins → Local plugins → AI Micro-learning, set
   the speech URL and the same secret as `SPEECH_TOKEN`.
4. Enable Moodle cron at least once per minute. This is required for generation
   and polling. For a development installation:

   ```sh
   php admin/cli/upgrade.php --non-interactive
   php admin/cli/cron.php
   ```

5. Open a course as an editing teacher and select **AI lecture narration** in
   course navigation, or open `/local/microlearning/narration.php?courseid=COURSE_ID`.

If Moodle runs in another container, `127.0.0.1` points to that container.
Use a private shared network/service address and adjust the bind/network configuration.
Do not expose port 8010 publicly. The plugin uses an exact-destination cURL helper for the administrator-configured
speech endpoint. Other Moodle URL protections remain unchanged; redirects to
other URLs are rejected.

## Teacher workflow

- Enter an approved script, with a line containing `---` between slides.
- Select a built-in voice, speed and pitch. Voice labels come directly from the
  model. The interface does not invent regional or gender labels the model does not supply.
- For cloning, select **Quản lý giọng của tôi**, upload your PCM16 WAV recording,
  confirm permission, then select that saved voice in the narration form.
- Submit, leave the page if desired, and refresh progress. Jobs retry temporary
  transport failures up to five consecutive attempts and expire after 24 hours.
- Play/download the WAV. Slide buttons seek to the corresponding timestamps and
  the active script is highlighted. Listen before distributing generated audio.
- Delete a saved reference from the voices page when it is no longer needed.
  A reference with an active narration job cannot be deleted until the job ends.

The narration UI accepts **scripts per slide**. A separate [import workflow](slide-import.md)
now extracts PDF/PPTX text and offers optional Gemini drafting. Slide-image rendering,
MP4 export and publishing audio to the student feed are not implemented.
Those existing material/feed services remain separate skeleton work.
Pitch is an audio transformation, not a guaranteed change of regional accent.
Cloning similarity, pronunciation and speed require listening tests on your hardware.

## Storage and recovery

- Moodle stores voice references and final WAVs through File API. Access requires
  course capability and ownership; files have no public storage URL.
- Moodle keeps an immutable script/config snapshot for each job.
- Engine SQLite persists jobs; interrupted running jobs resume after restart.
- Completed/failed engine jobs discard their submitted script/reference payload.
- Engine audio and per-user segment caches remain in the `speech-data` volume;
  deleting a voice does not erase previously generated lectures or cached audio.
- Back up and restrict both Moodle data and engine volumes. This release does not
  implement a cross-service GDPR export/erasure UI or automated retention policy.
- Do not change the pinned SDK/model files silently: changing models requires
  changing the cache model identifier in `pipeline.py`.

## Tests

These API/unit tests do not download or run the speech model:

```sh
python -m venv .venv
. .venv/bin/activate
pip install -r engine/requirements-test.txt
PYTHONPATH=engine pytest engine/tests -q
```

Deployment acceptance requires: Moodle upgrade; teacher upload/generation/playback;
another account denied file access; restart during generation; preset and cloned
Vietnamese audio listening tests; repeat after network isolation. Those checks
need a working Moodle/PHP installation and the downloaded model.

## Verification in the development session

- 11 Python tests passed, including ONNX cache materialization and path-escape rejection.
- PHP syntax validation passed; Moodle 4.5 installed the plugin tables successfully.
- Integrated Moodle runs at http://127.0.0.1:8088 with a separate MySQL database.
- The actual VieNeu model loaded 25 preset voices.
- A two-slide lecture submitted in the Moodle browser generated a 6.508-second WAV.
  Moodle cron fetched/stored it, the UI showed Done, and Slide 2 triggered playback.
- HTTP access to the private development secret directory returned 403.
- Initial ONNX loading required materializing Hugging Face snapshot symlinks into
  hardlinks. This is now handled automatically without disabling ONNX path validation.
- The reference-audio branch also generated a 2.270-second WAV using a synthetic
  reference from the preset test (no real person's voice was used).
- Real-speaker cloning quality still requires a consenting teacher's recording;
  automated engine checks cannot establish voice similarity or pronunciation quality.

Use [dev/README.md](../dev/README.md) for this installed local environment.

## Local preview without installing Moodle

`engine/demo.py` provides a loopback-only preview UI at http://127.0.0.1:8011.
It reads the secret from `engine/.env` and proxies requests to the local speech
server at port 8010; the secret is not sent to the browser. Run:

```sh
python3 engine/demo.py
```

Start the speech engine separately with Docker Compose before generating audio.
This preview is a single-user development tool, not the Moodle permission system;
all preview generation uses a single local owner ID. Do not expose it to a network.
In VS Code use Terminal → Run Task and choose the Microlearning preview or speech
service task (local `.vscode/tasks.json`, ignored by Git).
