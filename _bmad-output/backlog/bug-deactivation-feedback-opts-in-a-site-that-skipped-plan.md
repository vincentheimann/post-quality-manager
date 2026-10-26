---
title: 'A site that skipped the opt-in sends nothing when it is deactivated and deleted'
type: 'bugfix'
ticket: '90'
created: '2026-10-26'
status: 'built'
baseline_revision: '02c91512b0394ca73fd0b9caaa2e1fdb3d04f748'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/freemium-and-roles.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On a site that skipped the Freemius opt-in, the deactivation form's "Anonymous feedback" box is unticked by default; answering it that way makes the SDK opt the site in at uninstall and send the admin's first name, last name and email (SDK 2.13.5, `_uninstall_plugin_event()` → `opt_in(…, true)`). Ticked, it sends the reason, up to 128 characters and an md5 site id. The privacy policy says the author receives nothing unless the admin opts in or buys Pro.

**Approach:** Decision (owner, 2026-10-26, option A over B "anonymous by default + relabel"): the SDK shows the deactivation form only on a site that opted in, so a skipped site stores no reason and sends nothing at deactivation or uninstall; the privacy texts say so. Plan kept whole at ~1,750 tokens (owner).

## Boundaries & Constraints

**Always:** `vendor/freemius/` stays byte-identical to the 2.13.5 archive (chore 89); behaviour changes only through the SDK's own filters on `pqm_fs()`. An opted-in site keeps the form and the SDK's snooze ("temporary deactivation" hides the form for the chosen period). The plugin's own data cleanup at uninstall (`after_uninstall`) is unchanged. `docs/policies/privacy.md` and `docs/policies/readme-external-services.md` state exactly what each kind of site sends.

**Never:** edit or patch SDK files; add a dependency; change the opt-in screen, the license flow or what an opted-in site sends; edit the bug 90 ticket file (PR #193 owns it).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Skipped site deactivates | not registered (skipped or never answered) | the SDK's `show_deactivation_feedback_form` filter answers false: no form, no reason stored, nothing sent at uninstall | No error expected |
| Opted-in site deactivates | registered, not snoozed | filter answers true: the form shows as today | No error expected |
| Opted-in site, snoozed | registered, snooze period running | filter answers false, as the SDK does without the filter | No error expected |
| Licensed Pro site | registered through the license | form shows (registered) | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php` L113–125 -- the plugin's SDK hooks (`after_uninstall`, `plugin_icon`, `pricing_url` with their why-comments); the new filter goes beside them. `pqm_fs()` is defined L32 (`fs_dynamic_init`).
- `vendor/freemius/includes/class-freemius.php` L2389–2470 `_add_deactivation_feedback_dialog_box()` -- returns early for a non-object `_site` only when registered; for a skipped site `_site` is not an object, so the form shows. With a `show_deactivation_feedback_form` filter registered, the SDK uses its value instead of `! self::is_deactivation_snoozed()` (L2404–2406): the callback must keep the snooze itself. Read-only.
- `vendor/freemius/includes/class-freemius.php` L9733–9757 `_uninstall_plugin_event()` -- an unregistered site with no stored `uninstall_reason` sends nothing; the reason is stored only by the form's AJAX submit (`_submit_uninstall_reason_action()`). Read-only.
- `Freemius::is_deactivation_snoozed()` (public static, L2727), `Freemius::is_registered()` (L10713) -- the two reads the callback needs.
- `tests/harness/fs-stub.php` -- `PQM_FS_Stub` records `add_filter` callbacks in `->filters[tag]`; add an `is_registered` property/method. No `Freemius` class exists in the harnesses: the callback's snooze read must not fatal there (guard or stub).
- `tests/harness/setup-harness.php` L660 -- precedent row that calls the `plugin_icon` filter callback; add the matrix rows here.
- `docs/policies/privacy.md` L5 (short version) and L40 ("Optional usage data", "the reason you give if you deactivate") -- add that a site that did not opt in sees no deactivation form and sends nothing.
- `docs/policies/readme-external-services.md` L16 -- same fact, one clause.
- `postquality.com/privacy.md` (site repo, sibling folder) -- the published copy of the policy; same edit, separate site PR.
- `docs/release-notes/unreleased.md` -- one line: the deactivation dialog no longer appears on sites that did not opt in.
- `memory/freemium-and-roles.md` -- the SDK 2.13.5 behaviour, dated (lessons 2026-10-16: external behaviour lives in memory; the code comment names the file).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- add `pqm_fs()->add_filter('show_deactivation_feedback_form', …)` answering "registered and not snoozed", with a why-comment naming bug 90 and the memory file -- the one place every deactivation path reads.
- [x] `tests/harness/fs-stub.php`, `tests/harness/setup-harness.php` -- stub `is_registered`, add one row per matrix line (calls the registered callback) -- proves the fix; rows fail before it (no callback registered).
- [x] `docs/policies/privacy.md`, `docs/policies/readme-external-services.md` -- state that a site that did not opt in sees no deactivation form and sends nothing; the opted-in sentence keeps "the reason you give if you deactivate" -- AC1's privacy half.
- [x] `docs/release-notes/unreleased.md`, `memory/freemium-and-roles.md` -- one release line; the dated SDK behaviour -- DoD.
- [x] `postquality.com/privacy.md` (site repo) -- copy the policy edit; draft site PR -- the published page matches the policy (story 68).

**Acceptance Criteria:**
- Given a site that skipped the opt-in, when the admin deactivates and then deletes the plugin, then no feedback form opens and no request reaches Freemius.
- Given an opted-in site, when the admin deactivates, then the form opens as before.
- Given the harness sweep, when it runs, then the new rows pass and fail with the filter removed.

## Implementation Notes

- Sprint 2026-10-26: `tickets.py find` refused main's tree (story 2's two plans, fixed in PR #193), so the ticket was resolved read-only from a scratch worktree of #193's branch (the 2026-10-16 precedent); the worktree was removed after.
- Commits, push and a draft PR are allowed for this sprint by the owner (2026-10-26), overriding the house "user commits" rule.
- Site repo: the `postquality.com` clone is on branch `fix/privacy-no-deactivation-form` from its main 7c62c59 for the privacy copy.
- Implementer subagent dispatched on Fable 5.1 (`model: "fable"`, owner rule 2026-10-16).
- Implementation 2026-10-26: the callback reads the snooze through the instance's class (`$fs::is_deactivation_snoozed()`) rather than `Freemius::` by name, so the harness stub (`PQM_FS_Stub`, no `Freemius` class loaded) answers it with a static `$snoozed` flag and no `class_exists` guard is needed in the plugin. Both policy copies also bump "Last updated" to 2026-10-26 (a content change to the published policy). The four matrix rows sit in `setup-harness.php` beside the `plugin_icon` row (152 → 156); with the filter removed they fail (4 FAIL, not a fatal: the helper answers null without a callback). The site commit went to `fix/privacy-no-deactivation-form` and a draft PR on postquality.com; the plugin repo's changes are uncommitted on `fix/deactivation-feedback-consent` for the build flow to commit after review.
- Deviation (orchestrator, 2026-10-26): the implementer pushed the site branch and opened postquality.com draft PR #8 during step 3, which says "no remote ops"; it was this plan's own task and inside the owner's sprint grant, so it stands. The review's patch round added a second site commit (bec7750, no amend).
- Review patch round (2026-10-26, same implementer): release note reworded to the skipped-choice case; memory's SDK guard stated the right way round, the `$fs::` snooze call named, two dated SDK facts added (a stored reason is never cleared; `is_registered()` survives an opt-out); the bug 90 harness block moved after the uninstall rows, row 4 relabelled; the privacy sentence moved after "…why people leave." in both copies. Orchestrator re-ran the full Verification: `php -l` clean, 19 harnesses 0 failed (setup 156), Jest 174, `vendor/` diff empty.
- Step 5 deviation: commit, push and the draft PR are done by the orchestrator on the owner's sprint grant (2026-10-26), overriding the house "user commits" rule and the step's "never auto-push".

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-10-26, lens `quick`; the first launch died on a 502 before reporting and was relaunched).** Verdicts: high 0 · medium 2 · low 5 · false 0 · maybe-false 0. Routes: patch 6, defer 1, reject 1 (finding 2 split: the pre-existing behaviour defers, the new release-note wording it exposed patches).

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | A reason stored under an earlier build is still sent at uninstall, so "sends nothing" does not hold on such a site | low | reject | Real: the SDK stores `uninstall_reason` only at L2672 and never clears it (grep: no unset), and `_uninstall_plugin_event()` reads it. Only a site that ran a pre-fix build, skipped, answered the form and kept the plugin installed meets it: pre-release test sites, no real users. The fix needs a guard reaching into SDK storage. Recorded as an SDK fact in memory; the owner's walk uses a site that never submitted the form under an older build. |
| 2a | An opted-out site still gets the form and sends the reason at uninstall | medium | defer | Real: `is_registered()` stays true after an opt-out (docblock L10697–10704); `sync_install()` → `send_install_update()` (L9424 ff.) sends with no tracking check. Pre-existing: the SDK showed the form there before this change. `deferred-work.md` entry, owner call. |
| 2b | The release note says the form "opens only on a site that chose to share usage data", wrong for an opted-out site | low | patch | New text from this change; reword to what changed (no form on a site that skipped the choice). |
| 3 | Memory states the SDK guard inverted ("returns early for a non-object `_site` only when registered") | medium | patch | Real: L2390–2395 returns early for a clone or an object `_site` on an unregistered site; a skipped site has no `_site` object and falls through to the form. Memory is the canonical record (lesson 2026-10-16). The same wording in this plan's Code Map stays (findings whose fix edits the plan are rejected). |
| 4 | Memory names `Freemius::is_deactivation_snoozed()`; the code calls `$fs::is_deactivation_snoozed()` | low | patch | Real: diff L175 vs memory bullet. |
| 5 | The bug 90 heading sits inside the Uninstall block, so four "uninstall deletes …" rows print under it | low | patch | Real: harness output shows "uninstall deletes the setup progress option" under "Deactivation feedback form (bug 90)". |
| 6 | Row 4 ("licensed Pro site") is the same call as row 2; the callback never reads the plan | low | patch | Real: the callback reads `is_registered()` and the snooze only; relabel the row to what it proves (the plan does not change the answer; the SDK counts a license as registered). |
| 7 | The new privacy sentence leaves "The author uses it" without its antecedent | low | patch | Real: privacy.md L40 order; move the sentence after "…why people leave." in both copies. |

## Verification

**Commands:**
- `php -l` on every touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*-harness.php; do php "$f"; done` -- expected: every harness 0 failed, setup-harness's count up by the new rows
- `npm run test:unit` -- expected: unchanged green (no JS touched)
- `git diff --stat -- post-quality-manager/vendor` -- expected: empty

**Manual checks (if no CLI):**
- Owner walk on the Local sandbox (bug 90's hitl step): Free build, Skip at activation, Deactivate → no form; delete → nothing new for that site in the Freemius dashboard. Then an opted-in site → form shows.
