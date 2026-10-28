# Extended Requirements — 2026-10-28

The supplied meeting notes extend the proposed workflow to teacher-authored scripts, individually selected AI operations, function-specific adapters and cost tracking, slide narration with selectable voices, teacher voice profiles, local audio uploads, audio-to-document conversion, reusable content libraries, and timestamped audio search/navigation.

The current scaffold only covers PDF/AUDIO/VIDEO -> summaries/quizzes -> review/publication -> student quizzes. Chunks include source offsets, but no segment search endpoint or player UI exists. Script authoring, TTS, voice profiles and library modules are not implemented.

## Team TODOs

- TODO: Design script and transcript editing modules with explicit teacher review steps.
- TODO: Define speech-generation and teacher voice-profile contracts, ownership and provider adapters.
- TODO: Design library metadata, visibility, reuse, search and timestamp navigation APIs.
- TODO: Define usage/cost records for each AI operation and agree on provider configuration.
- TODO: Update the EERD, versioned JSON schemas, capabilities and API contracts before implementation.

These are proposed extensions, not existing tables or working features.
