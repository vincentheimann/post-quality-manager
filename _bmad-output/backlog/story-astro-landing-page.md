---
id: 50
type: story
title: "An Astro landing page on postquality.com presents the plugin to evaluators"
parent: none
covers: [UV7]
after: [45, 41]
assignee: ""
refined: false
hitl: true
risk: low
---

# An Astro landing page on postquality.com presents the plugin to evaluators

## Description

Story 45 puts the minimum on postquality.com: static pages for pricing, docs and policies, with the WordPress.org listing acting as the landing page. Once real users have bought the plugin, the domain gets a proper landing page built with Astro (owner's choice, 2026-10-09): the tagline and the four pillars, the screenshots and visual identity of story 41, the demo and the AI-cost guidance of story 49, the requirements (your own n8n and an AI provider key), the pricing table with the Freemius checkout, and the links to docs and policies. It replaces the front page of story 45 and keeps its other pages.

## Acceptance Criteria

Verify: the landing page is an Astro site deployed to postquality.com over HTTPS (GitHub Pages or the host the owner picks), passes a Lighthouse run without accessibility errors, follows `docs/brand.md` (full name on first mention, no third-party trademark in the product name, provider-neutral tagline), shows the pricing of ADR 0013 and the "Get Pro" checkout, embeds the demo of story 49, and keeps the pricing, docs and policy pages of story 45 reachable at their existing URLs; the owner approves the design (hitl).

## Boundaries

- No plugin code; the site lives in its own repository or folder, never inside the plugin zip.
- No analytics beyond what ADR 0013 D8 allows; no CMS; no blog until there is something to publish.

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (domain, prices, the static-first choice this story follows)
- home — _bmad-output/backlog/story-public-home-site-pricing-docs.md (story 45: the pages this replaces or keeps)
- identity — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41: logo, screenshots)
- content — _bmad-output/backlog/story-launch-content-demo-cost-guidance-announcement.md (story 49: demo, cost guidance)
- external — https://astro.build/ (the framework the owner chose)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). Trigger (owner): real users have bought the plugin; not ranked before that. Astro was the owner's choice; the build's plan picks the deployment path.
