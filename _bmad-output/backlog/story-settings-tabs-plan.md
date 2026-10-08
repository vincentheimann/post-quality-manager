---
title: 'The Settings page is split into tabs'
type: 'feature'
ticket: '27'
created: '2026-10-08'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'b03a3aa1e91da4bf628dd32a6a56fab15c5701c9'
context: ['{project-root}/docs/brand.md', '{project-root}/memory/freemium-and-roles.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Settings is one long page of seven sections, so finding one concern means scrolling past all the others.

**Approach:** Group the sections under four native WordPress `nav-tab` tabs: Connection (License, n8n Connection), AI (AI Model, AI Prompts & Context, Agent-Specific Overrides), Automation (Automated Reviews), and Usage & Limits (Monthly Quota). JavaScript switches the tabs over the single existing form, so every save still posts every section and the sanitizer is untouched. With JavaScript off, every section shows and the tab bar is hidden. The active tab lives in the URL hash (`#tab-<slug>`), and that hash survives a save.

## Boundaries & Constraints

**Always:** The role gates (`pqm_manage_settings` / `pqm_manage_agents`) and the plan locks stay exactly as they are. A tab renders only if at least one of its sections renders. A plan-locked section still shows locked inside its tab. Tabs get `role="tablist"`/`tab`/`tabpanel`, `aria-selected`, `aria-controls`, a roving `tabindex`, and Left/Right/Home/End keys with automatic activation. The existing anchors `#pqm-cron-settings` and `#pqm-quota-settings` keep working.

**Never:** No change to `sanitize_settings()`, to the option shape or to any REST route. No per-tab forms or per-tab saves. No React and no `@wordpress/components` on this page (ADR 0005, story decision). No new dependency.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Admin, pro | both caps | 4 tabs; each of the 7 headings sits in exactly one panel, in the mapping above | — |
| Editor | `pqm_manage_agents` only | Tabs AI (Overrides only) + Automation; Connection and Usage & Limits absent | — |
| Settings-only | `pqm_manage_settings` only | Tabs Connection, AI (Model + Prompts), Usage & Limits; Automation absent | — |
| Free site | any role above | same tabs; Overrides and Cron locked fieldsets inside their panels; ticket-13 off-switch unchanged | — |
| Deep link | `#pqm-cron-settings`, `#pqm-quota-settings`, `#pqm-context-settings` | the panel holding the target opens and the target scrolls into view; the URL becomes its `#tab-…` | unknown hash or a hidden tab → first visible tab |
| Save | any tab active | the page returns on the same tab with the success notice visible | — |
| Invalid field in a hidden tab | e.g. a bad URL in Connection while AI is active | its tab opens so the browser can focus the field and show the message | — |
| Save from one tab | full form POST (as rendered from stored values) with one field changed in tab X | every key outside tab X `===` stored | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php:538-1010` `render_settings()` -- today it renders the sections in tab order inside two cap blocks (`$can_manage_settings` holds License … Prompts, then `$can_manage_agents` holds Overrides + Cron, then `$can_manage_settings` again holds Quota). Re-cut the cap `if`s so that each panel is `<div class="pqm-tab-panel" id="pqm-panel-<slug>">` wrapping its sections, and the AI panel holds both cap blocks. Compute the tab list from the two caps before the markup. Add `id="pqm-context-settings"` to the AI Prompts & Context `h2`. Notices and `submit_button()` stay outside the panels. Do not touch the section contents or the locked fieldsets.
- `post-quality-manager/admin/class-pqm-admin.php:1098` -- the Workflow page's "Settings" link (model changes) → `…page=pqm-settings#tab-ai`.
- `post-quality-manager/admin/js/pqm-admin.js` -- add `initSettingsTabs()` to BOOT; it guards on `.pqm-tabs` like the other blocks. `options.php` redirects back to the referer through `add_query_arg('settings-updated', …)`, which keeps the fragment, so on every activation the JS also writes the tab hash into the form's `_wp_http_referer` input.
- `post-quality-manager/admin/css/pqm-admin.css` -- `.no-js .pqm-tabs { display: none; }`. Hide inactive panels only under `.js`. Drop the top gap on each panel's first `h2`.
- `src/setup/steps.js:568` -- the "Choose a Blog Profile" link → `#pqm-context-settings`. `src/dashboard.js:288` and `steps.js:580` (`#pqm-cron-settings`) stay as they are.
- `README.md:51` -- "Settings → AI Prompts & Context" → "the **AI** tab of Settings, under **AI Prompts & Context**".
- `tests/harness/render-matrix-harness.php` -- reuse `render_for()`, `$admin_caps`, `$editor_caps` and the section strings.
- `tests/harness/sanitize-matrix-harness.php` -- reuse `setup()`, `$stored_option` and `$ADMIN_ONLY` / `$CRON_KEYS`.
- `memory/freemium-and-roles.md` "Settings sections" bullet -- add the tab layout.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- tab nav (`nav.nav-tab-wrapper.pqm-tabs`, links `href="#tab-<slug>"`), panels, `pqm-context-settings` id, Workflow-page link.
- [x] `post-quality-manager/admin/js/pqm-admin.js` -- `initSettingsTabs()`: ARIA, click, keys, hash ↔ panel (section anchors resolve through `panel.contains()`), `history.replaceState`, the `_wp_http_referer` update, and a capture-phase `invalid` listener on the form that opens the field's panel.
- [x] `post-quality-manager/admin/css/pqm-admin.css` -- the rules from the Code Map.
- [x] `src/setup/steps.js`, `README.md` -- links and copy.
- [x] `tests/harness/render-matrix-harness.php` -- matrix rows 1–4: the tab list per role and plan, each heading in its expected panel, no empty panel, locked fieldsets inside the Overrides and Cron panels.
- [x] `tests/harness/sanitize-matrix-harness.php` -- matrix row 8: a `form_post()` helper builds the POST a pro Admin's form sends from stored values. For each tab, change one of its fields and assert that every other tab's keys are `===` stored. Repeat for an Editor's two tabs.
- [x] `memory/freemium-and-roles.md` -- the tab fact.

**Acceptance Criteria:**
- Given a free or pro site and any role, when the user clicks a tab or uses the arrow keys, then exactly one panel shows, and the tab's `aria-selected` and the URL hash follow it.
- Given JavaScript is off, when Settings loads, then every section the role may see shows and no tab bar renders.

## Implementation Notes

- Built by one implementation subagent. Files: `class-pqm-admin.php`, `pqm-admin.js`, `pqm-admin.css`, `src/setup/steps.js`, `README.md`, both matrix harnesses and `memory/freemium-and-roles.md`.
- Matrix rows Deep link, Save and Invalid field run only in a browser. The repo has no JS harness, so an ad-hoc jsdom smoke run in the session scratchpad (17/17) covered them, and the owner's manual pass on Local confirms them.
- Follow-up at the owner's request (2026-10-08), after a best-practice check:
  - **Flash fix:** `render_settings()` prints an inline guard under the tab bar (`wp_print_inline_script_tag`). It adds `pqm-tabs-pending` to `<html>` when the hash names another tab than the first, a CSS rule hides every panel while that class is set, and `initSettingsTabs()` removes it. The render harness checks the guard per role.
  - **Return to the tab after a save:** confirmed against WP 7.1.2 core. `options.php` redirects to `add_query_arg('settings-updated', 'true', wp_get_referer())`, and `add_query_arg()` keeps the fragment.
  - **Admin-bar offset:** confirmed against WP 7.1.2 core. `admin-bar.css` sets `html { scroll-padding-top: var(--wp-admin--admin-bar--height) }`, so `scrollIntoView()` lands below the bar. No code.
  - **JS tests:** first Jest + jsdom suite, `tests/js/settings-tabs.test.js` (13 tests), run by `npm run test:unit` through `@wordpress/scripts` with no new dependency. It covers the matrix rows Deep link and Invalid field, plus the keys, ARIA, RTL and the guard. Linted with `src/`, run in CI's node job, and added to the DoD in `AGENTS.md` and `_bmad/custom/bmad-build.toml`. A deliberate mutation of three behaviours made three tests fail.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick):** high 0 · medium 0 · low 3 · false 0 · maybe-false 1

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | After a save from a later tab, or a deep link, the first panel may paint before the footer JS switches tabs | maybe-false (low if true) | rejected | Only a paint between parsing the form and the footer script running would show it, and that depends on the browser. If it happens, it is a cosmetic flash. To settle: watch for it during the manual saves on Local. |
| 2 | ArrowLeft/ArrowRight are not mirrored on RTL sites | low | patch | WP sets `dir="rtl"` on `<html>`, and the nav-tabs then run right-to-left. Swap the directions under RTL. |
| 3 | The key handler hijacks Alt/Ctrl/Meta/Shift + arrows (Alt+Left = browser Back) | low | patch | No modifier check before `preventDefault()`. Return early on any modifier. |
| 4 | The `pqm-admin.js` docblock omits the tabs | low | patch | The header list of what the file does is now out of date. |

## Design Notes

- The hashes are `#tab-<slug>`, never an element id, so the browser does not jump past the save notice. Section ids are resolved separately and are the only case that scrolls.
- On load the server marks the first rendered panel active. The JS switches only when the hash names another panel. WordPress swaps `no-js` for `js` on `<body>` before the page paints, so when no tab hash is set the full page never flashes before the tabs apply.
- Skipped: a `hashchange` listener, because no same-page link changes the hash. Add it when one does.

## Verification

**Commands:**
- `php -l` on `class-pqm-admin.php` and `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean.
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green.
- `npm run lint:js && npm run build` -- expected: clean.

**Manual checks (owner, Local):**
- As Admin, then via View as Editor, on a free and a pro site: the tabs, the arrow keys, a save on each tab returning to it, the Dashboard nudge opening Automation, the setup dialog's Blog Profile link opening AI, a bad webhook URL saved from the AI tab opening Connection, and JavaScript off showing the whole page.
