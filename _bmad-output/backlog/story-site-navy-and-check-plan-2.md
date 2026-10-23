---
title: 'Build 2: the pricing and documentation pages, both launch states'
type: 'feature'
ticket: '86'
created: '2026-10-23'
status: 'ready-for-dev'
route: 'full'
route_source: 'pinned'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/build-brief.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/EXPERIENCE.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `/pricing/` is a Markdown table with no buy path, no currency choice, no guarantee near a button and no FAQ; `/docs/` is one long page with no contents, no "Getting n8n", no "Upgrade to Pro". Neither sells Pro nor reassures the evaluator at the cost question.

**Approach:** On build 1's foundation, build `/pricing/` with the comparison table, the pack section in both launch states (one interest button before launch; a CSS-only USD/EUR/CHF switch, three pack cards and hosted Freemius buy links after), the cost, tax and renewal lines, the assurance items and the FAQ; restructure `/docs/` into the copy deck's sections with anchors, the "Getting n8n" and "Upgrade to Pro" sections, the docs FAQ with the cost anchor, and a drift check against the plugin repository.

**Decisions (owner, 2026-10-22/23; brief § 4):** plain hosted checkout links, nine pricing IDs from `docs/distribution.md`, USD default, nothing stored; both launch states behind `launched`; pack cards with a who-it-is-for line and the monthly equivalent, no highlighted pack; the interest email's body names the pricing page; FAQ and Menu as details/summary; terms § 3 wording copied once build 1's policy edits are merged; n8n Cloud not named.

## Boundaries & Constraints

**Always:** build 1's layouts and CSS (extend `site.css`, no second stylesheet); a real `<table>` at every width with `<caption>`, row and column headers and visually hidden "Included" / "Not in Free" on the marks; currency radios focusable (never `display: none`), `@supports selector(:has(a))` guard with all three currencies shown as the fallback; every price carries its code; one `h1` per page; the pricing cost line under the packs in both states; `launched` branches as in build 1.

**Never:** a highlighted "popular" pack; a checkout script; a stored currency or geolocation; a token limit or n8n Cloud named; an empty Troubleshooting section; policy text changed on the site side; a merge.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Pricing, before launch | `launched: false` | table; packs as one line "49 / 99 / 199 … USD, EUR or CHF"; one interest button + note; cost line; assurance items; FAQ closed | — |
| Pricing, after launch | `launched: true` local build | legend "Show prices in", USD checked; three cards with the price in the shown currency and **Buy for N sites** → `https://checkout.freemius.com/plugin/41144/plan/71340/?pricing_id=<id>` (the exact URL form verified against `docs/distribution.md` and Freemius's docs); buy note, tax and renewal lines, CHF line under CHF | — |
| Switch to CHF | keyboard arrow keys on the radios | the three cards' prices and links change at once; a reload shows USD again | browsers without `:has()`: all three currencies visible and labelled, the switch hidden |
| Phone table | 390 px | the table scrolls inside a labelled `role="region"` with `tabindex="0"`; never reflowed into cards | — |
| Docs anchors | `/docs/#getting-n8n`, `/docs/#cost` from the home page | land on the matching `h2` / FAQ item | — |
| FAQ | click or Enter on a summary | opens one item; marker turns from plus to minus; all closed on load | — |

</frozen-after-approval>

## Code Map

- `postquality.com/pricing.html` -- replaces `pricing.md`; layout default (its own hero-less header block: h1 + lead in the text roles), sections: comparison table (`<caption>Free and Pro compared</caption>`, `<th scope="row">`, `<th scope="col">`, marks via the check symbol or a muted dash + `<span class="vh">`), packs (`{% if site.launched %}` the `<fieldset class="cur">` with three radios `name="cur"` `autocomplete="off"` and the cards inside one ancestor so `:has(#cur-chf:checked) .price[data-cur="chf"]` shows; each card: name, the who-it-is-for line (brief D13), price `data-cur` × 3, "per year" with the muted monthly equivalent, feature line, buy link with `<span class="vh"> in CHF</span>`; `{% else %}` the one-line prices + interest button + note, the `mailto:` body phrase "from the pricing page" if the owner takes D16), the cost line, assurance items (reuse build 1's markup), FAQ (`<details class="faq">`, question in `<summary>`, answers from the copy deck; "What counts as a site?" quotes terms § 3 as merged).
- `postquality.com/_data/pricing.yml` -- packs (sites, price) and the nine pricing IDs by currency and pack, read by the cards; the checkout base URL from `docs/distribution.md` (plugin 41144, plan 71340).
- `postquality.com/docs.md` -- front matter `layout: page`; body restructured: a contents list; `## Requirements`; `## Getting n8n {#getting-n8n}` (two sentences, link named "Self-hosting n8n (docs.n8n.io)"); `## Getting started` (`{% if site.launched %}` Install from WordPress.org + version line `{% else %}` the interest sentence; then the three values, `{% include screenshot.html %}` setup.png, the n8n API key sentence shortened, HTTPS line); `## Set up by hand`; `## Using it` (screenshot 4 `review-list.png`, interim: none until build 3a; the include renders only if the file exists via `{% if site.static_files | where: "path", ... %}` or a `screenshots` flag in `_config.yml`); `## Upgrade to Pro` (ADR 0014 steps, starting with "Never delete the free plugin first"); `## FAQ` with the cost item carrying `id="cost"` and, before launch, "A worked cost example is coming."
- `postquality.com/assets/css/site.css` -- add: comparison table, the scroll region, the currency switch (44 × 44 tabs, selected navy fill + check glyph, borders kept in forced colours, `input:focus-visible + span` ring), pack cards (D6), FAQ summary (plus/minus marker in CSS, 44 px, hairline rules), docs contents list, in-page anchor offset for the band header.
- `post-quality-manager/docs/distribution.md` -- the hosted-checkout URL form per pricing ID recorded once (verify on Freemius's documentation: `pricing_id` parameter on the plan checkout URL), the sandbox link kept.
- Drift check: a scratchpad script compares the site's `docs.md` Requirements, Set up by hand and FAQ answers with their sentences in the plugin repository (`post-quality-manager/readme.txt` FAQ and the setup copy in `src/setup/steps.js` labels: "Connect your n8n", "Set up the workflow", "Anthropic API key"); the comparison method and the result go into Implementation Notes and the site README.
- Verification: local build in both flag states; screenshots of `/pricing/` (both states, 1280 and 390 px, CHF selected once) and `/docs/` (1280, 390) in the scratchpad; the FAQ and switch walked by keyboard in headless Edge is not possible, so the markup is checked by the crawler script (radios present, not hidden, legend, `details` closed, caption and scopes).

## Tasks & Acceptance

**Execution:**
- [ ] `postquality.com/_data/pricing.yml` -- packs, IDs, checkout base -- data once.
- [ ] `postquality.com/pricing.html` (delete `pricing.md`) -- table, packs both states, lines, assurance, FAQ -- AC1, AC2.
- [ ] `postquality.com/docs.md` -- sections, anchors, Getting n8n, Upgrade to Pro, FAQ with `#cost` -- AC3.
- [ ] `postquality.com/assets/css/site.css` -- table, switch, cards, FAQ, contents -- AC1–AC3.
- [ ] `post-quality-manager/docs/distribution.md` -- checkout URL form -- record.
- [ ] Drift check + verification; evidence and screenshot paths in Implementation Notes.

**Acceptance Criteria:**
- Given `launched: false`, when `/pricing/` is built, then no `checkout.freemius.com` link exists on the site and the one button is the interest `mailto:`.
- Given `launched: true` locally, when CHF is selected, then the three buy links carry the CHF pricing IDs 97905, 97906, 97907 and the USD and EUR prices are not visible.
- Given the home page's two docs links, when followed, then `#getting-n8n` and `#cost` exist on `/docs/`.
- Given the owner's screenshot review (hitl), when they approve, then the draft PR is opened.

## Implementation Notes

## Plan Change Log
