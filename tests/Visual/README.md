# Samtli Stitch Visual Regression

This suite compares real browser screenshots from the Docker-served PHP application against the Stitch reference PNGs in `docs/design-reference/stitch-export`.

Commands:

```bash
npm run visual:validate
npm run visual:baseline
npm run visual:test
npm run visual:report
```

`visual:baseline` records the current application state before a matching pass. `visual:test` captures the current implementation, compares it with `pixelmatch`, writes diffs and 50/50 overlays, and generates `artifacts/visual/report.md`.

Generated artifacts are ignored by Git. The original Stitch references are never modified.

