# Samtli demo-video pipeline design

## Purpose

Provide a repeatable local command that records the deployed Samtli application at `https://samtli.hampusandersson.dev`, synthesizes Swedish narration through the existing local F5 wrapper, and renders a reviewable H.264/AAC MP4.

The only visible tutorial scenes are `create-account`, `join-group`, and `create-discussion`. The final viewer sees no terminal, development tool, database UI, administrator UI, or setup activity.

## Live-data boundary

The deployed application is the recording target. Its normal application UI provides no deletion endpoint and the pipeline must not require production database credentials or a production-only endpoint.

Every run creates uniquely named, clearly owned data through normal browser flows:

- a hidden administrator account and `Demo video setup <run-id>` group;
- the visible demo account, created in `create-account`;
- its request to join the setup group, made visibly in `join-group`;
- an invisible approval by the generated administrator;
- a uniquely identified discussion created visibly in `create-discussion`.

Run IDs are part of account email addresses, names, group names, and discussion content. This avoids collisions and never changes ordinary user data. The administrator setup and approval use an unrecorded Playwright context; only the viewer context produces the video.

## Configuration

`demo-video/demo.config.yaml` is the single editable source for:

- the live base URL and output paths;
- viewport and FPS;
- F5 provider identifier and voice speed;
- global presentation pacing multiplier, typing speed, action offsets, scene holds, and optional title-overlay settings;
- the three scene IDs and their Swedish narration cues.

Narration strings are never embedded in the automation source. Each cue has a stable ID, text, and configurable presentation pause. The source produces `demo-video/output/script.md`, so the final narration can be reviewed without reading YAML.

`globalMultiplier` scales presentation-only delays. It does not change Playwright assertions, navigation timeouts, or F5 speed. Voice speed remains independently controlled by `tts.speed`.

## Pipeline architecture

The Node ESM implementation is split into focused modules:

- configuration parsing, validation, and derived script generation;
- local F5 wrapper invocation and WAV cache management;
- FFprobe duration probing and audio-timeline construction;
- live-data setup and teardown-safe browser helpers;
- the three recorded Playwright scene flows;
- FFmpeg rendering and artifact verification;
- command entry points for complete generation, environment checks, voice-only generation, and optional one-scene development runs.

The F5 wrapper runs via `spawn`/`execFile` with argument arrays, not shell-string interpolation. The cache key hashes narration text, speed, and a provider/version identifier. Cached WAV files live under `demo-video/.cache/tts` and are reused only when that key is unchanged.

## Recording and synchronization

Playwright records exactly one browser viewport from the viewer context. The hidden setup context has recording disabled. Browser automation uses role, label, and text selectors tied to rendered Samtli controls; a `data-testid` is added only if a stable accessible selector is genuinely unavailable.

For each cue the pipeline:

1. loads or generates the cue WAV;
2. measures the actual duration with FFprobe;
3. builds the audio timeline from that duration and config-driven silence;
4. keeps the matching real screen visible for the cue duration;
5. performs the associated visual action at a configured cue offset; and
6. applies the configured post-cue pause.

Technical waits use assertions and URL/state checks. No arbitrary presentation waits are embedded in scene logic. FFmpeg combines the browser recording and the assembled WAV timeline, yielding MP4/H.264/AAC/yuv420p at `demo-video/output/samtli-demo.mp4`.

## Commands and outputs

- `npm run demo-video` runs the complete workflow.
- `npm run demo-video:check` validates YAML, `F5_SWEDISH_WRAPPER`, the wrapper path, reachable F5 installation/reference audio, FFmpeg, a Playwright launch, and live URL access. It does not synthesize complete narration.
- `npm run demo-video:voice -- --scene <id>` refreshes only selected cached clips.
- `npm run demo-video -- --scene <id>` records and renders a selected scene when practical.

Successful runs create only ignored generated artifacts:

- `demo-video/output/samtli-demo.mp4`;
- `demo-video/output/script.md`;
- `demo-video/output/timing-report.json`;
- temporary recordings, audio assembly inputs, cache WAVs, and debug artifacts.

The timing report includes cue durations, configured presentation pauses, each scene duration, and overall duration. Console output presents the same compact summary.

## Failure handling

Failure reports name the current scene and URL and preserve a screenshot. Debug mode additionally preserves a Playwright trace; any partial browser video stays available. F5 invocation errors include captured stdout/stderr and a precise environment error, including the expected wrapper location when the wrapper variable is missing or invalid.

## Testing and acceptance

Focused Node tests cover configuration parsing, timing multiplier behavior, cache-key stability, script derivation, and structured failure diagnostics. The full acceptance path is:

1. run `npm run demo-video:check`;
2. generate one real Swedish F5 clip and verify non-zero duration;
3. run each Playwright scene against the live application;
4. render the complete MP4;
5. use FFprobe to confirm video and audio streams, H.264 video, and positive duration; and
6. inspect the completed video to ensure it shows only account creation, applying to join, and creating a discussion.

The README documents full generation, validation, narration editing, independent browser/voice pacing, scene-specific adjustments, and the voice-only command. `.gitignore` excludes all generated media, caches, traces, screenshots, and local environment files while retaining configuration, source, tests, and documentation.
