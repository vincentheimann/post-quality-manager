---
id: 50
type: story
title: "postquality.com is an Astro site on Netlify where an evaluator can read about the plugin and buy Pro"
parent: none
covers: [UV7, O9]
after: [45]
assignee: ""
refined: true
hitl: true
risk: medium
---

# postquality.com is an Astro site on Netlify where an evaluator can read about the plugin and buy Pro

## Description

Story 45 put the minimum on postquality.com: plain Jekyll pages on GitHub Pages, never reachable because the DNS was not switched. The owner prefers a dedicated site now (2026-10-09): an Astro site deployed on Netlify replaces those pages before the first sale, instead of after real buyers. It presents the plugin (tagline, the four pillars, how it works, what Free includes and what Pro adds, the requirements: your own n8n and an AI provider key), carries the pricing with one buy button per site pack opening the Freemius checkout, and keeps the docs and the four policy pages at the URLs already entered in Freemius and the plugin. The "Get Pro" link in Settings → License lands on its pricing page.

## Acceptance Criteria

1. **Served from Netlify**
   **Given** the site repository `github.com/vincentheimann/postquality.com` rebuilt as an Astro project (static output, no adapter)
   **When** a commit lands on `main`
   **Then** Netlify builds it (`npm run build`, publish `dist`) and serves it at https://postquality.com with a valid certificate, `www` redirects to the apex, and GitHub Pages is switched off for that repository so the domain has one host
2. **The URLs stay stable**
   **Given** the links already published (plugin header, README, Freemius store settings, `docs/policies/` links, the License section's "Get Pro")
   **When** each is opened
   **Then** `/`, `/pricing/`, `/docs/`, `/privacy/`, `/terms/`, `/refund/` and `/support/` answer 200 with their content; the policy pages render the text of `docs/policies/`, the source of truth
3. **Buy works**
   **Given** the pricing page
   **When** an evaluator picks 1, 5 or unlimited sites
   **Then** the matching Freemius checkout opens for plan 71340 with that pack, in USD, EUR or CHF at 49 / 99 / 199 a year, and the page states the 14-day money-back guarantee (ADR 0013 D4–D6); before the plans are released the buttons use the sandbox checkout, and switching them to live is one change made at story 44's last step
4. **It reads like the product**
   **Given** `docs/brand.md`
   **When** the owner reads every page
   **Then** the full name is used on first mention, the tagline is the provider-neutral one, no third-party trademark is part of the product name, and the owner approves the design (hitl)
5. **It stays lean**
   **Given** the built site
   **When** Lighthouse runs on `/` and `/pricing/`
   **Then** it reports no accessibility error; the pages load no analytics, cookie or tracking script, and no third-party script beyond what the checkout needs

## Boundaries

- Must not change: the page URLs above, the policy text (edit `docs/policies/` first, then copy), the prices and plan settings in Freemius.
- Out of scope: the logo, screenshots and banners (story 41: placeholders until then, swapped when it lands); the demo and AI-cost guidance (story 49); a blog, a CMS, Netlify Forms or Netlify Analytics; any plugin code (the site never ships inside the zip).

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (domain, prices, public home; amended 2026-10-09 for this story)
- home — _bmad-output/backlog/story-public-home-site-pricing-docs.md (story 45: the content and URLs this site takes over)
- data — docs/distribution.md (product and pricing IDs, the sandbox checkout link, the hosting and DNS rows)
- copy — docs/brand.md; docs/policies/
- identity — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41: assets swapped in later)
- content — _bmad-output/backlog/story-launch-content-demo-cost-guidance-announcement.md (story 49: demo and cost guidance added later)
- external — https://docs.astro.build/en/guides/deploy/netlify/ (static Astro on Netlify: no adapter, `npm run build`, `dist`)
- external — https://docs.netlify.com/manage/domains/configure-domains/configure-external-dns/ (apex ALIAS or A record, `www` CNAME)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43) with "after real buyers" as its trigger; Astro was the owner's choice the same day.
- Decision (owner, 2026-10-09): built now, before the first sale, on Netlify instead of GitHub Pages; it replaces story 45's Jekyll pages rather than only its front page. `after` drops 41: visuals come in when story 41 lands.
- DNS (owner, at Infomaniak, once the Netlify site exists): apex `ALIAS @ → apex-loadbalancer.netlify.com` if the registrar offers ALIAS, else `A @ → 75.2.60.5`; `CNAME www → <site>.netlify.app`; remove the parking `A` to 128.65.195.180; leave MX and the three Freemius DKIM CNAMEs untouched. Read the exact values from Netlify's "Pending DNS verification" before adding them. The GitHub Pages records of story 45 are not added.
- Owner steps (hitl): create the Netlify account and import the repository; add the DNS records; approve the design.
- Plan-time choices: checkout as plain links (the KISS default) or Freemius's checkout overlay script (needs AC5's script allowance); keeping the Jekyll history in the same repository (the default) or a new one.
- Dropped (owner, 2026-10-12): the GitHub Pages site of story 45 went live over HTTPS and stays as the MVP ("the website will be improved later depending on the success of the plugin"); Astro and Netlify are off the table, the best-fit tooling is chosen at that time. Superseded by story 57 (content, design, Freemius checkout, plugin-site best practices).
