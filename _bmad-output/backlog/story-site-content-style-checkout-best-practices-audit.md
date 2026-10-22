# Audit of postquality.com against story 57's checklist

_Written 2026-10-22 by an agent, read-only, as the first deliverable of story 57's refinement. Inspected: the live pages `/`, `/pricing/`, `/docs/`, `/privacy/`, `/terms/`, `/refund/`, `/support/`, plus `/sitemap.xml`, `/robots.txt`, `/feed.xml`, a missing page (`/this-page-does-not-exist/`) and `/favicon.ico`, fetched with curl (status, headers, raw HTML, the theme's `/assets/main.css`); the site's source in github.com/vincentheimann/postquality.com, default branch `main` at commit `fc5442c376df76206f4196e4e87acf86a74df792` (2026-10-09, "Pricing: add the CHF column"), read through the GitHub API; the external links the pages carry. Not inspected: Lighthouse (not installed; every Lighthouse item is "not measured", with how the build should measure it), the pages in a real browser or at phone width (the mobile verdict comes from the HTML and CSS). Contrast figures are computed from the theme's CSS colours, not measured on screen. Reference points for "what a plugin site shows": wpforms.com (home) and rankmath.com/pricing/ (pricing), read 2026-10-22, and Freemius's checkout documentation._

## What the site is today

Seven Markdown pages on Jekyll 3.10 with the stock `minima` theme (skin `classic`), served by GitHub Pages; `_config.yml` sets the title, the tagline as description, the author and email, and three header pages (Pricing, Documentation, Support policy). There is no `_layouts/`, `_includes/`, `assets/` or `404.html` in the repository. Every page is text only: no image on any of the seven. All seven answer 200 over HTTPS; `http://` and `www.` redirect (301) to `https://postquality.com/`. The pages were last deployed 2026-10-09 (`Last-Modified` header) and have not changed since.

Status values: **met**, **partly**, **missing**, **not applicable**. "Owned by" names another ticket when it already covers the gap; otherwise "story 57".

## Front page

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| A hero with the full name and the tagline | partly | `/` opens with an `<h1>` "Home" (the minima `home` layout's page heading), then a second `<h1>` "Post Quality Manager" and the tagline in bold. Name and tagline are right; the stray "Home" heading sits above them and there is no hero treatment. | story 57 |
| One screenshot or the demo (story 49) | missing | No `<img>`, video or embed on `/`. No screenshot exists yet; the demo does not exist yet. | stories 41 (screenshots), 49 (demo); placement story 57 |
| One primary action (install Free, buy Pro) | missing | The "Get it" section is two bullets with inline text links: "GitHub" (releases) and "pricing". No button. The GitHub link is a dead end for visitors (repository private, see Beyond the list); Pro is not on sale. | story 57; the action targets wait on entry 4.8 (public repo), story 38 (listing), story 44 (checkout) |
| The four pillars | partly | Named in one sentence ("Structure, Readability, Engagement and Credibility"), not explained or shown. | story 57 |
| How it works | partly | One paragraph under "How it works": site → your n8n workflow → your AI provider → result in WP Admin. No steps, no visual. | story 57 |
| Free vs Pro | partly | Two bullets on `/` ("Free: Review and Key Takeaways…", "Pro: the Rewrite, Sources, SEO and Audit agents…"); the comparison table lives on `/pricing/` only. | story 57 |
| The requirements (your own n8n and an AI provider key) | partly | Stated in prose on `/` ("You bring the n8n instance and the AI provider account"); the full list (WordPress 6.6, PHP 8.0, network reachability, HTTPS for Rewrite) is on `/docs/` only. | story 57 |
| The compatibility line (WordPress and PHP versions, `Tested up to`) | missing | Not on `/`. `/docs/` gives "WordPress 6.6 or later, PHP 8.0 or later" but no `Tested up to` (7.1 in `readme.txt`). | story 57 |

## Pricing

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| One buy button per site pack opening the Freemius checkout | missing | `/pricing/` has no link to checkout.freemius.com; it says "Pro goes on sale with the first public release." The Freemius plans are still unreleased (story 44 plan status `built`, its last step releases them and adds a plain checkout link). | story 44 (plain link), then story 57 (one button per pack) |
| Plan 71340, USD, EUR and CHF at the ADR 0013 prices | met (prices shown) | The "Pro, per year" table shows 49 / 99 / 199 for 1 / 5 / unlimited sites in USD, EUR and CHF, matching ADR 0013 D5 and `docs/distribution.md`. Nothing yet ties the figures to plan 71340 or its pricing IDs, since there is no button. | story 57 |
| The 14-day guarantee | met | "**14-day money-back guarantee**, no questions asked", with a link to `/refund/`. | — |
| What happens when a license lapses | missing | Not on `/pricing/`. The text exists in `/terms/` § 4 ("the Pro features lock again and the site returns to the free tier. Nothing is deleted…") and in `/refund/`. | story 57 |
| An FAQ on the AI cost (story 49) | missing | No FAQ on `/pricing/`; one line says "you pay your own n8n hosting and AI provider separately". `/docs/` has a cost FAQ entry ending "A worked example per provider will be added here." | story 49 (the text); story 57 (placing it on pricing) |

## Documentation

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| A getting-started path | partly, outdated | `/docs/` has Requirements, Install (three steps, the second listing the setup guide's seven manual steps) and Using it. It was written 2026-10-09, before the n8n provisioning epic (entries 6.1–6.12): the plugin's README now describes a guide that asks three values (n8n address and API key, then the Anthropic key once) and creates the credentials and the workflow itself. The page describes only the older manual path. It also points to "Post Quality → n8n Workflow" while the README still says "Content Quality → n8n Workflow" (known drift, story 41). | story 57 |
| A screenshot per step | missing | No image on `/docs/`. | story 41 (screenshots); story 57 (placement) |
| FAQ | partly | Four questions: AI cost (placeholder), does content leave my site, multisite, where to report a bug. | story 57; cost answer story 49 |
| Troubleshooting | missing | No troubleshooting section (connection test fails, webhook not reachable, wrong token, n8n Cloud cannot reach a local site). The Requirements bullet on reachability is the nearest thing. | story 57 |
| The release notes per version | missing | No changelog or release-notes page. Release notes live in `docs/release-notes/unreleased.md` and on GitHub Releases (v3.9.0 latest, pre-release), both in the private plugin repository. | story 15 (writes the notes); story 57 (publishes them) |

## Trust

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| The support page with its response time | met | `/support/`: "an answer within **2 business days** (Monday to Friday)" for Pro, the forum for Free, security reports. Text identical to `docs/policies/support.md`. | — |
| The policies linked from every page | missing | The header shows Pricing, Documentation, Support policy. Privacy is linked only from `/docs/`; Terms and Refund only from `/pricing/`; `/` links none of them. The footer carries name, email and tagline, no policy links. | story 57 |
| The author named | met | The footer of every page shows "Vincent Heimann" (minima `h-card`) and hello@postquality.com; the JSON-LD names him as `Person`. | — |
| A link to the WordPress.org listing | missing | No listing link. The plugin is not listed: `api.wordpress.org` answers "Plugin not found" for `post-quality-manager`, and wordpress.org/plugins/post-quality-manager/ redirects to a search. `/docs/` and `/support/` link the forum URL, which answers 404. | story 38 |
| A link to GitHub | partly | `/` links the releases page; `/docs/` links releases, issues and the security policy. The plugin repository is private, so all of them answer 404 to a visitor. | entry 4.8 (repository goes public) |
| The current version shown | missing | No version on any page. | story 57 |

## Discoverability

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| Page titles | partly | `jekyll-seo-tag` (minima's default) gives each page `<title>{page} \| Post Quality Manager`. The front page reads "Home \| Post Quality Manager", which says nothing; the nav label is "Support policy". | story 57 |
| Meta descriptions | partly | Present on all seven pages, but all seven carry the same text (the site tagline from `_config.yml`). | story 57 |
| Open Graph and Twitter cards with an image | partly | `og:title`, `og:description`, `og:url`, `og:type`, `og:site_name` and `twitter:card` are present; there is no `og:image`, `twitter:card` is `summary` with no image, and the front page's `og:title` is "Home". | story 57; the image from story 41 (or the story 51 icon) |
| Canonical URLs | met | `<link rel="canonical">` on every page with the trailing-slash URL; `/pricing` redirects 301 to `/pricing/`. | — |
| `sitemap.xml` | missing | `/sitemap.xml` answers 404; `_config.yml` lists no `jekyll-sitemap` plugin. | story 57 |
| `robots.txt` | missing | `/robots.txt` answers 404. | story 57 |
| A 404 page | partly | A missing URL answers status 404 (correct), but with GitHub Pages' generic page ("Page not found · GitHub Pages"): no site name, no navigation back. The repository has no `404.html`. | story 57 |
| The favicon (story 51) | missing | `/favicon.ico` answers 404 and no page has a `<link rel="icon">`. The files exist in the plugin repository (`assets/icon.svg`, `assets/icon-512x512.png`); `docs/distribution.md` says story 57 wires them. | story 57 (story 51 produced the files) |
| `SoftwareApplication` structured data | missing | The JSON-LD on each page is `WebSite` (front) or `WebPage` with an `author` `Person`; no `SoftwareApplication` (name, operating system, category, offers with the three prices). | story 57 |

## Quality

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| Lighthouse without accessibility or performance errors | not measured | Lighthouse is not installed here. From the HTML and CSS, an accessibility run on `/` and `/pricing/` is likely to flag: link colour `#2a7ae2` on `#fdfdfd` at 4.15:1 (below the 4.5:1 AA threshold for body text, and links are not underlined, `text-decoration:none`); footer text `#828282` at 3.78:1; two `<h1>` on `/`; an empty first header cell in the pricing comparison table. `lang="en"` is set; there are no images, so no missing `alt`. Performance should be clean: 5–9 KB of HTML per page, one 7 KB stylesheet, no images, no JavaScript beyond the JSON-LD block. How to measure in the build: Lighthouse CI (`@lhci/cli`) or `npx lighthouse` against `/` and `/pricing/`, mobile and desktop presets, run from a GitHub Actions workflow in the site repository on every push, with accessibility errors failing the job. | story 57 |
| A mobile layout | met (not seen on a device) | `<meta name="viewport" content="width=device-width, initial-scale=1">`; minima's CSS has breakpoints at 600 px and 800 px and a collapsing nav (`nav-trigger`). The two pricing tables were not checked at 360 px. | story 57 (check at build) |
| No analytics or tracking script | met | Each page loads one stylesheet and one `application/ld+json` block; no external script, no font or pixel from a third party, no cookie set. `_config.yml` sets no `google_analytics` key. Matches ADR 0013 D8 and `docs/policies/privacy.md` § 3. | — |

## Conversion

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| One primary action per page for the visitor's intent (install Free, buy Pro) | missing | No page has a button or a single highlighted action. `/` offers two inline links; `/pricing/` ends on links to the policies; `/docs/` starts on requirements. The only install path (GitHub releases) is private. | story 57 |
| …or leave an email (story 63) | missing | The only contact is a `mailto:` link to hello@postquality.com. No form; GitHub Pages has no backend. | story 63 |
| A reason beside each action | partly | The Free bullet says what Free gives; the Pro bullet lists the Pro agents. No reason sits beside the pricing table or the "pricing" link, and the prerequisites are not set next to the install action. | story 57 |
| Social proof once it exists | not applicable | No real users yet (`docs/user-paths.md`); no rating, install count or testimonial to show. | story 57, once the listing exists (story 38) |
| No dark pattern (no fake urgency, no hidden dismiss) | met | No countdown, no strike-through price, no pop-up, no pre-ticked box. | — |
| Findings measured per spike 64 | missing | No measurement exists by design (no analytics). Spike 64 is unrefined. | spike 64 |

This section goes through `bmad-ux` next (ADR 0004, amended 2026-10-12, and the story's 2026-10-13 amendment). Questions for that review, not answered here:

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

## Consistency

| Item | Status | Evidence | Owned by |
|---|---|---|---|
| `docs/brand.md` | partly | Followed: the full name introduces the product, the tagline is verbatim, "PQM" never appears as a name (only the `X-PQM-Token` header value a user must type), the short form is used for menu paths. Not followed or not covered: the site shows none of the brand's visual elements (the story 51 icon and its colours `#1b2a41`, `#00a32a`); `docs/brand.md` has no section for the website's visuals yet. | story 57; visual rules story 41 |
| The visuals of story 41 | not applicable | Story 41 has not produced the banners, screenshots or imagery direction yet. | story 41 |
| The messaging of story 53 | not applicable | `docs/messaging.md` does not exist; story 53 waits on the owner's KPI figures. | story 53 |

## Beyond the list

Gaps a plugin-site evaluator would notice that the checklist does not name:

- **No view of the plugin itself.** Zero images across the seven pages. The owner wants the visitor to feel inside the plugin (captures of the plugin in use). Both reference sites lead with product imagery: wpforms.com puts a hero screenshot under the headline and offers a "Try Demo"; rankmath.com/pricing/ attaches explainer videos to plan features. Owned by story 41 (captures) and story 57 (layout).
- **Every outbound "get it" link is dead for a visitor.** The plugin repository is private (`gh api` reports `visibility: private`), so the releases, issues and security-policy links on `/` and `/docs/` answer 404; the WordPress.org forum link on `/docs/` and `/support/` answers 404 because the plugin is not listed. The front page's only install path ends on a 404. Owned by entry 4.8 and story 38; until they land, the pages could say so instead of linking (story 57).
- **The published privacy page is out of date.** `/privacy/` is dated 2026-10-09; `docs/policies/privacy.md` is dated 2026-10-14 and adds the n8n API key paragraph and the "To Anthropic, once, during setup" paragraph. The other three policies match their source line for line. Owned by story 68.
- **The terms name two currencies, the pricing page three.** `/terms/` § 2 and `docs/policies/terms.md` say "Prices are shown on https://postquality.com/pricing in USD and EUR"; the pricing page and ADR 0013 D5 (amended) include CHF. Owned by story 57 (edit `docs/policies/terms.md` first, then copy).
- **No changelog or "what's new".** wpforms.com links "View The Latest Updates" from its home page. The site has no release history and the GitHub Releases are private. Owned by story 15 (notes) and story 57 (page).
- **The setup docs predate the setup guide.** See Documentation; the site describes a seven-step manual path the plugin no longer leads with. Owned by story 57.
- **An empty feed is advertised.** Every page links `/feed.xml` (Atom), which holds no entry: the site has no posts. Either drop it or use it for release notes and the launch post (story 49). Owned by story 57.
- **No pricing FAQ.** rankmath.com/pricing/ carries about 30 questions under its plans (renewals, activation, site limits, refunds, what is kept on downgrade); `/pricing/` has none. Owned by story 57; the AI-cost answer by story 49.
- **No security headers beyond HTTPS.** GitHub Pages sends no `Strict-Transport-Security` or `Content-Security-Policy` and allows none to be set. Low risk for a static site with no script; relevant to the tooling choice.
- **The site repository's README is stale.** It still says an Astro landing page (story 50) replaces the front page. Owned by story 57.
- **A checkout script would be the site's first third-party code.** Freemius offers two ways to sell from a static page: hosted checkout links (`https://checkout.freemius.com/product/{id}/plan/{plan}/licenses/{n}/currency/{c}/`, no script on the site) and an overlay that loads `https://checkout.freemius.com/js/v1/` on the page. The overlay's documented `currency` values are `usd`, `eur`, `gbp` and `auto`; CHF support has to be checked at build. The privacy text promises a static site with no cookies; the overlay choice needs the owner's call (open question 3).

## Tooling options for the owner's choice

Story 57 leaves the hosting and the tooling to this refinement; Astro on Netlify (story 50) is off the table. No decision is taken here.

| Option | Cost | Effort | What it unlocks for the gaps above |
|---|---|---|---|
| A. Stay on Jekyll and GitHub Pages, with the site's own layouts and CSS (override or drop `minima`) | none | low to medium: a few `_layouts`/`_includes`, one stylesheet, a `_data` file for prices and pricing IDs | Every checklist item: hero, buttons, screenshots, policy links in a footer, favicon, `robots.txt`, `404.html`, `jekyll-sitemap` (on the GitHub Pages plugin allow-list), `SoftwareApplication` JSON-LD from the same data file. No custom response headers, only allow-listed plugins, no image processing. |
| B. A static generator (Eleventy, Hugo, or Jekyll unrestricted) built by GitHub Actions, still deployed to GitHub Pages | none on a public repository | medium: port seven pages to new templates, add a deploy workflow | All of A, plus any plugin, image resizing, components reused across pages, and a link check and Lighthouse CI in the same workflow before deploy. Still no custom headers. DNS unchanged. |
| C. The same generator on Cloudflare Pages (free tier) | none | medium, plus a DNS change at Infomaniak | All of B, plus a `_headers` file (HSTS, CSP), preview deploys per pull request, a `_redirects` file. A new processor for the privacy text to name; Cloudflare's own analytics must stay off (ADR 0013 D8). |

Recommendation: **A**, because every gap found is content, assets or a missing file, not a limit of the tool, and the URLs and DNS stay as they are, so moving to B later costs nothing now.

## Open questions for the owner

1. **Scope and timing.** The story waits for the plugin's success, but some gaps are hygiene that misleads visitors today (dead links, stale privacy page, no policy links, no favicon or 404 page). Options: (a) keep waiting for the trigger as filed; (b) cut a small hygiene slice now and leave content, visuals and conversion for the trigger; (c) build the whole checklist once 41, 49 and 53 have delivered.
2. **Tooling.** Options A, B or C above (recommendation A).
3. **Checkout integration.** Options: (a) hosted checkout links, one per pack and currency, no script on the site; (b) the Freemius overlay script on `/pricing/` only, disclosed in the privacy text; (c) links now, overlay later if conversion data (spike 64) argues for it.
4. **The Free action before the listing and the public repository.** Options: (a) make the repository public (entry 4.8) before any site work, so the GitHub links resolve; (b) replace the Free action with "write to us" or the email capture of story 63 until story 38 lands; (c) hide the Free action and keep only Pro until the listing exists.
5. **Currency on the pricing page.** Options: (a) keep three price columns and a button per pack and currency (nine buttons); (b) a currency switch without a cookie, three buttons; (c) one currency shown by default (USD), the others named in a line, the checkout letting the buyer change it.
6. **Showing the current version.** Options: (a) edited by hand at each release (`/release` gains a step); (b) read at build time from the GitHub or WordPress.org API (needs option B or C, or a scheduled rebuild); (c) not shown, the changelog page carries it.
7. **Where the documentation lives.** Options: (a) the site keeps its own docs and the setup text is refreshed each time the guide changes; (b) the site carries a short getting-started page and links to the WordPress.org readme and the GitHub README for the rest; (c) one source (the README) copied to the site at build, the way the policies come from `docs/policies/`.
8. **Social proof at launch.** Options: (a) none until the listing has ratings; (b) open-source and release-cadence signals (public repository, changelog) from day one; (c) a quote from the owner's own blogs as a named first user.
