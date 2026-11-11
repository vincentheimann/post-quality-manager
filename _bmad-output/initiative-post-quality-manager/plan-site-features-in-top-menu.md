---
title: "postquality.com's top menu leads to the Features page (story 100 follow-up)"
type: 'feature'
ticket: ''
created: '2026-11-03'
status: 'done'
baseline_revision: '8357f11fdf72d6f405dc8ab7478a506a1f921f32'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The owner did not find the Features page (`/docs/features/`, story 100) on postquality.com's home page: the only link there is a small "See every feature →" line inside the dark Pro card, about two-thirds down the page, and the top menu offers Pricing, Documentation and Support only.

**Approach (owner, 2026-11-03: "Add to the top menu"):** a "Features" item in the header's Main nav and in the phone Menu, first, before Pricing, Documentation and Support, on every page and in both launch states. The Pro-card line and the documentation's "Using it" bullet stay. The header keeps its rules: one row on desktop, no horizontal scroll at any width down to 320 px, the Menu taking over where the row no longer fits. The site's UX spec (DESIGN.md, EXPERIENCE.md) names the new item.

**Renegotiated (owner, 2026-11-03, after the review: "\"Docs\" in the menu"):** the menu reads **Features, Pricing, Docs, Support**. "Docs" is the menu label for `/docs/`; the page keeps its title "Documentation". The short label keeps the row about as wide as with three links, so the switch to the Menu stays at `{spacing.bp-medium}` (1000 px).

</frozen-after-approval>

## Implementation Notes

Oneshot, in-session on Opus 5.5 (bmad-build rendered by hand from the workspace root). The site repository's baseline is `14c0127b6c7f3bad064e09a4dddd5e8a2b11dbfd`; branches `feat/site-features-in-menu` (site) and `chore/site-features-in-menu` (plugin).

- Site `_includes/header.html`: "Features" (`/docs/features/`) first in the Main nav and in the Menu panel, and "Docs" as the `/docs/` item's label in both. Every page shares the include, so every page has it. No CSS change.
- The fit, measured in headless Edge with Playwright on both launch states built locally (the row's natural width, viewport px; the header row is capped at 1120 px, content 1024 + 48 px gutters):

  | Before launch | Segoe UI 16 / 20 px | Arial 16 / 20 | Tahoma 16 / 20 | Verdana 16 / 20 |
  |---|---|---|---|---|
  | Three links (baseline) | 925 / 1030 | 946 / 1053 | 975 / 1089 | 1055 / 1182 |
  | Four links, "Documentation" | 1015 / 1134 | 1040 / 1165 | 1073 / 1204 | 1161 / 1309 |
  | Four links, "Docs" (built) | 937 / 1037 | 963 / 1068 | 988 / 1098 | 1067 / 1191 |

  After launch the action is shorter ("Get the free plugin", 178 px against 260 px), and every variant needs less. With "Docs" the row is 12 px wider than the baseline in every font. The fonts and sizes where the baseline already wraps (Verdana; any font between 1000 px and its 20 px figure) wrap the same way.
- First build, replaced: the four full labels with `bp-medium` moved to 1060 px. The review showed it still wrapped with a Large browser font and with wide system fonts, and that it swapped a fitting launched row for the Menu at 1000–1059 px. Reverted entirely (CSS, README, token); see the change log.
- Specs: DESIGN.md's Header row and EXPERIENCE.md's Header and Menu disclosure rows name Features, Pricing, Docs, Support. EXPERIENCE.md's Fixed URLs and Page map gain `/docs/features/` (missing since story 100, a header destination now).
- Verified after the change: both states build. `/`, `/docs/features/` and `/pricing/` at 320, 360, 390, 760, 999, 1000, 1020, 1040, 1280 and 1440 px (60 checks): no horizontal scroll, nothing past the right edge, the header one row (64 px) from 360 px up (two rows at 320 px: the documented wrap below 360 px, unchanged), the Menu below 1000 px and the row from 1000 px. With the root font at 20 px (a Large browser font), `/` at 1040, 1120, 1280 and 1440 px in both states: one row, no horizontal scroll (8 checks). Desktop row and open Menu looked at in screenshots.

## Plan Change Log

- 2026-11-03, review pass 1, intent_gap (triage row 1): four full labels cannot fit the capped header row before launch with a Large browser font (1134 px needed against 1120) or a wide system font, at any breakpoint. The owner renegotiated: "Docs" as the `/docs/` label. Known-bad state avoided: a two-row pre-launch header at every desktop width for Large-font visitors, and the Menu replacing a fitting launched row at 1000–1059 px. KEEP: Features first in both lists, the spec rows, the Pro-card line and the "Using it" bullet. Reverted: the 1060 px breakpoint in `site.css`, the README and the DESIGN.md token. Not reverted and re-derived in place (oneshot, one file).

## Review Triage Log

Quick lens (Opus 5.5), pass 1, 2026-11-03, on the four-label build with `bp-medium` at 1060 px.

- Row 1: Large browser font (20 px) wraps the pre-launch header at every desktop width; wide system fonts (Tahoma, Verdana) too. **high**, intent_gap. Confirmed by measurement (table above): the four full labels need 1134 px at 20 px, beyond the 1120 px cap; the baseline needed 1030. Resolved by the owner's "Docs".
- Row 2: the 1060 px margin was measured in Segoe UI only, and the CSS comment states that figure as universal. **medium**, same root cause as row 1 (group A). Gone with the revert; the table now records four fonts.
- Row 3: after launch, the Menu replaced a fitting row at 1000–1059 px. **medium**, group A. Gone with the revert.
- Row 4: the notes listed only the header and the pillars as side effects of the moved breakpoint (also `.steps`, `.packs`, `.pack`). **low**, group A. Moot: the breakpoint is back at 1000 px.
- Row 5: EXPERIENCE.md's Page map and Fixed URLs lack `/docs/features/`, now a header destination. **low**, patch. Added both.
