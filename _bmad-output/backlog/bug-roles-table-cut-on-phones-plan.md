---
title: 'On a phone, the Roles table scrolls inside its own frame'
type: 'bugfix'
ticket: '108'
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

**Problem:** On a phone, Settings → Roles shows the six-column `widefat` table "What each role can do" (`admin/class-pqm-admin.php` `render_roles__premium_only()`, L1303) wider than the screen, so the whole page scrolls sideways and Manage agents and Manage settings start off screen. Measured on the showcase (Pro build of main, 2026-11-06): at 360 px the table is 465 px wide in a 338 px panel and the page is 475 px wide. The cells' min-content alone adds up to 463 px, 120 px of it core's `.widefat` padding, so no layout of this table fits a phone.

**Approach:** The ticket's second expected layout, a table that scrolls inside its own frame: wrap the table in `<div class="pqm-roles-scroll">` with `overflow-x: auto` in `admin/css/pqm-admin.css`, the table's 8 px vertical margin moving to the frame. Then the page stops scrolling sideways, and each checkbox is reached by scrolling the frame (touch, or Tab, which scrolls the focused box into view). Harness rows in `tests/harness/render-matrix-harness.php` keep the frame in the markup and the rule in the CSS.

</frozen-after-approval>

## Implementation Notes

Oneshot: one wrapper `<div>` in the Roles markup, one CSS rule changed, harness rows; no PHP logic, JS, schema, REST or template change.

- Measured before the plan (2026-11-06, the showcase, Pro build `npm run zip` of main aff3616 installed over 3.9.0 Free, unlicensed so the rows sit in the locked fieldset; the layout is the same as on a licensed site, and `measure.js` removes `disabled` in the page only to try the clicks, nothing saved). Five roles; Contributor and Subscriber lack View reviews, so two rows show the hint.

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
- 2026-11-06, built by the orchestrator (Opus 5.5; oneshot, no implementer subagent), commit 34f5b7b:
  - `class-pqm-admin.php`: `<div class="pqm-roles-scroll">` around the table, with a comment.
  - `pqm-admin.css`: `.pqm-roles-table { margin: 8px 0; }` becomes `.pqm-roles-scroll { margin: 8px 0; overflow-x: auto; scroll-padding-inline: 2.5rem; }`.
  - `render-matrix-harness.php`: the table sits directly in one frame with the quota note after it (licensed and free site), and the CSS row. The three rows fail on main (456 passed, 3 failed) and pass on the fix (459). Harnesses 23 files 0 failed; Jest 181.
- Found while building: Tab reached every box, but a box already partly in view stayed partly cut (Edge scrolls a focused element only when it is fully hidden). Measured at 360 px: 16 of 20 boxes fully in view. With `scroll-padding-inline` wider than a phone checkbox (core's 1.5625rem), every box partly out of view counts as hidden and is scrolled fully in: 20 of 20. A `scroll-margin-inline` on the boxes changed nothing (16 of 20).
- Showcase check of the branch (2026-11-06, same backup and recipe, Pro build `npm run zip` of 34f5b7b, unlicensed):

  | Window | Page width | Frame / table | Clicks toggled | Tab, boxes fully in the frame |
  |---|---|---|---|---|
  | 360 | 360 | 338 / 465, scrolls | 20 of 20 | 20 of 20 |
  | 400 | 400 | 378 / 465, scrolls | 20 of 20 | — |
  | 782 | 782 | 760 / 760 | 20 of 20 | — |
  | 783 | 783 | 705 / 705 | 20 of 20 | — |
  | 1280 | 1280 | 1078 / 1078 | 20 of 20 | — |

  From 782 px up every column starts and ends where it did on main. No page errors; `debug.log` only WP-CLI's own lines; restore 3× "same".
- Follow-up outside this build: the site's Features page may now take a phone crop of the Roles section (ticket Notes); it needs a licensed capture sitting. That sitting also runs `measure.js` on the licensed tab, the one AC 1 names (review row 3).
- Released 2026-11-06 in **3.12.4** (#260, release commit d3dbbc7; pre-release). Release run 42241927319 green. Zip `post-quality-manager-3.12.4.zip` sha256 `f5fd0fce…a462`: one root folder, `admin/build/` inside, the Roles table inside `<div class="pqm-roles-scroll" role="region" … tabindex="0">`. Uploaded to Freemius as **deployment 171447**, release mode pending; the Free build check said "PHP identical: 11 files."; the -free and -pro artifacts were uploaded. Release check 2026-11-09: the zip's 218 files equal the plugin folder on main, which chore 103's showcase walk installed that day on WordPress 7.1.4; the clean WordPress 6.6 install waits for story 38's pre-production site.

## Plan Change Log

## Review Triage Log

Quick lens (Opus 5.5 subagent), pass 1, 2026-11-06, three findings (verdicts: 2 medium, 1 low rejected):

| # | Finding | Verdict | Route | Outcome |
|---|---|---|---|---|
| 1 | The CSS comment restates two external facts (core's 1.5625rem phone checkbox, Edge's focus scrolling) with no version and no memory line, against `memory/lessons.md` 2026-10-16 (bug 116 was patched for the same point). | medium | patch | `memory/admin-ui-rebuild.md` gains the line, dated, with WordPress 7.1.4, Edge 156.0.4314.58 and `forms.css` L1632–1636; the comment names the file. |
| 2 | On a free site the locked fieldset disables every box, so nothing in the frame takes focus and a browser that does not focus a scroller by itself leaves Manage agents and Manage settings out of keyboard reach; Post Reviews' `.pqm-table-scroll` already solves this. | medium | patch | Reused that pattern: `role="region"`, `aria-label` "What each role can do" (the headline's string), `tabindex="0"`, and a `:focus-visible` outline. Harness rows pin them. Walked on the showcase at 360 px (the code of 08d8f53, which this log was amended into after the walk; unlicensed, backup and restore 3× "same"): one Tab from the Roles tab link focuses the frame (outline solid 2px #2271b1, page 360/360), ArrowRight scrolls it to the end (127 of 127 px), and with the boxes enabled in the page Tab goes frame, then 20 boxes, all fully in view. No page errors. |
| 3 | AC 1 names the licensed tab; every browser run was on the unlicensed Pro build with the fieldset enabled in the page. | low | reject | The licensed markup is the same table in the same frame without the fieldset around it (harness rows check both). The fieldset's `min-inline-size: auto` already lets its content overflow it, and the frame clips that, so the licensed tab cannot lay out wider. A licensed run needs the owner's activation; it joins the Roles phone-crop sitting (Implementation Notes). |

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every file 0 failed; the render matrix's Roles rows show the table directly inside `<div class="pqm-roles-scroll">`, on a licensed and a free site, and the CSS gives the frame `overflow-x: auto`.
- `npm run test:unit` -- expected: every suite passes (no JS touched).

**Manual checks (if no CLI):**
- The showcase, Pro build of the branch, Settings → Roles (Playwright, Edge) at 360, 400, 782, 783 and 1280 px: the page is never wider than the window; at 360 and 400 px the frame scrolls and every enabled checkbox toggles where it is on screen; at 782 px and up the table fills the panel as before.
