---
title: 'The release job prints the PHP requirement Freemius recorded for the version'
type: 'chore'
ticket: ''
created: '2026-11-09'
status: 'built'
baseline_revision: '2f41434fc55cff2926966b8f77a7e811a271f769'
route: 'oneshot'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/.claude/skills/release/SKILL.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `/release` step 7 must read the PHP requirement Freemius records for a version (`requires_programming_language_version`, which the SDK hands to WordPress's update gate for Pro updates) whenever `Requires PHP` changes, and the way to read it was left unsettled until the first such release, 3.13.0. The release job fetches the version's deployment JSON from Freemius but prints only its release mode, so nobody can read the value without the API token, which lives only in a GitHub secret.

**Approach:** The job's Freemius step prints the recorded value beside the release mode, on both paths (a new upload and a version already on Freemius), compared with `readme.txt`'s `Requires PHP` (Freemius reads the readme's header, changelog 2022-12-05), with a `::warning` when it is missing or differs. `/release` step 7 reads that line; a release from before the line (3.13.0) is read on the Deployment page's "Min PHP Version" column. `memory/delivery-pipeline.md` records where Freemius takes the value and shows it. Owner's choice, 2026-11-09: "Release job prints it".

</frozen-after-approval>

## Implementation Notes

- Oneshot: three files (`.github/workflows/release.yml`, `.claude/skills/release/SKILL.md`, `memory/delivery-pipeline.md`), a few lines each, no schema, REST or template change.
- Built 2026-11-09: `release.yml` keeps `requires_programming_language_version` in the `tags/latest.json` projection and, after both paths, prints "Freemius records Requires PHP <x> for <v>; readme.txt says <y>." with a `::warning` when the value is missing or differs (never failing the job: the release is already published at that point, and the step's records are what `/release` reads); `/release` step 7 reads that line, and a release from before it on the Deployment page's "Min PHP version" column; `memory/delivery-pipeline.md` records the source (readme.txt), the column and the owner's read of 2026-11-09 (3.13.0 → 8.1, 3.12.4 → 8.0, every version Unreleased). Beyond the three files: the 3.13.0 release lines of plans 109, 118 and 103 say the PHP read was done (records only). Checks: the YAML parses (PyYAML); the new lines, extracted from the file and run under `set -euo pipefail` with a Python stand-in for the one `jq` expression (no `jq` on this PC), print the line alone for 8.1/8.1 and the line plus the warning for a missing, null or 8.0 value, exit 0 each time; harness sweep green.

## Review Triage Log

- 2026-11-09, pass 1 (lens: quick). Verdicts: high 0, medium 3, low 2, false 0, maybe-false 0. Routes: patch 4 entries, defer 1 (a pre-existing line the review named).
  - F1 low → patch. The cut-off release was named 3.13.0, but 3.13.1 shipped before this change (its run printed no PHP line): step 7 and the records now say "3.13.1 and earlier" and "from the release after 3.13.1"; 3.13.1 needs no read (8.1 in both).
  - F2 medium → patch. The new memory sentence gave a false cause for "Unreleased" ("Release plans is off"); the plans have been on since 2026-10-28 (`docs/distribution.md` L74). It now says the job uploads as pending and Released is the owner's step. The false premise came from `/release` step 9's stale clause, deferred below (pre-existing, outside this change).
  - F3 medium → patch. `memory/lifecycle.md`'s PHP row still said the Freemius read was pending; it now records the 3.13.0 read and the job's line (DoD: memory in the same change).
  - F4+F5 medium → patch (one root cause, the rule for the line's warning). An absent field in the PUT reply (unverified, possibly filled after processing) would have said "a bug ticket" about a record the job never saw, and the step did not say when a warning decides. The job now prints three outcomes (equal, different, absent from the reply) without naming a ticket; step 7 reports the line at every release and decides only when `Requires PHP` changed: different → bug ticket; absent → the Deployment page's column, a ticket only if empty or different there. Memory names both replies (PUT on upload, the projected `tags/latest.json` on a re-run).
  - Deferred: `/release` step 9's "while 'Release plans' is OFF" (deferred-work entry).
  - Re-checked after the patches: YAML parses; the extracted lines print the right one of three outputs for 8.1, missing, null and 8.0, exit 0; harness sweep green. Records added on the way: the 3.13.1 release line in story 111's plan (release check of 3.13.1, steps 1–5, 7, 8 green).

## Verification

**Commands:**
- `python -I -c "import yaml,sys; yaml.safe_load(open('.github/workflows/release.yml'))"` or an equivalent YAML parse -- expected: parses.
- The step's new shell lines run locally in Git Bash against a sample deployment JSON (with the field 8.1, without it, with 8.0) and a readme saying 8.1 -- expected: the printed line, and the warning only when missing or different.
- `for f in tests/harness/*.php; do php -d sys_temp_dir="C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/15bc8415-d28a-4945-bbb0-af050c7ba7d6/scratchpad" "$f" || break; done` -- expected: green (no harness reads `release.yml`; `release-header-harness.php` reads the headers).

**Manual checks:**
- The next release's Package job log carries the line; `/release` step 7 reads it.
