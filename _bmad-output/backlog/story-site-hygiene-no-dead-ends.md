---
id: 85
type: story
title: "postquality.com sends no visitor to a dead end and says what is true today"
parent: none
covers: [UV7, O9]
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# postquality.com sends no visitor to a dead end and says what is true today

## Description

The first slice of story 57, cut on 2026-10-22 from its audit: the site's hygiene gaps that mislead a visitor today, fixed on the current Jekyll and GitHub Pages site. Every link that ends on a 404 while the plugin repository is private and the plugin is unlisted is removed or replaced; the Free action becomes a way to leave interest by email until the plugin can be installed; the policies are linked from every page; the site gains a favicon, a 404 page of its own, `sitemap.xml` and `robots.txt`; the privacy, terms and docs pages say what the plugin does today; link and footer text meet the contrast minimum. Content, visuals, the checkout buttons and the conversion work stay with story 57. The owner reviews and merges the change in the site's repository, which this project's push rules do not cover (hitl).

## Acceptance Criteria

1. **No dead end**
   **Given** the seven pages of story 45 (`/`, `/pricing/`, `/docs/`, `/privacy/`, `/terms/`, `/refund/`, `/support/`)
   **When** every link on them is followed from outside, logged out
   **Then** each answers 200, or is a `mailto:` to hello@postquality.com; no link points to the private plugin repository or to an unlisted WordPress.org page
2. **Interest instead of install**
   **Given** the front page and the support page
   **When** a visitor looks for how to get the Free plugin
   **Then** they find one action to leave their interest by email, with one sentence saying the plugin is not installable yet and what they will hear about
3. **Policies and housekeeping**
   **Given** any page of the site
   **When** it loads
   **Then** its footer links the privacy, terms and refund pages; the browser shows the plugin's icon as favicon (story 51's files); a missing URL shows the site's own 404 page with a way home; `sitemap.xml` lists the seven pages and `robots.txt` points to it
4. **Texts that are true today**
   **Given** `docs/policies/` in the plugin repository
   **When** the site's privacy, terms and refund pages are compared with it
   **Then** they render the same text, the privacy page with the n8n and Anthropic key paragraphs (story 68), the terms naming USD, EUR and CHF (edited in `docs/policies/terms.md` first); and the docs page describes the current setup (the setup guide, three values typed, the manual path as the alternative)
5. **Readable**
   **Given** the site's stylesheet
   **When** body links and footer text are measured against their background
   **Then** both reach 4.5:1, and the front page has one `<h1>`
6. **Nothing else changes**
   **Given** the boundaries below
   **When** the change is reviewed
   **Then** the seven URLs, the prices, and the absence of any analytics or third-party script are unchanged, and the owner approves the result (hitl)

## Boundaries

- Must not change: the seven page URLs, the prices and plans, `docs/policies/` beyond the terms' currency line, the no-tracking rule (ADR 0013 D8).
- Out of scope (story 57): screenshots, the demo, new copy beyond the sentences above, buy buttons, Open Graph image, structured data, Lighthouse in CI, the layout redesign.

## References

- parent — none; first slice of `_bmad-output/backlog/story-site-content-style-checkout-best-practices.md` (story 57)
- audit — `_bmad-output/backlog/story-site-content-style-checkout-best-practices-audit.md`
- privacy — `_bmad-output/backlog/story-privacy-page-matches-the-policy.md` (story 68)
- interest — `_bmad-output/backlog/story-interest-capture-and-follow-up.md` (story 63)
- copy — docs/brand.md; docs/policies/; docs/distribution.md (Website section)
- icon — `_bmad-output/backlog/story-plugin-icon.md` (story 51)

## Notes

- Decision: owner, 2026-10-22 — a hygiene slice now; Jekyll and GitHub Pages kept, with the site's own layouts and CSS where the slice needs them; the Free action becomes interest by email until the plugin is listed or the repository is public.
- Decision: owner, 2026-10-22 — the interest action is a `mailto:` to hello@postquality.com with a prefilled subject, no form and no third party; story 63 replaces it with its form and follow-up.
- Decision: owner, 2026-10-22 — story 68 is folded into this slice (its privacy sync is criterion 4); 68 closes with this one.
- Decision: owner, 2026-10-22 — the agent pushes a branch to the site repository and opens a draft PR there, under project-scoped allow rules the owner adds to the workspace's `.claude/settings.local.json` (`git push https://github.com/vincentheimann/postquality.com.git *`, `gh pr create --repo vincentheimann/postquality.com *`); the owner merges. Until those rules exist, the build stops before the push.
