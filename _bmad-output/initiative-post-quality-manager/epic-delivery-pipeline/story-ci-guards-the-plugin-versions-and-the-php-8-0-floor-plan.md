---
title: 'CI guards the plugin versions and the PHP 8.0 floor'
type: 'chore'
ticket: '2'
created: '2026-10-08'
status: 'done'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'auto'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'd1cb93f8e25959932ac0f8cdce554237c665ccb5'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Nothing checks that the plugin header `Version`, `PQM_VERSION` and `package.json` agree (commit 495f61a once fixed a mismatch by hand), and CI lints only on PHP 8.3 while the header promises 8.0.

**Approach:** Extend the release-header harness with a version-agreement row, run that harness in the release workflow before anything is uploaded or published, and lint every plugin PHP file on 8.0 as well as 8.3.

## Boundaries & Constraints

**Always:** the harness sweep runs only on 8.3 (harnesses may use newer PHP). A mismatch on a release fails the package job before upload, so the draft stays unpublished. `PQM_WORKFLOW_VERSION` is not part of the check.

**Never:** no new dependency, no PHPCompatibility sniff (Plugin Check is 4.4), no change to how the release is published or flagged (the pre-release question is the owner's).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| In step | all three at 3.1.0 | harness row passes, CI green | — |
| Header differs | header 3.1.1, others 3.1.0 | harness fails, message lists all three values | CI red |
| Constant differs | `PQM_VERSION` 3.1.1 | same | CI red |
| package.json differs | `"version": "3.1.1"` | same | CI red |
| 8.1-only syntax | e.g. an `enum` in a plugin file | the 8.0 lint fails on that file | CI red |
| Release out of step | release-please bumped two of three | package job fails before build and upload | release stays a draft |

</frozen-after-approval>

## Code Map

- `tests/harness/release-header-harness.php` -- row 6: header `Version` (already parsed in row 1), `PQM_VERSION` (row 3's line), `package.json` via `json_decode`; one check, failure label prints all three.
- `.github/workflows/ci.yml` -- `php` job: matrix `php: ['8.0', '8.3']`, lint on both, harness sweep only when `matrix.php == '8.3'`; header comment updated.
- `.github/workflows/release.yml` -- `package` job: a "Versions agree" step right after checkout runs `php tests/harness/release-header-harness.php` (PHP ships on ubuntu-latest).
- `memory/delivery-pipeline.md` -- record the check and the 8.0 lint.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/release-header-harness.php` -- add row 6, update the docblock line "is entry 2, not here".
- [x] `.github/workflows/ci.yml` -- PHP matrix as above.
- [x] `.github/workflows/release.yml` -- the "Versions agree" step.
- [x] `memory/delivery-pipeline.md` -- built facts.

**Acceptance Criteria:**
- Given this branch pushed, when CI runs, then both PHP lint legs and the sweep are green.
- Given a throwaway commit on the branch changing one version, when CI runs, then it fails naming the three values; reverted before merge.
- Given a throwaway commit adding an `enum` to a plugin file, when CI runs, then only the 8.0 leg fails; reverted before merge.

## Implementation Notes

- Skipped: `package-lock.json` versions -- npm and release-please keep it in step; add if it ever drifts.
- Known ceiling: `php -l` on 8.0 catches 8.1 syntax, not 8.1-only functions such as `array_is_list()`; Plugin Check (4.4/4.5) and the WP 6.6 install test cover runtime. A scan today found no 8.1 syntax in the plugin.

- Built 2026-10-08. Files: the harness (row 6, 19 checks), `ci.yml` (matrix, `fail-fast: false` so one leg's failure does not hide the other), `release.yml` (step "Versions agree" before Node setup), `memory/delivery-pipeline.md`.
- Local results: harness 19/19 at 3.1.0; with `package.json` at 3.1.1 it fails 18/1 and names the three values (restored); full sweep 14/14; both workflows parse as YAML with the expected steps.
- Merged 2026-10-08 (PR #73): CI green on GitHub, PHP 8.0 lint and 8.3 lint + sweep. The two seeded CI commits were not run: agent pushes were blocked by the auto-mode classifier, and the owner merged on the local seeded-mismatch result. The 8.0 seed (an `enum`) stays unproven in CI; `php -l` on 8.0 rejecting `enum` is PHP behaviour, not repo logic.

## Review Triage Log

- Quick self-review: 0 findings. Checked that the release checkout on a manual dispatch (empty `tag_name`, default ref) still runs the harness, and that the harness needs only PHP 8.0+, which ubuntu-latest ships.

## Verification

**Commands:**
- `php tests/harness/release-header-harness.php` -- expected: all rows pass at 3.1.0.
- Local: set `package.json` to 3.1.1, rerun -- expected: row 6 fails listing the three values; restore.

**Manual checks:**
- The two throwaway CI commits above, on the pushed branch, before merge.
