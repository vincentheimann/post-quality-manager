---
title: 'CI and the release workflow run on the current majors of their GitHub Actions'
type: 'chore'
ticket: '102'
created: '2026-11-04'
status: 'done'
baseline_revision: '743e4cc6f50349cc81b8286d0e60a13ec69fedfe'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Both workflows run `actions/checkout`, `setup-node`, `upload-artifact` and `download-artifact` at v4, which declare Node 20. Every run is annotated "Node.js 20 is deprecated … forced to run on Node.js 24", and the forced run will become a failure.

**Approach:** Move the 14 `uses:` lines to the current majors (checkout v7, setup-node v7, upload-artifact v7, download-artifact v8), keeping the repo's major-tag convention, with no change to any input, step or artifact name. Drop the npm cache from the release job. Update the two memory lines that name the versions.

**Decisions (owner, 2026-11-04):**
- Ticket AC2 ("no Node.js deprecation warning remains") is read as the Node 20 runtime annotations. The upstream log line `[DEP0005] Buffer() is deprecated` that download-artifact v8.0.2 prints (actions/download-artifact#484) is recorded in `memory/lifecycle.md` and rechecked at the next `/lcm`.
- release.yml's package job drops `cache: npm` (L71): no dependency cache in a publishing job that holds `contents: write` and the Freemius token (setup-node's guidance on cache poisoning). CI keeps its cache.

## Boundaries & Constraints

**Always:** major tags for `actions/*` as today; `wordpress/plugin-check-action` stays SHA-pinned at `10857da…` (v1.1.9); artifact names and paths unchanged (`admin-build`, `free-build`, `post-quality-manager-<v>.zip`, `-pro.zip`, `-free.zip`); every `with:` input kept as is.

**Never:** new inputs (`archive: false`, `fetch-tags`, `persist-credentials`, `package-manager-cache`); Dependabot (ADR 0017 allows it later); a change to what a job builds, checks or publishes; touching `setup-php@v2` or `release-please-action@v5` (both already declare node24).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| PR CI | a pull request | PHP, Build, Plugin Check (both builds) and Free build pass; `admin-build` downloads by name and extracts as before | download-artifact v8 checks the digest (`digest-mismatch: error` by default); today's digests match |
| Release dispatch | Release workflow run by hand on the branch (no tag) | release-please skips (push only); the package job checks out `github.ref`, builds the zip and uploads `post-quality-manager-<v>.zip` (L93); the GitHub Release, Freemius, Free check, `-pro` and `-free` steps skip (gated on `release_created`); nothing is published | `if-no-files-found: error` and `overwrite: true` keep their meaning in v7 |
| Next release | a tag `v<version>` from release-please | checkout `ref: v<version>` resolves the tag as in v4 (same refspecs when `fetch-tags` is unset); the `-pro`/`-free` uploads (L196, L251) run for the first time on v7 (ticket AC3) | — |

</frozen-after-approval>

## Code Map

- `.github/workflows/ci.yml` -- checkout L96, L130, L170, L192 → `@v7`; setup-node L98 → `@v7`; upload-artifact L121, L208 → `@v7`; download-artifact L173, L199 → `@v8`. Keep setup-php L134/L145/L194 and plugin-check-action L179/L214.
- `.github/workflows/release.yml` -- checkout L58 → `@v7` (its `ref:` L60 unchanged); setup-node L68 → `@v7`, and remove `cache: npm` L71 (keep `node-version: 22`); upload-artifact L93, L196, L251 → `@v7`. Keep release-please-action L47.
- `memory/lifecycle.md` L13 -- the CI row: the four actions at their new majors, the date of the move, no npm cache in the release job, and the residual DEP0005 log line (Design Notes) to recheck at the next `/lcm`.
- `memory/delivery-pipeline.md` L29 -- "upload-artifact v4 deletes a same-named artifact…" → v7; the behaviour is unchanged (`deleteArtifactIfExists` in v7.0.2).

Research (2026-11-04, every skipped major's release notes, READMEs, action.yml files and job logs): none of the breaking changes touches this repo's usage. checkout v5–v7: Node 24, runner ≥ 2.327.1 (hosted runner 2.338.2), fork checkout refused only under `pull_request_target`/`workflow_run` (not used), credentials file moved (no step runs git). setup-node v5–v7: automatic caching only with `packageManager`/`devEngines` (absent; `cache: npm` explicit), `always-auth` removed (unused). upload-artifact v6–v7: Node 24, new `archive` input defaults to `true` (same zip as v4). download-artifact v5: extract path changes only for downloads by ID (we download by name); v8: Content-Type check, digest mismatch errors by default.

## Tasks & Acceptance

**Execution:**
- [x] `.github/workflows/ci.yml` -- the nine version strings -- current majors
- [x] `.github/workflows/release.yml` -- the five version strings, and remove `cache: npm` (L71) -- current majors; no dependency cache in the publishing job
- [x] `memory/lifecycle.md`, `memory/delivery-pipeline.md` -- the two lines above -- the record follows the code

**Acceptance Criteria:**
- Given the PR, when its CI runs, then `gh pr checks` shows every check passing, and no job's annotations carry "Node.js 20 is deprecated".
- Given a manual dispatch of the Release workflow on the branch (the agent's `gh workflow run`, at the owner's word; it publishes nothing), when the package job runs, then it passes, uploads `post-quality-manager-<v>.zip` under today's name, and its annotations carry no Node 20 deprecation.
- Given the next release (3.11.0), when its package job runs, then the `-pro` and `-free` artifacts upload under today's names (ticket AC3; checked at that release, recorded in the ticket's done flip).

## Implementation Notes

- Implemented 2026-11-04 from the Code Map, with no deviation: 9 `uses:` strings in `ci.yml`, 5 in `release.yml`, and `cache: npm` removed from release.yml's `setup-node` (`node-version: 22` kept). `setup-php@v2`, `release-please-action@v5` and the SHA-pinned `plugin-check-action` are untouched. `git diff 743e4cc -- .github/workflows`: only those lines change.
- Checked upstream before writing the memory lines (`gh api`, 2026-11-04): the tags `checkout@v7`, `setup-node@v7`, `upload-artifact@v7` and `download-artifact@v8` exist, and each `action.yml` declares `node24`. Every kept `with:` input is still declared: upload-artifact v7 has `name`, `path`, `if-no-files-found` and `overwrite`; download-artifact v8 has `name` and `path`. In upload-artifact v7.0.2, `src/upload/upload-artifact.ts` calls `deleteArtifactIfExists` when `overwrite` is set. setup-node v7's `package-manager-cache` defaults to `true` but caches only when `package.json` has a `packageManager` or `devEngines.packageManager` naming npm; ours has neither, so the release job runs without a cache. `memory/lifecycle.md` records that condition.
- Local checks: both workflows parse as YAML (PyYAML), all four touched files are LF, and the harness sweep passes 22 of 22 (no harness reads `.github/`). Not run here: the PR's CI, the annotation check and the Release dispatch (AC1, AC2). They need a push, which step 3 excludes. AC3 waits for 3.11.0.
- Matrix test audit (orchestrator, 2026-11-04): a workflow file has no unit or harness test. Row 1 (PR CI) is proven by the PR's own checks, row 2 (Release dispatch) by the dispatched run, row 3 (next release) by 3.11.0's package job; each result is recorded below when it runs. The implementer ran on Opus 5.5 (owner rule 2026-10-26).
- Proof, 2026-11-04 (orchestrator): **AC1 passed.** PR #236, CI run 41805804476: `gh pr checks` shows Build admin app, PHP lint and harness sweep, Plugin Check and Free build Plugin Check all `pass`. The four jobs' annotations carry no "Node.js 20 is deprecated"; what remains is the runner image's Ubuntu 26 notice and Plugin Check's database-call warnings, both there before this change. **AC2 passed.** Release dispatch run 41806350953 (owner's go) on this branch: Release PR skipped, Package plugin zip `success`, artifact `post-quality-manager-3.10.2.zip` (1,143,289 bytes) under today's name, only the Ubuntu 26 notice, no Node 20 annotation, and no release published (latest is still v3.10.2 of 2026-11-03). AC3 (the `-pro`/`-free` uploads on v7) waits for 3.11.0.
- **AC3 passed, on 3.11.0's release (2026-11-04).** Run 41855100995 (push on main after #239 merged): Release PR and Package plugin zip `success`; the zip uploaded to Freemius as deployment 170592, release mode pending; the Free-build check printed "PHP identical: 11 files."; artifacts `post-quality-manager-3.11.0.zip`, `-pro.zip` and `-free.zip`, today's names. 3.11.1's run 41856571404 did the same (deployment 170594, the same three artifacts). Read from `gh run view` and the runs' artifact lists on 2026-11-05. Done 2026-11-05 (records PR).

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-04, Quick lens on Opus 5.5; diff since 743e4cc):** high 0 · medium 0 · low 2 · false 1 · maybe-false 0. The lens found no defect in `ci.yml` or `release.yml` (14 `uses:` lines, one removed `cache: npm`; upstream tags, node24 runtimes, kept inputs, the empty-`ref` fallback and the fork-PR guard checked with `gh api`).

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | Plan AC1 and AC2 not met yet (no PR checks, no annotation check, no Release dispatch) | false | reject | The criteria run after the push by design: step 05 commits, pushes and opens the PR, then the checks and the dispatch (owner's go given 2026-11-04) are recorded here; the plan moves to `built`, not `done`, until then. |
| 2 | `memory/lifecycle.md` L13 says setup-node v7 caches "if `package.json` gained a `packageManager` or `devEngines` field"; v7.1.0 caches only when `packageManager` or `devEngines.packageManager` names npm | low | patch | Direct correction of the record; story 103 may add `devEngines.runtime`, which does not turn caching on. The same wording in this plan's Implementation Notes is left as written (a fix that edits the plan is rejected). Sent to the implementer. |
| 3 | `memory/lifecycle.md` L13 keeps "Last checked" 2026-11-03 while the cell states facts checked 2026-11-04 | low | patch | Direct correction: keep the `/lcm` date, mark the 2026-11-04 facts as story 102's. Sent to the implementer. |

Patch round (2026-11-04, the same Opus implementer): rows 2 and 3 fixed in `memory/lifecycle.md` L13 after a fresh `gh api` read (latest releases unchanged, #484 open). The implementer also gave this plan's Implementation Notes the same npm-only wording; accepted (outside the frozen block). Re-run: `git diff 743e4cc -- .github/workflows` shows only the 14 `uses:` lines and the removed `cache: npm`; `git diff --check` clean.

## Design Notes

A Node `DeprecationWarning` **log line** may remain: download-artifact v8.0.2 prints `[DEP0005] Buffer() is deprecated` (upstream issue actions/download-artifact#484, open). It is printed inside the action's own bundle; no workflow input removes it, and v8 is the current major (ticket AC1). The runner image's "ubuntu-latest will migrate to Ubuntu 26" notice also stays; it is not an action's.

## Verification

**Commands:**
- `gh pr checks <n> --repo vincentheimann/post-quality-manager` -- expected: every check passes
- `gh api repos/vincentheimann/post-quality-manager/check-runs/<id>/annotations` for each job of the PR run and the dispatched Release run -- expected: no "Node.js 20 is deprecated" annotation
- `git diff 743e4cc -- .github/workflows` -- expected: only `uses:` version strings change, plus the removed `cache: npm` line in release.yml
