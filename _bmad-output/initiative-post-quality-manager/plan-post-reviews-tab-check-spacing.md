---
title: 'The Post Reviews detail tabs keep a space after the check mark'
type: 'bugfix'
ticket: ''
created: '2026-10-29'
baseline_revision: '85bd6aeec1502f0f693b2ffd7faca12b37effa79'
status: 'done'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On the Post Reviews detail panel, a tab whose command has a result reads "✓Review" and "✓Takeaways", with no space: the `'✓ '` span is a flex item of the tab button (`components-tab-panel__tabs-item`, an inline-flex `Button`), and a flex item's trailing space collapses (deferred-work entry from story 86's captures, 2026-10-23).

**Approach:** give `.pqm-tab-done` a right margin the width of a space in `src/post-reviews/style.css`, so the gap no longer depends on whitespace; the markup and its text stay as they are.

</frozen-after-approval>

## Implementation Notes

- 2026-10-29, oneshot (session 8346ecaf, Opus 5.5; skill rendered by hand from the workspace root): route oneshot because the change is one CSS declaration in one file. The progress pills (`src/kit/progress-pills.js`) render the same `'✓ '` inside an inline-block pill, where the space survives: not touched.

## Plan Change Log

## Review Triage Log

Pass 1 (2026-10-29, lens `quick`, Opus): 0 high, 0 medium, 1 low, 0 false, 0 maybe-false.
- low, patch: the deferred-work entry this change fixes had no `resolved:` line (the repo resolves an entry in the branch that fixes it, e.g. bug 90's 2eb523d). Added it.
- Verified on the side: `npm run build` compiles, and the built CSS carries `margin-right:.25em` in `style-index.css` and `margin-left:.25em` in `style-index-rtl.css`; `npm run lint:js` clean; `npm run test:unit` 174 passed. Not walked in the browser: the look stays the owner's pass (`memory/local-verification.md`).

## Verification

**Commands:**
- `npm run build` -- expected: compiles
- `npm run lint:js` and `npm run test:unit` -- expected: clean, 174 passed

**Manual checks (if no CLI):**
- The showcase's Post Reviews page, a reviewed post's detail panel: the done tabs read "✓ Review", "✓ Takeaways".
