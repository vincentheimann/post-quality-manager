# Sources extract — postquality.com UX pass (story 57)

_Extracted 2026-10-22, read-only, for the bmad-ux facilitator. Facts only, no design. Paths relative to `post-quality-manager/` unless marked `site:` (= `C:/Git/Post Quality/postquality.com`)._

## Persona & journey

- **Persona:** solo professional blogger, any niche (marketing, technical/IT, lifestyle); self-hosts or controls their WordPress; comfortable following a setup guide; not necessarily an n8n or API expert. No real users, no telemetry; value claims are owner-verified only (`docs/user-paths.md`). Illustrative name in the n8n spine: "Lena, solo tech blogger" (`[ASSUMPTION]`).
- **A1 Problem recognition:** the back-catalog ages; SEO plugins score keywords, not editorial quality. UV: discoverability near zero.
- **A2 Evaluation:** n8n instance **and** AI key = "the single biggest adoption filter — no demo, no screenshots, no 'what will this cost me in API spend' guidance. Many evaluators bounce here."
- **A3–A5:** install; a setup guide asks three values (n8n address, n8n API key, Anthropic key), provisions n8n, tests both ways, runs the first review (the aha: four-pillar score plus top edits). Manual path exists; n8n Cloud path untested.
- **UV7** (`docs/user-paths.md` EBM table, row 7): Distribution (WordPress.org, updater), stages A1–A2, "largest long-term UV, deliberately deferred post-MVP". Story 57 covers UV7 and O9.
- **Objections on record:**
  - n8n: own instance (self-hosted or Cloud); site and n8n must reach each other (local or password-protected sites unreachable from Cloud); Rewrite needs HTTPS (`site:docs.md`).
  - API key: Anthropic key checked once, written into an n8n credential, never stored by the plugin (`docs/policies/privacy.md`).
  - Cost: you pay Anthropic per call; quota and token limit cap spend; worked example pending (story 49); prices cover the plugin only.
  - Trust: content goes only to your n8n and AI provider; the author receives nothing unless you opt in or buy Pro. Repository private, not listed, zero users, no social proof (audit).

## Product facts the site must convey

- **What it does:** "reviews your posts with AI and keeps the results in WP Admin: a score for every post, what to fix first, and the trend across your blog" (`post-quality-manager/readme.txt`).
- **Four pillars:** Structure, Readability, Engagement, Credibility. Niche tailoring via Blog Profile and Editorial Standards, never code (`site:index.md`).
- **How it works:** site → your own n8n workflow → AI provider → result back in WP Admin (score, issues, suggested edits, takeaways, sources, SEO and audit notes). You bring n8n and the key; the plugin brings dashboard, workflow, agents (`site:index.md`).
- **Free:** Review, Key Takeaways, Dashboard (average, trend, Fix next), Post Reviews, Insert into Post; updates; email support best effort (forum once listed). "The free plugin is the trial" (`site:pricing.md`, `readme.txt`).
- **Pro:** Rewrite, Sources, SEO and Audit agents; per-agent model, effort and instructions; Automated Reviews on a schedule; Roles tab; email support within 2 business days (`site:pricing.md`, `docs/policies/terms.md` § 1).
- **Prices (ADR 0013 D4–D6):** one yearly Pro plan, renews at the same price; 49 / 99 / 199 for 1 / 5 / unlimited sites, same figures in USD, EUR, CHF; no lifetime, trial, coupon or renewal discount; 14-day money-back guarantee; Freemius is seller of record. Pro plan 71340, nine pricing IDs (`docs/distribution.md`); plans not yet released (story 44's last step).
- **Lapse (D7, `docs/policies/terms.md` § 4):** Pro features lock, site returns to Free, nothing deleted (reviews, settings, schedules, role rights kept); renewing unlocks again.
- **Requirements:** WordPress 6.6+, PHP 8.0+, `Tested up to: 7.1`, `Stable tag: 3.9.0` (pre-release) (`readme.txt`); Yoast optional; Multisite not supported; licence GPL-2.0-or-later.
- **Seller/author:** Vincent Heimann, private person; hello@postquality.com.

## Brand constraints (`docs/brand.md`)

- **Voice:** professional and analytical; encouraging "colleague" tone; action-oriented; clear-verb CTAs; frame as optimization; concise; no accusatory framing, jargon, patronizing praise or "Success! …".
- **Value proposition:** placeholder line until `docs/messaging.md` (story 53, waits on the owner's KPI figures); public copy via the copywriting skill (story 60).
- **Naming:** first mention **Post Quality Manager**, then **Post Quality**; one name per sentence; never "PQM"/"PQ"; "Content Quality" retired; tagline verbatim, provider-neutral ("Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin."); no third-party trademark (WordPress, Claude, n8n, OpenAI, Google) in name, tagline **or imagery**; "X for Y" allowed in a description.
- **Icon (story 51, 2026-10-12):** geometric Q whose tail becomes a check, "quality, verified". Tile `#1b2a41`, mark `#ffffff`, check `#00a32a`. Never mirror, stretch or add a third colour. Files `assets/icon.svg`, `icon-128/256/512x512.png` (site has the SVG and 128 px).
- **Undecided:** logo lockup (owner 2026-10-22: designed **in this UX pass**, then recorded in `docs/brand.md`); imagery direction, banners, screenshots, colour accents (story 41); no website-visuals section in `docs/brand.md`; messaging (53).

## Plugin visual language (what screenshots will show)

- **System:** native WP admin, light scheme; Dashboard and Post Reviews on `@wordpress/components` (ADR 0005); other pages PHP with wp-admin classes (`ux-dashboard-post-reviews/DESIGN.md`, `ux-n8n-workflow-page/DESIGN.md`).
- **Tokens (wp-admin defaults):** primary `#2271b1` (hover `#135e96`), ink `#1d2327`, ink-muted `#50575e`, surface `#ffffff`, canvas `#f0f0f1`, line `#c3c4c7`, success `#00a32a`, warning `#dba617`, error `#d63638`, info `#72aee6`; system font stack, 13px body, 23px/400 page H1.
- **Status palette:** Ready `#00a32a`, Needs Revision `#dba617`, Major Revision `#d63638`, Processing `#646970`; always icon + label + colour, no emoji; yellow only as a filled badge behind a dark label.
- **Dashboard:** stat tiles (Average score, Needs attention, Not analyzed, Reviews this month) linking to filtered views; a **Fix next** queue of 5 (Review, Open in Editor); 8-week average-score sparkline.
- **Post Reviews:** status chips, score-range filter, sortable table, bulk "Review selected", expandable detail tabs, pillar meters in WP blue, Top edits, a locked Pro button.
- **n8n Workflow page:** status block (white box, 4 px left accent per state), one `.button-primary`, `<details>` disclosures; setup wizard as a Modal. No second primary button, new colour, icon set or card grid.
- The dashboard mockup's H1 still reads "Content Quality" (pre-naming drift).

## Current site inventory

Live site = commit `fc5442c` (2026-10-09): Jekyll 3.10, stock `minima` classic, seven text-only pages, header nav Pricing / Documentation / Support policy. The local working tree is on branch `feat/site-hygiene` with uncommitted story 85 changes (texts below are that tree).

- `/`: bold tagline; problem paragraph; pillars in one sentence; "How it works" paragraph; "Get it": Free = mailto "leave your email", Pro → pricing.
- `/pricing/`: "free plugin is the trial"; Free vs Pro table (7 rows); packs × USD/EUR/CHF table; guarantee; "Pro goes on sale with the first public release".
- `/docs/`: Requirements; Install (setup guide, or "Set up by hand instead"); Using it; FAQ (4).
- `/support/`, `/privacy/`, `/terms/`, `/refund/`: copies of `docs/policies/`.
- Story 85 adds: `404.html`, `robots.txt`, sitemap, favicon, footer policy links, contrast fix (links `#2470d1`, grey `#737373`).
- **Still missing per audit:** hero; any screenshot or demo; a primary-action button; pillars explained; how-it-works steps or visual; compatibility line; buy buttons; lapse rule, AI-cost FAQ and pricing FAQ on `/pricing/`; screenshot per docs step; troubleshooting; changelog; current version; WordPress.org and GitHub links (blocked); per-page meta descriptions; `og:image`; `SoftwareApplication` JSON-LD; front-page title "Home"; empty `/feed.xml`; social proof; tables at 360 px; Lighthouse. References: wpforms.com (hero screenshot, "Try Demo", latest updates), rankmath.com/pricing/ (~30-question FAQ, explainer videos).

## Decisions already taken

- 2026-10-08 naming (story 10). 2026-10-09 ADR 0013 (Freemius, prices, guarantee, lapse, "Free"/"Pro"; CHF amendment). 2026-10-12 GitHub Pages stays, Astro/Netlify dropped (story 50); icon (story 51).
- 2026-10-13: Conversion audit runs through `bmad-ux`; copy via story 60 from story 53's messaging.
- 2026-10-21 owner vision: a large UX and conversion pass with "a few honest neuromarketing nudges (no dark pattern)", captures that put the visitor "inside the plugin, as if they were using it", made once on the Local sandbox with fictional demo content and reused for site, listing, README and Freemius (`.memlog.md`).
- 2026-10-22: hygiene slice = story 85; tooling = Jekyll on GitHub Pages with own layouts and CSS (option A); checkout = plain hosted Freemius links, one per site pack, no script; Free action = interest by email (story 63) until listed or repo public; logo lockup designed in this pass.

## Constraints

- URLs fixed: `/`, `/pricing/`, `/docs/`, `/privacy/`, `/terms/`, `/refund/`, `/support/`.
- Policy text from `docs/policies/` only; prices and plans in Freemius unchanged.
- No analytics, no cookies, no tracking or third-party script (ADR 0013 D8; privacy § 3: "The website is static. It sets no cookies and runs no analytics.").
- GitHub Pages: allow-listed plugins only, no custom headers, no image processing.
- Interest capture: today a `mailto:`; story 63 picks the tool under the no-tracking rule (hitl).
- No dark pattern (no fake urgency, no hidden dismiss); Lighthouse: no accessibility error on `/` and `/pricing/`; mobile layout.
- Out of scope for 57: plugin code, producing visuals (41, 51), demo and cost text (49), KPI wording (53). Story 57 is not ranked: waits on A1 evidence.

## Open UX questions (verbatim, audit)

**Questions for bmad-ux:**

1. What is the visitor's intent on each page (`/`, `/pricing/`, `/docs/`, `/support/`), and which one action serves it?
2. Before the WordPress.org listing and before Pro goes on sale, what does each primary action become?
3. On `/pricing/`, one button per site pack, or one pack selector feeding one button; where do the guarantee and the lapse rule sit relative to the buttons?
4. How does the visitor pick USD, EUR or CHF without geolocation or a cookie?
5. Where do the prerequisites (own n8n, an AI key, the AI cost) meet the evaluator, so nobody installs and then bounces (`docs/user-paths.md` A2)?
6. What reason sits beside each action, in the persona's words (solo professional blogger, not an n8n expert)?
7. What is honest social proof at zero users (open source, release cadence, a public changelog), and what replaces it once the listing has ratings?
8. Which screenshots, in which order, give the "inside the plugin" feeling; is a short video or a click-through tour needed on top?
9. Where does the "not ready yet, leave an email" path (story 63) sit without competing with the primary action?
10. Which figures from spike 64 would show that each action works?
11. Do the comparison and price tables hold at phone width, or do they need a card layout?

**Open questions 5–8 (still open per story 57 Notes):**

5. **Currency on the pricing page.** Options: (a) keep three price columns and a button per pack and currency (nine buttons); (b) a currency switch without a cookie, three buttons; (c) one currency shown by default (USD), the others named in a line, the checkout letting the buyer change it.
6. **Showing the current version.** Options: (a) edited by hand at each release (`/release` gains a step); (b) read at build time from the GitHub or WordPress.org API (needs option B or C, or a scheduled rebuild); (c) not shown, the changelog page carries it.
7. **Where the documentation lives.** Options: (a) the site keeps its own docs and the setup text is refreshed each time the guide changes; (b) the site carries a short getting-started page and links to the WordPress.org readme and the GitHub README for the rest; (c) one source (the README) copied to the site at build, the way the policies come from `docs/policies/`.
8. **Social proof at launch.** Options: (a) none until the listing has ratings; (b) open-source and release-cadence signals (public repository, changelog) from day one; (c) a quote from the owner's own blogs as a named first user.
