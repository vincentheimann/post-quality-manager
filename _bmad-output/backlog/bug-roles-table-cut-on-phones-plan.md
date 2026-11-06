---
title: 'On a phone, the Roles table scrolls inside its own frame'
type: 'bugfix'
ticket: '108'
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

**Problem:** On a phone, Settings → Roles shows the six-column `widefat` table "What each role can do" (`admin/class-pqm-admin.php` `render_roles__premium_only()`, L1303) wider than the screen, so the whole page scrolls sideways and Manage agents and Manage settings start off screen. Measured on the showcase (Pro build of main, 2026-11-06): at 360 px the table is 465 px wide in a 338 px panel and the page is 475 px wide. The cells' min-content alone adds up to 463 px, 120 px of it core's `.widefat` padding, so no layout of this table fits a phone.

**Approach:** The ticket's second expected layout, a table that scrolls inside its own frame: wrap the table in `<div class="pqm-roles-scroll">` with `overflow-x: auto` in `admin/css/pqm-admin.css`, the table's 8 px vertical margin moving to the frame. Then the page stops scrolling sideways, and each checkbox is reached by scrolling the frame (touch, or Tab, which scrolls the focused box into view). Harness rows in `tests/harness/render-matrix-harness.php` keep the frame in the markup and the rule in the CSS.

</frozen-after-approval>

## Implementation Notes

Oneshot: one wrapper `<div>` in the Roles markup, one CSS rule changed, harness rows; no PHP logic, JS, schema, REST or template change.

- Measured before the plan (2026-11-06, the showcase, Pro build `npm run zip` of main aff3616 installed over 3.9.0 Free, unlicensed so the rows sit in the locked fieldset; the layout is the same as on a licensed site, and `measure.js` removes `disabled` in the page only to try the clicks, nothing saved). Five roles; Editor and Author lack View reviews, so two rows show the hint.

  | Window | Page width | Table / panel | Rows with the hint |
  |---|---|---|---|
  | 360 | 475, scrolls sideways | 465 / 338 | 185 px tall, the hint in a 77 px column |
  | 400 | 475, scrolls sideways | 465 / 378 | same |
  | 782 | 782 | 760 / 760 | 62 px |
  | 1280 | 1280 | 1078 / 1078 | 37 px |

- Prototyped in the page with an injected wrapper and style (2026-11-06): the frame alone (v1) gives page 360/360 and 400/400, and 20 of 20 clicks toggle their box after the frame scrolls them into view; 782 and 1280 unchanged.
- Skipped: a sticky Role column (`position: sticky; left: 0` on the row headers). In the prototype (v2) the scrolled boxes slid under it, and 6 of 20 clicks at 360 px (3 of 20 at 400 px) hit the Role cell instead of the box. Add it when phone users lose track of which row they are in, with a scroll padding that keeps a focused box clear of it.
- Skipped: a shorter hint row on phones (the hint keeps wrapping in its 77 px column) — a wider View reviews column means more sideways scrolling; add when the row height is reported as a problem.
- The fieldset, the `roles_posted` marker before the table and the quota note under it stay where they are; the frame sits inside the locked fieldset on a free site.

## Plan Change Log

## Review Triage Log

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every file 0 failed; the render matrix's Roles rows show the table directly inside `<div class="pqm-roles-scroll">`, on a licensed and a free site, and the CSS gives the frame `overflow-x: auto`.
- `npm run test:unit` -- expected: every suite passes (no JS touched).

**Manual checks (if no CLI):**
- The showcase, Pro build of the branch, Settings → Roles (Playwright, Edge) at 360, 400, 782, 783 and 1280 px: the page is never wider than the window; at 360 and 400 px the frame scrolls and every enabled checkbox toggles where it is on screen; at 782 px and up the table fills the panel as before.
