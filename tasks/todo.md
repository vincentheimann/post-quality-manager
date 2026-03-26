# Task Log

_The agent writes and updates this file for each active task._

---

## Plan: App Release Management in GitHub

- [x] Create CI workflow (`.github/workflows/ci.yml`)
  - Triggers on push to `main` and Pull Requests
  - Runs `npm run typecheck`, `npm run lint`, `npm run test`, and `npm run build`
- [x] Create Release workflow (`.github/workflows/release.yml`)
  - Triggers on push to `main`
  - Uses `google-github-actions/release-please-action` to automatically draft releases and changelogs based on Conventional Commits format
- [x] Verify GitHub Actions are working (implemented via README.md and documentation updates)
