---
title: 'Spaced radio labels, a status box as wide as its dividers, model ids on one line'
type: 'bugfix'
ticket: 130
created: '2026-11-16'
status: 'built'
baseline_revision: '9e04c26d5b6d91efc95ce2a37b98338e8376f016'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/backlog/bug-crowded-radios-wide-status-box-and-split-model-id.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Three defects in `admin/css/pqm-admin.css`, all measured in headless Edge on WordPress 6.6 with 4.0.0's Free build:
- **(c) Radio spacing:** the radio labels' right margins never apply, because core's `.form-table td fieldset label` sets `margin` with `!important`. The gap after each label (3.8 px) is narrower than the gap between a radio and its own label (4 px).
- **(d) Status box:** its 760 px cap is content-box, so the box measures 797 px beside 760 px dividers.
- **(e) Model ids:** an id like `claude-sonnet-5-5` wraps at its hyphens (2 lines at 1024 and 1280 px). At 360 px the per-agent table (415 px) is already wider than its cell (348 px), and core's phone `overflow-x: hidden` on `body` cuts it off.

**Approach:**
- **(c)** Space the labels with `padding-right`, which core leaves alone, and drop the two dead margin rules. At 782 px or less, stack each option on its own line with a plugin rule that outranks core's inline-block. Core's own phone rule (0,1,2) loses to its desktop rule (0,1,3), so no fieldset stacks today.
- **(d)** `box-sizing: border-box` on `.pqm-workflow-status`.
- **(e)** `white-space: nowrap` on the table's `code`. Wrap the table in a focusable, labelled frame that scrolls sideways (bug 108's pattern), so neither a phone nor a long custom id can cut a cell off.

**Decision (owner, 2026-11-16):** the frame is in scope. The ticket's first open question was settled by measurement: the table overflows at 360 px on 4.0.0.

</frozen-after-approval>

## Implementation Notes

Route oneshot: a handful of CSS rules, one wrapper `div` and harness rows, about 40 lines in 3 files. No schema, REST or template change.

**Measured on 4.0.0** (WordPress66, WordPress 6.6, the Free asset of v4.0.0; `scratchpad/s130/measure.js`, viewport widths 360 / 782 / 1024 / 1280):

| Defect | 360 | 782 | 1024 | 1280 |
|---|---|---|---|---|
| (c) own gap / gap to next radio, both fieldsets | 4 / 3.8 | 4 / 3.8 | 4 / 3.8 | 4 / 3.8 |
| (c) lines: Blog Profile (7 labels) / Editorial Standards (4) | 4 / 3 | 2 / 1 | 2 / 2 | 2 / 1 |
| (d) status box / dividers / content width, URL saved | 338 / 338 / 338 | 760 / 760 / 760 | 797 / 760 / 822 | 797 / 760 / 1078 |
| (e) registry id lines; document width | 2; 425 | 1; 782 | 2; 1024 | 2; 1280 |
| (e) long custom id (76 chars) lines; document width | 13; 409 | 2; 782 | 2; 1024 | 1; 1280 |

- **The ticket's Notes say core stacks fieldset labels at 782 px or less.** It does not:
  - the phone rule `.form-table fieldset label { display: block; }` (0,1,2) loses to `.form-table td fieldset label { display: inline-block; }` (0,1,3), read in WordPress66's `wp-admin/css/forms.css` L901 and L1779;
  - neither fieldset stacks on 4.0.0 (table above).
  
  Criterion 1's stacking clause therefore needs the plugin's own rule for both fieldsets.
- **The ticket's first open question, settled by measurement:** the table overflows at 360 px on 4.0.0.

**Built 2026-11-16:**
- **`admin/css/pqm-admin.css`:**
  - `.pqm-radio-label { padding-right: 16px; }` replaces the two margin rules that core's `!important` voided (the old L23 and L25). L25's `display: inline-block` was core's own value.
  - New phone query: `.form-table td fieldset .pqm-radio-label { display: block; }` (0,2,2).
  - `box-sizing: border-box` on `.pqm-workflow-status`.
  - `white-space: nowrap` on `.pqm-llm-preview code`.
  - New `.pqm-llm-scroll` frame rules. These are separate from `.pqm-roles-scroll`, whose exact rule a bug 108 harness row reads; there is no scroll padding, as the table holds nothing focusable.
- **`admin/class-pqm-admin.php` `render_agent_table()`:** the table sits in `<div class="pqm-llm-scroll" role="region" aria-label="What Each Agent Sends to Claude" tabindex="0">`. The harness's exact table tag (L1479) still matches.
- **`tests/harness/render-matrix-harness.php`:** 4 rows, covering the frame markup and the three CSS fixes. On the old stylesheet and markup they fail (477 passed, 4 failed); on the fix, 481 pass.

**Measured on the fix** (the same site and build, with the fixed stylesheet copied in and the frame patched into the installed `class-pqm-admin.php`):

| Defect | 360 | 782 | 1024 | 1280 |
|---|---|---|---|---|
| (c) own gap / gap to next radio | 4 / stacked | 4 / stacked | 4 / 19.8 | 4 / 19.8 |
| (c) lines: Blog Profile / Editorial Standards | 7 / 4 | 7 / 4 | 2 / 2 | 2 / 1 |
| (d) status box / dividers | 338 / 338 | 760 / 760 | 760 / 760 | 760 / 760 |
| (e) registry id lines; document width; frame scrolls | 1; 360; yes | 1; 782; no | 1; 1024; no | 1; 1280; no |
| (e) long custom id lines; document width; frame scrolls | 1; 360; yes | 1; 782; yes | 1; 1024; no | 1; 1280; no |

- No `debug.log` was written. WordPress66 was restored afterwards: `walk_check` reports it the same, and the core version is still 6.6. The captures and scripts stay in the session scratchpad.
- The measurements ran on WordPress 6.6 only. The core rules behind (c), `forms.css` L901 and L1779, are the same in trunk (ticket Notes, read 2026-11-11). Story 38's 4.0.2 pass carries the owner's look on the current release.

## Review Triage Log

**Pass 1 (Quick, 2026-11-16):** 4 findings, all real and low. Three patched, one deferred. No bug in the shipped behaviour and no broken rule; one criterion is half-measured.

- **low, defer:** criterion 1's "and on the current WordPress release" was not measured; only WordPress 6.6 was. The core rules are the same in trunk (ticket Notes), so the fix should hold there too. The measurement or the owner's look in story 38's 4.0.2 pass settles it (`deferred-work.md`).
- **low, patch:** the plan recorded the phone rule's specificity as 0,2,3; it is 0,2,2. That still outranks core's 0,1,3. Only the plan's figure was wrong.
- **low, patch:** inside the frame, the table's `margin-top: 8px` no longer collapsed with the description's 5 px margin. The gap grew to 13 px, a change the Boundaries forbid. The 8 px moved to `.pqm-llm-scroll`, as bug 108's frame carries its own margin, and the harness row now reads it. The 1 px bottom shadow that `overflow` clips is accepted: bug 108's Roles frame does the same.
- **low, patch:** the frame's `aria-label` added a second msgid ("What each agent sends to Claude") beside the heading's. It now reuses the heading's exact string, as bug 108's frame does, and the harness row matches it.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass, the new rows included.
- `php -l post-quality-manager/admin/class-pqm-admin.php` -- expected: clean.
- `node measure.js` on WordPress66 with the fixed stylesheet and markup -- expected:
  - (c) gap to next radio > own gap above 782 px, and one line per option at 360 and 782 px, in both fieldsets;
  - (d) box = dividers = min(760, content) at every width;
  - (e) registry id on 1 line above 782 px, document width = window width at every width, the long id scrolling inside the frame.
