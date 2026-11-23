---
id: 146
type: bug
title: "Post Reviews and the Dashboard break at 800 px, in RTL and on translated sites"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# Post Reviews and the Dashboard break at 800 px, in RTL and on translated sites

## Description

Post Reviews and the Dashboard lose their layout in several cases the pre-production pass looked at: the rows stop short of the table's border, opening Details at about 800 px squeezes the table, long translated headers overlap, the sparkline's label and tooltip collide in RTL, and review dates keep English month names on a translated site. Both pages should keep their layout at 800 px and wider, in RTL, and in a translated admin.

## Reproduction

Local WordPress 6.6 (story 38's T1 and T2 frames and E looks, 2026-11-19, Free 4.0.5; sitting F, 2026-11-23, Pro 4.0.7).

1. Post Reviews at 1280 or 1024 px, Details open: the rows stop 165–240 px short of the table's right border (deferred-work.md L107–109).
2. Post Reviews at an 800 px viewport: opening Details squeezes the table to about 70 %, with an empty block on the right, titles wrapping per word and a horizontal scrollbar.
3. de_DE with a long-string test pack: a long one-word Score header overlaps Status.
4. LTR: the Score filter runs 8 px past its wrapper.
5. Dashboard at about 800 px: the fifth card ("Ready to ship") wraps alone onto a second row.
6. he_IL (RTL): the sparkline's endpoint label sits on its dot; the last-point tooltip runs past the card under the admin menu; the time axis is not mirrored (oldest week on the left) while the card mirrors.
7. fr_FR: Post Reviews' dates, the queue's "Last reviewed …" and the sparkline tooltip keep English month names ("November 19, 2026 11:54 am").
8. n8n Workflow page: the `/webhook/pqm-execute-…` code breaks at a hyphen.
9. Expected: no overlap, no empty column, no sideways scroll at 800 px and wider; the chart's label and tooltip stay inside the card in RTL; dates in the site's language.

## Cause Hypothesis

- Rows: the detail cell's colSpan counts the Agents run column that the container query hides, so the span opens an empty column.
- Score header: a fixed `5.75em` width with a sort button that does not wrap.
- Sparkline: the SVG text inherits `direction: rtl`; the tooltip does not wrap and is placed from the card's right edge.
- Dates: `display_date()` formats with `get_date_from_gmt()`, which does not translate month names; `wp_date()` does.

## Acceptance Criteria

1. **The layout holds**
   **Given** Post Reviews and the Dashboard at 800, 1024 and 1280 px, LTR and RTL, English and a long-string pack
   **When** the pages load and Details opens
   **Then** rows reach the table's border, nothing overlaps, no sideways scroll appears, and the cards wrap evenly
2. **The chart reads in RTL**
   **Given** an RTL admin
   **When** the sparkline shows
   **Then** its endpoint label sits beside its dot and its tooltip stays inside the card; the time axis follows the owner's decision
3. **Dates follow the site's language**
   **Given** a translated admin
   **When** a review date shows
   **Then** month and day names are in that language
4. **Tests cover the condition found and fixed**
   **Given** the JS tests and the PHP harnesses
   **When** they run
   **Then** a test that fails on the defect covers the colSpan with the hidden column and the translated date
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behaviour already holds, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- source — story 38's plan, Implementation Notes: the 2026-11-19 frames T1 and T2 and the E looks sittings A and B, the 2026-11-23 licensed sitting (P054)
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L107–109

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P3 — layout and language defects; every value stays readable. Risk low.
- Open question: whether the chart's time axis mirrors in RTL (the owner's eye).
- Handoff: story 133 also changes the sparkline (the endpoint label and the tooltip); order the two builds.
