---
title: 'Update workflow reports a failed test right after a successful update'
type: 'bugfix'
ticket: '84'
created: '2026-10-22'
status: 'done'
baseline_revision: 'ed06a8b1de95d083e8cd07eb6a4af90449f3b95c'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** After **Update workflow**, n8n republishes the workflow and answers before its webhook is registered again, so the single ping that follows meets a 404 and the page reports a failed test for an update that worked (owner's production walk, 2026-10-22).

**Approach:** The update's ping goes through `PQM_REST_API::ping_after_activation()`, the bounded 404 retry bug 80 gave provisioning (3 pings, 2 s apart, 404 only); a 404 that outlasts it is reported as today. The rotation keeps one ping (it changes a credential, not the workflow).

</frozen-after-approval>

## Implementation Notes

Oneshot: one call swapped in `update_n8n_workflow_callback()`, two comments, harness rows in `n8n-managed-harness.php` (404 then pass; persistent 404 = 3 pings) and the label of the source check in `n8n-provisioning-harness.php`; the memory line on the publication window names the update as a caller. No route, payload or template change. Implemented in-session on Opus 5.5 (session rule); rendered by hand from the workspace root (owner rule 2026-10-16). The rotation's single ping kept: `memory/n8n-provisioning.md` entry 10's walk recorded the workflow unchanged by a rotation (the ticket's assumption confirmed).

## Verification

**Commands:**
- `php -l post-quality-manager/includes/class-pqm-rest-api.php` -- expected: clean.
- every `tests/harness/*-harness.php` -- expected: 0 failed; n8n-managed gains the new rows.

**Verification 2026-10-22:** `php -l` clean on the three touched PHP files; every harness 0 failed (n8n-managed 165 → 169, n8n-provisioning 156 → 156); with the old single-ping call put back, four n8n-managed rows fail (persistent 404 = 3 pings, 404 then a pass ×2, 404 404 then a pass) and pass with the fix. Each failed ping records its failure, as provisioning's retry does, so the persistent-404 row expects one `pqm_workflow_version_seen` write per ping. No `unreleased.md` line (no change sites must be told about).

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-10-22): 5 findings — high 0, medium 0, low 5, false 0, maybe-false 0; all 5 → patch.**

1. low → patch — `class-pqm-rest-api.php`: two new comments restated n8n's behaviour (a PUT republishes) against `memory/lessons.md` 2026-10-16. Verified. Patch: both name bug 84 and `memory/n8n-provisioning.md` instead.
2. low → patch — `memory/n8n-provisioning.md` L21: the new observation carried no n8n version. Verified. Patch: "version not read on that walk — 2.42.x at the 2026-10-16 walk".
3. low → patch — `ping_after_activation()`'s loop comment said "just created" only. Verified. Patch: "created or updated".
4. low → patch — `update_n8n_workflow_callback()`'s docblock said "the ordinary ping", and memory L15 said the same, against L21. Verified. Patch: both name the 404 retry through `ping_after_activation()`.
5. low → patch — `n8n-provisioning-harness.php` L444's new label claimed the update retries, which its source check does not test (it passed with the defect back). Verified. Patch: the label says what the check proves and points to n8n-managed's rows for which callbacks retry.
