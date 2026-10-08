---
title: 'Plugin Check reports in CI'
type: 'chore'
ticket: '4'
created: '2026-10-08'
status: done
route: 'one-shot'
route_source: 'auto'
review: 'quick'
review_source: 'auto'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'dc48d53'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** WordPress.org's own Plugin Check has never run on the plugin, so directory-review findings would surface only at submission (backlog story 38).

**Approach:** A workflow running the official `wordpress/plugin-check-action` on every push and pull request against the built `post-quality-manager/` folder (the folder `release.yml` zips, `admin/build/` inside), all categories, action pinned by commit SHA, non-blocking (`continue-on-error` on the Plugin Check step; owner report 2026-10-08, see Implementation Notes). The first report is triaged into the plan for entry 5.

## Boundaries & Constraints

**Always:** the checked folder is the built one; the action version is pinned.

**Never:** no plugin code change (4.5 fixes), no `ci.yml` change, no `readme.txt` (story 38).

</frozen-after-approval>

## Code Map

- `.github/workflows/plugin-check.yml` -- new: checkout, Node 22, `npm ci`, `npm run build`, `wordpress/plugin-check-action@10857da…` (v1.1.9) with `build-dir: ./post-quality-manager`; the Plugin Check step is `continue-on-error: true`.
- `story-the-plugin-passes-plugin-check-and-the-check-blocks-ci-plan.md` -- new (draft): the triage.
- `memory/delivery-pipeline.md` -- built facts.

## Implementation Notes

- Verified from the action source before writing it (lesson 2026-10-08): inputs `build-dir`, `categories` (empty = all), `ignore-codes`; the slug is the folder's basename (`post-quality-manager`); the step fails on `ERROR` results only (warnings only with `strict`); results go to file annotations, the job log, and the `plugin-check-results` artifact.
- Built 2026-10-08. First run 37782106347: the Plugin Check step failed on 1 error while the workflow concluded success, so the agent reported it as non-blocking. The owner saw both Plugin Check checks red on PR #77: a job-level `continue-on-error` keeps the workflow run green, but the job's check run still concludes `failure`. Moved to the step, so the findings no longer fail the job and a broken checkout or build still does. Report: 1 error (`no_plugin_readme`), 29 warnings, triaged in entry 5's plan.

## Review Triage Log

- Quick self-review: 0 findings.

## Verification

- The Plugin Check job runs on the PR; its report is readable in the job log and as the artifact; the triage in entry 5's plan names the one reported error.
