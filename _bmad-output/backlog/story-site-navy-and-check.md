---
id: 86
type: story
title: "postquality.com is built in Navy and check: the chosen design, lean copy, real screenshots"
parent: none
covers: [UV7, O9]
after: [85]
assignee: ""
refined: true
hitl: true
risk: medium
---

# postquality.com is built in Navy and check: the chosen design, lean copy, real screenshots

## Description

Story 57's UX pass picked direction 2, Navy and check, and wrote the spines (`_bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md`, `EXPERIENCE.md`), but nothing of it reached the site: postquality.com is still the stock `minima` theme after story 85's hygiene slice. This story builds the site from the spines and the build brief (`ux-postquality-website/build-brief.md`, 2026-10-23), which overrides the spines' draft copy with a lean copy deck and takes the open "best practice" calls. Three builds, each its own plan and draft PR: (1) foundation and the home page, with the three policy edits the pricing copy needs; (2) the pricing and documentation pages in both launch states behind one flag; (3) the four screenshots from a demo site, the sharing card, discoverability and the acceptance pass.

## Acceptance Criteria

Verify: the seven URLs of story 45 still answer 200 over HTTPS and `/nope/` answers 404 with the site's page; every page uses the site's own layouts (no `minima` include left) and DESIGN.md's tokens; the home page's first screen at 1280 px shows the navy band, lockup B on the band strip, the headline, one white primary button and the Dashboard picture overlapping the band; the copy on every page is the build brief's copy deck, facts unchanged; the policy pages render the text of `docs/policies/` after its three edits; `launched: false` shows the interest email everywhere and no install or buy link, and a local build with `launched: true` shows the install and buy paths; Lighthouse's accessibility category reports no failing audit on any page; no third-party request of any kind loads; the owner approves screenshots of the built pages at 1280, 390 and 320 px before each PR (hitl).

## Boundaries

- Must not change: the page URLs; the policy text beyond the three decided edits (edit `docs/policies/` first, then copy); prices, plans and pricing IDs; the icon artwork.
- Out of scope: the outlined-SVG wordmark (story 41); the worked cost example and the demo (story 49); the final messaging sweep (stories 53, 60); the interest tool (story 63); analytics of any kind (ADR 0013 D8); naming n8n Cloud before its walk passes; a merge.

## References

- brief — _bmad-output/initiative-post-quality-manager/ux-postquality-website/build-brief.md (analysis, decisions D1–D9, copy deck, owner questions)
- spec — _bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md; EXPERIENCE.md (tokens, components, states, accessibility floor)
- direction — _bmad-output/initiative-post-quality-manager/ux-postquality-website/.working/direction-navy-and-check.html (composition reference, not markup)
- parent story — _bmad-output/backlog/story-site-content-style-checkout-best-practices.md (story 57, decisions in its Notes)
- previous slice — _bmad-output/backlog/story-site-hygiene-no-dead-ends.md (story 85, the local build recipe in its plan)
- data — docs/distribution.md (pricing IDs, checkout URL); docs/brand.md; docs/policies/

## Notes

- Filed 2026-10-23 by Fable 5.1 from the owner's assessment of the UX pass. Plans written the same day: `story-site-navy-and-check-plan.md` (build 1), `-plan-2.md` (build 2), `-plan-3.md` (build 3). Implementer: Opus 5.5 (owner's word, 2026-10-23); review lenses at the session model.
- Decision: owner, 2026-10-23 — all six questions in the brief's § 4 answered with the recommended defaults (fresh Local demo site; headline "Score every post. Know what to fix next."; both launch states; lockup as live text; value before requirements; placement phrase in the interest email). The three plans are `ready-for-dev`; the build waits for the owner's order.
- Self-review 2026-10-23 (Fable 5.1) added brief D10–D16: value before requirements, a closing action, a leaner hero with the deletion promise at the ask, honest pack guidance, images as a performance item, a five-second test and a persona read before the owner's review, learning the converting placement from the inbox alone. Build 1 can start on the brief's recommended defaults; the headline is a one-line edit.
- Order: build 1 first. Build 3a (captures) may run before build 1 if the demo site is ready; otherwise build 1 ships the interim illustration and build 3a swaps the images.
