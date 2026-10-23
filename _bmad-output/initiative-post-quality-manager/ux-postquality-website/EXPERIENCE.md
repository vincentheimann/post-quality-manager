---
name: postquality.com
status: draft
updated: 2026-10-22
sources:
  - .memlog.md
  - .working/sources-extract.md
  - .working/journeys-and-pages-draft.md
  - .working/direction-navy-and-check.html
  - .working/logo-lockups.html
  - ../../backlog/story-site-content-style-checkout-best-practices.md
---

# postquality.com — Experience Spine

How the public website of Post Quality Manager works: its pages, actions, states and visitor journeys (story 57). Visual rules live in [DESIGN.md](DESIGN.md), whose tokens are cited by path in braces, such as `{colors.navy}`. Composition references: [direction-navy-and-check.html](.working/direction-navy-and-check.html) (home page before and after launch, phone) and [logo-lockups.html](.working/logo-lockups.html) (header, footer, favicon, sharing card). Neither is a markup reference, and **this spine wins on conflict** with both (overrides under Inspiration & Anti-patterns). Key-screen mocks: `/pricing/` on sale (comparison table, currency switch, pack cards, FAQ), `/docs/` (Getting started, Getting n8n), the phone Menu; Text pages stay spine-only. All copy is draft until story 60 writes it from story 53's messaging; repeated strings live once in Voice and Tone → Copy strings, cited in *italics*.

**Build overrides (2026-10-23):** [build-brief.md](build-brief.md) § 2–4 wins over this spine where they differ: since build 2 also D17–D22 (pricing leads with the packs; one hero button with a "What you need first ↓" link instead of **Check what you need**; plain buy notes and site answer; Pro support within 5 business days; the signature opens a new tab); the copy deck replaces § Copy strings and the page copy, the home order is hero → pillars → What you need → How it works → Free and Pro → closing action, and the interest email's body names its page. Story 86 builds it.

**Validation:** [validation-report.html](validation-report.html), [review-rubric.md](review-rubric.md), [review-accessibility.md](review-accessibility.md), [review-honesty-conversion.md](review-honesty-conversion.md); fixes applied per [triage.md](triage.md).

## Foundation

- **Form factor:** a responsive static website, designed desktop first because the evaluator reads at a desk before installing. No horizontal scroll down to 320 px (WCAG 1.4.10); 360 px is the design target. Confirmed by the owner.
- **Stack:** Jekyll (3.10 today) on GitHub Pages, with the site's own layouts and CSS; the stock `minima` theme goes. GitHub Pages allows only allow-listed plugins, no custom headers and no image processing.
- **UI system:** none. DESIGN.md is the visual identity.
- **No JavaScript required.** Every pattern below works with HTML and CSS alone.
- **No third-party request** of any kind: system fonts, images served by the site, no embeds. **No cookies, no analytics, no tracking** (ADR 0013 D8; privacy § 3).
- **Checkout:** plain links to Freemius's hosted checkout, one per site pack, with no checkout script on the site.
- **Fixed URLs:** `/`, `/pricing/`, `/docs/`, `/support/`, `/privacy/`, `/terms/`, `/refund/`. The plugin, Freemius and the readme link to them.
- **Stakes:** a real sales channel once Pro is on sale, and a showcase of the plugin. Commercial, not regulated. Confirmed by the owner.

## Information Architecture

### Page map

One primary action per page, matched to what the visitor wants to know. "After launch" means both launch triggers have fired; the mixed cases are in State Patterns.

| Page | The visitor wants to know | Primary action before launch | Primary action after launch | Blocks, in order |
|---|---|---|---|---|
| `/` | Is this for me, and can I run it? | **Tell me when it's available** | **Get the free plugin** (to `#need`) | Hero with the Dashboard screenshot and ticks · What you need block · Pillar list with the review screenshot · How it works (steps, screenshot 3 at step 1, flow diagram, data line) · Free vs Pro teaser (plan cards, assurance items) · the author line, before the footer |
| `/pricing/` | What does Pro cost, and is buying safe? | **Tell me when it's available** (one, with the *pricing interest note*) | **Buy for 1 site / 5 sites / unlimited sites**, in the shown currency | Comparison table · currency switch · pack plan cards, the *pricing cost line* under them, the assurance items, *buy note* and *renewal line* beside the buttons · pricing FAQ |
| `/docs/` | How do I set it up and use it? | None (the install section's interest email is a primary button on light) | None | Contents list · Requirements · Getting n8n (its link named "Self-hosting n8n (docs.n8n.io)") · Getting started (screenshot 3, once) · Set up by hand · Using it · Upgrade to Pro · FAQ · Troubleshooting (content gap) |
| `/support/` | Where do I get help? | Email hello@postquality.com | Email for Pro; the WordPress.org forum for Free once listed (policy text) | Support policy, copied from `docs/policies/support.md` |
| `/privacy/`, `/terms/`, `/refund/` | The rules | None | None | Policy copies, unchanged, as Text pages |
| 404 | I am lost | **Back to the home page** | Same | Text page: icon F at 64 px, "Page not found", links to Home, Pricing, Documentation |

Secondary actions on the home page: **Check what you need** (jumps to the What you need block) before launch, **See Pro pricing** after launch.

Where the actions lead:

- **Interest email:** `mailto:hello@postquality.com?subject=Tell%20me%20when%20Post%20Quality%20Manager%20is%20available&body=Please%20tell%20me%20once%20when%20Post%20Quality%20Manager%20can%20be%20installed.` One link, one subject, shown only while the free plugin is not installable. One list, one message at the first public release saying whether Pro is on sale, then each address is deleted (privacy § 3 once updated).
- **Get the free plugin:** `/#need`. The What you need block ends with **Install from WordPress.org** (or the GitHub release, if the repository goes public first). Until the free plugin is installable, the free action stays the interest email, so no install button points to nothing.
- **Buy for N sites:** the hosted Freemius checkout for that pack in the shown currency. Three visible buttons, with the nine pricing IDs from `docs/distribution.md` behind them.

### On every page

- **Header:** a full-width band strip with lockup B linking to `/`, then Pricing, Documentation, Support and the site-wide primary action as a small button (before launch **Tell me when it's available**, after launch **Get the free plugin**). Below `{spacing.bp-medium}`: lockup B and a Menu with the same links, the action last. If Pricing or Documentation ever leaves the header, it joins the footer.
- **Footer:** the tagline, the four policy links (Privacy, Terms, Refund, Support), hello@postquality.com, the no-cookies line, and the author and licence line.

### Surface closure

Every visitor need has a page: seeing the product, requirements, getting n8n, cost, privacy, currency, guarantee and lapse, install, upgrade, buy, help, security reports, leaving an email, the current version, being lost. The unfilled needs are content gaps, not missing pages: troubleshooting text, the worked cost example (story 49), and a way to measure success (Open Questions).

## Voice and Tone

Microcopy. The brand posture is in DESIGN.md § Brand & Style; the rules come from `docs/brand.md`.

- **Voice:** professional and analytical, an encouraging colleague. Action-oriented, with clear verbs on buttons; frames issues as optimisation; concise. No accusatory framing, jargon, patronising praise, "Success! …" or hype words ("revolutionary", "supercharge").
- **Naming:** first mention **Post Quality Manager**, then **Post Quality**; one name per sentence. The header lockup B counts as the first mention, and the hero lead names it in full again; on every other page the h1 or lead names Post Quality Manager in text once ("Post Quality Manager Pro pricing"). Never "PQM", "PQ" or "Content Quality".
- **Tagline,** verbatim wherever it appears (footer, sharing card): "Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin."
- **Third parties:** no trademark in the name, tagline or imagery. Their names appear in plain text where a fact needs them ("your own n8n", "an Anthropic API key"). An "X for Y" description is allowed ("AI content reviews for WordPress").
- **A reason beside each action:** every primary action carries an action note or footnote that says what happens next.
- **Plain facts are the reassurance.** Never: a broader data claim than the *data line*; hiding that the plugin cannot be installed yet; an AI-cost figure before story 49's worked example, n8n's prices, or a spending limit the plugin does not have; the author's blogs or their domains; demo content presented as a real blog's results; a version line nobody updates; testimonials, ratings before the listing has any, user or download counts, "trusted by", results figures, countdowns, fake scarcity.

### Copy strings

| Name | Draft string |
|---|---|
| status line | "Pre-release, not installable yet" |
| version line | "Version X, updated <date>, tested up to WordPress N.N" (the readme's `Tested up to`), with a "What's new" link to the WordPress.org listing's changelog |
| action note, before launch | "The button opens your email app, or write to hello@postquality.com. You hear once, when you can install it." |
| action note, after launch | "Start free with Review and Key Takeaways. Pro comes with a 14-day money-back guarantee." |
| Free footnote, before launch | "Not installable yet. Opens your email app." |
| Pro footnote, before sale | "Pro goes on sale with the first public release." |
| pricing interest note | "Not on sale yet. The button opens your email app; you hear once, when the first public release is out." |
| n8n line | "Your own n8n, self-hosted. Your site and n8n must reach each other over the network. n8n is a cost of its own: the server you run it on." |
| n8n API key line | "The setup guide also asks for an n8n API key. The plugin keeps it in your site's database to set up and update the workflow, so create one for this site alone, with an expiry date. Prefer not to? Set the workflow up by hand." |
| cost line | "You pay Anthropic per review, on your own key, and your n8n hosting separately. To cap what you spend, set a spend limit in your Anthropic account: the plugin's Monthly Quota only limits how many AI actions each Author runs (Editors and Administrators have no limit)." |
| pricing cost line | Terms § 2: "The price covers the plugin only. You pay your own n8n hosting and your own AI provider separately." |
| tax line | "Prices exclude VAT or sales tax; Freemius adds it at checkout where it applies." |
| buy note | "Checkout opens on Freemius, the seller of record." plus the *tax line* |
| renewal line | "Renews automatically every year at the price you paid. Freemius emails you before each renewal. Cancel the renewal any time in the customer portal; Pro stays on until the end of the year you paid for." (terms § 3–4 once updated) |
| guarantee | "14-day money-back guarantee. Ask within 14 days of buying or of a renewal and get the full amount back, no questions asked. Freemius refunds it to your original payment method." |
| lapse line | "Nothing deleted if your license ends. The site returns to Free: reviews, settings, schedules and role rights are kept, and Review and Key Takeaways keep working. Renewing unlocks everything again." |
| CHF line | "In CHF, pay by card. PayPal is available in USD and EUR." |
| HTTPS line | "Rewrite needs HTTPS on your site (it signs in with a WordPress Application Password)." |
| data line | "Your content goes only to your own n8n and, through it, to Anthropic. The author receives nothing from your site unless you opt in to usage data or buy Pro." |
| author line | "Built by the author and used on real blogs." |
| Dashboard caption | "The Dashboard in WP Admin. Demo posts and scores, fictional; not a measured result." |
| install note | "Opens the plugin's WordPress.org page." |

Button labels: Tell me when it's available · Check what you need · Get the free plugin · Install from WordPress.org · See Pro pricing · Buy for 1 site · Buy for 5 sites · Buy for unlimited sites · Back to the home page · Menu · Skip to content.

## Component Patterns

Behaviour. Visual specs live in DESIGN.md § Components under the same names.

| Component | Where | Behavioural rules |
|---|---|---|
| Lockup | Header, footer, 404, favicon, sharing card | The header lockup links to `/` with `aria-label="Post Quality Manager, home"` around an `aria-hidden="true"` SVG, so the name is read once. The footer lockup D is not a link; the 404 icon takes `alt=""`. Variant per spot as in DESIGN.md → Lockup; never F in the header. |
| Header | Every page (`/docs/`, Text pages and 404 included) | As in On every page. Plain links. |
| Menu disclosure | Header, below `{spacing.bp-medium}` | Native `<details>`/`<summary>` labelled "Menu", opening Pricing, Documentation, Support, then the site-wide action. Closed on every page load. The panel is in flow, so nothing is covered when focus leaves it or when Back restores it open. No script. |
| Skip link | First focusable element of every page | "Skip to content" targets `<main id="main" tabindex="-1">`. `<header>` holds only the lockup, the nav and the action; the hero starts `main`. Off-screen until focused, never `display: none`. |
| Primary button | Hero, header (small), plan cards, What you need, `/docs/`, 404 | A link styled as a button; nothing is posted to the site; destinations per Where the actions lead. An interest button's accessible name adds a visually hidden " (opens your email app)"; the hero's also points `aria-describedby` at the action note. Install and buy buttons carry the *install note* and the *buy note*. One primary action per page, plus the header's. |
| Secondary button | Hero, Pro plan card | **Check what you need** jumps to the What you need block (before launch). **See Pro pricing** opens `/pricing/`. |
| Focus ring | Every interactive element | Shown on keyboard focus (`:focus-visible`), never removed; its colour follows the surface (DESIGN.md → Focus ring). |
| Hero | `/` | Order: eyebrow, h1, lead, requirement line (after launch only), actions, *status line* or *version line*, action note, ticks; then the Dashboard screenshot over the band. Copy note for story 60: the h1 "Score every post. Know what to fix next." takes the tagline's verb. |
| Ticks | Hero | Facts on record, not links: own n8n and Anthropic key · content goes only there · "A free plugin to try it first" before launch, "Free: Review and Key Takeaways, no license" after. |
| Action note | Under the status or version line | The *action note, before launch* or *after launch*: what a click does and what the visitor gets. |
| Requirement line | Hero, after launch | Above the actions: your own n8n and an Anthropic API key, each paid separately; WordPress 6.6+, PHP 8.0+; a link to the What you need block. |
| Version line | Under the hero actions; beside **Install from WordPress.org** | The *status line* before launch, the *version line* once installable, from one site variable edited by hand at each release (a `/release` checklist step). |
| Screenshot frame | `/` (screenshots 1–3), Getting started (3), `/docs/` (4) | A static image whose alt says what it shows (screen, key numbers), and a caption naming the screen and saying the demo content is fictional. Two crops of one image get an alt true of both, or two `<img>` switched by media query, each with its own alt. |
| What you need block | `/`, under the Dashboard screenshot; anchor `#need` | Three items: **your own n8n** (*n8n line*, then `No n8n yet? <a href="/docs/#getting-n8n">How to get n8n</a>`; the site never explains n8n hosting), **an Anthropic API key** (checked once, written into an n8n credential, never stored by the plugin; then the *n8n API key line*), **what you pay** (*cost line*; "A worked cost example is coming." before launch only). Then WordPress 6.6+, PHP 8.0+, "Multisite is not supported", and the fit: "A good fit if you run your own WordPress site and are happy to follow a setup guide for n8n. Not a fit if you want a hosted service with no setup, run Multisite, or n8n cannot reach your site." After launch it ends with **Install from WordPress.org**, the *version line* and the *install note*. |
| Pillar list | `/` | Structure, Readability, Engagement, Credibility, and one sentence on tailoring through a Blog Profile and Editorial Standards, never code. Screenshot 2 sits beside it. |
| Steps | `/`, How it works | Three steps: connect your own accounts (the setup guide asks for three values, sets the workflow up and tests both ways; screenshot 3 illustrates it), review a post, fix what matters first. |
| Flow diagram | `/`, How it works | `<ol aria-label="Where your content goes">`, one node per item: Your site → Your n8n workflow → Anthropic's models → Back in WP Admin. Arrows `aria-hidden`. |
| Trust line | `/` | The *data line* under the flow diagram and wherever the site says what reaches the author; the *author line* under the Free vs Pro teaser, blogs never named, joined by the repository link once public. Ratings deferred: only once the listing has some, as its own average and count, linked. |
| Plan card | `/` (Free, Pro); `/pricing/` (three packs) | Free (terms § 1, support policy): Review, Key Takeaways, Dashboard (average, trend, Fix next), Post Reviews, Insert into Post, updates, support best effort. Pro: the Rewrite, Sources, SEO and Audit agents; per-agent model, effort and instructions; Automated Reviews; the Roles tab; email support within 2 business days; the *HTTPS line*. Home: Free with a plan note (requirements in one line, "You pay Anthropic per review and your n8n hosting.", a link to `#need`) and the free action; Pro with the 1-site price, the other two in one line, the *tax line* and **See Pro pricing**. `/pricing/`: one card and buy button per pack, in the shown currency, the *tax line* under the prices. Each card owns its footnote (State Patterns). |
| Comparison table | `/pricing/`, first | A `<table>` at every width, `<caption>` "Free and Pro compared", features as `<th scope="row">`, plans as `<th scope="col">`; each mark has visually hidden text ("Included" / "Not in Free"). Rows: the Plan card's features, Rewrite with the *HTTPS line*, the two support levels. On phones an overflowing row scrolls inside `<div role="region" aria-labelledby="…" tabindex="0">`. |
| Currency switch | `/pricing/`, above the packs, once Pro is on sale | `<fieldset><legend>Show prices in</legend>` with one labelled `<input type="radio" name="cur" autocomplete="off">` per currency, USD checked. Inputs stay focusable (`appearance: none`, or the clip pattern styling `input:focus-visible + span` and `input:checked + span`), never `display: none`, `visibility: hidden`, `hidden` or zero-size. The swap uses `:has()` from a shared ancestor, guarded by `@supports selector(:has(a))`; without it, all three currencies show, labelled, and the switch hides. A hidden currency's price and buy link both get `display: none`. Prices carry their code ("49 CHF per year, 1 site"); buy links add a visually hidden " in CHF". No `role="tablist"`. Nothing stored, no geolocation: a reload shows USD. Under the CHF packs, the *CHF line*. |
| Assurance item | `/` (Free and Pro); `/pricing/` beside the buy buttons | Not links: the *guarantee*, with "Freemius handles checkout, invoices, tax and refunds."; the *lapse line*. |
| Upgrade to Pro | `/docs/`, after Using it | First line: "Never delete the free plugin first: deleting removes your stored reviews and settings." Then: download the Pro build from Freemius's email; on each site upload it beside the free build (Plugins → Add New Plugin → Upload Plugin; its folder is `post-quality-manager-premium/`, so WordPress offers no replace) and activate it, which deactivates Free and keeps reviews and settings; enter the key; once Pro is active the free build may be deleted with no data lost (corrected 2026-10-23, story 86 build 2 review); move an activation; cancel (*renewal line*); refund (refund.md); when the license ends (*lapse line*). Source: ADR 0014. |
| FAQ | `/pricing/`, `/docs/` | The pricing FAQ covers at least: what else you pay (*cost line*, *pricing cost line*); "Is tax included?" (*tax line*); "How do I cancel?" (*renewal line*); what counts as a site, copied from terms § 3 (until it is updated, only what it says now); "One n8n can serve several sites; the setup guide gives each site its own workflow." The docs FAQ is copied from the plugin repository. One `<details>`/`<summary>` per question, the question as the summary, all closed on load; no script. |
| Text page | `/support/`, policies, 404 | One h1, the page title (policy copies drop their Markdown `# Title`). 404: "There is no page at this address. These links lead back:" Home, Pricing, Documentation, and **Back to the home page**. |
| Footer | Every page | Tagline verbatim; Privacy, Terms, Refund, Support; hello@postquality.com as a `mailto:` link with the address visible; the no-cookies line quoted from privacy § 3 once it names the host; "© 2026 Vincent Heimann · GPL-2.0-or-later", which names the author. |
| Sharing card | Every page's `og:image` | One static PNG, the dark version, built once (GitHub Pages does no image processing). A second version with the Dashboard is possible once real screenshots exist. |

## State Patterns

The pages are static, so the site has no loading, empty, offline or error states of its own; the only error surface is the 404 page. Its states are launch states, changed by editing and rebuilding the site. Free and Pro launch together (owner, 2026-10-22: ADR 0014's release order deploys the Pro zip to Freemius first and generates the Free build from it), so there are two launch states, switched once: **before launch** (today) and **after launch**, when the plugin is listed on WordPress.org or its GitHub release is public, the plans are released in Freemius (story 44's last step), and the launch gates have passed (the n8n Cloud walk; story 49's worked cost example published).

| State | Treatment |
|---|---|
| **Before launch** (today) | Header, hero, Free card: **Tell me when it's available**; **Check what you need**; *status line*, *action note, before launch*, ticks before launch, *Free footnote, before launch*. Pro card: *Pro footnote, before sale*. `/pricing/`: "49 / 99 / 199 a year, in USD, EUR or CHF" in one line, no switch or pack buttons, one interest button with the *pricing interest note*. `/docs/`: the interest email. |
| **After launch** | Header, Menu, hero and Free card: **Get the free plugin** (to `/#need`); secondary **See Pro pricing**; requirement line; *version line*; *action note, after launch*; ticks after launch. `/pricing/`: the switch, the buy buttons, the *buy note*, the *renewal line* and the assurance items. `/docs/`: **Install from WordPress.org**. |

| State | Surface | Treatment |
|---|---|---|
| Free installable | `/docs/` | Getting started: **Install from WordPress.org**, then the setup guide's three values, shown once by screenshot 3, with the *n8n API key line*. |
| Listed on WordPress.org | Free card, `/support/` | The Free card reads "Support forum on WordPress.org, best effort", linked; `/support/` sends Free questions there (policy text). |
| Repository public | `/` | The repository link joins the author line. |
| Currency chosen | `/pricing/` | USD on every load; nothing remembered. |
| Menu | Header below `{spacing.bp-medium}` | Closed on load; open, it pushes the page down. |
| Hover and focus | Links, buttons | Hover colours per DESIGN.md; text links thicken their underline; header links underline on hover; the focus ring on keyboard focus. |
| Not found | Any unknown URL (`404.html`, story 85) | The Text page 404. |
| No email app | Any interest or support `mailto:` | hello@postquality.com is printed in the action note and the footer, so it can be copied. |
| Interest tool replaced (story 63) | Every interest action | Today a `mailto:`; story 63 picks a tool under the no-tracking rule. |

## Interaction Primitives

- Links and in-page anchors (`#need`). External links open in the same tab; any `target="_blank"` says "opens in a new tab".
- `mailto:` with a prefilled subject and body for interest; a plain `mailto:` for support.
- Plain links to Freemius's hosted checkout.
- Native `<details>`/`<summary>` (the Menu, the FAQ) and native radio inputs (the currency switch).
- No animation is specified; hover changes colour or underline weight only. Any future motion sits inside `@media (prefers-reduced-motion: no-preference)`.
- **Banned:** JavaScript dependencies, cookies, stored state of any kind, analytics, third-party embeds (a hosted video player would load a third party), countdowns, fake scarcity, pre-ticked boxes, hidden dismiss.

## Accessibility Floor

Behaviour. Contrast ratios live in DESIGN.md § Colors.

- **WCAG AA:** 4.5:1 for text, 3:1 for large text and UI parts. `lang="en"`. Acceptance per the site build story: automated checks on every page plus a manual pass.
- **One h1 per page** (on `/`, the hero headline); one h2 per section. Landmarks: header, the main nav labelled "Main", main, footer with the policy nav labelled "Policies".
- **Skip link** first in the tab order.
- **Keyboard:** every action reachable with Tab in reading order. The focus ring shows on every interactive element, including the Menu and FAQ summaries and the currency tab whose radio input has focus.
- **Links in running text** are underlined (DESIGN.md → Text link); colour alone never marks a link.
- **Menu and FAQ:** each `<summary>` toggles with Enter or Space; the state is exposed natively and shown by a marker that changes shape; no heading inside a `<summary>`.
- **Currency switch:** radio inputs in a `legend`-labelled group; Tab enters on the selected one, the arrow keys switch prices at once; the selected tab differs by more than colour.
- **Lists:** `role="list"` on every styled `ul`/`ol` (ticks, pillar list, steps, plan feature lists); the step order stays exposed by the `ol` or as text in the numeral.
- **Screenshots:** alt text says what each image shows; the caption says the demo content is fictional. Inside screenshots, status always shows as icon, label and colour (the plugin's own rule).
- **Decorative icons** (checks, line icons, arrows) are hidden from assistive technology; the claims they decorate are text. Exception: the comparison table's marks carry visually hidden text.
- **Targets:** at least 24 × 24 CSS px, design target 44 px. Buttons 52 px (54 on desktop); the header's small button and the Menu 44 px; currency tabs at least 44 × 44; FAQ summaries full width, 44 px tall; header nav links padded to 44 px; footer policy links keep their 8 px row gap.
- **Links that leave the page** say where they go (Primary button).

## Responsive & Platform

| Width | Behaviour |
|---|---|
| ≥ `{spacing.bp-medium}` | Desktop header row. Centred hero; What you need and steps in three columns; pillars, plans and assurance items in two. |
| `{spacing.bp-phone}` to `{spacing.bp-medium}` | Header: lockup B and the Menu. The pillar list and the review screenshot stack. |
| < `{spacing.bp-phone}` | Lockup B at a 28 px tile. Hero left-aligned, h1 `{typography.display-mobile}`, actions full width and stacked (primary first), ticks stacked. The Dashboard screenshot switches to its phone crop and still overlaps the band. Every grid goes to one column; plan cards stack with full-width buttons; the flow diagram turns vertical. |
| `{spacing.min-viewport}` | The design target: lockup B and the Menu share one row. |
| `{spacing.reflow-floor}` | The floor: no horizontal scroll. The header wraps, the Menu on a second line; long URLs, code and headings wrap (DESIGN.md → Layout & Spacing). |

- The direction's target for a 390 × 844 phone: the first screen shows the header, the hero, both actions, the ticks and the top of the Dashboard crop.
- The comparison stays a table at every width.

## Inspiration & Anti-patterns

- **Chosen: Navy and check** (option 2 in [compare-home-first-screens.html](.working/compare-home-first-screens.html)): a brand-led product landing page, with the plugin picture overlapping the band so the visitor lands inside the plugin.
- **Shared with the audit's reference sites:** a hero screenshot (wpforms.com) and a pricing FAQ (rankmath.com/pricing/). **Not taken:** their demo and explainer videos (no video or tour for now).
- **Rejected:** 1 WordPress look ([direction-inside-wp-admin.html](.working/direction-inside-wp-admin.html)), the plugin's WP blue and grey; 3 Editorial ([direction-editorial.html](.working/direction-editorial.html)), serif on warm paper, whose serif wordmark (E) would read as a second brand; 4 Plain and honest ([direction-plain-and-honest.html](.working/direction-plain-and-honest.html)), documentation-like with a smaller plugin picture, whose core idea survives: the prerequisites before any install action.
- **Lesson from the comparison:** with large plugin mocks under design banners, the owner could not tell the website from the plugin. On the site, every plugin picture is framed and captioned as a screenshot.
- **Overridden in the direction file:** `<p class="h1">`, `role="img"` on divs, the green 3 px ring with a 3 px offset (now navy or white), the white keyline and the navy header (now lockup B on the band strip), the wrapped phone links (now the Menu), `id="main"` on a non-landmark, the download icon, its data tick (now the *data line*), its two greys (now the lockup's). **In the lockup sheet:** its 404 and light footer (now the band strip, navy links, white footer, the Text page 404).
- **Rejected, dark patterns:** countdowns, fake scarcity, pre-ticked boxes, hidden dismiss, and invented social proof of any kind.

## Honest nudges

The owner's vision, a few honest nudges and no dark pattern, lives in: What you need block, requirement line, ticks, data line, action note, assurance items beside the buy buttons, status line, author line, the Dashboard over the band. Not used: countdowns, scarcity, social proof that does not exist, AI-cost figures before story 49.

## Screenshots

Four screenshots, in this order (owner, story 57 Q8):

| # | Screen | Where |
|---|---|---|
| 1 | Dashboard: stat tiles, Fix next, an 8-week line that is flat or uneven (or cropped out) | `/` hero, plus a phone crop (tiles and the top of Fix next, no admin menu) laid out for the 328 px column and exported at 2× |
| 2 | A review with the four pillar scores and Top edits | `/`, beside the pillar list |
| 3 | The setup guide's three values | `/` (How it works, step 1) and Getting started |
| 4 | Post Reviews list | `/docs/` |

- Screenshot 1 takes the *Dashboard caption* and an alt true of both crops, figures from the final capture, e.g. "Post Quality Dashboard: average score 71; 3 Major revision, 9 Needs revision, 14 Not analyzed, 22 Ready to ship; Fix next led by a post scored 42."
- Made once on the Local sandbox with fictional demo content, and reused for the WordPress.org listing, the README and Freemius. No video or click-through tour for now.
- The site ships real images, each exported at the sizes it is shown, because GitHub Pages does no image processing. The HTML mocks in the direction file are placeholders until then.
- Before use, check that the screens show today's labels: an earlier Dashboard mockup still read "Content Quality" (pre-naming drift), and a capture must read "Post Quality". No third-party logo in the frame, including the WordPress logo in the admin bar.

## Content sources and drift checks

| Content | Source of truth | On the site |
|---|---|---|
| Policies (privacy, terms, refund, support) | `docs/policies/` in the plugin repository | Copied; edit the source first, then copy. The copy drops the Markdown `# Title` (the layout prints `page.title` as the h1); the drift check covers it |
| Install text and FAQ | The plugin repository | Copied with a drift check, as the policies are (story 85 found drift; the cost answer is fixed in postquality.com draft PR #2) |
| Troubleshooting | To be written in the plugin repository from the plugin's own failure messages | Copied like the rest of the docs |
| Prices, plans, guarantee, renewal, lapse | ADR 0013, terms, refund.md and Freemius | Never changed by the site |
| Version line | Each release | Edited by hand (`/release` step) |
| Provider list and spend-cap wording | ADR 0009, stories 25, 29, 30 | Every "Anthropic" string and the *cost line* change with that release; the token limit is named only once it ships |
| n8n Cloud | The Cloud walk, recorded in `docs/user-paths.md` | Not named until the walk passes; then the *n8n line* and Getting n8n add it, with n8n's links and the free trial's missing API |
| Tagline, naming, voice | `docs/brand.md` | Verbatim |
| Lockup | This pass | Recorded in `docs/brand.md` at Finalize, as SVG files plus the specs |
| Page copy and value proposition | Story 53 (messaging), then story 60 (copywriting) | Draft until then; story 60 replaces the Copy strings' values, not their names |
| Worked AI cost example, demo | Story 49 | A launch gate; "A worked cost example is coming" only before launch |

**Prerequisites of the site build**, edited in `docs/policies/` first: terms § 3, what counts as a site (each production site, not its staging or local copies, once Freemius's licensing allows it; client sites allowed, the license stays the buyer's); terms § 4, cancelling the renewal keeps Pro until the paid year ends; privacy § 3, "An email asking to hear about the launch is kept until that one message is sent, then deleted.", and GitHub Pages named as host, GitHub logging visitors' IP addresses for security (GitHub's wording checked first).

## Success signals

No analytics (ADR 0013 D8 stands; owner, 2026-10-22). Before launch, the site works if interest emails arrive; after launch, Freemius sales and the installs in the WordPress.org stats are the signals.

## Key Flows

### Flow 1 — Lena checks whether they can run it (before launch)

*Lena, solo tech blogger: 240 posts since 2016 on a self-hosted WordPress. No n8n yet, but follows setup guides without trouble. Their problem (A1): older posts drift, and the SEO plugin scores keywords, not whether a post is still good. Evening, at the desk.*

1. Lena lands on `/` from a search or a link. `[ASSUMPTION]` How visitors find the site is outside the site (A1's discoverability gap).
2. First screen: "Score every post. Know what to fix next." and the Dashboard screenshot with its Fix next list. Lena recognises their own back-catalog in it.
3. The ticks answer the first doubts: their own n8n and Anthropic key; posts go only there; a free plugin to try it first.
4. Lena scrolls to the What you need block (or clicks **Check what you need**): self-hosted n8n, with **How to get n8n** leading to Getting n8n on `/docs/`; the two keys; the *cost line*, both bills named; WordPress 6.6+, PHP 8.0+; who it fits. This is the A2 moment: the doubt is answered and the way forward shown before any install.
5. The pillar list and the review screenshot show what one review gives: four pillar scores and Top edits.
6. Free and Pro, then `/pricing/`: 49 a year, the *tax line*, the *guarantee*, the *lapse line*.
7. **Climax:** "I can run this, and I want it." Lena clicks **Tell me when it's available**; the email app opens with the subject and body filled in, and the action note has already said they will hear once, when they can install it.

Failure: no email app opens, so Lena copies hello@postquality.com from the action note. Honest exit: a visitor without n8n who does not want one leaves informed at step 4, instead of installing and bouncing in WP Admin.

### Flow 2 — Lena installs the free plugin (after launch)

1. The launch email arrives; Lena opens the home page again.
2. Same first screen, the requirement line above the actions; **Get the free plugin** leads to the What you need block.
3. The block ends with **Install from WordPress.org** (or the GitHub release), the *version line* and the *install note*.
4. Lena opens **Documentation → Getting started**: install, then the setup guide's three values (n8n address, n8n API key, Anthropic key), shown once by screenshot 3, with the *n8n API key line*. The text is copied from the plugin repository, so it matches the plugin.
5. **Climax:** a confident install, with no surprise left. The first score itself happens in WP Admin (A5), which is the plugin's job.

Failure: neither the listing nor a public release exists yet, so the free action is still the interest email; the install button never points to nothing.

### Flow 3 — Tom buys Pro for three sites (after launch)

*Tom, freelance marketer: their own blog plus two client blogs. Has used the free plugin for three weeks and wants Automated Reviews and Rewrite on all three sites.*

1. In WP Admin, Tom clicks a locked Pro button ("Available with Pro") or the plugin's upgrade link, and lands on `/pricing/`. Depends on bug 59 (`_bmad-output/backlog/bug-upgrade-links-lead-nowhere.md`): Settings → "Get Pro" reaches the pricing page; Freemius's Upgrade links lead nowhere.
2. Tom works in Switzerland and selects **CHF** in the currency switch; the three packs show 49 / 99 / 199 CHF, with the *CHF line*.
3. Three sites: the **5 sites** pack.
4. Beside the buy buttons: the *guarantee*, the *renewal line*, the *lapse line* and the *buy note*.
5. The pricing FAQ answers the rest: both bills, the *tax line*, what counts as a site (client sites included, once terms § 3 says so), one n8n for all three sites.
6. **Climax:** Tom clicks **Buy for 5 sites**, and Freemius's hosted checkout opens in CHF.
7. After the site: Freemius emails the license key and a Pro download link. On each site, Tom uploads the zip beside the free build (Plugins → Add New Plugin → Upload Plugin), activates it, which deactivates Free, and enters the key. Reviews and settings carry over.

Failure: Pro is not on sale yet, so `/pricing/` says so and Tom keeps using Free. A reload shows USD again, because nothing is stored; Tom selects CHF again.

### Flow 4 — Tom needs help

*Tom, now a Pro customer, whose test ping fails.*

1. Tom opens **Support** from the header (from the Menu on a phone).
2. Tom reads what to include: plugin and workflow versions, WordPress and PHP versions, what they did.
3. **Climax:** Tom writes to hello@postquality.com with everything support needs, in one message.

Failure: no email app, so Tom copies the address from the footer. A security problem is reported privately to the same address.

## Story 57 questions answered

| Audit question | Answered in |
|---|---|
| 1 Intent and one action per page | Page map |
| 2 Actions before the listing and before Pro is on sale | Page map, State Patterns |
| 3 One button per pack or a selector; where the guarantee and lapse sit | Plan card |
| 4 Currency without geolocation or a cookie (OQ5, option b) | Currency switch |
| 5 Where the prerequisites meet the evaluator | What you need block, Requirement line |
| 6 A reason beside each action | Voice and Tone, Action note |
| 7 Honest social proof at zero users (OQ8, option c), and once ratings exist | Trust line |
| 8 Screenshots, order, video | Screenshots |
| 9 Where "not ready, leave an email" sits | Page map |
| 10 Figures that show each action works | Success signals |
| 11 Tables at phone width | Comparison table, Responsive & Platform |
| OQ6 Showing the version (option a) | Version line |
| OQ7 Where the docs live (option c) | Content sources and drift checks |

## Open Questions

1. **Trader identity.** Owner check before Pro goes on sale: does the offer page need a postal address (Swiss UWG Art. 3(1)(s), EU UCPD Art. 7(4)(b)), or does Freemius as seller of record suffice? If needed, a business or PO-box address, never a home one.

Content gaps, owned elsewhere: troubleshooting text (plugin repository); the worked cost example (story 49, a launch gate); final copy (stories 53 and 60); the interest-capture tool (story 63); the real screenshots; the lockup's outlined artwork; the setup time. **Build items:** `triage.md` § Build items (B1–B8) and discoverability (page titles, meta descriptions, canonical URLs, `SoftwareApplication` JSON-LD, og:image) are acceptance input for the site build story.
