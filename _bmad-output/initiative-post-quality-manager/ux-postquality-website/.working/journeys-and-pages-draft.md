# Visitor journeys and page map: draft for the owner (2026-10-22)

_Drafted by the facilitator at the owner's request ("Go ahead"), from `docs/user-paths.md` (path A, stages A1–A5), the decisions in `.memlog.md` and `sources-extract.md`. Not decided until the owner confirms. `[ASSUMPTION]` marks what the facilitator filled in; `[OPEN]` marks what only the owner can answer. Look: direction 2, Navy and check._

## The visitors

- **Lena, solo tech blogger** (the name already used in the n8n UX spine). 240 posts since 2016 on a self-hosted WordPress; has **no n8n yet**, but follows setup guides without trouble (owner, 2026-10-22, best practice: design for the hardest common case, the A2 drop-off). Their problem (A1): older posts drift, and the SEO plugin scores keywords, not whether a post is still good.
- **Tom, freelance marketer** `[ASSUMPTION]`. Runs their own blog plus two client blogs; has used the free plugin for three weeks and wants Automated Reviews and Rewrite on all three sites.

## Journey 1: Lena checks whether they can run it (today, before launch)

1. Evening, at the desk. Lena lands on the home page from a search or a link `[ASSUMPTION: how visitors find the site is outside the site; A1's discoverability gap]`.
2. **First screen:** "Every post scored. Know what to fix next." and the Dashboard picture with a Fix next list. Lena recognises their own back-catalog in it.
3. The three ticks under the buttons answer the first doubts at a glance: runs on your own n8n and Anthropic key; your posts go only there; the free plugin is the trial.
4. Lena scrolls to **What you need**: an n8n instance (n8n Cloud or self-hosted, either works; "No n8n yet?" points to how to start one), an Anthropic API key, you pay Anthropic per review on your own key, WordPress 6.6+ and PHP 8.0+. This is the A2 moment: Lena has no n8n, so the doubt is answered and the way forward shown **before** any install, not after.
5. The review picture with the four pillars and Top edits shows what one review gives.
6. Free vs Pro and a look at `/pricing/`: 49 a year, 14-day money-back guarantee, nothing deleted if Pro stops.
7. **Climax:** "I can run this, and I want it." Lena clicks **Tell me when it's available**; the email app opens with the subject filled in; the line under the button says they will hear once, at launch.
8. Honest exit: a visitor without n8n who does not want one leaves informed at step 4, instead of installing and bouncing in WP Admin.

## Journey 2: Lena installs the free plugin (after launch)

1. The launch email arrives; Lena opens the home page again.
2. Same first screen; the primary button now reads **Install the free plugin**, with "Version X, updated <date>" beside it and the "What you need" line above it.
3. The button leads to the plugin's WordPress.org page, or to its GitHub release if the repository goes public first. This is decided (story 57, 2026-10-22): until one of the two exists, the free action stays the interest email, so the install button never points to nothing.
4. Lena opens **Documentation → Getting started**: install, then the setup guide's three values (n8n address, n8n API key, Anthropic key), one screenshot per step. The text is copied from the plugin repository, so it matches the plugin.
5. **Climax on the site:** a confident install with no surprise left. The real "aha" (the first score) happens in WP Admin, which is the plugin's job (A5).

## Journey 3: Tom buys Pro for three sites (after launch)

1. In WP Admin, Tom clicks a locked Pro button ("Available with Pro") or the plugin's upgrade link, and lands on `/pricing/` `[ASSUMPTION: the plugin links to the pricing page]`.
2. Tom works in Switzerland and clicks the **CHF** tab; the three packs show 49 / 99 / 199 CHF.
3. Three sites → the **5 sites** pack.
4. Beside the buy buttons: 14-day money-back guarantee, renews at the same price, and if Tom stops paying, the sites return to Free and nothing is deleted.
5. The pricing FAQ answers the rest: the AI cost is separate (Anthropic, on your key); Freemius sells and invoices, VAT included; what counts as a site.
6. **Climax:** Tom clicks **Buy for 5 sites**; Freemius's hosted checkout opens in CHF.
7. After: the license key goes into the plugin; Pro unlocks.

## Journey 4 (short): someone needs help

A Pro customer whose test ping fails opens **Support** from the header, reads what to include (plugin and workflow versions, WordPress and PHP versions, what they did), and writes to hello@postquality.com. A security problem is reported privately by the same address.

## Page map

| Page | What the visitor wants to know | Primary action today | Primary action after launch | Main blocks |
|---|---|---|---|---|
| `/` Home | Is this for me, and can I run it? | Tell me when it's available (mailto) | Install the free plugin | Navy hero + Dashboard picture, ticks, What you need, what a review gives (pillars), how it works in 3 steps, Free vs Pro teaser, trust line (built and used on the author's own blogs) |
| `/pricing/` | What does Pro cost, and is buying safe? | Tell me when it's available (same mailto) `[ASSUMPTION]` | Buy for 1 / 5 / unlimited sites, in the chosen currency | Free vs Pro comparison (cards on phones), USD / EUR / CHF tabs, three packs, guarantee + renewal + lapse beside the buttons, pricing FAQ |
| `/docs/` | How do I set it up and use it? | — (the interest mailto in the install section) | — | Requirements, Getting started (screenshot per step), Set up by hand, Using it, FAQ, Troubleshooting `[OPEN: content does not exist yet]` |
| `/support/` | Where do I get help? | Email hello@postquality.com | same | Support policy (copy of `docs/policies/support.md`) |
| `/privacy/`, `/terms/`, `/refund/` | The rules | — | — | Policy copies, unchanged |
| 404 | I am lost | Back to the home page | same | Links to Home, Pricing, Documentation |

**On every page:**
- Header: logo B, then Pricing, Documentation, Support, and the primary action as a small button on desktop `[ASSUMPTION]`. On phones: logo B and a "Menu" button that opens the links, with no script `[ASSUMPTION]`.
- Footer: the tagline, the four policy links, hello@postquality.com, and "This site sets no cookies and runs no analytics."

## Plugin pictures (story 57 question 8) `[ASSUMPTION]`

Four screenshots, in this order:
1. Dashboard, in the home hero.
2. A review with the four pillars and Top edits, on the home page.
3. The setup guide's three values, on the home page and in Getting started.
4. Post Reviews list, in the docs.

No video or click-through tour for now: they add work, and a hosted player would load a third party. Every picture is made once on the Local sandbox with demo content and reused for the WordPress.org listing, the README and Freemius.

## Check: every need has a page, every page has a journey

- **Covered:** see the product, requirements, cost, privacy, currency, guarantee and lapse, install, buy, help, security report, interest, version, being lost.
- **Gaps:**
  - the troubleshooting text (to be written in the plugin repository from the plugin's own failure messages, then copied like the rest of the docs);
  - the worked cost example (story 49, waiting);
  - the measure of success. With no analytics, the only signals are the number of interest emails and Freemius sales; this replaces story 57's question 10.
