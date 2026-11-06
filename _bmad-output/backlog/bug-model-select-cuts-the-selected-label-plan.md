---
title: 'The model selects show the selected model label whole'
type: 'bugfix'
ticket: '116'
created: '2026-11-06'
status: 'done'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
baseline_revision: '12c7a7736082fb338e8053ac884a9db074e6e971'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** WordPress core caps every `select` at 25rem (400 px); since story 101 the Default Model's label "Claude Sonnet 5.5 — best quality/cost balance ($2 / $10 per MTok)" needs about 461 px and Haiku 5.5's as much, so the closed Default Model select and the six agent Model selects cut them ("… ($2 / $10 pe") on every desktop width.

**Approach:** One rule in `admin/css/pqm-admin.css` lifts the cap on the model selects to the width of their cell (`max-width: 100%`), so the select takes its longest label's width when the cell has room; nothing else on the page changes, and the phone layout (core's full-width selects below 783 px) stays as it is.

</frozen-after-approval>

## Implementation Notes

Oneshot: one CSS rule in one file, no PHP, JS, schema, REST or template change. Owner decision 2026-11-06: fix it in the licensed sitting, before plan 101's `feature-ai` capture, rather than capture the cut label or shorten the labels.

- Core's rules (WordPress 7.1.4 on the showcase, `wp-admin/css/forms.css`): desktop `.wp-core-ui select { max-width: 25rem; }` (L340–349, specificity 0,1,1); below 783 px `.form-table td select { width: 100%; display: block; max-width: none; }` (L1711–1719). The rule must outrank 0,1,1: `#pqm_default_model` (an id, as `#pqm_custom_model` at L60 already is) and `.pqm-wrap .pqm-agent-model` (0,2,0; the Settings page's wrap carries `pqm-wrap`, `admin/class-pqm-admin.php` L735). On a phone core's `width: 100%` still applies and `max-width: 100%` changes nothing.
- A select is as wide as its widest option, whichever is selected: with Custom selected, the select (466 px) and the Custom model ID field (`regular-text`, 25em) need about 829 px, so the field wraps under the select up to about 1440 px windows (at 1280 the cell is 828 px), 5 px in from the select's left edge (its 6 px `margin-left`, L60). Before the fix it sat beside the select at 1280.
- Skipped: a shared class on both selects — the id and the existing `pqm-agent-model` already name them (JS hooks in `admin/js/pqm-admin.js` L207, L224, L257 stay untouched).
- 2026-11-06, built and checked on the showcase (Opus 5.5 session; Quick lens on Opus 5.5): commit 4f0dc69, Pro build `npm run zip` of the branch installed over the licensed Pro build of main 12c7a77. Measured with Playwright driving Edge (`measure.js`, every Settings select with all tab panels shown and every override box open; box against the select's natural width with `max-width: none`):
  - main's build: the seven model selects 400 px at 790, 1024 and 1280 px (cap `400px`), natural width 466 px: label cut. At 400 px core's phone rule: 378 px (Default Model), 356 px (agent Model), cap `none`.
  - this branch: the seven model selects 466 px = their natural width at 790, 1024 and 1280 px (cap `100%`): the label reads whole. At 400 px: 378 / 356 px, unchanged, inside the screen (a phone is narrower than the label: core cuts it there before and after). Every other select (effort 215, pattern 89, schedule 83, command 81) the same width as on main at all four widths.
  - Label columns (AC2): between 783 and about 817 px windows the table cells grow to the select's 466 px and the label column gives way: at 790 px Default Model 224 px instead of 230, the agent boxes 202 instead of 230 (their Reasoning Effort, Temperature and pattern controls 28 px further left); at 817 px 230 / 229, from 830 px up all 230 as before. The frozen Approach's "nothing else on the page changes" holds from about 818 px up only; below that, showing the whole label and keeping the column are exclusive (the cell is 440–462 px there). Plan 101's capture at 790 px shows the Default Model column 6 px left of the Blog Profile's.
  - Custom selected (no save): the Custom model ID field under the select at 790, 1024 and 1280 px, never over it.
  - The capture: `feature-ai` 1440 × 1712 at 790 px (2x), label whole.
- Merged 2026-11-06 (#249, e07e5dd), 14 s after 3.12.1's release PR, so it is not in 3.12.1. Released in **3.12.2** (release commit a7c3473; pre-release). Release run 42200212224 green. Zip `post-quality-manager-3.12.2.zip` sha256 `0e09b70b…f80f`, with the rule in `admin/css/pqm-admin.css`. Uploaded to Freemius as **deployment 171328**, release mode pending; the Free build check said "PHP identical: 11 files."; the -free and -pro artifacts were uploaded. Site #15 (the `feature-ai` capture) merged the same minute, so the live Features page matches 3.12.2.

## Plan Change Log

## Review Triage Log

- 2026-11-06, Quick lens pass 1 (Opus 5.5; measured on a static copy of the AI tab with the showcase's core CSS): 4 findings — 0 high, 0 medium, 4 low, 0 false, 0 maybe-false; 2 patched, 2 rejected.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | Between 783 and about 817 px windows the label columns give way (agent boxes 230 → 202 px, their controls 28 px left; Default Model 230 → 224), against AC2 and the Approach's "nothing else changes" | low | reject | Reproduced on the showcase (790: 224 / 230 / 202; 817: 230 / 230 / 229; 830 and 1280: all 230). The cell there is 440–462 px, the label needs 466: no rule shows it whole without the column giving way, and the other path is the cut label this bug removes. A 35 px band of window widths, cosmetic; recorded in the notes and in `memory/admin-ui-rebuild.md`. |
| 2 | At 1280 px with Custom selected the Custom model ID field wraps under the select, 5 px in (its `margin-left`) | low | reject | Reproduced (under at 790, 1024, 1280). Only while Custom is selected, the field stays readable and never overlaps; a fix needs a layout change for that row (flex and gap), more than a simple correction. The notes' "narrow desktop windows" claim corrected. |
| 3 | AC3: the notes record no measurement; the need was label + 36 px, under the select's real width | low | patch | The notes now give every select's box against its natural width (`max-width: none`, 466 px) at each width seen, before and after. |
| 4 | The CSS comment restates WordPress core's cap with no version, against `memory/lessons.md` 2026-10-16; the plan named 7.1.3, the showcase runs 7.1.4 | low | patch | The fact went to `memory/admin-ui-rebuild.md` with the version and date; the comment names that file; the notes say 7.1.4. |

## Verification

**Commands:**
- `npm run zip` on the committed branch, installed on the showcase (`docs/local-verification.md`, Installing a build) -- expected: `Plugin updated successfully.`
- Playwright on Settings → AI (Edge, a WP-CLI session cookie for user 1) at 790, 1024, 1280 and 400 px: for the Default Model and each agent Model select, the computed `max-width`, the box width, and the selected label's width in the select's font plus 36 px of padding -- expected: box ≥ need at 790, 1024 and 1280 px, the other selects' widths unchanged against main's build, the model selects inside the screen at 400 px.

**Manual checks (if no CLI):**
- A look at the Settings → AI capture at 790 px (2x) and at 1280 px: the label reads whole; Custom selected at 1024 and 1280 px: the Custom model ID field sits beside the select or under it, never over it.
