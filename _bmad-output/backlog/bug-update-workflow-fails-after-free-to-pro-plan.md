---
title: 'Update workflow connects Rewrite on a site set up without a WordPress credential'
type: 'bugfix'
ticket: 113
created: '2026-11-04'
status: 'done'
baseline_revision: 'c3f4b45c6e322f590a620abed4f89995b97abc63'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/n8n-provisioning.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A Pro site whose setup map has no WordPress credential (set up on the Free build, adopted with Rewrite unwired, or provisioned without Application Passwords) gets the Rewrite node sent with a credential that has a name and no id. n8n 2.46 refuses to publish such a node (400 `Node "Update WP Post (Rewrite)" uses invalid credential`, on `/activate` and on a PUT of a published workflow), so Update workflow fails every time and "Try again in a moment" repeats it, and provisioning without Application Passwords fails at activation. When n8n holds any other `httpBasicAuth` credential, it instead binds the node to that one, silently.

**Approach:** On the Pro build, Update workflow creates the missing WordPress credential the way provisioning does (an Application Password of the administrator who clicks, then `POST /credentials`), records it in the setup map, then PUTs the Pro template with every credential linked by id. The plugin never sends n8n a credential without an id: a role the map lacks loses its node's credential entry instead (decision 2).

## Boundaries & Constraints

**Always:** owner decisions 2026-11-04: (1) create the credential at Update; provisioning's D1 path is in scope (one root cause); the plan is kept whole. (2) A Pro site without Application Passwords, at setup and at Update, publishes Rewrite without a credential: `linked_template()` removes a null-id role's entry from the node (and an emptied `credentials`), n8n publishes (probe D), the other commands work, the existing warnings stay. (3) Pro only, while the map has no `wordpress` and Application Passwords are available for the viewer, the update notice adds one sentence after the managed one: "Update workflow also creates an Application Password on your profile, so n8n can save rewrites; the plugin revokes it when the setup is removed." Order: gate → GET → Application Password → `POST /credentials` → store the map → PUT. A failed GET creates nothing; a refused `POST /credentials` revokes the new Application Password and leaves the map as it was; a refused PUT keeps the new credential in the map, so the next try reuses it (no second Application Password). Credential and Application Password code only in `__premium_only` methods or Pro-marker blocks (ADR 0014; `free-build-harness.php` L552). n8n's reply body is never repeated, keys never printed. External-system facts go to `memory/n8n-provisioning.md`; code comments name that file (lessons 2026-10-16).

**Never:** no new REST route or argument, no option or schema change, no template change (v9 stays, no version bump). No credential lookup by name in n8n (`GET /credentials` is never called). Skipped: reusing a WordPress credential wired by hand after an adoption; add it when a site reports a second credential.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Free → Pro | Pro build, map {webhook, anthropic}, Application Passwords on | GET, `POST /credentials` (httpBasicAuth `WordPress - <host>`, user + password), PUT with all 8 nodes linked by id; map gains `credentials.wordpress` + `application_password`; reply `rewrite_ready: true` | — |
| Adopted, Rewrite unwired | `wizard_created: false`, no `wordpress` | as Free → Pro | — |
| Retry after a refused PUT | map already has the new `wordpress` | GET, PUT only; no second Application Password | — |
| Credential refused | `POST /credentials` 4xx/5xx or unreachable | no PUT, map unchanged | Application Password revoked; 502 `pqm_update_failed`, `could_not('credential_wordpress')` |
| Application Password refused | WordPress returns WP_Error | zero n8n writes | 500 `pqm_update_failed`, provisioning's sentence |
| No Application Passwords (update and provisioning D1) | `wp_is_application_passwords_available_for_user()` false | nothing created; the Rewrite node carries no `credentials`; activate / PUT pass; `rewrite_ready: false` + today's message; the map stays without `wordpress` | — |
| A name-only Rewrite already bound by n8n | read workflow's Rewrite node carries another credential's id, map without `wordpress` | Update relinks it: the new credential (Application Passwords on) or none (off) | — |
| Free build | map without `wordpress` | GET, PUT as today; nothing created | — |
| n8n's rule in the harness | any body with an enabled node whose credential has no id, on PUT or `/activate` | the stub answers 400 `Node "<name>" uses invalid credential` | the old code fails this row |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `update_workflow()` L1386–1433 (GET L1399; the role loop L1415–1420, whose L1419 indexes `$carried` with a null id: a PHP 8.5 deprecation); `linked_template()` L1043 (null id = name-only today, L1054); `run()` L939–1006 (D1 L950–952, POST credential L955, map L1003); reuse `wordpress_credential__premium_only()` L1109, `revoke_application_password__premium_only()` L1137, `created_id()` L1160, `could_not()`/`label()` L1560–1590 (`credential_wordpress` label exists); `diagnose()` L487 skips the httpBasicAuth node when the map has no `wordpress` (keep).
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `update_n8n_workflow_callback()` L1810 (`rewrite_ready` L1827 = map has `wordpress`; docblock L1807 states name-only), `provision_n8n_callback()` L1636–1638 (D1 message); `with_body()` L1841.
- `post-quality-manager/admin/class-pqm-admin.php` -- `render_workflow_body()` L1519, the managed update sentence L1587; `privacy_policy_content()` L604 ("writes the new template onto it").
- `src/setup/steps.js` L375–387 -- the setup's Application Password sentence (wording source for decision 3; the dialog itself is unchanged).
- `tests/harness/lib/n8n-world.php` -- `wp_remote_request()` L87–92 answers a FIFO queue (closures get no args); Application Password stubs L51, L103–114 (`tw.app_passwords`, `app_created`, `app_revoked`); `$adopted_map` L562 (no `wordpress`), `pro_map()` L356, `linked()` L340, `body()` L337.
- `tests/harness/n8n-managed-harness.php` -- Entry 9 L186–285 (L281 "Rewrite unwired" asserts name-only: rewrite it); source pins L393–395 (first two calls GET/PUT) and L403–406 (`update_workflow()` body: 2 requests, no POST): update them to the new design.
- `tests/harness/n8n-provisioning-harness.php` -- D1 rows L111–129, revoke rows L459–468.
- `tests/harness/free-build-harness.php` -- Free update L520–546; L552 no Application Password text in the Free class.
- `docs/policies/privacy.md` L14, `post-quality-manager/readme.txt` L59 -- "keeping its credentials … changes nothing else there".
- `memory/n8n-provisioning.md` -- L10 (D1), L15 (entry 9: "a role the map lacks leaves its node name-only"), L24 (2.46.4 observation).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- after the GET, Pro block: when the map lacks `wordpress`, a new `connect_wordpress__premium_only( array $setup )` creates the Application Password and the credential, stores the map, returns it or a WP_Error; the role loop never indexes with null; `linked_template()` drops a null-id role's entry (decision 2), which `run()`'s D1 inherits; docblocks name `memory/n8n-provisioning.md` -- root cause, one path for both callers
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- docblocks only (L1807 and the provisioning one say name-only); messages unchanged -- the reply stays `{updated, ping_ok, outdated, message, rewrite_ready}`
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- decision 3's sentence in `render_workflow_body()`, inside a Pro-marker block; `privacy_policy_content()` names the credential Update may create -- consent and policy truth
- [x] `tests/harness/lib/n8n-world.php` -- the stub mirrors n8n's rule (matrix row "n8n's rule"): it consumes the queued answer and returns the 400 -- AC3, CI catches a name-only send
- [x] `tests/harness/n8n-managed-harness.php`, `n8n-provisioning-harness.php`, `free-build-harness.php`, `render-matrix-harness.php` -- every matrix row; rewritten pins; decision 3's sentence shown only in its state, never on Free -- AC1–AC3
- [x] `docs/policies/privacy.md`, `post-quality-manager/readme.txt` -- the update sentence -- truth (the postquality.com copy follows in story 101's site PR)
- [x] `docs/release-notes/unreleased.md` -- one paragraph: on Pro, Update workflow now connects Rewrite on a site set up on the Free build, with an Application Password of the administrator who clicks
- [x] `memory/n8n-provisioning.md` -- L10, L15, L24 rewritten; one findings line: n8n 2.46.6's credential check, the binding, the 409 (source + probe)
- [x] `docs/local-verification.md` "n8n 4. Start it" -- wait for `/rest/settings` 200 after `/healthz` (2.46.6 answered `/healthz` while `/rest/owner/setup` was still 404) -- the recipe ran wrong once today
- [x] `_bmad-output/backlog/bug-rollback-leaves-the-workflow-while-n8n-unpublishes.md` -- file bug 114 (P2, unrefined): rollback and Start over leave the workflow on n8n 2.46 (409 after deactivate) -- a defect found becomes its own ticket

**Acceptance Criteria:**
- Given every touched PHP file, when `php -l` runs, then no syntax error; given `tests/harness/*.php` and `npm run test:unit`, then 0 failed.
- Given a disposable n8n on the current release and the showcase, when the walk runs Free setup → Pro build → Update workflow → Review and Rewrite, then each passes (the sprint's single walk, after bug 112).

## Implementation Notes

- 2026-11-04, implementer subagent (Opus, `memory/model-split`), session run from the workspace root: the plan applied by hand, no BMad skill registered; the review lenses stay with the orchestrating session. Nothing committed.
- `class-pqm-n8n.php`: `update_workflow()` gains one Pro-marker block between the GET and the role loop (`if ( ! isset( $setup['credentials']['wordpress'] ) )` → `connect_wordpress__premium_only( $setup )`); the role loop computes `$names` only for a non-null id (no null offset). `connect_wordpress__premium_only()` reuses `wordpress_credential__premium_only()`, `created_id()`, `could_not('credential_wordpress')` and `revoke_application_password__premium_only()`; it stores the map with `update_option( …, false )` before returning it. `linked_template()` unsets a null-id role's entry and an emptied `credentials`; `run()` is unchanged (its D1 inherits it).
- Build choices the plan left open: (1) the name written beside the new credential's id is `''`, the existing rule for any map id the read workflow does not carry (entry 9's "re-pointed trigger" row); n8n links by id. (2) A refused Application Password at Update answers `{status: 500}` without `step`, like every other update error. (3) Decision 3's sentence is its own `$details` entry (its own `<p>`) right after the in-place one, read from the map inside the Pro block (`empty($map['credentials']['wordpress'])`), so the in-place assertions of story 76 hold unchanged. (4) The world's `/activate` check reads the last workflow body the run sent (the create, or a PUT). (5) `privacy.md`'s "Last updated" moved to 2026-11-04, as earlier policy changes did.
- Harness: the world gained `n8n_publish_refusal()` and `tw.app_refuses`. Swapping the baseline `class-pqm-n8n.php` back in (file restored, hash checked) fails the new rows: the provisioning D1 run is refused at activation and rolled back (4 FAIL, then a fatal), the managed Free → Pro rows send no credential (fatal at the first PUT body). Free build: one row for decision 3's absence (render and the stripped admin source) and one for "nothing created".
- Verified: `php -l` on the 3 plugin and 5 harness files; every `tests/harness/*.php` 0 failed (n8n-managed 204, n8n-provisioning 157, render-matrix 448, free-build 97, the rest unchanged); `npm run test:unit` 174 passed; LF only in every touched file; `readme.txt` 9,164 bytes (< 10 KB).
- Not done here: the acceptance walk on a disposable n8n (Free setup → Pro build → Update workflow → Review and Rewrite), the sprint's single walk after bug 112. `memory/MEMORY.md`'s index line is unchanged (the n8n-provisioning file carries the facts).
- Orchestrator, review pass 1 (2026-11-04): build choice (1) proven on the disposable n8n 2.46.6: a Free workflow published, then the Pro template PUT with Rewrite `{id, name: ''}` → 200; n8n stored and published the credential's own name (`WordPress - example.test`), resolved by id. n8n stopped by its verified PID after. Patch round by the same Opus implementer: the `SETUP_OPTION` docblock names Update workflow as the third writer and the Pro Application Password in the map's shape, in words (the literal key and the `__premium_only` method name would fail `bin/build-free.php`'s survivor check and `free-build-harness.php` L552: that docblock ships in the Free build); `n8n_publish_refusal()`'s docblock says what the stub does and points to `memory/n8n-provisioning.md` for n8n's rule. Re-run: every harness 0 failed, `npm run test:unit` 174 passed, `php -l` clean.
- **Walk, 2026-11-04 16:50–17:21 (orchestrator with the owner; the sprint's single sitting, after bugs 112 and 114 were built).** The showcase and a fresh disposable n8n **2.46.6** on 127.0.0.1 (`docs/local-verification.md` "A disposable n8n"), both builds from `fix/112-114-workflow-left-in-n8n` (3.11.1, which carries this fix, plus 112/114). Backup first (3.9.0 Free build, 30 reviews, 1 user).
  - Free build: the agent connected n8n through `POST /n8n/connection`; the owner ran the setup dialog and typed the Anthropic key → "Ping OK … the round trip works". Review on a sample post → `major_revision`, score 22, in 17 s.
  - Pro build installed over it (map {webhook, anthropic}, no Application Password) → **Update workflow: 200 `updated`, `rewrite_ready: true`; the map gained `wordpress` and `application_password`, one Application Password on the owner's profile; n8n published 31 nodes, Rewrite linked by id to the new "WordPress - <showcase host>" credential.** Ticket AC1 and AC3 met (Free → Pro → Update workflow → Review on the current n8n).
  - The ping sent right after the update reported the Free variant as outdated; a second ping a moment later: OK, up to date (bug 99 reproduced; its ticket carries the line). Pro Review 17 s (score 22), Takeaways 10 s.
  - **Not run:** Sources, SEO, Audit and Rewrite answered 403 "Sources is a Pro agent": the showcase has had no license since its restore, and none was active by 17:18. So Rewrite at run time (the WordPress credential saving a rewrite) is unproven; it is carried by the licensed Pro-command walk on v9. AC2 (an adopted map without `wordpress`) runs the same block, which keys on the missing role, not on how the map was made: harness rows; on a real n8n only the Free → Pro map.
  - Restore: plugin list, tables/reviews/users/log, plugin files and `wp-config.php` all "same"; n8n stopped by its verified PID, the n8n and walk folders deleted. The recipe's owner step gave a false success once (n8n 2.46.6 start-up); `docs/local-verification.md` "n8n 5" now checks it (records PR, 2026-11-05).
- Done 2026-11-05 (records PR): #240 merged 2026-11-04 (main 0c00cca), released in 3.11.1 (pre-release; Freemius deployment 170594, pending).
- **Rewrite at run time, proven 2026-11-06 (the licensed Pro-command walk, Opus 5.5 session):** the showcase on the licensed Pro build of main 12c7a77 (3.12.0 + bug 99), a fresh disposable n8n 2.46.6 provisioned on the Pro build (map: webhook, anthropic, wordpress; one Application Password). Rewrite on a demo post stored `rewritten` in 5 s: n8n's Update WP Post (Rewrite) node PATCHed the post through the WordPress credential (status Pending, an excerpt, one revision). Provisioning and Update workflow make that credential with the same `wordpress_credential__premium_only()` (`class-pqm-n8n.php` L967 and L1595), so the run-time half the 2026-11-04 walk left unproven now stands for the Update path's credential too. AC2's adopted case stays harness-only. The same walk found bug 117 (Sources and Audit parse failures, template side). Records in plan 101's Implementation Notes.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-04, Quick lens on Opus 5.5; diff since c3f4b45):** high 0 · medium 0 · low 4 · false 1 · maybe-false 0 · rejected by rule 1.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | Two concurrent Update clicks (two tabs) each create an Application Password and a credential; the second map write drops the first password's record (never revoked); the same write can restore a map a concurrent Start over just deleted | low | reject | Real: `update_workflow()` reads the map, `connect_wordpress__premium_only()` writes it whole, no lock. Only two tabs reach it within the same second (`pqm-admin.js` L451 `el.pqmBusy` stops a double click in one tab); the orphan is the owner's own password inside the owner's own n8n, listed on their profile as "Post Quality Manager (n8n) <date>". The fix is a lock with branches (provisioning's `claim()`), more than a direct correction. |
| 2 | AC2 (the walk) unmet; `{id, name: ''}` unproven on a real n8n | false | reject | The AC itself schedules the walk in the sprint's single sitting after bug 112 (owner, 2026-11-04); the plan stays `built` until it passes. The empty name was proven on n8n 2.46.6 during this pass (Implementation Notes): PUT 200, the stored name resolved by id. |
| 3 | The plan's task boxes unticked at `in-review` | — | reject (rule: a fix that edits the plan) | Bookkeeping; ticked by the orchestrator at this pass. |
| 4 | Start over's warning on an adopted map ("its credentials were made by hand") is partly untrue once Update created the plugin's WordPress credential; unticked, that credential stays in n8n with a revoked password | low | reject | Real (`start_over(false)` revokes the map's Application Password and sends no DELETE). It takes adopt → Pro Update → Start over unticked, and the site stops calling that workflow anyway (its webhook URL is cleared); exact copy needs a branch on the map. |
| 5 | `SETUP_OPTION` docblock names only provision() and adopt() as writers and omits the Application Password | low | patch | Direct correction; patched (in words: the docblock ships in the Free build). |
| 6 | `n8n_publish_refusal()` docblock restates n8n's rules held in memory (lessons 2026-10-16) | low | patch | Direct correction; patched to the stub's own behaviour plus the memory pointer. |

## Design Notes

n8n 2.46.6 source (2.46.4 byte-identical): `credentials-permission-checker.ts` L484–505 refuses any enabled node whose credential entry has no `id`, before the type filter, with no reachability filter. It runs on `/activate` and on a PUT of an active workflow (`publishIfActive` defaults to true), never on create or on a PUT of an unpublished one; a refused PUT leaves the old version published. A node with no `credentials` key passes, and fails only at run time ("Credentials not found"). `Update WP Post (Rewrite)` continues on error into `Format Rewrite Confirmation`, which answers "Could not save the rewrite to WordPress: …" after the Claude Rewrite call has run.

Probe on a disposable n8n **2.46.6** (2026-11-04, dummy credentials, public API): a Pro template with Rewrite name-only creates (200) and is refused at `/activate` (400): **provisioning's D1 path is broken today**. A Free workflow published, then a PUT of the Pro template with Rewrite name-only: 400 (bug 113 reproduced). **With any other `httpBasicAuth` credential on the instance, n8n binds the name-only Rewrite node to it** (stored `{id, name: "Decoy - another site"}`) on create and on PUT, and both pass: today's code can wire Rewrite to another site's WordPress login and send it to this site, and the diagnosis skips that node. Linked by id: PUT 200. No `credentials` key, or `authentication: none`: activate 200, nothing bound. Disabled: 200. A PUT on an unpublished workflow is not checked. Separate finding, 3 of 3 tries: a DELETE right after `/deactivate` answers 409 "Workflow is still being unpublished. Please try again in a few moments." and passes a second later: `roll_back()` and Start over leave the workflow (bug 114, filed by this plan).

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed
- `npm run test:unit` -- expected: green
- `php -l` on each touched PHP file -- expected: no syntax errors

**Manual checks (if no CLI):**
- The sprint walk (`docs/local-verification.md` "A disposable n8n"): Free setup, Pro build installed over it, Update workflow → updated + ping OK, Review and Rewrite land; the setup map holds `wordpress` and `application_password`; restore → "same".
