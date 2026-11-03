---
title: 'A re-run of the release job finds its version on Freemius through the latest deployment (story 58)'
type: 'bugfix'
ticket: ''
created: '2026-11-03'
status: 'built'
baseline_revision: '69359902e758edb657f18fc4cd7bb539a0f9073c'
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

**Problem:** Story 58's AC3 failed on 3.10.1. The agent re-ran the `package` job of run 41491659473 (attempt 2, 2026-11-02, at the owner's word). Its lookup, which pages through `GET …/tags.json`, did not find 3.10.1, so the job posted the zip again, and Freemius answered `400 duplicate_plugin_version` ("Product version 3.10.1 already exists."). No second deployment was created; the step failed. The owner's read-only calls with the product token (2026-11-03) found the cause:
- `GET …/tags.json?count=50&fields=id,version,release_mode,created` returns `{"tags":[]}`, while deployments 169782 (3.10.0) and 169805 (3.10.1) exist, both pending.
- `GET …/tags/latest.json?type=all&is_premium=false` returns 169805, 3.10.1, pending, `has_free: true`.
- `type=released` returned the same pending deployment, though the spec says it loads only released ones.
- `GET …/tags.json?count=50` without `fields` returns no deployment either, and no error.

So the list returns none of the product's deployments, every one of which is pending, with or without `fields`. The "already on Freemius" branch has never run.

**Decision (owner, 2026-11-03, plan checkpoint):** AC3 covers re-running the newest release (or a by-hand upload of it); a re-run of an older release stops on Freemius's refusal (`duplicate_plugin_version`) and uploads nothing. Ticket 58's AC3 and Notes, and `/release` step 7, say so.

**Approach:** the lookup reads `tags/latest.json?type=all`, the documented call that includes pending deployments. When that deployment's version is this release's, nothing is uploaded and its builds are fetched and checked as today; otherwise the job uploads as today. Only the fields the step uses are kept from the reply: the reply also carries a download link that works without a token for 24 hours. Record the Freemius facts in `memory/delivery-pipeline.md` and `docs/distribution.md`, and the 3.10.0 and 3.10.1 runs in story 58's plan. Ship it as `fix(release): … (story 58)`, so 3.10.2's package job re-run is AC3's next try.

</frozen-after-approval>

## Implementation Notes

Oneshot: about 15 lines in `.github/workflows/release.yml` (the lookup and its comment), plus records in `memory/delivery-pipeline.md`, `docs/distribution.md` and story 58's plan. No plugin code, schema, REST or template change. Run in-session on Opus 5.5 (session rule); `bmad-build` was rendered by hand from the workspace root, routed `oneshot` by step 2.

- Verified (2026-11-03), with a stand-in for jq, not jq 1.7.1: running the downloaded jq binary was refused by the session's safety rules, so `jqshim.py` answers the step's two filter forms (`.field` with `-r`/`-e`, `{a, b}` with `-c`). The step script was extracted unchanged from `release.yml` and run in bash against a stub `curl` that plays Freemius and logs every call. Results: re-run (latest = 3.10.1, V = 3.10.1): one GET of `latest.json`, no POST, no PUT, "already on Freemius as deployment 169805 (pending)", both builds fetched, outputs `id`, `pro`, `free`. New version (V = 3.10.2): GET, POST, PUT, both builds, "Uploaded … release mode pending". Older re-run (V = 3.10.0): GET, then a POST refused with 400; the step fails and prints Freemius's error. The stand-in then raises on empty input where jq exits 4, as in the real 3.10.1 re-run log. The stub's download link reached neither the log nor `$GITHUB_OUTPUT` in any case. `release.yml` parses as YAML; `bash -n` passes on the step.
- Re-routed to `full` after review pass 1: the change touches 4 files beyond the plan (the house rule allows 3 for oneshot), and AC3's scope is the owner's call (Open Questions).

Skipped: finding the deployment of an older version when a newer one exists, for a re-run of an older release's job. Add it when such a re-run is needed. Until then that re-run fails on Freemius's own refusal, which the log prints ("Product version X already exists."), and nothing is uploaded twice.
Skipped: a product with no deployment at all (`latest.json` then fails the step). This product has deployments; add it if the job is reused elsewhere.
- Patches after the checkpoint (2026-11-03): the step comment names `memory/delivery-pipeline.md` without restating the facts, and dates `latest.json` to 2026-11-03; ticket 58's AC3 (Given: the newest deployment; And: an older release's re-run stops on Freemius's refusal) and a Decision line in its Notes; `/release` step 7; the records in `memory/delivery-pipeline.md`, `docs/distribution.md` and story 58's plan state the evidence as observed (none of the deployments, all pending, with or without `fields`). Re-ran the stub cases and the YAML parse after them.

## Code Map

- `.github/workflows/release.yml` L122–124 (the step's endpoint comment), L165–179 (the lookup, then POST + PUT only when absent). The `api()` helper prints Freemius's error body on failure. `get_zip` and the `has_free` check read `ID` and `TAG` and stay unchanged.
- `_bmad-output/backlog/story-release-uploads-zip-to-freemius.md` L32–35, AC3: "Given `<version>` already exists on Freemius (a re-run of the job, or an earlier manual upload) … no second version is created, the existing version's builds are fetched and checked". Changing AC3 is the owner's call.
- `.claude/skills/release/SKILL.md` step 7: the re-run log line and "re-run the job" after a red Freemius step.
- Records: `memory/delivery-pipeline.md` L43, `docs/distribution.md` L102, story 58 plan § Plan Change Log.

## Plan Change Log

## Review Triage Log

Pass 1, 2026-11-03, Quick lens (Opus): 7 findings, all real. 1 intent gap, which re-routes to `full`; the others are patched after the owner's answer, since the lookup they touch may change.
- intent_gap: AC3 narrowed to the newest version. `latest.json` returns only the highest-version deployment (spec: "whichever has the higher version"), so an older release's re-run posts and fails before fetching its builds. AC3 in ticket 58 states no such limit. → Open Question 1.
- medium, patch after Q1: the records state "the list shows no pending deployment" as the cause, but both empty reads passed `fields=`, and every deployment is pending, so "`fields=` empties the list" fits the evidence equally well. The owner's read without `fields` settles it.
- low, patch after Q1: `/release` step 7 still promises "already on Freemius" for any re-run (option B narrows it).
- low, route: oneshot at 4 files beyond the plan, against `_bmad/custom/bmad-build.toml` (3 files). Re-routed to `full`.
- low, patch: the step comment restates the Freemius facts besides naming `memory/delivery-pipeline.md` (lesson 2026-10-16). It will name the memory file only.
- low, patch: the comment dates `latest.json` to the 2026-10-13 spec read; it was taken up on 2026-11-03.
- low, patched in this plan: Verification named jq 1.7.1 while a stand-in ran, and no results were recorded. Implementation Notes now hold both.

Pass 2, 2026-11-03, Quick lens (Opus), after the checkpoint and the patches: 2 findings, medium 1, low 1, both patched (records only). Pass 1's patches were confirmed in the diff.
- medium, patch: the memory insertion split the job's sentence, so `memory/delivery-pipeline.md` L43 read as if an older release's re-run went on to fetch both builds. That is the opposite of the code: the POST fails under `set -euo pipefail`, so `get_zip` never runs. Patched: the job's flow (lookup, upload, pending, fetch both builds) comes first, then the Freemius facts and the owner's decision.
- low, patch: "a re-run of an older release stops on Freemius's refusal" holds only when that version is already on Freemius. An older release whose first run never reached the POST (secret unset or rotated, `latest.json` failing) uploads on its re-run, as a first upload. Patched: the derived records (ticket 58 AC3's And clause, `/release` step 7, `docs/distribution.md`, memory) say "an older release already on Freemius". The owner's decision is unchanged; its frozen wording describes the case it was taken for.

## Verification

**Commands:**
- The step's script, extracted from `release.yml`, run in bash against a stub `curl` that plays Freemius (calls logged) and jq (1.7.1, or the `jqshim.py` stand-in when the binary cannot run), for three cases. Expected:
  - a re-run (latest = this version): no POST and no PUT; the "already on Freemius as deployment 169805 (pending)" line; both builds fetched.
  - a new version (latest older): one POST and one PUT; the "Uploaded … release mode pending" line.
  - an older version's re-run (latest newer): one POST refused with 400; the step fails and prints Freemius's error.
- In none of them does the stub's `url` field reach the log or `$GITHUB_OUTPUT`.
- `python -I -c "import yaml"`, or a parse with any available YAML reader, on `release.yml`; else `gh workflow view` after the push.
