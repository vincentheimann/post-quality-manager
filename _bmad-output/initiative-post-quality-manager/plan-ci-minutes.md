---
title: 'CI spends minutes only where a check can change the answer'
type: 'chore'
ticket: ''
created: '2026-10-15'
status: 'done'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'f9dcd82961a457b14256e6d51e7ca9fb21a3d0a8'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On 2026-10-15 the account used its 2,000 included Actions minutes for October (1,996 from this private repository, all Linux) and the $0 budget now blocks every run until 2026-11-01. The breakdown:
- every PR commit runs CI twice, once on `push` to the branch and once on `pull_request` (113 duplicate runs, about 575 minutes);
- ticket, memory, UX and docs-only changes run the full CI (84 of 265 commits on `main`);
- the two Plugin Check jobs (3 minutes each) run again on `main` after a rebase merge that already passed them on the PR;
- PHP 8.0 and 8.3 lint are two one-minute jobs, each billed a whole minute;
- a new push to a PR leaves the previous run going.

**Approach:** Change only `.github/workflows/ci.yml`'s triggers and job layout so that the same checks run once per change that can affect them:
- `pull_request` runs everything;
- `push` runs only on `main`, without the Plugin Check jobs;
- docs-only changes skip CI;
- one PHP job lints both versions;
- a newer run on the same PR cancels the older one.

The checks themselves do not change.

## Boundaries & Constraints

**Always:**
- Every check that runs today still runs on every PR that touches code: the JS lint, unit tests and build; PHP lint on 8.0 and 8.3 with the harness sweep on 8.3; Plugin Check on the built plugin and on the Free build.
- `main` still gets the build and the PHP lint and harness sweep on every code push. The release-please merge commits (which open no CI of their own, `GITHUB_TOKEN`) are covered by the harness sweep, whose `release-header-harness.php` guards the version and readme rules.
- `paths-ignore` lists explicit paths only: no `!` negation, which GitHub documents for `paths`, not `paths-ignore`. Files a harness reads stay outside it: `README.md` (`caps-harness.php`) and `docs/brand.md` (`release-header-harness.php`).
- A change touching both an ignored path and anything else runs CI (GitHub's rule: the run is skipped only when every changed path is ignored).

**Never:**
- `release.yml` changes; a self-hosted runner; making the repository public (it holds the Pro code, ADR 0014); a paid budget.
- Dropping a check, or Plugin Check's blocking on errors.
- `workflow_dispatch` on CI (add when a manual re-run of a skipped change is needed).

**Decisions (owner, 2026-10-15):** no budget change; the go-live waits; development continues locally until the minutes reset on 2026-11-01. Local gates are the merge evidence until then, stated in each PR.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Code PR opened or updated | `pull_request` touching `src/`, plugin PHP, tests, `bin/`, workflows, `package*.json` | One run: build, PHP, Plugin Check, Free build Plugin Check | — |
| Push to the PR branch | `push` to a non-`main` branch | No run (the `pull_request` run covers it) | — |
| Second push before the first run ends | two `pull_request` runs, same PR | The older run is cancelled | — |
| Docs-only PR | every changed path in the ignore list | No run | — |
| Mixed PR | an ignored path plus code | Full run | — |
| `README.md` or `docs/brand.md` changed | read by harnesses | Full run (not ignored) | — |
| Merge to `main` (rebase) | `push` to `main` with code | Build and PHP jobs only; no Plugin Check | — |
| Release PR merge | `push` to `main`: version files, `CHANGELOG.md`, readme | Build and PHP (the harness sweep checks the version and readme rules) | — |
| Two pushes to `main` in a row | `push`, `main` | Both run; nothing cancelled on `main` | — |

</frozen-after-approval>

## Code Map

- `.github/workflows/ci.yml`:
  - `on:` L30–32 (`push:` / `pull_request:` unfiltered).
  - Jobs: `build` L35, `php` L69 (matrix `['8.0','8.3']`, sweep `if: matrix.php == '8.3'`), `plugin-check` L96 (`needs: build`), `free-build` L117 (`needs: build`).
  - The header comment L1–28 describes when each job runs; update it.
- `.github/workflows/release.yml` -- unchanged. It builds the released commit itself and does not read CI artifacts (`memory/delivery-pipeline.md` L28).
- Harness readers outside code paths, checked by grep 2026-10-15: `tests/harness/caps-harness.php` L302 reads `README.md`; `tests/harness/release-header-harness.php` L129 reads `docs/brand.md`; `bin/build-free.php` names `vendor/freemius/README.md` (inside the plugin, not ignored).
- Ignore list: `_bmad-output/**`, `_bmad/**`, `.claude/**`, `memory/**`, `docs/adr/**`, `docs/policies/**`, `docs/release-notes/**`, `docs/user-paths.md`, `docs/distribution.md`, `n8n/**`, `assets/**`, `AGENTS.md`, `CLAUDE.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CHANGELOG.md`. No CI step or harness reads any of them.
- `memory/delivery-pipeline.md` -- the CI line: when each job runs.
- Plan A's bookkeeping, folded into this branch: `_bmad-output/initiative-post-quality-manager/plan-wizard-ticks-on-a-hand-made-site.md` status `done`; the epic's 6.6 Done line in `epic-n8n-provisioning.md` (the ticks follow-up fixed by PR #129).

## Tasks & Acceptance

**Execution:**
- [x] `.github/workflows/ci.yml`:
  - **Triggers:** `on.push.branches: [main]` and `on.pull_request`, both with the same `paths-ignore` list.
  - **Concurrency:** group `ci-${{ github.event.pull_request.number || github.ref }}`, `cancel-in-progress: ${{ github.event_name == 'pull_request' }}`.
  - **Plugin Check jobs:** `plugin-check` and `free-build` get `if: github.event_name == 'pull_request'`.
  - **PHP job:** becomes one job. It sets up PHP 8.0 and lints, then sets up PHP 8.3, lints and runs the sweep (two `shivammathur/setup-php@v2` steps).
  - **Header comment:** updated.
- [x] `memory/delivery-pipeline.md` -- the CI trigger line.
- [x] Plan A's status `done`; the epic's Done line for 6.6 says the ticks follow-up was fixed by PR #129 (2026-10-15).

**Acceptance Criteria:**
- Given the new `ci.yml`, when read against every matrix row, then each row's trigger and job set follow from the YAML alone.
- Given the first code PR after 2026-11-01, when its checks are read with `gh pr checks <n> --repo …`, then exactly one CI run appears per push, with all four job names (Build admin app, PHP lint and harness sweep, Plugin Check, Free build Plugin Check), and they pass.

Skipped: a job-level filter that skips Plugin Check when no plugin file changed — add if PR minutes are still high in November.

## Review Triage Log

Pass 1 (2026-10-15, lens `quick`): 0 high, 2 medium, 2 low, 0 false, 0 maybe-false. All four patched. The schema check (`@action-validator/cli`) and the local gate were green before the pass.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | On `main` the concurrency group is the ref; GitHub keeps one pending run per group and cancels an older pending one, so a third quick push cancels the middle run | medium | patch | GitHub workflow syntax, `concurrency` (`queue: single`). Breaks "every code push on main". Fix: push runs get a unique group (`github.run_id`). The implementer flagged it too. |
| 2 | The merged PHP job skips the 8.3 lint and the harness sweep after a failed 8.0 lint, where the old `fail-fast: false` matrix still reported them | medium | patch | Steps default to `success()`. Fix: `if: ${{ !cancelled() }}` on the 8.3 setup, lint and sweep. |
| 3 | Entry 4.8's recorded required checks name the old PHP job names; a required check of a path-skipped workflow stays pending and would block docs-only PRs | low | patch | `story-public-repository-welcomes-issues-and-security-reports-plan.md` L17, `epic-delivery-pipeline.md` L62. Not reachable today (no branch protection on a free private repository); records updated with the caveat. |
| 4 | "The PR already passed them on the same commits" is inaccurate: a PR run tests the merge ref, and release-please merges never go through Plugin Check ("Tested up to" is only checked there) | low | patch | Comment and memory reworded; the next code PR checks "Tested up to". |

## Design Notes

- Expected effect at October's pace: CI drops to about a third (roughly 600–700 minutes for the same work). Of today's ~10 minutes per run, about 6 are the two Plugin Check jobs, which no longer run on `main`, and the duplicate branch run disappears.
- Two `setup-php` steps in one job: the action switches the active PHP binary per step (its README documents matrix and per-step use). The lint step runs once per version, and the harness sweep only after the 8.3 setup.

## Verification

**Commands:**
- `npx --yes @action-validator/cli .github/workflows/ci.yml`, or `actionlint` if installed -- expected: no schema error. If neither runs offline, a YAML parse (`python -c "import yaml,sys; yaml.safe_load(open(sys.argv[1]))" .github/workflows/ci.yml`) plus a read against the matrix.
- The full local gate (unchanged code, as merge evidence): `php -l`, the harness sweep, `npm run test:unit`, `npm run lint:js`, `npm run build`.

**Manual checks:**
- After 2026-11-01: the first code PR's `gh pr checks` and the first `main` push's run list (`gh run list --workflow ci.yml`), recorded in this plan.
