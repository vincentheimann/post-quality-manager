---
title: 'The model selects show the selected model label whole'
type: 'bugfix'
ticket: '116'
created: '2026-11-06'
status: 'in-progress'
route: 'oneshot'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
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

- Core's rules (WordPress 7.1.3 on the showcase, `wp-admin/css/forms.css`): desktop `.wp-core-ui select { max-width: 25rem; }` (L340–349, specificity 0,1,1); below 783 px `.form-table td select { width: 100%; display: block; max-width: none; }` (L1711–1719). The rule must outrank 0,1,1: `#pqm_default_model` (an id, as `#pqm_custom_model` at L60 already is) and `.pqm-wrap .pqm-agent-model` (0,2,0; the Settings page's wrap carries `pqm-wrap`, `admin/class-pqm-admin.php` L735). On a phone core's `width: 100%` still applies and `max-width: 100%` changes nothing.
- A select is as wide as its widest option, whichever is selected: with Custom selected, the select (about 470 px) and the Custom model ID field (`regular-text`, 25em) may need two lines on narrow desktop windows; the field wraps under the select. Checked in Verification.
- Skipped: a shared class on both selects — the id and the existing `pqm-agent-model` already name them (JS hooks in `admin/js/pqm-admin.js` L207, L224, L257 stay untouched).

## Plan Change Log

## Review Triage Log

## Verification

**Commands:**
- `npm run zip` on the committed branch, installed on the showcase (`docs/local-verification.md`, Installing a build) -- expected: `Plugin updated successfully.`
- Playwright on Settings → AI (Edge, a WP-CLI session cookie for user 1) at 790, 1024, 1280 and 400 px: for the Default Model and each agent Model select, the computed `max-width`, the box width, and the selected label's width in the select's font plus 36 px of padding -- expected: box ≥ need at 790, 1024 and 1280 px, the other selects' widths unchanged against main's build, the model selects inside the screen at 400 px.

**Manual checks (if no CLI):**
- A look at the Settings → AI capture at 790 px (2x) and at 1280 px: the label reads whole; Custom selected at 1024 and 1280 px: the Custom model ID field sits beside the select or under it, never over it.
