---
title: 'PHP strip and dead weight'
type: 'chore'
ticket: '7'
created: '2026-10-05'
status: done
baseline_revision: '4019f808d3b1ba3e164f9a967da9e749fc8ad32e'
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

**Problem:** The legacy PHP + jQuery renders of the Dashboard and Post Reviews still exist, even though they are unrouted since stories 5.1 and 5.6. Along with them sit their helpers, their AJAX trigger, the `pqmAdmin` keys they read and roughly 600 lines of JS and 250 lines of CSS. The React pages still borrow a few legacy CSS rules, so they don't render from one source yet (CAP-3, CAP-6).

**Approach:** Each `brownfield-cleanup.md` item gets deleted, moved or re-deferred with a reason. The React pages' last legacy CSS dependencies move into `src/kit/style.css`. `pqm-admin.css` / `pqm-admin.js` become Settings + n8n Workflow assets only.

## Boundaries & Constraints

**Always:** Settings and the n8n Workflow page behave exactly as today (wizard, ping, import-URL copy, presets, model controls, locked sections). Both React pages look the same after the CSS move, including RTL. Every deleted symbol has no remaining caller: grep before deleting. Comments that point at the legacy code get updated. KISS: deletion first, no refactor beyond the move.

**Never:** No change to the gate, quota, any REST route or payload, the webhook or the n8n template (ADR 0005). `render_settings()` / `render_workflow()` markup is untouched. No ticket file is edited (bmad-build rule).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| React pages | open Dashboard, Post Reviews | same look as before; `pqm-admin.css` / `.js` not enqueued | — |
| Score badge colours | scores 20 / 55 / 85 / none | red / orange / green / grey, now from the kit | — |
| Quota note | free-plan Author on Post Reviews | note right-aligned in the top bar (left on RTL) | — |
| Settings JS | presets, model select, Reset agent, ping | unchanged; `pqmAdmin` still carries the keys they read | — |
| Workflow JS | wizard steps, Save & test, Copy import URL | unchanged, fade animation intact | — |
| Old deep link | `admin.php?page=pqm-posts&filter=needs_review` | unfiltered list, no error | — |
| Removed AJAX | POST `admin-ajax.php?action=pqm_trigger_command` | WP's default `0` / 400, nothing runs | — |

**Decision (owner, 2026-10-05): parity gaps.** The legacy list's Yoast SEO column (SEO + readability badges while Yoast is active; Current Value in user-paths B2, checked by backlog story 6 item 6) and its "— Pending" marker are not in the React list. 5.7 strips them anyway and does not port them. The port becomes a new epic-ui-rebuild story, "Post Reviews: Yoast column and Pending marker", created through `bmad-ticket` after 5.7 and ordered before the 5.9 manual pass.

**Decision (owner, 2026-10-05): plan size.** The plan is kept whole at about 2,500 tokens. It has one goal, and the exact line ranges make the deletions safe.

</frozen-after-approval>

## Code Map

Paths under `post-quality-manager/` unless they start with `src/` or `tests/`.

- `admin/class-pqm-admin.php` -- delete `render_dashboard()` :409-510, `render_posts()` :576-829 (contains the GET `pqm_action=trigger` path, the `review_status` read and the PHP quota span), helpers `render_yoast_badges` / `yoast_badge` / `score_class` / `format_status` / `format_date` / `sortable_header` :1553-1633 (only legacy callers) and `ajax_trigger_command()` :1516-1551 (only caller: dead `pqm-admin.js` `executeCommand`). `enqueue_assets()` :121-191: load `pqm-admin.css` only off the React hooks, and drop the localized keys that only dead JS reads: `restUrl`, `nonce`, `gatedCommands`, `hiddenCommands`, `quota`, `i18n.lockedTitle`, `i18n.quotaNote`. Keep `ajaxUrl`, `pageNonce`, `defaultModel`, `blogProfiles`, `agentDefaults`, `voicePresets`, `modelRegistry` and the other `i18n` keys. Update the stale comments :33-34, :54-55, :135-138 and the file header. Keep `render_dashboard_app()` (nudge dismiss), `render_app_shell()`, `agent_labels()`, `render_import_url()`.
- `post-quality-manager.php:55` -- remove the `wp_ajax_pqm_trigger_command` hook. Keep :56-58.
- `includes/class-pqm-db.php:494-~520` -- delete `get_dashboard_stats()` (only caller: `render_dashboard`). `get_commands_for_posts()` stays (REST uses it).
- `admin/js/pqm-admin.js` -- delete `DIMS` / `CMD_ORDER` :10-11, the click delegate + `indexOf('Processing')` resume :24-58, and everything from :373 to the end (`loadDetail` … `esc`, including the `apply_` branch, `pollForResult`, `quotaNote*`, `actionBtn`, `statusLabel`). Keep the init calls :18-22 and `initSettingsPage`, `initModelControls` (incl. `effectiveDefault`), `initTestConnection` / `showTestResult`, `initImportUrlCopy`, `initWizard`. Check that the file closes cleanly.
- `admin/css/pqm-admin.css` -- move to `src/kit/style.css`: `.pqm-wrap` max-width, `.pqm-score-badge` + `-green/-orange/-red/-none` :72-80, `.pqm-quota-note` base :86-89, `.pqm-posts-table .pqm-col-*` widths :95-96 and :104-107, `.pqm-cell-score` / `.pqm-cell-status` :111/:114. Then delete every legacy-only or unused block: stat cards, CTA, status bar, filters, Yoast, pills, detail rows / tabs / cards / breakdown / edits / content list / rewrite preview / empty / detail actions / history / toast / spinner / loading / data-* / modal, the responsive block, `.pqm-section`, `.wp-list-table th.column-*`, `.pqm-help-icon`, `.pqm-post-state`. Keep the Settings / Workflow blocks :296-395. **Keep `@keyframes pqmFade` :169-171**: the wizard uses it, so move it next to the wizard rules.
- `src/kit/style.css` -- receives the moved rules. Keep the `.pqm-table-topbar .pqm-quota-note` override :86-92 consistent (the base rule now gets RTL flipping). Update the "legacy pqm-admin.css still loads" comments :87-89, :240-241.
- `src/dashboard/style.css:3-5` -- update the same comment.
- `src/post-reviews-table.js:69-71, :92` and `src/post-reviews/filter-bar.js:63` -- drop the `?filter=needs_review` pass-through (only the deleted PHP card emitted it). The REST `filter` arg stays (contract).
- `src/kit/score-badge.js:4,26` -- verify that the class names it uses match the moved rules. No change expected.
- `tests/harness/quota-harness.php:239-241` -- retire the source check on `ajax_trigger_command` (it would pass vacuously once the function is gone).
- Untouched: `class-pqm-rest-api.php`, `templates/n8n-workflow.json`, the other harnesses (none pin legacy renders; `render-matrix-harness` covers Settings only).

## Tasks & Acceptance

**Execution:**
- [x] `admin/class-pqm-admin.php`, `post-quality-manager.php`, `includes/class-pqm-db.php` -- delete the legacy renders, helpers, AJAX trigger + hook, `get_dashboard_stats()`; trim the bridge and gate the CSS enqueue -- PHP ships only shells
- [x] `src/kit/style.css`, `src/dashboard/style.css`, `admin/css/pqm-admin.css` -- move the React-used rules into the kit, delete the dead rules, keep `pqmFade` -- one style source per page
- [x] `admin/js/pqm-admin.js` -- delete the legacy page code -- Settings / Workflow only
- [x] `src/post-reviews-table.js`, `src/post-reviews/filter-bar.js` -- drop the `filter` pass-through
- [x] `tests/harness/quota-harness.php` -- retire the vacuous check
- [x] `memory/admin-ui-rebuild.md`, `memory/MEMORY.md` -- record the strip and the CSS ownership
- [x] Record the brownfield disposition table in Implementation Notes (every item: deleted / moved / re-deferred + reason)

**Acceptance Criteria:**
- Given the branch, when grepping `post-quality-manager/` and `src/`, then `render_posts`, `render_dashboard(`, `get_dashboard_stats`, `pqm_trigger_command`, `review_status`, `apply_`, `.pqm-status-bar`, `.pqm-content-list` and `.pqm-rewrite-preview` have no hits.
- Given the React pages, when loaded, then the network panel shows no `pqm-admin.css` / `pqm-admin.js`, and the matrix rows hold.
- Given `npm run build`, `php -l` and the harness sweep, then all green.

## Implementation Notes

**Brownfield disposition** (`spec-ui-rebuild/brownfield-cleanup.md`, every item):

| # | Item | Disposition | Reason / where it went |
|---|------|-------------|------------------------|
| 1 | Legacy GET trigger path in `render_posts()` | deleted | Went with `render_posts()`; React triggers call `POST /trigger-review` only. |
| 2 | JS `apply_` command branch | deleted | Went with `triggerCommand` / `executeCommand` and the rest of the legacy JS (`pqm-admin.js` 910 → 335 lines). |
| 3 | Unused CSS `.pqm-status-bar`, `.pqm-content-list`, `.pqm-rewrite-preview` | deleted | Along with every other legacy-only block (`pqm-admin.css` 446 → 108 lines). |
| 4 | `review_status` filter param | deleted | Only `render_posts()` read it; the React chips use the REST `status` arg (5.3). |
| 5 | JS `esc()` helper | deleted | React escapes; no remaining caller. |
| 6 | Quota note, PHP span + JS `quotaNote()` / `quotaNoteText()` | deleted (both) | Kit `QuotaNote` (with the only-next-to-a-rendered-button guard) is the single source; its base CSS rule moved into the kit. `pqmAdmin.quota` / `i18n.quotaNote` dropped. |
| 7 | Hidden-command rule, `$can_review` + `pqmAdmin.hiddenCommands` in `actionBtn()` | deleted (both) | React reads `viewer.hidden_commands` via `canRender()`; `gatedCommands` / `hiddenCommands` dropped from the bridge. |
| 8 | Effective-model resolution (3 copies) | re-deferred | `pqm_effective_model()`, the `defaultModel` ternary and JS `effectiveDefault()` all serve the Settings page, which ADR 0005 keeps out of this epic; the JS copy resolves unsaved form state, so it cannot call PHP. |
| 9 | Status/date formatting drift (`format_status()` / `format_date()` vs JS `statusLabel` + date splitting) | deleted | All four went with the legacy renders; kit `statusLabel()` and the REST display dates (`get_date_from_gmt()`, 5.4) are the one source. |
| 10 | Upgrade copy in 4 wordings | 1 deleted, 3 re-deferred | `i18n.lockedTitle` deleted (only dead JS read it). The two Settings strings (`class-pqm-admin.php` per-agent overrides / scheduled reviews) describe different locked features — Settings is out of scope; the REST refusal string belongs to backlog story 11 (REST copy i18n, retro A5). |
| 11 | `class-pqm-admin.php` sheds Dashboard + Post Reviews rendering and their JS bridge | done | 1,634 → 1,150 lines; PHP ships only the React shells; `pqmAdmin` keeps only the Settings / Workflow keys; `pqm-admin.css` / `.js` no longer enqueue on the React hooks. `render_settings()` / `render_workflow()` untouched. |
| 12 | Three ad-hoc polling loops → one hook | done | `use-polling.js` (5.4) replaced them; the last legacy loop (`pollForResult` + the `indexOf('Processing')` resume) is deleted here. |
| 13 | Inline styles in JS-built markup → component styling | done | All string-built JS markup is gone; backlog story 5 was already dropped as subsumed. |
| — | Not this epic: retro A5, A6, A8, A11 | untouched | REST/sanitizer-side, tracked elsewhere. Backlog bug 18 (AJAX/GET success on n8n non-2xx) is closed by deletion — both wrappers are gone; the owner closes the ticket. |

**Deviations from the Code Map (all to honour the Always constraints):**
- `.pqm-wrap { max-width: 1200px }` is **copied**, not moved: `render_settings()` and `render_workflow()` also use `.pqm-wrap`, so removing it from `pqm-admin.css` would have changed those pages. It now sits in both files.
- `.pqm-posts-table .pqm-col-actions .button` (old :107) was dropped, not moved: it targets buttons inside the Actions `<th>`, and the React header holds none.
- The `.pqm-table-topbar .pqm-quota-note` override was removed: the moved base rule (`margin-left: auto`, same colour and size) now gets RTL-flipped by wp-scripts, so the override and its "legacy CSS is not RTL-processed" workaround became redundant. Built output checked: LTR `margin-left:auto`, RTL `margin-right:auto`.
- `class-pqm-rest-api.php`: one docblock comment (`dashboard_callback()`) pointed at `render_dashboard()`; reworded (comment only, no code change) so the AC grep holds. `src/kit/score-badge.js`: only its header comment changed (it pointed at `PQM_Admin::score_class()` and `pqm-admin.css`); class names already match the moved rules.
- Old `?filter=needs_review` deep links: the param is ignored, so the list loads unfiltered; `writeQueryToUrl()` strips `filter` alongside the managed URL keys, so it leaves the address bar on the first URL write (initial load or any chip / sort / page change).

**AC grep residue (expected):** `render_posts_app` (the kept React shell, substring of `render_posts`), `apply_filters(` (WP core, 2 sites) and the `apply_*` mention in `class-pqm-features.php`'s gate docblock (gate untouched by rule). No other hits.

**Verification run (2026-10-05):** `npm run build` exit 0; `php -l` clean on `class-pqm-admin.php`, `post-quality-manager.php`, `class-pqm-db.php`, `class-pqm-rest-api.php`, `quota-harness.php`; `node --check pqm-admin.js` clean; harness sweep all green (caps 173, cron-gate 24, license 21, quota 40, render-matrix 38, rest-posts 121, rest-reviews 24, sanitize-matrix 86). Manual site checks pending.

**Orchestrator re-check (step 03, against the diff):** I re-ran the build, `php -l`, `node --check`, the harness sweep and the AC grep; all match the numbers above. Three scripted cross-checks also came back clean:
- No call in `pqm-admin.js` targets a deleted function, and the file reads exactly the eight bridge keys kept.
- No class or id that surviving Settings/Workflow PHP or JS uses was dropped from `pqm-admin.css`, and `pqmFade` is defined and used.
- Exactly one React class lost legacy styling: `.pqm-post-row`. Its only legacy rule targeted `.pqm-detail-toggle`, which React never renders, so nothing visual is lost.

Built RTL CSS: `.pqm-quota-note{…margin-right:auto}`.

**Matrix test audit:** every matrix row is rendering or enqueue behaviour on a real WP page. The repo has no JS test path (deferred in 5.5) and no harness boots `enqueue_assets()`, so no row has an automated test. Static checks cover parts of them: the bridge-key and class cross-checks above (rows 4–5), and the AC grep plus the removed hook line (row 7). Every row is otherwise covered by the pending manual site pass, as in 5.3–5.6.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, 2026-10-05):** 5 findings: 0 high, 1 medium, 3 low, 1 false. 4 patched by the implementer, 1 rejected, none deferred. Verification re-ran after the patches: build exit 0, `php -l` clean, 8/8 harnesses with 0 FAIL lines and exit 0, AC grep and stale-comment grep with no hits.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `class-pqm-rest-api.php` :100, :407, :484: comments still name the AJAX handler / GET fallback as callers | low | patch | Verified: all three grep hits remained after the strip, against the plan's "comments pointing at the legacy code get updated". Comments now name only `POST /trigger-review`, and the grep is clean. |
| 2 | `memory/freemium-and-roles.md` :14, :17, :18 describe the deleted `pqmAdmin` gate/quota keys, the PHP Run Review button and the AJAX/GET gate paths | medium | patch | Verified at those lines. AGENTS.md DoD: durable facts are updated in the same change. Clauses rewritten to the REST-only trigger path plus the `viewer` block / kit `QuotaNote`. The agent-local mirror carried none of them. |
| 3 | RTL: the detail panel quota note moves to the far end of `.pqm-panel-actions` | false | reject | It does move, but that's a correction. The legacy rule (never RTL-flipped) gave RTL `margin-left:auto`, which kept the note next to the button, while LTR pushed it to the far end. RTL didn't mirror LTR. The kit rule is now RTL-flipped, so RTL mirrors LTR, the same result the 5.5 top-bar override was built to produce. LTR is unchanged. |
| 4 | Old `?filter=needs_review` stays in the URL forever (the note claimed it clears on the first chip/sort) | low | patch | Verified: `writeQueryToUrl()` stripped only `QUERY_KEYS`. Direct correction: `'filter'` added to `removeQueryArgs`. The fetch effect writes the URL on first load, so the param drops at once. Implementation Notes bullet corrected. |
| 5 | README :31 says triggers go via AJAX; README :39 and user-paths B2 still promise the Yoast column | low | patch | Verified at those lines. README :31 now names `POST /trigger-review`. README :39 and B2 state the column is removed pending the follow-up epic-ui-rebuild story, per the owner decision; the Yoast payload enrichment claim stays. |

## Design Notes

Re-deferred with reasons, to be copied into the disposition table:
- **Effective model** (3 copies). `pqm_effective_model()`, the `defaultModel` ternary and JS `effectiveDefault()` all serve the Settings page, which ADR 0005 keeps out of this epic. The JS copy resolves unsaved form state, so it can't call PHP.
- **Upgrade copy** (3 wordings left once `lockedTitle` dies). The two Settings strings describe different locked features, which is out of scope here. The REST error belongs to backlog story 11 (REST copy i18n, retro A5).

Closed by deletion: backlog bug 18 (AJAX/GET success on n8n non-2xx). Both wrappers go, and React already reads `success:false`. The owner closes the ticket. Backlog story 5 is already dropped as subsumed.

## Verification

**Commands:**
- `npm run build` -- expected: exit 0
- `php -l` on the 3 touched PHP files and `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green
- the AC grep -- expected: no hits

**Manual checks:**
- Real site: walk the matrix, then the 5.6 Dashboard checklist items 2, 4, 9, 11 and the 5.5 single-run AC as a regression pass for the CSS move. Check the Settings and Workflow pages (wizard on a disconnected site) with the console open.

**Result (2026-10-05):** the owner walked the 14-step site check on the real site: React pages without the legacy assets, score badges and column widths, quota note in LTR and RTL, single run, Dashboard tiles, queue, sparkline and REST-down notice, the old `?filter` link, Settings as Admin and as Editor, the Workflow wizard disconnected and connected, and the removed AJAX action. All green, no comments. Story verify is met end to end.
