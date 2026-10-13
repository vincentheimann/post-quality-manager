---
id: 57
type: story
title: "postquality.com presents the plugin properly: content, design, the Freemius checkout and plugin-site best practices"
parent: none
covers: [UV7, O9]
after: [45]
assignee: ""
refined: false
hitl: true
risk: medium
---

# postquality.com presents the plugin properly: content, design, the Freemius checkout and plugin-site best practices

## Description

Story 45 put the MVP on postquality.com: seven Jekyll pages on GitHub Pages, live over HTTPS since 2026-10-12. They are plain text on the stock `minima` theme: no visual, no screenshot, no call to action, and the pricing page says "Pro goes on sale with the first public release" with no link to the Freemius checkout. The owner's assessment (2026-10-12): the site needs a huge enhancement in content and style, the Freemius link is missing, and many best practices of a WordPress plugin website are probably not met. This story raises the site to what an evaluator expects from a plugin's home. The hosting and the site tooling are chosen at refinement, not now: GitHub Pages stays until then, and Astro on Netlify (story 50) is no longer the plan. The trigger is the plugin's success after the first public release.

Best practices to audit at refinement; the written gap list (met / missing / not applicable per item) is the first deliverable:

- **Front page:** a hero with the full name, the tagline, one screenshot or the demo (story 49) and one primary action (install Free, buy Pro); the four pillars; how it works; Free vs Pro; the requirements (your own n8n and an AI provider key); the compatibility line (WordPress and PHP versions, `Tested up to`).
- **Pricing:** one buy button per site pack opening the Freemius checkout (plan 71340, USD, EUR and CHF at the ADR 0013 prices); the 14-day guarantee; what happens when a license lapses; an FAQ on the AI cost (story 49).
- **Documentation:** a getting-started path with a screenshot per step, FAQ, troubleshooting, and the release notes per version.
- **Trust:** the support page with its response time, the policies linked from every page, the author named, links to the WordPress.org listing and to GitHub, the current version shown.
- **Discoverability:** page titles and meta descriptions, Open Graph and Twitter cards with an image, canonical URLs, `sitemap.xml`, `robots.txt`, a 404 page, the favicon (story 51), `SoftwareApplication` structured data.
- **Quality:** Lighthouse without accessibility or performance errors, a mobile layout, no analytics or tracking script (ADR 0013 D8).
- **Conversion:** one primary action per page for the visitor's intent (install Free, buy Pro, or leave an email, story 63), a reason beside each action, social proof once it exists, no dark pattern (no fake urgency, no hidden dismiss); the review of this section runs through `bmad-ux` (ADR 0004 amendment 2026-10-12: screens and flows), and its findings are measured per spike 64.
- **Consistency:** `docs/brand.md`, the visuals of story 41 and the messaging of story 53, once they exist.

## Acceptance Criteria

Verify: the refinement starts from the written audit of the current site against the list above, and the owner picks the scope and the tooling from it (hitl); after the build, the seven URLs of story 45 (`/`, `/pricing/`, `/docs/`, `/privacy/`, `/terms/`, `/refund/`, `/support/`) still answer 200 over HTTPS from the chosen host; the pricing page opens the Freemius checkout for each site pack at the ADR 0013 prices; every page follows `docs/brand.md`; the policy pages still render the text of `docs/policies/`; Lighthouse reports no accessibility error on `/` and `/pricing/`; no analytics or tracking script loads; the owner approves the result (hitl).

## Boundaries

- Must not change: the page URLs above (the plugin, Freemius and the readme link to them), the policy text (edit `docs/policies/` first, then copy), the prices and plans in Freemius.
- Out of scope: plugin code; producing the visuals (stories 41 and 51, used here); the demo and the cost guidance text (story 49, embedded here); the KPI wording (story 53, used here).
- Not ranked: waits for evidence of the plugin's success (`docs/user-paths.md` A1 signals: listing views, downloads, website visits).

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (public home; amended 2026-10-12: GitHub Pages is the MVP, this story improves it)
- home — _bmad-output/backlog/story-public-home-site-pricing-docs.md (story 45: the MVP this story builds on)
- superseded — _bmad-output/backlog/story-astro-landing-page.md (story 50, dropped 2026-10-12)
- data — docs/distribution.md (product and plan IDs, the checkout link, the hosting and DNS rows)
- copy — docs/brand.md; docs/policies/
- checkout — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44: the live checkout link on the pricing page is its last step)
- identity — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41); _bmad-output/backlog/story-plugin-icon.md (story 51)
- content — _bmad-output/backlog/story-launch-content-demo-cost-guidance-announcement.md (story 49); _bmad-output/backlog/story-value-messaging-kpis.md (story 53)

## Notes

- Filed 2026-10-12 (owner), the day the GitHub Pages site went live over HTTPS. Supersedes story 50, dropped the same day: Astro and Netlify are off the table, the best-fit tooling is chosen in this story's refinement.
- If story 44's last step lands first, it adds the plain checkout link to the pricing page; this story turns it into one buy button per site pack.
- Amended 2026-10-13 (owner): the audit gains a Conversion section and runs through `bmad-ux`; the copy is written with the copywriting skill (story 60) from the messaging of story 53. Related: stories 61 (in-plugin nudges), 62 (Freemius surfaces), 63 (interest capture), spike 64 (measurement).
