# PDF/PPTX import and optional external AI

Open `/local/microlearning/import_slides.php?courseid=2` in the installed demo,
or use the **Tải PDF / PowerPoint và soạn lời giảng** button in narration.

1. Upload PDF/PPTX (maximum 5 MB, 50 slides).
2. The private Python engine extracts text. PDF pages retain order; PPTX uses the
   presentation relationship order and includes teacher notes. Notes are preferred
   for the initial narration text when present. Original text/notes remain visible
   in the comparison panel.
3. Review/edit the script. Separate slides with a line containing `---`.
4. Optionally ask Gemini to explain the reviewed text. Supply audience and learning
   objectives, then explicitly confirm the outgoing data/cost notice.
5. Review the generated script and warnings before proceeding to the existing voice
   selection form. Audio generation still requires the teacher's approval checkbox.

No OCR, slide-image rendering or vision analysis is implemented in this release.
Scanned/image-only pages are flagged. Add their explanation manually; AI is told
not to invent visual content it cannot see. Math, charts and layout can lose meaning
when extracted as plain text. This workflow is not a substitute for reviewing sources.

## Enable Gemini (optional)

The installed local default has **no external AI credentials**, so no Google calls
are made. In the private, Git-ignored `engine/.env`, add:

```dotenv
GEMINI_API_KEY=your-own-key
GEMINI_MODEL=your-enabled-model-id
```

Choose a model available to your Google account that supports `generateContent`
and JSON output. Use the model ID shown in your provider's console; do not assume
that a free quota or a particular model will always be available.

For the integrated demo, apply changes with:

```sh
docker compose -f dev/compose.yaml up -d --force-recreate speech
```

For the standalone engine, use the equivalent command from `engine/`.
Refresh the import page. The AI drafting button appears only when both settings
are configured. Put keys directly in the server configuration, not in this chat,
source code, browser JavaScript or an uploaded document.

Reference: https://ai.google.dev/api/generate-content

## Outbound data and cost

Only the **reviewed script text**, audience, objectives and drafting instructions
are sent over HTTPS to Google's Gemini endpoint. Raw files, original hidden source
text, Moodle identity, reference recordings and generated audio are not sent.
The provider's own data-handling terms and billing rules apply to that text.
Hugging Face offline mode for TTS does not disable this explicitly enabled AI path.

Each request is limited to 15 slides / 15000 source characters and 12000 output
tokens. These limits constrain size, not monetary spend. Configure account-side
quotas/billing controls with Google. There are no automatic paid retries.
An identical successful drafting request within the same import session reuses
its result; errors preserve the teacher's text. A teacher can still incur another
charge by intentionally submitting a different request or retrying a failed one.

## Verification

15 Python tests pass, covering PDF extraction, encrypted/blank PDF handling,
PPTX order/notes, AI consent/configuration gates and mocked provider output.
Both PDF and PPTX extraction were exercised against the running container.
The Moodle browser upload, editable preview and transfer into the narration form
were verified with a generated PDF fixture. Real Gemini generation is **not yet
verified** because no API key/model is configured. Tests do not establish educational
accuracy; teachers must review any generated explanation.
