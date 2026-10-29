---
title: 'A lapsed Pro license says so, with a link to renew'
type: 'bugfix'
ticket: 93
created: '2026-10-28'
status: done
baseline_revision: '1c98ae2ff60f885dac3930e6c538643a5702d57d'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context:
- '{project-root}/memory/freemium-and-roles.md'
- '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** When a Pro license expires, the Freemius SDK's sync detaches it from the site, so `PostQuality_License::has_lapsed()` finds no license and Settings → License shows the Free copy with "Get Pro". The SDK's own expired-license notice, shown on every admin page, has no link. A customer who wants Pro back finds no path in WP Admin (story 44 AC2, ADR 0013 D7).

**Approach:** The Pro build records a lapse in one option from the SDK's own hooks: `cancelled`, or `downgraded` when the SDK deactivated this site's license earlier in the same request (its expiry path). Any other license transition, and every license activation, clears the record. `has_lapsed()` answers true when Pro is off and either this site's license is still attached (today's case) or the record is set. The lapse copy's link and the SDK's `license_expired` notice open the pricing page with "Renew Pro" (owner decisions in the ticket).

**Decision (owner, 2026-10-28, review loop 1):** a lapse is a real expiry or a real cancellation only. A license Freemius unlinks from the install for another reason (moved to another site from the customer portal) is not a lapse, and a license that comes back by any path, the sync included, ends one.

## Boundaries & Constraints

**Always:** the record is written only from the two SDK hooks, never on a page load. The hooks and the notice filter sit in the main file's `is__premium_only()` block (story 65 precedent): the Free build registers none of them. The option carries the `postquality_` prefix, is not autoloaded, and `PostQuality_Activator::uninstall()` deletes it. A lapsed site still behaves as Free; stored overrides, schedules and role rights stay (D7).

**Never:** reading the SDK's private storage (`last_license_key`, subscriptions) or `has_any_license()`; a new notice, toast or nag (story 44 AC3); changing a notice the SDK already stored before this build; a release-note line (no site has a lapsed license yet).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Expired, detached by the sync | Pro build, `after_license_change` fired `downgraded`, no license on the site | lapse copy with "Renew Pro" → pricing page, no "Get Pro"; paid commands refused | No error expected |
| Cancelled, detached | `cancelled` fired, no license | same | No error expected |
| Cancelled, still attached | license object present, Pro off | same (today's path) | No error expected |
| Never licensed, or the license sits on another site | no record, no license | Free copy with the one "Get Pro", as today | No error expected |
| Unlinked by Freemius, not expired | `downgraded` with no SDK deactivation earlier in the request | no record (an earlier one cleared); Free copy | No error expected |
| Renewed | `after_license_activation` fired | record cleared; Pro copy once Pro is on | No error expected |
| License put back by the sync | `activated`, `upgraded`, `changed` or `extended`, no activation hook | record cleared; a later deactivation shows the Free copy | No error expected |
| The customer deactivates the license | only `after_license_deactivation` fires | Free copy (not a lapse) | No error expected |
| SDK adds its expired notice | `sticky_message_license_expired` filter | the SDK's text, then a "Renew Pro" link to the pricing page | No error expected |
| Lapsed, stored cron on, cron command free | Settings → Automation | the Enable off-switch shows (ticket 13) | No error expected |
| Uninstall | record set | option deleted with the rest | No error expected |
| Free build | stripped tree | none of the three hooks registered | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-license.php` L35–39 `has_lapsed()`: add the record branch; add the option name as a class constant, a request-scoped static flag and three small `__premium_only` hook callbacks here (the class is the one licensing seam, `memory/freemium-and-roles.md`): on `after_license_deactivation` set the flag; on `after_license_change` record for `cancelled`, or for `downgraded` while the flag is set, else delete the record; on `after_license_activation` delete it.
- `post-quality-manager/post-quality-manager.php` L133–141, the `is__premium_only()` block holding story 65's `sticky_message_connect_account` filter: register `after_license_deactivation`, `after_license_change`, `after_license_activation` and `sticky_message_license_expired` there with `postquality_fs()->add_action/add_filter`. SDK 2.13.5 (`class-freemius.php`): `after_license_change` passes `($plan_change, $plan)` (L21527). The expiry branch (L21328–21331) calls `_deactivate_license()`, whose handler fires `after_license_deactivation` (L21784; L21762 on an API error) before `downgraded` is set; the unlink branch (L21225–21230 into L21303) sets `downgraded` with no deactivation; `cancelled` (L21266) needs the license's `is_cancelled`; an in-WP deactivation fires only `after_license_deactivation`; the sync's license-back values are `activated`, `upgraded`, `changed` (L21303) and `extended` (L21352), with no activation hook (`_update_site_license()` fires none). The notice filter is applied when the sticky is added and stored (`managers/class-fs-admin-notice-manager.php` L482), so it must be registered at load, cron included.
- `post-quality-manager/admin/class-pqm-admin.php` L777–782: the lapse line's link → `POSTQUALITY_PRICING_URL`, text "Renew Pro"; the sentence before it stays.
- `post-quality-manager/includes/class-pqm-activator.php` `uninstall()`: delete the new option.
- `tests/harness/fs-stub.php`: `add_action`/`add_filter` already record callbacks by tag; a harness calls them to fire a hook.
- Harnesses: `license-harness.php` Row 3 (lapse states); `render-matrix-harness.php` L163–168 (lapse copy and link, expectation changes with the owner's decision), the ticket-13 rows L203–240 (off-switch); `setup-harness.php` near L731 (story 65's filter checks: the pattern for the notice filter); `free-build-harness.php` L616 (no Pro filter in the Free tree); `stored-names-harness.php` Row 6 (`any_leftovers()` after uninstall); `prefix-harness.php` `STORED_LITERALS` (L46) if it lists stored names.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-license.php` -- record and clear per the Approach; `has_lapsed()` reads the record -- root cause: the detach removes the only signal read today
- [x] `post-quality-manager/post-quality-manager.php` -- the three hooks and the notice filter in the Pro block -- SDK transitions and the notice link
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- "Renew Pro" to the pricing page -- owner decision
- [x] `post-quality-manager/includes/class-pqm-activator.php` -- uninstall deletes the option -- nothing outlives deletion
- [x] the harnesses above -- one check per matrix row, firing the hooks in the SDK's order (deactivation, then change); the detached-state checks fail on the baseline (ticket AC4)
- [x] `memory/freemium-and-roles.md` -- one dated line in "Real licensing": what the SDK does on expiry and how the plugin records it (SDK 2.13.5, read 2026-10-28)

**Acceptance Criteria:**
- Given the new rows against the baseline, when the sweep runs, then only the detached-state, link and notice checks fail; with the fix every harness passes.

## Implementation Notes

- 2026-10-28, planning: ticket refined and owner-approved the same day (#206). Skill rendered by hand from the workspace root; subagents on Opus.
- 2026-10-28, checkpoint 1: owner approved and continued; plan kept at ~1,800 tokens (one goal); the walk in Manual checks runs after the build, the owner doing the Freemius steps.
- 2026-10-28, build (implementer subagent on Opus): the two callbacks are `record_lapse__premium_only()` and `forget_lapse__premium_only()` on `PostQuality_License`, so the Free strip removes them with the block that registers them; `LAPSED_OPTION` stays in both builds because `uninstall()` reads it there. The notice link is a plain `<a>`, as in the SDK's own expired-license variant that carries one. Outside the Code Map: `caps-harness.php`'s exact uninstall option list needed the new option. Baseline run (the new harnesses on an export of 1c98ae2 plus the git-ignored `admin/build/`): 17 checks fail, all new or changed rows: license 6 (the detached record and `has_lapsed()`, the renewal clearing it), render matrix 3 (the "Renew Pro" link with the license attached, the two detached states), setup 3 (hook registration, notice text and escaping), and 4 on the new option itself (caps uninstall list, stored-names Row 6, `LAPSED_OPTION` on the Free tree, prefix Row 10). The criterion-2 rows, the deactivation, the other transitions, the ticket-13 lapsed off-switch and the Free build's no-hook row pass on both. With the fix: every harness green, `npm run test:unit` 174 passed.
- 2026-10-28, orchestrator, step 3: diff read; `php -l` clean; 22 harness files 0 failing (license 41, render-matrix 427, setup 170, free-build 91, stored-names 59, caps 287, prefix 22); Jest 174. Against the baseline plugin code: 17 checks fail, the detached-state, link and notice checks plus four that check the new option exists (its name, Free build constant, uninstall lists, stored literals). Matrix audit: every row has a check that ran and passed.
- 2026-10-28, checkpoint 1 after loop 1: owner approved the amended plan and continued.
- 2026-10-28, build 2 (implementer subagent on Opus): build 1's KEEP items re-applied from its kept diff; new: a third callback, `note_license_deactivation__premium_only()` on `after_license_deactivation`, sets the private static `$license_deactivated` (not a `__premium_only` name, so it survives the strip, unused on Free); `record_lapse__premium_only()` stores `cancelled`, or `downgraded` while that flag is set, and deletes the record on any other transition; the main file registers the three actions and the notice filter in the Pro block. Harnesses fire the callbacks in the SDK's order (release, then change) and start each "request" by resetting the flag through `ReflectionProperty` (guarded by `property_exists`, so the baseline runs); the clearing rows assert the record was there first, so none passes vacuously on the baseline. New rows: unlinked by Freemius (no record, an earlier one cleared), put back by the sync (each of `activated`, `upgraded`, `changed`, `extended` and the other transitions clears; a later customer deactivation shows the Free copy), in license-harness Row 3c and the render matrix; setup-harness checks the deactivation registration (build 1 checked its absence); free-build checks all four registrations absent. Mutation check: build 1's rule (every `downgraded`) fails the unlinked rows (license 2, render 1); dropping the else-delete fails the put-back rows (license 3, render 2). Baseline run (the new harnesses on an export of 1c98ae2 plus the git-ignored `admin/build/`): 21 checks fail, all new or changed rows: license 9, render matrix 5, setup 3, and one each in caps, free-build, prefix and stored-names on the new option. With the fix: `php -l` clean, 22 harness files 0 failing (license 44, render-matrix 429, setup 169, free-build 91, stored-names 59, caps 287, prefix 22); `npm run test:unit` 174 passed.
- 2026-10-28, orchestrator, end of session (owner stopped): build 2 verified by the orchestrator (`php -l` clean, 22 harness files 0 failing, Jest 174) and committed at the owner's request so the work survives the night. NOT DONE: step 4 review pass 2 (quick lens on build 2's diff against the baseline), then step 5, then the showcase walk with the owner's Freemius steps. Resume at step 4.
- 2026-10-29, orchestrator (new session, Opus 5.5; skill rendered by hand from the workspace root), step 4 pass 2: quick lens on Opus, 4 findings (Review Triage Log). Three low patches applied by the orchestrator, since the build 2 implementer belonged to the previous session: the harness echo's line break, the Free build's uninstall check reads the stripped activator, the memory sentence on the SDK's error path. Re-verified: `php -l` clean on the six touched PHP files, 22 harness files 0 failing (render-matrix 429, free-build 91), no CR bytes in the touched files, `npm run test:unit` 174 passed.

- 2026-10-29, showcase walk (agent with the owner's Freemius steps through Claude in Chrome; `docs/local-verification.md` frame): backup, Pro build of the branch from `npm run zip`, `WP_DEBUG` and the log on. Owner: sandbox license extended, activated on the site with its key (the SDK asked for the key only), then its expiry set to the day before. Agent, before the sync: registered, `is_pro()` true, license attached, no record. `_sync_cron_method()` as user 1: license detached, plan free, record `downgraded`, `has_lapsed()` true, `is_pro()` false. Settings over HTTP (session minted by WP-CLI): the lapse line with "Renew Pro" → `POSTQUALITY_PRICING_URL`, no "Get Pro", the SDK's "Your license has expired…" notice with the same link; no `debug.log` after the page load. Restore: plugin files, database, `wp-config.php`; plugin list, tables/reviews/users/log and the file checksums all "same"; walk files and the Pro zip deleted. Matrix rows walked: expired-and-detached, the notice; the other rows rest on the harnesses.

## Plan Change Log

- 2026-10-28, loop 1 (intent_gap, review findings 1 and 2): the owner chose "real expiry only". Amended in the frozen block: the Approach, a Decision line, two matrix rows (unlinked by Freemius; put back by the sync). Amended outside it: the Code Map's license and main-file lines (the three callbacks, the hook order read in the source), two tasks; tasks unticked. Known-bad state avoided: every `downgraded` read as a lapse; a record surviving a license the sync puts back. Build 1 reverted; its diff kept outside the repository for reference. KEEP from build 1: the `LAPSED_OPTION` constant outside the strip; `__premium_only` callbacks; the registrations in the Pro block, outside `is_admin()`; `update_option( …, false )`; uninstall deleting the option in both builds; the notice filter's `%s <a href="%s">%s</a>` with `esc_url` and `esc_html__`; the admin lapse line ("Renew Pro" → `POSTQUALITY_PRICING_URL`) and its comment; every harness row of build 1 (license 3b/3c, render-matrix, setup, free-build, stored-names Row 6, caps uninstall list, prefix stored literals), with the new rows added; the memory line's SDK facts, corrected for the new rule.

## Review Triage Log

Pass 1 (2026-10-28, lens `quick`, Opus): 0 high, 2 medium, 0 low, 1 false, 0 maybe-false. Route: intent_gap (findings 1 and 2, one root cause), loopback 1; finding 3 rejected.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | Every `downgraded` writes the record, but the sync also fires `downgraded` when Freemius unlinks the license from the install while the local plan is paid (a license moved to another site from the customer portal): the site then reads "your Pro license has lapsed … Renew Pro" | medium | intent_gap | Read in SDK 2.13.5: the plan/license-change test (L21225–21230) leads to L21303 (`$is_free` false, no new license → `downgraded`) with no `_deactivate_license()`. Against matrix rows "customer deactivates" and "license on another site". The frozen Approach names `downgraded` as the signal. |
| 2 | Only `after_license_activation` clears the record; a license the sync puts back (`activated`, `upgraded`, `changed`, L21296–21303) fires no activation hook, so a later in-WP deactivation shows the lapse copy | medium | intent_gap | Read: `_update_site_license()` fires no hook; `after_license_activation` only at L14015 and L21681. Same root cause: the frozen Approach fixes the record's lifecycle on two hooks. |
| 3 | The AC says only detached-state, link and notice checks fail on the baseline; four more fail (the new option's name, uninstall lists, Free constant, stored literals) | false | reject | Not a defect of the diff: those four test the new option's existence, which cannot pass on the baseline. Its fix would edit this build's plan (rule: rejected). |

Pass 2 (2026-10-29, lens `quick`, Opus, on build 2's diff against the baseline): 0 high, 0 medium, 3 low, 1 false, 0 maybe-false. Route: patch (3, applied by the orchestrator: the step-03 implementer ran in the previous session and could not be re-engaged); finding 1 rejected.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | Against the baseline, 12 checks fail beyond "the detached-state, link and notice checks" the AC names: the six record-clearing rows, the setup registration check and five on the new option's existence | false | reject | Extends pass 1's row 3 (same AC, same claim). The clearing rows start with `$before &&` so none passes vacuously; they test a record the baseline cannot hold. No bad outcome in the code; the fix would edit this build's plan. |
| 2 | `render-matrix-harness.php` L178–179: the `echo` holds a literal line break where `\n` belongs (the heredoc artifact of lessons 2026-10-24) | low | patch | Read with `cat -A`: `(bug 93)$` then `";$`; output identical, every other echo ends in `\n`. Direct correction. |
| 3 | `free-build-harness.php` L642–643: titled "uninstall() deletes a record a former Pro install left" but asserts only that `LAPSED_OPTION` is defined; moving the activator's delete into a Pro block would stay green | low | patch | Read: the stripped tree's activator is never read for that line (L531's grep covers other options only). One `str_contains` on the stripped activator, L531's pattern. |
| 4 | `memory/freemium-and-roles.md` L16: says `_deactivate_license()` detaches the license and resets the plan on the API-error path too | low | patch | Read in SDK 2.13.5 `handle_license_deactivation_result()` L21753–21765: the error branch fires the hook and returns before `_update_site_license( null )` and the plan reset. The expiry branch still sets `downgraded` (L21328–21331), so the record is set while the license stays attached; `has_lapsed()` answers true either way, so behaviour holds and the frozen Approach is met (it is a real expiry). Corrected the sentence. |

Settling finding 1 from the source: the expiry branch (L21328–21331) calls `_deactivate_license()`, whose result handler fires `after_license_deactivation` (L21784) before the branch sets `downgraded`; the unlink branch (L21303) fires no deactivation; an in-WP deactivation fires `after_license_deactivation` and no `after_license_change`. The hooks tell the three cases apart.

## Design Notes

Signal choice: the SDK keeps no public "this site's license lapsed" fact after the detach. Its private storage (`last_license_key`, an md5 matched against the account's licenses, or the stored subscriptions) would work without new state but ties the plugin to SDK internals; the `after_license_change` hook is a documented SDK action (`start.php` L456). A site that lapsed before this build carries no record and shows the Free copy: no customer exists yet.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 0 failed
- `npm run test:unit` -- expected: 174 passed (no JS touched)

**Manual checks (if no CLI):**
- Walk on the showcase with the Freemius fixture kept for this bug (ticket Notes), Pro build of this branch, in the `docs/local-verification.md` frame. Owner: extend the sandbox license, confirm the opt-in email, enter the key, then set its expiry to yesterday. Agent: the SDK sync through WP-CLI, then Settings → License and the stored notice read over HTTP (lapse copy, "Renew Pro" link, notice link), then the restore. The owner removes the fixture in the Freemius dashboard afterwards.
