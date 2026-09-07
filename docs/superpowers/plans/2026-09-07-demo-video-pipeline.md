# Samtli Demo Video Pipeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `npm run demo-video`, which creates a Swedish-narrated MP4 walkthrough of the deployed Samtli account, join-request, and discussion flows.

**Architecture:** Node ESM modules under `demo-video/src` parse YAML, generate and cache F5 WAV narration, drive an unrecorded setup context plus a recorded Playwright viewer context, construct a measured audio timeline, and invoke FFmpeg for final muxing. The workflow operates only through the production UI and creates unique demo-owned data for every execution.

**Tech Stack:** Node.js ESM, `yaml`, `node:test`, Playwright, PowerShell/F5-Swedish wrapper, FFmpeg/FFprobe, GitHub Actions-free npm scripts.

**Spec:** `docs/superpowers/specs/2026-09-07-demo-video-pipeline-design.md`

## Global Constraints

- Record `https://samtli.hampusandersson.dev`; do not recreate or mock the application UI.
- Visible scene IDs are exactly `create-account`, `join-group`, and `create-discussion`.
- Create only clearly prefixed unique live demo data through normal UI flows; do not require or use production database credentials.
- Keep Swedish narration in `demo-video/demo.config.yaml`, never in browser automation source.
- Invoke `F5_SWEDISH_WRAPPER` using process argument arrays and preserve Swedish UTF-8 text.
- Default TTS speed is `0.85`; `globalMultiplier` affects only presentation timing.
- Emit H.264/AAC/yuv420p MP4 at `demo-video/output/samtli-demo.mp4`.
- Do not commit generated media, WAVs, caches, recordings, debug traces, screenshots, or local environment files.

---

### Task 1: Add the demo-video configuration contract and unit-tested loader

**Files:**
- Create: `demo-video/demo.config.yaml`
- Create: `demo-video/src/config.mjs`
- Create: `demo-video/test/config.test.mjs`
- Modify: `package.json`
- Modify: `.gitignore`

**Interfaces:**
- Produces `loadConfig(configPath): Promise<DemoConfig>` and `selectedScenes(config, sceneId): SceneConfig[]`.
- `DemoConfig` contains `video`, `tts`, `pacing`, `overlays`, `live`, and ordered `scenes`.
- Later tasks consume canonical paths and selected scene data from this module.

- [ ] **Step 1: Write the failing configuration tests**

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { loadConfig, selectedScenes } from '../src/config.mjs';

test('loads the three required ordered scene identifiers', async () => {
  const config = await loadConfig(new URL('../demo.config.yaml', import.meta.url));
  assert.deepEqual(config.scenes.map((scene) => scene.id), [
    'create-account', 'join-group', 'create-discussion',
  ]);
  assert.equal(config.tts.speed, 0.85);
});

test('rejects invalid scene filters and invalid pacing multipliers', async () => {
  const config = await loadConfig(new URL('./fixtures/invalid-pacing.yaml', import.meta.url));
  await assert.rejects(() => selectedScenes(config, 'missing'), /Unknown scene/);
  await assert.rejects(() => loadConfig(new URL('./fixtures/invalid-pacing.yaml', import.meta.url)), /globalMultiplier/);
});
```

- [ ] **Step 2: Run the configuration test to confirm the missing-module failure**

Run: `node --test demo-video/test/config.test.mjs`

Expected: FAIL because `demo-video/src/config.mjs` does not exist.

- [ ] **Step 3: Add `yaml`, the test script, config source, and ignored generated directories**

Use `npm install --save-dev yaml`. Add `demo-video:test` to run `node --test demo-video/test/*.test.mjs`. Add `.gitignore` entries for `demo-video/.cache/`, `demo-video/output/*.mp4`, `demo-video/output/recordings/`, `demo-video/output/debug/`, and `demo-video/output/tmp/` while retaining source config and README.

Create a configuration with exact keys:

```yaml
live:
  baseUrl: "https://samtli.hampusandersson.dev"
video: { output: "demo-video/output/samtli-demo.mp4", viewport: { width: 1440, height: 900 }, fps: 30 }
tts: { provider: "f5-swedish-local-v1", speed: 0.85 }
pacing: { globalMultiplier: 1.0, typingDelayMs: 55, beforeActionMs: 500, afterActionMs: 800 }
overlays: { enabled: true, durationMs: 1200 }
```

Include the three required scene objects, their title, `finalHoldMs`, and Swedish `narration` cue arrays (`id`, `text`, `pauseAfterMs`, `actionOffsetMs`). Narration references live control labels such as `Create account`, `Explore`, `Request to join`, `Start discussion`, `Subject`, and `First post`.

- [ ] **Step 4: Implement strict parsing and normalization**

Implement `loadConfig` with `yaml.parse`, resolve all project-relative paths from the repository root, require exact scene IDs/order, positive video dimensions/FPS, speed and multiplier greater than zero, and non-empty cue text. Implement `selectedScenes` to either return all scenes or exactly one matching ID.

- [ ] **Step 5: Run the focused test and commit**

Run: `npm run demo-video:test -- config.test.mjs`

Expected: PASS.

```bash
git add package.json package-lock.json .gitignore demo-video/demo.config.yaml demo-video/src/config.mjs demo-video/test/config.test.mjs
git commit -m "feat: add demo video configuration"
```

### Task 2: Build narration caching, duration inspection, and timeline construction

**Files:**
- Create: `demo-video/src/audio.mjs`
- Create: `demo-video/test/audio.test.mjs`

**Interfaces:**
- Consumes `DemoConfig.tts`, narration cue data, and config-root paths from Task 1.
- Produces `cacheKey(cue, tts)`, `ensureNarrationClip(options)`, `probeDurationSeconds(path)`, and `buildTimeline(sceneRuns, multiplier)`.
- `buildTimeline` returns cues with `startMs`, `durationMs`, `pauseAfterMs`, and `sceneDurationMs`.

- [ ] **Step 1: Write failing audio and timeline tests**

```js
test('cache keys change when text, speed, or provider changes', () => {
  const cue = { id: 'one', text: 'Hej' };
  assert.notEqual(cacheKey(cue, { provider: 'f5-a', speed: 0.85 }), cacheKey(cue, { provider: 'f5-a', speed: 0.8 }));
  assert.notEqual(cacheKey(cue, { provider: 'f5-a', speed: 0.85 }), cacheKey({ ...cue, text: 'Hallå' }, { provider: 'f5-a', speed: 0.85 }));
});

test('global multiplier scales presentation pauses but not measured narration', () => {
  const timeline = buildTimeline([{ id: 'create-account', cues: [{ durationMs: 1000, pauseAfterMs: 500 }], finalHoldMs: 200 }], 1.25);
  assert.equal(timeline.scenes[0].cues[0].durationMs, 1000);
  assert.equal(timeline.scenes[0].cues[0].pauseAfterMs, 625);
  assert.equal(timeline.scenes[0].durationMs, 1875);
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `node --test demo-video/test/audio.test.mjs`

Expected: FAIL because `audio.mjs` does not exist.

- [ ] **Step 3: Implement F5-safe generation and measured timeline code**

Use `createHash('sha256')` over provider, speed, and text. Generate cache names as `<hash>.wav`. Invoke PowerShell using `spawn(process.env.ComSpec ?? 'powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', wrapper, '-Text', text, '-Output', absolutePath, '-Speed', String(speed)])`; capture stdout and stderr, reject non-zero exits, and require a non-empty output file. Use `ffprobe` JSON output to read stream/container duration. Scale only pause, action-offset, before/after action, and final-hold values.

- [ ] **Step 4: Run focused tests and commit**

Run: `npm run demo-video:test -- audio.test.mjs`

Expected: PASS.

```bash
git add demo-video/src/audio.mjs demo-video/test/audio.test.mjs
git commit -m "feat: add cached demo narration timeline"
```

### Task 3: Implement live setup and resilient real-UI Playwright flows

**Files:**
- Create: `demo-video/src/live-data.mjs`
- Create: `demo-video/src/recording.mjs`
- Create: `demo-video/test/live-data.test.mjs`

**Interfaces:**
- Consumes config, selected scenes, and audio timeline from Tasks 1–2.
- Produces `createRunIdentity()`, `prepareLiveData(browser, config, identity)`, and `recordScenes(browser, config, identity, timeline, options)`.
- `recordScenes` returns the browser-video path and per-scene timing results.

- [ ] **Step 1: Write failing identity and selector-contract tests**

```js
test('run identities are distinct and identify every live record as demo data', () => {
  const first = createRunIdentity();
  const second = createRunIdentity();
  assert.notEqual(first.runId, second.runId);
  assert.match(first.adminEmail, /^demo-video-admin\+/);
  assert.match(first.viewerEmail, /^demo-video-viewer\+/);
  assert.match(first.groupName, /^Demo video setup /);
});

test('live control contract uses accessible labels from deployed Samtli', () => {
  assert.deepEqual(requiredControls, ['Create account', 'Log in', 'Explore', 'Request to join', 'Start discussion', 'Subject', 'First post']);
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `node --test demo-video/test/live-data.test.mjs`

Expected: FAIL because the live-data module does not exist.

- [ ] **Step 3: Implement the unrecorded setup flow**

Use a separate no-video context to register and log in the generated administrator, create the unique setup group, and retain its credentials and group route. Register the viewer only in the recorded context. After the viewer submits the visible request, use the setup context to visit the administrator join-request page and approve only the request tied to the generated viewer email. Never query, edit, or delete any unrelated account, group, request, membership, or discussion.

- [ ] **Step 4: Implement recording scenes with role/label/text selectors**

Set `recordVideo` only on the viewer context. Fill the registration labels at config typing speed; keep the post-registration login success state visible; log in only as the necessary continuation. Show `Explore`, submit `Request to join`, and hold `Membership request sent.`. After invisible approval, return as the viewer to the group, open `Start discussion`, fill `Subject` and `First post`, submit, and hold the resulting discussion. Associate each cue with config-driven presentation delays, actual cue duration, and assertions such as `waitForURL` or `expect(locator).toBeVisible()`.

- [ ] **Step 5: Run unit tests and one live non-rendered scene smoke test; commit**

Run: `npm run demo-video:test -- live-data.test.mjs`

Then run: `node demo-video/src/cli.mjs record --scene create-account --no-render`

Expected: unit PASS and a recorded create-account browser artifact with no administrator page in the recording.

```bash
git add demo-video/src/live-data.mjs demo-video/src/recording.mjs demo-video/test/live-data.test.mjs
git commit -m "feat: record live demo walkthrough scenes"
```

### Task 4: Add rendering, reports, diagnostics, and validation command

**Files:**
- Create: `demo-video/src/render.mjs`
- Create: `demo-video/src/check.mjs`
- Create: `demo-video/src/cli.mjs`
- Create: `demo-video/test/check.test.mjs`

**Interfaces:**
- Consumes paths/timeline/recording results from Tasks 1–3.
- Produces `validateEnvironment(config)`, `renderVideo(input)`, `writeTimingReport(report)`, and CLI commands `check`, `voice`, `record`, `render`, and `all`.

- [ ] **Step 1: Write failing command and diagnostic tests**

```js
test('environment validation reports the F5 variable and expected wrapper path', async () => {
  await assert.rejects(() => validateEnvironment(config, {}), /F5_SWEDISH_WRAPPER.*Expected wrapper/s);
});

test('timing report preserves scene, cue, pause, and total durations', () => {
  const report = createTimingReport([{ id: 'join-group', durationMs: 14100, cues: [{ id: 'join-01', durationMs: 2500, pauseAfterMs: 700 }] }]);
  assert.equal(report.scenes[0].id, 'join-group');
  assert.equal(report.totalDurationMs, 14100);
});
```

- [ ] **Step 2: Run the test to confirm it fails**

Run: `node --test demo-video/test/check.test.mjs`

Expected: FAIL because `check.mjs` does not exist.

- [ ] **Step 3: Implement validation and final render**

`validateEnvironment` checks the defined wrapper env variable, `Test-Path`-equivalent wrapper accessibility, wrapper-declared/reference-audio availability, `ffmpeg -version`, `ffprobe -version`, Playwright Chromium launch, configuration parsing, and a live URL HTTP response. It must never install or modify F5 infrastructure. Build an FFmpeg concat/filter input for silence and WAV clips, mux it with the browser WebM, encode `libx264`, `aac`, `yuv420p`, and use `-movflags +faststart`. Validate final streams and duration with FFprobe.

For failure, write `demo-video/output/debug/<scene>.png`, include scene and URL in the exception, preserve partial video, and in debug mode preserve trace ZIP. Include TTS process stdout/stderr in narration errors.

- [ ] **Step 4: Implement human-readable outputs**

Derive `script.md` directly from YAML scenes/cues. Write `timing-report.json` with each cue’s actual duration, scaled pauses, scene duration, and total duration. Print the requested compact duration and output summary after success.

- [ ] **Step 5: Run tests, check, and commit**

Run: `npm run demo-video:test -- check.test.mjs`

Run: `npm run demo-video:check`

Expected: tests PASS; check either PASS or emits the precise F5 environment diagnostic without attempting installation.

```bash
git add demo-video/src/render.mjs demo-video/src/check.mjs demo-video/src/cli.mjs demo-video/test/check.test.mjs
git commit -m "feat: render and validate demo video"
```

### Task 5: Wire npm commands, documentation, and full acceptance verification

**Files:**
- Modify: `package.json`
- Create: `demo-video/README.md`
- Modify: `.gitignore`

**Interfaces:**
- `npm run demo-video` executes `node demo-video/src/cli.mjs all`.
- `npm run demo-video:check`, `demo-video:voice`, `demo-video:record`, and `demo-video:render` forward arguments to the same CLI.

- [ ] **Step 1: Add commands and document exact operator controls**

Add these npm scripts:

```json
"demo-video": "node demo-video/src/cli.mjs all",
"demo-video:check": "node demo-video/src/cli.mjs check",
"demo-video:voice": "node demo-video/src/cli.mjs voice",
"demo-video:record": "node demo-video/src/cli.mjs record",
"demo-video:render": "node demo-video/src/cli.mjs render"
```

Document live URL use, `F5_SWEDISH_WRAPPER`, full generation, `demo-video:check`, narration edits in YAML, `globalMultiplier: 1.25`, `tts.speed: 0.80`, create-account pace fields, join-group confirmation hold, create-discussion final hold, scene filtering, and voice-only regeneration.

- [ ] **Step 2: Execute preflight and a real F5 clip**

Run: `npm run demo-video:check`

Run: `npm run demo-video:voice -- --scene create-account`

Expected: check PASS and at least one cache WAV exists with non-zero FFprobe duration.

- [ ] **Step 3: Execute all live scenes and render the final MP4**

Run: `npm run demo-video`

Expected: all three live user journeys PASS; output includes `demo-video/output/samtli-demo.mp4`, `script.md`, and `timing-report.json`.

- [ ] **Step 4: Verify final media and visible scope**

Run: `ffprobe -v error -show_entries stream=codec_type,codec_name,pix_fmt -show_entries format=duration -of json demo-video/output/samtli-demo.mp4`

Expected: one H.264 video stream, one AAC audio stream, `yuv420p`, and duration greater than zero.

Open the finished MP4 and confirm the visible narrative shows only creating an account, applying to join, and starting a discussion.

- [ ] **Step 5: Run regression checks and commit**

Run: `npm run demo-video:test`

Run: `composer check:syntax`

Run: `git diff --check`

Expected: all commands PASS.

```bash
git add package.json package-lock.json .gitignore demo-video/README.md
git commit -m "docs: document demo video generation"
```
