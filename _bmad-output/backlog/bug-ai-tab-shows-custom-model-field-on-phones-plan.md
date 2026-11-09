---
title: 'The hidden Custom model ID field stays hidden on phones'
type: 'bugfix'
ticket: '107'
created: '2026-11-06'
status: done
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
baseline_revision: 'aff36163af77ad20be94c1f9b6c84e13605d5716'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Below 783 px, WordPress core's `.form-table td input[type="text"] { display: block; }` (specificity 0,2,2, `wp-admin/css/forms.css`) outranks the plugin's hide class `.pqm-hidden { display: none; }` (0,1,0, `admin/css/pqm-admin.css` L20), so Settings → AI shows the empty Custom model ID field (`#pqm_custom_model`) under Default Model while a listed model is selected.

**Approach:** The hide class wins wherever it is used: `.pqm-hidden { display: none !important; }`, one rule in `admin/css/pqm-admin.css`, and a harness row that keeps it so; the JS that toggles the class (`admin/js/pqm-admin.js` L250) and the markup stay as they are.

</frozen-after-approval>

## Implementation Notes

Oneshot: one CSS line and one harness row, no PHP, JS, schema, REST or template change.

- The class's users (`class-pqm-admin.php` L879 the Custom model ID input; L1089–1090 and L1104–1105 the effort and temperature descriptions and hints, `<p class="description">`; L1141 the pattern Edit link) and its toggles (`pqm-admin.js` L232–239, L250, L280). Only the input is a text input in a `.form-table` cell; core's phone rule (`forms.css` L1708–1719, WordPress 7.1.4) also lists `select`, `textarea` and `span.description`, none of which carries the class today. `!important` makes the class win over any of them, now and for a later user.
- Skipped: a scoped selector at core's specificity (`.form-table td input.pqm-hidden`) — it wins only by load order — add when a reason to override the hide class appears.
- 2026-11-06, built by the orchestrator (Opus 5.5; oneshot, no implementer subagent), commit 0e734b7: `.pqm-hidden { display: none !important; }` with a comment naming core's rule; `render-matrix-harness.php` gains "Hide class on phones (bug 107)", which fails on the old rule (456 passed, 1 failed) and passes on the fix (457). Harnesses 23 files 0 failed; Jest 181.
- Showcase check (2026-11-06, Playwright driving Edge, a WP-CLI session for user 1; backup first of the starting state, 3.9.0 Free, 13 tables, 30 rows, 1 user). Both builds of the branch at commit 0e734b7: the Free build from `bin/build-free.php`, then the Pro build from `npm run zip` installed over it. Settings → AI with the default model Claude Sonnet 5.5 (none saved):

  | Build | Window | Custom model ID field, listed model | After choosing "Custom model ID…" (no save) | `.pqm-hidden` elements shown before the choice (the field included) |
  |---|---|---|---|---|
  | Free | 400 | `display: none` | `block` | none of 1 |
  | Free | 1280 | `display: none` | `inline-block` | none of 1 |
  | Pro | 400 | `display: none` | `block` | none of 13 |
  | Pro | 1280 | `display: none` | `inline-block` | none of 13 |

  No page errors; `debug.log` only WP-CLI's own lines; restore 3× "same". The pre-fix state at 400 px is the ticket's reproduction (story 100's phone capture); it was not re-captured.
- Follow-up outside this build: the site's Features page may now take a phone crop of the AI section (ticket Notes); it needs a licensed capture sitting.
- Released 2026-11-06 in **3.12.4** (#260, release commit d3dbbc7; pre-release). Release run 42241927319 green. Zip `post-quality-manager-3.12.4.zip` sha256 `f5fd0fce…a462`: one root folder, `admin/build/` inside, `admin/css/pqm-admin.css` with the `!important` `.pqm-hidden` rule. Uploaded to Freemius as **deployment 171447**, release mode pending; the Free build check said "PHP identical: 11 files."; the -free and -pro artifacts were uploaded. Release check 2026-11-09: the zip's 218 files equal the plugin folder on main, which chore 103's showcase walk installed that day on WordPress 7.1.4; the clean WordPress 6.6 install waits for story 38's pre-production site.

## Plan Change Log

## Review Triage Log

Quick lens (Opus 5.5 subagent), pass 1, 2026-11-06, four findings:

| # | Finding | Verdict | Route | Outcome |
|---|---|---|---|---|
| 1 | The harness reads only the CSS text; nothing checks that `#pqm_custom_model` renders with `pqm-hidden` under a listed model and without it under Custom, so dropping the class from the markup would bring the field back at every width with every suite green. | medium | patch | Two rows in `render-matrix-harness.php` read the input's class: `regular-text code pqm-hidden` on a fresh site, `regular-text code` with Custom saved; removing the class from the markup fails the first (459 passed). The JS toggle (`pqm-admin.js` L250, untouched) still has no test file; left, outside a CSS fix. |
| 2 | `memory/` not updated: core's phone rule and the `.pqm-hidden` convention live only in the plan and a CSS comment (bug 116 recorded its core rule in `memory/admin-ui-rebuild.md`). | medium | patch | `memory/admin-ui-rebuild.md` gains the phone rule, why the `hidden` attribute loses to it too, and that `.pqm-hidden` is the class to use. |
| 3 | The showcase evidence does not name the build tested. | low | patch | The note names both builds of the branch at 0e734b7 (Free from `bin/build-free.php`, Pro from `npm run zip`). |
| 4 | On phones `#pqm_custom_model { margin-left: 6px; }` (L60) still applies to the full-width field, so it starts 6 px right of the select and overflows the cell by 6 px once Custom is chosen. | low | defer | Pre-existing, not caused by this fix; logged in `deferred-work.md`. |

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every file 0 failed, the new row in `render-matrix-harness.php` among them: `.pqm-hidden` declares `display: none !important`.
- `npm run test:unit` -- expected: every suite passes (no JS touched).

**Manual checks (if no CLI):**
- The showcase, Free build of the branch, Settings → AI with a listed default model (Playwright, Edge) at 400 and 1280 px: `#pqm_custom_model` computes `display: none`; after choosing "Custom model ID…" (no save) it shows at both widths. The effort and temperature hints and the pattern Edit link keep their states.
