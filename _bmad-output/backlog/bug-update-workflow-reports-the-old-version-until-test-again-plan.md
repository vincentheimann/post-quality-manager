---
title: 'After Update workflow, the n8n Workflow page shows the new version, or says n8n is still switching'
type: 'bugfix'
ticket: 99
created: '2026-11-05'
status: 'done'
baseline_revision: 'd2a6d5c7bb244f857e9247f8dc2d2e978f26232e'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** n8n answers Update workflow's PUT once the new version is queued, and its webhook keeps running the previous published version until the publication consumer moves it (source read at `n8n@2.46.6`, 2026-11-05, Design Notes). The ping right after gets a 200 from the old template, so the page records the old version (or the Free variant), says "needs N" and offers Update workflow again.

**Approach:** The ping after a PUT or a provisioning retries an answer from an older template the way it retries a 404, within the same budget. A passed update forgets the stored report, as a template serve does. An old answer that outlasts the retries is not recorded: the outcome line says n8n is still switching, and the page reads "Saved, not checked yet." with **Test the connection**.

## Boundaries & Constraints

**Always:** one fix in `ping_after_activation()`, which both callers that just published go through. "Older" means a version below `POSTQUALITY_WORKFLOW_VERSION` or, on Pro, the Free variant, inside an `if ( postquality_fs()->is__premium_only() )` block (the Free build's strip pattern). Never the ping's `outdated` flag: `diagnosed()` also sets it for a mis-wired workflow. The Test button, POST /setup/connect and the rotation keep one ping. The budget stays `PROVISION_PINGS` (3) and the `postquality_provision_ping_retry_wait` filter (2 s). Copy follows `docs/brand.md`.

**Never:** poll n8n's API for the switch (`activeVersionId` is already new while the old version serves; the publication-status route is the internal `/rest`, session auth); record an old answer as the update's result; a new page state, option, REST field or template change.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Old, then new | PUT passes; ping 1 answers N−1, ping 2 answers N | 2 pings; N recorded by a test; "Your workflow answers." in success | No error expected |
| Free, then Pro (Pro build) | ping 1 answers N `free`, ping 2 answers N | 2 pings; recorded without the variant; success | No error expected |
| Old outlasts the retries | 3 pings all answer N−1 | 3 pings; nothing recorded, the earlier report gone; warning "Workflow updated. n8n is still switching to the new version: press Test the connection in a few seconds."; "Saved, not checked yet." with Test the connection, no Update workflow | No error expected |
| 404, old, new | ping 1 404, ping 2 N−1, ping 3 N | 3 pings; N recorded, the 404's failure cleared | No error expected |
| Current, mis-wired | ping 1 answers N, diagnosis names nodes | 1 ping; recorded as mis-wired, as today | No retry |
| Ping refused after the PUT | ping 1 answers 401 | 1 ping; the failure on an empty report: failed state, no version line | As today, "The workflow was updated in n8n. …" |
| Free build | ping 1 answers N `free` | 1 ping; recorded; success | The variant is never "older" on Free |
| Provisioning | ping 1 answers older | retried the same way; its outcome as today | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php`:
  - `ping_after_activation()` L1663–1672: retry condition L1667 (404 only); docblock L1655–1662.
  - `PROVISION_PINGS` L1176.
  - `update_n8n_workflow_callback()` L1818–1841: message, reply, `finish()`.
  - `finish()` L1704 records when `ok`; passing `null` for the ping records nothing.
  - `tone()` L1718 returns warning when `outdated`.
  - `provision_n8n_callback()` L1624: unchanged.
- `post-quality-manager/admin/class-pqm-admin.php`, read-only:
  - `ping_reply()` L2234: `version` and `variant` parse. At the current version it waits for the callback (`postquality_ping_callback_wait`, 8 s) before the variant check.
  - `diagnosed()` L2357.
  - `workflow_page_state()` L1425: a null report gives `set-up` with `checked` false, "Saved, not checked yet." (L1555–1557), and Test the connection as the primary (L1620).
- Forgetting the report, precedent:
  - serve: `class-pqm-rest-api.php` L800–802;
  - download: `class-pqm-admin.php` L2095–2097;
  - both use `delete_option( 'postquality_workflow_version_seen' )` with a comment.
  - `postquality_record_workflow_failure()` (`pqm-functions.php` L458) builds on an empty report, so no version line appears.
- `tests/harness/n8n-managed-harness.php` L503–520: bug 84's section, the style for the new rows.
  - Setup: `managed([CLEAN_WF, UPDATED, CLEAN_WF], $setup_map + $older_report)`. Every passing try runs a diagnosis GET and needs one more `CLEAN_WF`.
  - Answers: `$GLOBALS['tw']['ping_queue']`.
- `tests/harness/lib/n8n-world.php`:
  - `ping_queue` L52–53;
  - the default answer L358, `resp(200, ['ok'=>true,'workflow_version'=>POSTQUALITY_WORKFLOW_VERSION])`;
  - zero waits L54.
  - Harnesses run as Pro (`fs-stub.php` L40). The Free build's ping stub is in `free-build-harness.php` L237 and L526.
- `tests/harness/n8n-provisioning-harness.php` L582–584: no retry filter and no `sleep(` in `class-pqm-admin.php`. This must still hold.
- No JS change. The update reply's readers (`pqm-admin.js` `post()`, `src/n8n-connection/index.js` `confirmUpdate`) only swap `status_html`; `ping_ok` and `outdated` are read by the setup dialog's connect and provision replies only (grepped).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php`
  - Action: `ping_after_activation()` also retries an `ok` answer from an older template; the docblock names it. `update_n8n_workflow_callback()` deletes the stored report once the update passed. When the last ping still answers older, it sends the still-switching message, records nothing, and returns `ping_ok` true and `outdated` true.
  - Rationale: AC1.
- [x] `tests/harness/n8n-managed-harness.php` (and `lib/n8n-world.php` if a helper is needed)
  - Action: one row per matrix line. The "Old, then new" row fails on the baseline.
  - Rationale: AC2.
- [x] `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md` Flow 4, and `memory/n8n-provisioning.md` L21 (the cause from the source read)
  - Action: update both.
  - Rationale: the records follow the code.

**Acceptance Criteria:**
- Given every touched PHP file, when `php -l`, every `tests/harness/*.php` and `npm run test:unit` run, then all pass.
- Given a disposable n8n 2.46.6 and the showcase with a managed workflow one version behind (or the Free variant on a Pro build), when Update workflow runs (`rest_do_request()` POST /n8n/workflow), then the page body shows the new version, or the still-switching outcome, never "needs N" with Update workflow.

## Implementation Notes

Implementer subagent, 2026-11-05, on `fix/99-version-after-update-workflow` (baseline `d2a6d5c`).

- **Code.** `ping_after_activation()` decides on `$window` = a 404, or `answered_older()`. That new private helper returns true for a passing ping whose version is below `POSTQUALITY_WORKFLOW_VERSION`, or for the Free variant inside the Pro block. It never reads `outdated`. The ceiling comment sits in the docblock. `update_n8n_workflow_callback()` runs `delete_option( 'postquality_workflow_version_seen' )` as soon as `update_workflow()` passes. When `answered_older()` still holds after the retries, it sends the still-switching sentence and calls `finish( $reply, null )`. `ping_ok` and `outdated` are then true as the ping returned them, so `tone()` gives a warning. The budget, the filter, and the provisioning, rotation and connect callers are unchanged.
- **Addition beyond the matrix: a second `delete_option` in the still-switching branch.** In a 404, old, old run, try 1's 404 records a failure on the emptied report. Without the second delete, the page would show "The last test … did not pass: 404" under the still-switching outcome. The second delete clears that, so the page reads "Saved, not checked yet." in every sequence. A harness row covers it ("404, old, old").
- **Harness, `n8n-managed-harness.php`.** A bug 99 section after bug 84's has one row per matrix line. It also has three extra pins: the Free variant outlasting the retries on Pro, the 404, old, old run, and POST /setup/connect still pinging once on an older answer. The rotation's existing older-answer row now also checks one ping.
  - **Rows the fix changes.**
    - The row "an older workflow still answers the ping … the block offers the update again" asserted the bug, so it was removed; the bug 99 rows replace it.
    - The "ping fails after" rows now expect the earlier report gone and the failure on an empty report, with no version line (matrix row "Ping refused after the PUT").
    - Four `writes()` assertions gain the `-postquality_workflow_version_seen` delete.
  - **Baseline check.** Run against `d2a6d5c`'s `class-pqm-rest-api.php`: 19 rows fail, "Old, then new" among them; the rest pass. With the fix: 219 passed, 0 failed.
- **Free build row.** It is in `free-build-harness.php`'s update row, not the managed harness, because the managed harness runs as Pro only. Its `wp_remote_post()` stub now records pings. The row checks one ping, the variant recorded, and a success line. A Free build made locally shows `answered_older()` stripped to the version check.
- **The provisioning row** is in the managed harness, as the plan says, next to the update's. It covers old, then new (2 pings, the template's version recorded) and old outlasting the retries (3 pings, the older version recorded as today, no still-switching sentence).
- **Records.** EXPERIENCE.md Flow 4 has a "n8n still switching" paragraph. `memory/n8n-provisioning.md` line 21 has the cause from the source read and the fix. Lines 15 (the update route) and 22 (the outbox line) said the retry was 404-only, so they now name bug 99 as well.
- **Verified.** `php -l` is clean on the three touched PHP files. Every `tests/harness/*.php` exits 0. `npm run test:unit` passes: 10 suites, 181 tests. No JS change: grepping `src/` and `admin/js/` confirms that the update reply's readers, `pqm-admin.js` `post()` and `confirmUpdate`, only swap `status_html`. There is no `.pot` file to regenerate.
- **Left to the orchestrator.** The disposable n8n walk (Verification, manual checks), the review, the commit and the PR.
- **Walk (2026-11-05, second AC; a fork of the orchestrator ran it, no clicks).** A disposable n8n 2.46.6 on 127.0.0.1 (port 5688) ran against the showcase, which was backed up first.
  - **Setup:** a Free build of the tree, provisioned through `rest_do_request()`. The report read version 9, variant `free`. Then the Pro build (`npm run zip` of cf40c5a); the page showed the Free-variant notice with Update workflow.
  - **Update** (POST /n8n/workflow as user 1): 3.5 s, `ping_ok` true, `outdated` false, `rewrite_ready` true, "Workflow updated. Ping OK — … the round trip works." in success. The page read "Your workflow answers. Version 9 · checked just now by a test." with no "needs", no Update workflow and no still-switching line. The report: 9 by a test, no variant.
  - **The window met and retried** (n8n's own executions): execution 2 (20:46:56.9) ran the old published version (`21c0c9e7`, 16 nodes, Free), although `activeVersionId` was already the new one (`11ede0f6`). Execution 3 (20:46:59.6, the retry 2 s later) ran the new one (31 nodes, Pro). One cycle was enough, so the two spare cycles were not run.
  - **Stand-in:** provisioning checks the Anthropic key live (`check_provider_key()`, `GET https://api.anthropic.com/v1/models?limit=1`, a 2xx passes). The walk's own script answered 200 for that one URL through `pre_http_request`. Nothing in the walk calls Claude, and the dummy key lived only in n8n's credential, deleted with n8n.
  - **Logs:** `debug.log` held 54 lines, all WP-CLI's own deprecations.
  - **One aside, not filed:** the first load after the Free build printed "Table 'wp_postquality_reviews' already exists" from the story-91 rename. The rename had completed (30 rows), most likely run at the same moment by a web request (an open showcase tab). Only pre-story-91 sites meet that upgrade, and no public site runs one.
  - **Restore:** plugin list, tables/reviews/users/log, plugin files + `wp-config.php` all "same". n8n stopped by its recorded PID and its task runner's, ports 5678/5688/5689 free, the n8n folder, the walk folders and the dist zip deleted.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-05, Quick lens on Opus 5.5; diff since d2a6d5c):** high 0 · medium 0 · low 3 · false 1 · maybe-false 0. The lens found no code bug: it re-ran `php -l`, the 23 harnesses and Jest, checked every matrix row against its harness row, and read the premium block (ADR 0014 pattern), `tone()` in the still-switching case, the 404 → old → new overwrite and the late-callback guard (`pending_ping()` compares ids).

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | The second AC (the disposable n8n walk) is not met | false | reject | The walk runs after the review by design (Verification: "The orchestrator runs it after the review"); the plan stays `in-review`/`built` until it passes. |
| 2 | `memory/n8n-provisioning.md` L15 (Entry 9), rewritten by the diff, still says the route answers `status_html` "on a passing ping" and "a failed ping records nothing" | low | patch | Real: `finish()` (rest-api L1738) adds `status_html` to every reply, and `ping_webhook()` (admin L2203) records each failed try's failure, now on the report the update emptied; the diff's own "ping fails after" and 401 rows assert it. Direct correction of one memory sentence. |
| 3 | The records naming where the report is forgotten list a serve or a URL change, not a passed update: the `postquality_workflow_report()` and `postquality_record_workflow_failure()` docblocks (`pqm-functions.php`), `memory/n8n-provisioning.md` L13 (Entry 7), `memory/llm-options-architecture.md` L15 "Forget on serve" | low | patch | Real: `update_n8n_workflow_callback()` now deletes `postquality_workflow_version_seen` (miswired sentence included) once the PUT passes. Direct corrections, four places. |
| 4 | `n8n-managed-harness.php` bug 84 row label "a 401 first: no retry (only a 404 is the window)" | low | patch | Real: since this diff a passing answer from an older template is retried too (`$window`), as the bug 99 rows below it assert. A label change only. |

Patch round (2026-11-05, the same Opus implementer): rows 2, 3, 4 fixed (the Entry 9 and Entry 7 memory lines, the two `pqm-functions.php` docblocks, `memory/llm-options-architecture.md` "Forget on serve", the bug 84 row label); comments, memory and a label only. Re-run by the orchestrator: `php -l` clean on the four touched PHP files, every `tests/harness/*.php` 0 failed (23 files), `npm run test:unit` 181/181, LF only. Before the patches the orchestrator also re-ran the baseline check: `d2a6d5c`'s `class-pqm-rest-api.php` swapped in, the managed harness 200 passed, 19 failed ("old, then new" and "old outlasts the retries" among them), the fixed file restored byte-identical (`cmp`).

## Design Notes

The source read at `n8n@2.46.6`, 2026-11-05:
- **The PUT queues the new version and answers.** The PUT republishes through `activateWorkflow()`. With the publication service on (the default), that writes `activeVersionId` and an outbox record, wakes the consumer and answers.
- **The webhook runs the old version until the consumer moves it.** The webhook runs the nodes of the `workflow_published_version` row (`live-webhooks.ts` `loadFromPublishedVersion`). Only the consumer advances that row. When the trigger's registration is unchanged, the webhook row stays, so the old version answers instead of a 404.
- **The window.** It is normally under a second. A missed wake falls back to the 15 s poll.

So the ping is the only proof, and retrying the ping is the fix. Three pings 2 s apart cover the normal window; the rare fallback ends in the still-switching outcome.

Ceiling (one code comment): at the current version, a Free answer waits for the callback on each try, so a site n8n cannot call back waits up to 3 × 8 s.

Skipped: a still-switching sentence for provisioning. A fresh workflow has no previous version to answer; add one if a provisioning ever meets the case.

Skipped: a release-notes line. "Update the workflow in one click" already promises the new version.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed
- `npm run test:unit` -- expected: green

**Manual checks (if no CLI):**
- The disposable n8n walk (`docs/local-verification.md` "A disposable n8n", then the showcase backup, a Pro build of this branch, provisioning, a Free → Pro update or a version stand-in, POST /n8n/workflow, the page body read through `render_workflow_body()`, then restore "same"). This is an agent walk, with no clicks. The orchestrator runs it after the review; the implementer leaves it.
