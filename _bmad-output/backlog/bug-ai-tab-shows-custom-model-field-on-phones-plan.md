---
title: 'The hidden Custom model ID field stays hidden on phones'
type: 'bugfix'
ticket: '107'
created: '2026-11-06'
status: 'in-progress'
route: 'oneshot'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
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

## Plan Change Log

## Review Triage Log

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every file 0 failed, the new row in `render-matrix-harness.php` among them: `.pqm-hidden` declares `display: none !important`.
- `npm run test:unit` -- expected: every suite passes (no JS touched).

**Manual checks (if no CLI):**
- The showcase, Free build of the branch, Settings → AI with a listed default model (Playwright, Edge) at 400 and 1280 px: `#pqm_custom_model` computes `display: none`; after choosing "Custom model ID…" (no save) it shows at both widths. The effort and temperature hints and the pattern Edit link keep their states.
