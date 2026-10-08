---
title: 'Refactor sweep'
type: 'refactor'
ticket: '7'
created: '2026-10-08'
status: done
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '7a2cc157d57ff398480b958fdf4ddea57401b7ca'
context: ['{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Entries 4.1–4.6 left cleanup behind: three workflows each check out, install and build the admin app; the 4.1 review's artifact re-run question is still open in `deferred-work.md`; the 4.3 guard duplicates two template rows of `rest-reviews-harness.php`; the release skill still says story 15 writes `3.0.0.md` although 3.0.0–3.1.1 have shipped.

**Approach:** Cleanup only, no behaviour change. Plugin Check becomes a job of `ci.yml` that consumes the build job's `admin-build` artifact instead of rebuilding, and `plugin-check.yml` goes. The zip upload in `release.yml` gets `overwrite: true` (documented by upload-artifact v4: a matching artifact is deleted before upload, no failure when absent), which settles the deferred re-run question. The two duplicated template rows move out of the reviews harness; the guard gains one check so nothing is lost. The release skill and the memory file say what is true now.

**Decisions (owner, 2026-10-08):** Plugin Check on the artifact (not a composite action shared by three workflows); the reviews-harness rows are dropped, the guard checks that every filled placeholder appears. `release.yml` keeps its own build: it builds the tagged commit.

## Boundaries & Constraints

**Always:** the PR check stays named "Plugin Check", blocking on errors only, `ignore-codes: no_plugin_readme` with the story 38 comment, action pinned by SHA. Every harness stays green. Conventional Commits (`chore:`/`ci:`), the owner commits.

**Never:** no plugin code change, no new dependency, no change to how releases are tagged or published, no composite action, no change to the push/pull_request triggers (seed-branch proofs rely on push runs), no edit of `AGENTS.md` (managed block) or of the specs.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Push or PR | current code | build job green, Plugin Check job downloads `admin-build`, reports 0 errors | CI green |
| Build fails | lint, test or build error | Plugin Check job skipped (`needs: build`) | CI red on the build job |
| Seeded error | an unescaped `echo` of request input on a branch | Plugin Check job fails | CI red |
| Package re-run | zip upload re-run in the same workflow run | artifact replaced, no "already exists" failure | — |
| Guard: placeholder gone | `__PQM_SITE_HOST__` removed from the template | guard fails naming the placeholder | sweep red |
| Guard: site host | a WordPress host in the template | guard fails naming the host (reviews harness no longer checks) | sweep red |

</frozen-after-approval>

## Code Map

- `.github/workflows/ci.yml` -- new job `plugin-check` (name `Plugin Check`, `needs: build`): `actions/checkout@v4`, `actions/download-artifact@v4` with `name: admin-build`, `path: post-quality-manager/admin/build`, then the `wordpress/plugin-check-action@10857da…` step copied verbatim from `plugin-check.yml:31-38` (SHA pin, `build-dir`, `ignore-codes` + story 38 comment). Header comment gains one paragraph from `plugin-check.yml:1-6`. The `build` job (L19-51) is unchanged; its artifact is no longer "inspection only".
- `.github/workflows/plugin-check.yml` -- deleted (house rule: the owner approves the deletion with this plan).
- `.github/workflows/release.yml:79-84` -- "Upload zip artifact": add `overwrite: true` with a one-line comment (re-run of the package job).
- `tests/harness/rest-reviews-harness.php:333-338` -- remove the `$raw` URL and placeholder rows (54 → 52 checks); lines 318-332 and 339+ stay.
- `tests/harness/template-guard-harness.php` -- after the "every placeholder is one pqm_workflow_json() fills" check: one check that every `FILLED_PLACEHOLDERS` entry occurs in `$raw`, plus the moved URL-set row (review pass 1) (26 → 28 checks).
- `.claude/skills/release/SKILL.md` step 3 -- "exists on `main` (story 15 writes the first one)" instead of `3.0.0.md`.
- `_bmad-output/initiative-post-quality-manager/deferred-work.md` -- remove the 4.1 entry (last entry, "Re-running a failed `package` job…"): closed by `overwrite: true`.
- `memory/delivery-pipeline.md` -- L28 (artifact now feeds Plugin Check), L34 (Plugin Check is a `ci.yml` job), the 4.3 paragraph (reviews harness no longer duplicates), and a sweep line with the `overwrite` fact.

## Tasks & Acceptance

**Execution:**
- [x] `.github/workflows/ci.yml` -- add the Plugin Check job on the artifact -- one build per run.
- [x] `.github/workflows/plugin-check.yml` -- delete -- its job lives in `ci.yml`.
- [x] `.github/workflows/release.yml` -- `overwrite: true` on the zip upload -- closes the 4.1 deferral.
- [x] `tests/harness/rest-reviews-harness.php`, `tests/harness/template-guard-harness.php` -- move the template rows -- closes the 4.3 deferral.
- [x] `.claude/skills/release/SKILL.md`, `deferred-work.md`, `memory/delivery-pipeline.md` -- docs and memory say what is true.

**Acceptance Criteria:**
- Given the branch pushed, when CI runs, then the build job, both PHP legs and the Plugin Check job are green, and no "Plugin Check" workflow runs separately.
- Given the harness sweep, when it runs locally, then every harness passes with the new counts.
- Given the deferred findings taken (4.1 artifact re-run, 4.3 duplicate rows), when the sweep is merged, then each is closed in its record; nothing is re-filed.

## Implementation Notes

- Skipped: a composite action for the ci/release build pair — about net-zero lines; add when a third copy appears.
- Skipped: a `push: branches` filter to stop push + pull_request double runs — seed-branch proofs (4.2, 4.3, 4.5) rely on push runs.
- Not taken (named here, nothing to re-file): the 4.6 second locale, the 4.2 `package-lock.json` row, stale `brownfield.md` lines (dated record), the AGENTS.md findings from stories 26 and 36 (`bmad-project-context` refresh, owed). Stale remote branches `chore/plugin-check-blocking`, `chore/plugin-check-blocking-seed`, `chore/plugin-check-in-ci` are merged; the owner deletes them.
- Built 2026-10-08 by one implementation subagent. Files: `ci.yml` (job `plugin-check`, header comment; "node job" renamed "build job" to match the job), `plugin-check.yml` deleted, `release.yml`, both harnesses, the release skill, `deferred-work.md`, `memory/delivery-pipeline.md`.
- The pinned action (v1.1.9, `action.yml`) sets up Node 24 itself, so the job needs no Node step; the old workflow's Node setup served only `npm run build`.
- Local: full sweep 15/15 green (guard 27/27, reviews 52/52); seeds "placeholder gone" and "site host" each fail only the matching guard check, template restored. The rows Push or PR, Build fails, Seeded error and Package re-run run on GitHub only: the owner's post-push `gh pr checks` covers the first; `needs` semantics the second; the Plugin Check step is unchanged from 4.5, whose seed proved the third; upload-artifact's documented `overwrite` the fourth.
- Deferrals closed: 4.1 (artifact re-run, entry removed from `deferred-work.md`) and 4.3 (template rows, its plan's Implementation Notes line 67; that plan is a done record and stays as written, this note closes it). Nothing re-filed.

- After review pass 1 (2026-10-08): the four patches applied; full sweep 15/15 green, guard 28/28, reviews 52/52; a plain-HTTP API URL fails only the new guard row. The 27/27 above records the first run.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick):** high 0 · medium 1 · low 3 · false 0 · maybe-false 0

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | The reviews-harness URL row was dropped, not moved: the guard's host allow-list passes a template with no literal URL, a plain-HTTP API address, or another path or port on that host | medium | patch | Verified: the host regex stops at `:` and `/`, and no other harness names `anthropic.com`; memory claimed the row "moved". Patched: the guard checks that the literal URLs are exactly `https://api.anthropic.com/v1/messages` (28 checks). |
| 2 | Stale lines in `memory/delivery-pipeline.md`: CI summary (PHP 8.3 only, no Plugin Check), story 15 "writes `3.0.0.md`", manifest "carries 3.0.0" | low | patch | Verified against `ci.yml`, L23 of the same file and the manifest (3.1.1). Direct text corrections. |
| 3 | `memory/admin-ui-rebuild.md` L5 says "node job" after the `ci.yml` header was renamed to "build job" | low | patch | Verified; one-word correction. |
| 4 | AC3: the 4.3 deferral is not closed in its own record (4.3 plan, Implementation Notes) | low | patch | Verified: line 67 unchanged. A closing sentence is appended to that bullet. |

## Design Notes

- **Why the artifact, not a shared action:** the artifact is already uploaded on every run and had no consumer; `release.yml` must build the tagged commit itself, so a composite action would only serve the ci/release pair at about the same line count.
- **Check name on the PR:** GitHub names the check run after the job (`name: Plugin Check`), so moving the job between workflows keeps what the owner reads on the PR.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green; guard 28/28, reviews 52/52.
- `php -l` on the two harnesses -- expected: clean.
- `python -c "import yaml,sys; [yaml.safe_load(open(f)) for f in sys.argv[1:]]" .github/workflows/ci.yml .github/workflows/release.yml` -- expected: both parse, `ci.yml` has jobs `build`, `php`, `plugin-check`; `plugin-check.yml` no longer exists.

**Manual checks (owner):**
- After push: `gh pr checks <n> --repo vincentheimann/post-quality-manager` shows build, PHP 8.0, PHP 8.3 and Plugin Check green, Plugin Check runs after the build job.
