# postquality.com build brief: Navy and check, lean copy

_Written 2026-10-23 by Fable 5.1 at the owner's request ("the last UX pass was quite poor, the site does not look professional; direction 2 Navy and check was good but never implemented; the content must stay lean, natural and hookable"). Analysis, the decisions this build takes, the copy deck, and the questions left to the owner. The build itself is story 86 (`_bmad-output/backlog/story-site-navy-and-check.md`) in three bmad-build plans beside it; the implementer runs on Opus 5.5 (owner's word, 2026-10-23)._

## 1. Analysis

**What is live.** postquality.com is still the stock `minima` theme with story 85's hygiene fixes (true texts, policy links, favicon, 404, sitemap). No layout, no colour, no screenshot, no call to action beyond a text link. The site repository's `main` is `2e1d851`; nothing of the UX pass has reached it.

**What the UX pass delivered.** Four rendered home-page directions and a logo lockup sheet (`.working/`), the owner's picks (direction 2 Navy and check, lockup B on the band `#33496b`), two spine documents (`DESIGN.md`, 4,400 words of tokens and component specs; `EXPERIENCE.md`, 6,600 words of page map, copy strings, states and flows), three review lenses, a synthesis report and a 70-row triage, all applied to the spines. It then stalled: the three key-screen mocks (home, pricing, docs) never landed, the spines stayed `draft`, the lockup never reached `docs/brand.md`, the workspace was never committed, and no site build story was filed.

**Why it reads as poor.**

1. **Effort went into documents, not the site.** About 20,000 words of spines, reviews and triage against zero lines of CSS. The one artefact the owner could react to (the direction mock) is the one that was never carried forward.
2. **The copy got heavier with every review.** The honesty lens was right on the facts (no VAT included, no token limit, n8n Cloud untested, n8n's own cost) but each fix was written into the spines as a full disclaimer. The "cost line" is now three sentences with a parenthesis about Editors and Administrators; the "n8n API key line" is a paragraph. Written as is, the home page would read like terms of service.
3. **Open items were resolved by more mocks** ("settled in the `/pricing/` mock", "settled in the phone Menu mock") instead of by building the page.

**What is good and is kept.** The direction mock itself (its structure, type scale, navy band, white primary button, green ticks, the Dashboard overlapping the band); DESIGN.md's token table and contrast figures (verified, reusable as CSS custom properties); EXPERIENCE.md's page map, launch states, component behaviour (details/summary Menu and FAQ, radio currency switch, real table) and accessibility floor; the facts the honesty lens corrected. The spines are the specification of record; this brief overrides them only where § 2 says so.

**What blocks a professional look today, in order.**

| Gap | Effect on the visitor | Where it is fixed |
|---|---|---|
| No layout or CSS of the site's own | Looks like a default blog theme | Build 1 |
| No screenshot of the plugin | Nothing to picture; the product stays abstract | Build 3a (captures); build 1 ships an interim illustration if 3a has not run |
| Copy written as disclaimers | Slow, defensive, no hook | Copy deck, § 3 |
| Lockup not applied anywhere | The icon exists, the brand does not | Build 1 |
| No sharing card, no descriptions, no structured data | Links shared elsewhere show nothing | Build 3b |
| Pricing has no buy path and no FAQ | Pro has no page that sells it | Build 2 |

## 2. Decisions taken by this brief

Owner-delegated "best practice" calls, marked where the owner may override at the plan checkpoint.

- **D1 Copy.** The copy deck in § 3 replaces EXPERIENCE.md's draft strings for this build. Every fact stays as the honesty lens corrected it; the caveats move from the hero and the cards to `/docs/` and the pricing FAQ, where a reader who wants them looks. Stories 53 and 60 may re-sweep later; they replace values, not structure.
- **D2 Lockup as live text.** The header and footer lockups are the icon SVG plus HTML text in the system sans (600 "Post Quality", 400 "Manager"), sized per DESIGN.md's T-units. No outlined-SVG wordmark is produced now; the only rendered brand asset is the sharing card PNG. The outlined artwork stays a `docs/brand.md` item for story 41. Reason: one fewer asset pipeline, accessible text for free, and the plugin's own screens use the system sans too.
- **D3 Both launch states, one flag.** `_config.yml` carries `launched: false`, `version`, `wporg_url` and the nine Freemius pricing IDs. Layouts render both branches; the after-launch branch is checked in a local build with the flag flipped and never published before the launch gates (n8n Cloud walk, story 49's cost example). The release step is then a one-line edit.
- **D4 Real captures from a dedicated demo site.** Four screenshots, made once, on a fresh Local site with fictional posts and seeded reviews (owner's question Q1). Until they exist, build 1 shows an interim illustration rendered from the direction file's HTML Dashboard mock, captioned as an illustration with fictional data, never as a screenshot.
- **D5 Policy prerequisites in build 1.** terms § 3 (what counts as a site, client sites), terms § 4 (cancelling keeps Pro to the end of the paid year), privacy § 3 (interest emails kept until the one launch message, GitHub Pages as host and its IP logs): edited in `docs/policies/` first, copied to the site.
- **D6 The three "settled in a mock" items are settled here.** Menu panel: the band colour, white links in 48 px rows with a faint rule between them, the site action last, full width; the summary "Menu" with a chevron that flips when open. Pack cards: three equal white cards, no highlighted pack (no "most popular" without data), price in the `price` type with the currency code, one primary button on light per card. FAQ summaries: full width, 44 px, navy 700, a plus marker drawn in CSS that turns into a minus when open, hairline between items.
- **D7 Cost example on `/docs/` only.** "A worked cost example is coming" is not on the home page; the home item links to the docs FAQ, which carries that sentence before launch.
- **D8 No new mocks, no new reviews before building.** Build 1's review is the owner looking at screenshots of the built page at 1280, 390 and 320 px.
- **D9 Branches and repositories.** `feat/site-navy-and-check` in both repositories; draft PRs; the owner merges. The UX workspace (this folder) is committed first on `chore/ux-postquality-website` so the brief and the spines are in git.

Added 2026-10-23 after a self-review of the plan against conversion practice, lean UX and UI basics (owner may override, Q5–Q6):

- **D10 Value before requirements.** Home order becomes: hero → Four pillars (what a review gives, screenshot 2) → What you need → How it works → Free and Pro → closing action → author line. The spine put What you need straight under the hero; that honours "no surprise before action" but shows the visitor the hurdles before the value, which maximises early exits. The honesty rule holds as long as What you need precedes every install or buy action, and it still does: the hero's only pre-launch action is an email, and after launch the requirement line sits above the hero buttons.
- **D11 A closing action.** Every landing page repeats its primary action after the last objection is answered. A short tint section before the author line: the ask, the smallest honest reason to say yes, one button. Before launch the reason is the privacy promise itself: one email at launch, then the address is deleted. That promise also joins the hero's action note, because it lowers the cost of giving an email where the decision is made.
- **D12 Fewer lines in the hero.** The status line and the action note merge into one line before launch ("Pre-release. One email when it's installable, then your address is deleted."). The lead opens with the reader's problem and closes with the outcome, so the first screen hooks before it describes.
- **D13 Honest guidance on the packs.** No "most popular" badge (no data), but one line under each pack saying who it is for (one blog; your blog plus client sites; agencies and networks) so Tom can choose without guessing. The yearly price may show its monthly equivalent in muted text ("about 4 a month"), plain arithmetic, no "only".
- **D14 Images as a performance item.** Images are conversion: the hero picture is the first screen. Each capture ships as WebP with a PNG fallback inside `<picture>`, under 250 KB, with `width` and `height` set (no layout shift), `fetchpriority="high"` on the hero image and `loading="lazy"` on the rest.
- **D15 Lean validation without users.** Build 1's acceptance adds a five-second test and a persona read: a fresh-context subagent sees only the first-screen screenshot for the "what is it, for whom, what do I do next" questions, then reads the built page as Lena (no n8n yet) and lists every point of confusion. Findings are fixed before the owner's review, so the owner reviews a page that already passed one cold read.
- **D16 Learning which action converts, without tracking.** Optional (Q6): the interest `mailto:` body carries one visible phrase naming where it was clicked ("from the home page", "from the pricing page"). The visitor sees it and may delete it; nothing is stored or sent by the site. The owner then learns, from the inbox alone, which placement works. One list, one subject, as decided.

## 3. Copy deck

Rules: one idea per sentence; a fact, not a reassurance; verbs on buttons; the full name once per page, then "Post Quality"; no hype, no counts, no urgency. Strings marked **(after)** render only when `launched: true`.

### Header and footer

- Header: lockup B → `/` · Pricing · Documentation · Support · button **Tell me when it's available** / **Get the free plugin** (after, to `/#need`). Phone: lockup B · **Menu**.
- Footer: lockup D · tagline verbatim: "Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin." · Privacy · Terms · Refund · Support · hello@postquality.com · "No cookies, no analytics. Hosted on GitHub Pages." · "© 2026 Vincent Heimann · GPL-2.0-or-later".
- Skip link: "Skip to content".

### Home

**Hero**

- Eyebrow: AI content reviews for WordPress
- H1 (default, the green bar under "fix next"): **Score every post. Know what to fix next.**
  Alternatives for the owner (Q2): A "Every post scored. Know what to fix next." (the mock's). B "Your back-catalog is aging. Know which post to fix next."
- Lead: As a blog grows, older posts quietly go stale, and nothing tells you which one to fix. Post Quality Manager scores every post with AI, inside WP Admin, and ranks what to fix next.
- Buttons: **Tell me when it's available** (mail icon; alternative label for the owner: **Email me at launch**) · **Check what you need** (to `#need`). After: a requirement line above the buttons, "You need your own n8n and an Anthropic API key, each paid separately. What you need →", then **Get the free plugin** · **See Pro pricing**.
- One line under the buttons, before launch: Pre-release. One email when it's installable, then your address is deleted. Or write to hello@postquality.com. After: Version {version}, updated {date}, tested up to WordPress {tested} · What's new → and, on a second line, Start free with Review and Key Takeaways. Pro comes with a 14-day money-back guarantee.
- Ticks: Runs on your own n8n and Anthropic key · Nothing is sent anywhere else · Free plugin, no license needed. After, third tick: Nothing deleted if Pro ends.
- Screenshot 1 caption: The Dashboard in WP Admin. Demo posts, fictional scores. (Interim: An illustration of the Dashboard with fictional data.)

Section order on the page (D10): Four pillars · What you need · How it works · Free and Pro · Closing action · author line.

**Four pillars** (kicker "What a review looks at", h2 "Four pillars, any niche")

- Structure · Readability · Engagement · Credibility
- Every post is scored on the same four pillars, then you get the edits that matter most. Your niche comes in through a Blog Profile and your Editorial Standards, not through code.
- Screenshot 2 caption: A review in Post Reviews. Fictional post.

**What you need** (`id="need"`, kicker "Before you install")

- Lead: Post Quality runs on your own accounts, so you hold the keys. Check these three first.
- Your own n8n — Self-hosted, and reachable from your site and back. No n8n yet? [How to get n8n](/docs/#getting-n8n).
- An Anthropic API key — The setup guide checks it once and stores it in n8n, never in the plugin.
- Your own AI bill — You pay Anthropic per review and your n8n hosting. Cap it with a spend limit in your Anthropic account. [What it costs](/docs/#cost).
- Chips: WordPress 6.6+ · PHP 8.0+ · Single site, no Multisite.
- Fit line: A good fit if you run your own WordPress and can follow a setup guide. Not a fit if you want a hosted service with nothing to set up.
- After: **Install from WordPress.org** · version line · "Opens the plugin's WordPress.org page."

**How it works** (h2 "Three steps to your first score")

1. Connect your accounts — The setup guide asks for three values: your n8n address, an n8n API key and your Anthropic key. It builds the workflow and tests the connection both ways. (Screenshot 3 caption: The setup guide's three values.)
2. Review a post — Click Review. Your site sends the post to your own n8n workflow, which asks Anthropic's models.
3. Fix what matters first — The score, the issues and the suggested edits come back into WP Admin. The Dashboard ranks what to fix next.
- Flow: Your site → Your n8n workflow → Anthropic's models → Back in WP Admin
- Data line (green check): Your posts go only to your own n8n and, through it, to Anthropic. [Privacy](/privacy/).

**Free and Pro** (h2 "Start free. Pro adds the agents and the automation.")

- Free card — No license needed. · Review and Key Takeaways on every post · Dashboard: Fix next and the trend · Post Reviews · Insert into Post · Updates · Email support, best effort. Plan note: Needs your own n8n and Anthropic key. [What you need](#need). Button: **Tell me when it's available**. Footnote: Not installable yet. Opens your email app. After: **Get the free plugin**, footnote "Free, from WordPress.org."
- Pro card — **49** per year, 1 site · USD, EUR or CHF · 99 for 5 sites, 199 for unlimited sites · Plus VAT or sales tax where it applies. · Everything in Free · Rewrite, Sources, SEO and Audit agents · Per-agent model and instructions · Automated Reviews on a schedule · Roles: what each role may do · Email support within 2 business days. Button: **See Pro pricing**. Footnote: On sale with the first public release. After: no footnote.
- Assurance 1: **14-day money-back guarantee** — Full refund within 14 days of buying or renewing, no questions asked. Freemius handles checkout, tax, invoices and refunds.
- Assurance 2: **Nothing deleted if Pro ends** — Your site returns to Free. Reviews, settings, schedules and role rights stay.

**Closing action** (tint section, D11)

- Before launch — h2: Hear about it once. Text: One email when the free plugin is installable, then your address is deleted. No newsletter, no follow-up. Button: **Tell me when it's available**.
- After launch — h2: Start with the free plugin. Text: Review and Key Takeaways on every post, no license. Check what you need, then install from WordPress.org. Buttons: **Get the free plugin** (to `#need`) · **See Pro pricing**.
- Author line, under it: Built by the author and used on real blogs.

### Pricing

- H1: Post Quality Manager Pro pricing. Lead: The free plugin is the trial. Pro adds the agents and the automation, from 49 a year.
- Comparison table, caption "Free and Pro compared", columns Free | Pro: Review and Key Takeaways ✓ ✓ · Dashboard, Post Reviews, Insert into Post ✓ ✓ · Rewrite, Sources, SEO and Audit agents – ✓ · Per-agent model, effort and instructions – ✓ · Automated Reviews on a schedule – ✓ · Roles tab – ✓ · Updates ✓ ✓ · Support: email, best effort | email, within 2 business days. Note under the table: Rewrite needs HTTPS on your site.
- Packs (h2 "Pro, per year"). Before: "49 for 1 site · 99 for 5 sites · 199 for unlimited sites, in USD, EUR or CHF." One button **Tell me when it's available**; note: Not on sale yet. One email when the first public release is out, then your address is deleted. After: the currency switch (legend "Show prices in"), three cards "1 site / 5 sites / Unlimited sites", each with a who-it-is-for line (D13: "One blog." · "Your blog plus client sites." · "Agencies and networks.") and the muted monthly equivalent ("about 4 / 8 / 17 a month"), **Buy for 1 site** etc., under them: Checkout opens on Freemius, the seller of record. Prices exclude VAT or sales tax; Freemius adds it at checkout where it applies. Renews automatically every year at the price you paid; Freemius emails you before. Cancel any time in the customer portal; Pro stays on until the end of the year you paid for. CHF: card only; PayPal in USD and EUR.
- Under the packs, always: The price covers the plugin only. You pay your own n8n hosting and your own AI provider separately.
- Assurance items as on the home page.
- FAQ (details/summary): What else do I pay? · Is tax included? · How do I cancel? · What counts as a site? (terms § 3 wording once updated) · Can one n8n serve several sites? (Yes; the setup guide gives each site its own workflow.) · What happens when my license ends? (the lapse answer)

### Documentation

Sections, each an `h2` with an id: Requirements · Getting n8n (`getting-n8n`: two sentences, self-hosted n8n with a link to n8n's own self-hosting docs; Cloud not named until the walk passes) · Getting started (install, then the three values; screenshot 3) · Set up by hand · Using it (screenshot 4: Post Reviews) · Upgrade to Pro (first line: Never delete the free plugin first: deleting removes your stored reviews and settings. Then download from Freemius's email, upload with "Replace current with uploaded", enter the key; move an activation; cancel; refund; when the license ends) · FAQ (`cost` on the cost question; before launch it ends with "A worked cost example is coming."). The current page's facts are kept; sentences are shortened, nothing is added that is not true today.

### 404

Page not found. There is no page at this address. Home · Pricing · Documentation · **Back to the home page**.

## 4. Owner decisions (2026-10-23)

The owner agreed with every recommended default ("Agree with your default proposals. Finalize the plan, then wait on my order."):

1. **Demo site for the captures (D4):** a fresh Local site, created by the owner; the agent installs the plugin zip of `main`, seeds about 25 fictional posts and reviews, names nothing real.
2. **Headline:** "Score every post. Know what to fix next."
3. **Launch states (D3):** both states behind the flag now.
4. **Lockup (D2):** live text now; the outlined SVG stays with story 41.
5. **Home order (D10):** value first (pillars), then What you need.
6. **Learning without tracking (D16):** one visible phrase in the interest email's body names the page it came from.

Status: the three plans are `ready-for-dev`; the build starts on the owner's order. Still open, unchanged: the trader-identity check before Pro goes on sale (EXPERIENCE.md § Open Questions).
