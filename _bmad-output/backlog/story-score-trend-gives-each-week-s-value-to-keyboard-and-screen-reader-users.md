---
id: 133
type: story
title: "The Dashboard's score trend gives each week's value to keyboard and screen-reader users"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
estimate: ""
---

# The Dashboard's score trend gives each week's value to keyboard and screen-reader users

## Description

The Dashboard's "Average score — last 8 weeks" chart gives each week's date and average only on mouse hover. A keyboard user cannot reach the chart, and a screen reader hears only the summary "from X to Y". After this story, both can read each week's value, and the mouse behaviour stays as it is. Story 5.6 specified hover only, so this extends the spec rather than fixing a regression.

## Acceptance Criteria

1. **The keyboard reaches each week**
   **Given** the Dashboard with analyzed posts spanning two weeks or more
   **When** a keyboard user tabs to the chart and moves through it
   **Then** the chart takes one tab stop with a visible focus ring, and each week in turn shows the same "Week ending {date} — average {n}" text as hover, from the first week to the last
   **And** leaving the chart by Tab or Escape clears the text, and the rest of the Dashboard's tab order is unchanged
2. **A screen reader gets each week's value**
   **Given** the same chart and a screen reader
   **When** the reader reaches the chart
   **Then** it announces the summary trend as today and can read each week's date and average, in order
3. **Mouse and short history unchanged**
   **Given** the chart with the mouse, or fewer than two weeks of history
   **When** it is used as today
   **Then** hover shows and clears the same text as on 4.0.2, and with fewer than two weeks the "No score history yet…" note shows with no focusable chart
4. **Right to left and translations**
   **Given** a right-to-left or translated site
   **When** a keyboard user moves through the weeks
   **Then** the text sits over the matching point as hover's does, and every new string is translatable with a translator note
5. **Tests cover the new paths**
   **Given** the JS test suite
   **When** it runs
   **Then** tests cover moving through the weeks from the keyboard, the text read to a screen reader, and the short-history case

## Boundaries

- Must not change: the chart's data, scale, colours, endpoint label and "from X to Y" summary; the hover tooltip's text and position (RTL included); the tiles, the Fix next queue and the nudges.
- No inline styles beyond the tooltip's existing position (story 5.6's RTL note): classes in the Dashboard stylesheet.

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md, pass list row P281 (pass to spec on the owner's word, 2026-11-16; frame F2-3's fail)
- spec — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md (story 5.6: the chart, its aria-label and hover tooltip, its RTL fix)
- checklist — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-manual-pass-epic-done-when-checklist.md, § 4 "The sparkline" ("hovering or focusing it gives the week and value")
- copy rules — docs/brand.md (§ Copy Rules)

## Notes

- Decision (owner, 2026-11-16): P281 passes to spec; the keyboard gap is a low-priority accessibility ticket filed with the walk's findings. It does not block story 38.
- Fact (source read 2026-11-17, main 8f4af64): `src/dashboard/sparkline.js` L69–84, the SVG has `role="img"`, the summary aria-label, and only `onMouseMove`/`onMouseLeave`; no `tabIndex`, focus or key handler. The tooltip at L139–156 is `aria-hidden="true"`.
- Open question: the pattern. Two common ones: (a) the chart takes focus and the arrow keys move between weeks, the text announced through a live region; (b) a visually hidden list of the weekly values next to the chart for screen readers, with (a) for sighted keyboard users. The builder proposes one in the plan; the owner picks.
- Assumption: no design pass. The mockup's hover tooltip is the visual, reused for keyboard focus.
- Assumption: risk low. One Dashboard component, shows at once, reverts cleanly.
