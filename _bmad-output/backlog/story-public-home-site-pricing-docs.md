---
id: 45
type: story
title: "The plugin has a public home: static pages on postquality.com for pricing, docs and policies"
parent: none
covers: [UV7, O9]
after: [43]
assignee: ""
refined: false
hitl: true
risk: low
---

# The plugin has a public home: static pages on postquality.com for pricing, docs and policies

## Description

Today every link points at the GitHub repository: the `Plugin URI` header, the README, the future readme. A directory listing, the Freemius checkout and the "Get Pro" link (story 44) all need a place that is not a code repository. The owner bought postquality.com on 2026-10-09 (ADR 0013). This story puts the minimum on it: a few static pages on GitHub Pages under the domain, built from content that already exists (README, the n8n Workflow page's steps, the walkthrough), and points the plugin header, README and readme at them. The proper landing page is story 50, built in Astro once real users have bought the plugin; until then the WordPress.org listing is the landing page.

## Acceptance Criteria

Verify: postquality.com serves over HTTPS from GitHub Pages with the custom domain configured; it has a front page (full name, the tagline from `docs/brand.md`, a link to the WordPress.org listing or the GitHub release until the listing exists, links to the pages below), a pricing page or checkout link matching ADR 0013 D4–D6 (49 / 99 / 199 a year for 1 / 5 / unlimited sites, USD and EUR, 14-day money-back), a documentation page (install, connect n8n, FAQ) and the policy pages of story 46; hello@postquality.com forwards to the owner's inbox and is the support address on every page; `Plugin URI` and `Author URI` in the plugin header, the README and the readme (story 38) link to the domain; the pages name no third-party trademark as part of the product name (brand rules).

## Boundaries

- No plugin behavior changes; only the two header URLs and documentation links.
- GitHub Pages only: no CMS, no hosting bill, no analytics beyond what ADR 0013 D8 allows; no design work (story 50 owns the landing page).
- Documentation is moved from the README, not rewritten; the README keeps the developer part.

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (domain, prices, mailbox, the static-first choice)
- naming — docs/brand.md § Naming (full name on first mention, no third-party trademark; the domain row)
- policies — _bmad-output/backlog/story-support-privacy-terms-refund-policies.md (story 46)
- landing — _bmad-output/backlog/story-astro-landing-page.md (story 50: the Astro site on Netlify that replaces these pages)
- code — post-quality-manager/post-quality-manager.php (Plugin URI, Author URI)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). The owner configures the registrar (DNS, mail forwarding; hitl); an agent drafts the pages from existing content.
- Decision (owner, 2026-10-09, ADR 0013): the domain is postquality.com and the mailbox hello@postquality.com; the landing page waits for real buyers (story 50), so story 41's assets are not a prerequisite here and `after` drops 41.
- Story 38 can submit with `Plugin URI` still on GitHub; this story is needed before the first sale because the checkout and the policies need a home.
- Decision (owner, 2026-10-09): the owner prefers a dedicated site to GitHub Pages. Story 50 (Astro on Netlify) is pulled forward and replaces these pages; the GitHub Pages DNS records are not added. This story keeps what it delivered: the content and URLs (`/`, `/pricing/`, `/docs/`, the four policies) that story 50 carries over, and the plugin header links. Its "serves over HTTPS from GitHub Pages" criterion moves to story 50 AC1; the owner marks it done on that basis.
