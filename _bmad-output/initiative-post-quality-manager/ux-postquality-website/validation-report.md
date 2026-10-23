# Validation Report — postquality.com (Post Quality Manager website)

- **DESIGN.md:** `post-quality-manager/_bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md`
- **EXPERIENCE.md:** `post-quality-manager/_bmad-output/initiative-post-quality-manager/ux-postquality-website/EXPERIENCE.md`
- **Run at:** 2026-10-22T17:21+02:00

## Overall verdict

A strong contract for the home page. Every colour token has a hex value and a contrast ratio, and the rubric walker recomputed all of them. Every `{path}` reference resolves. All four journeys and story 57's eleven audit questions map to flows and components. Five of the six `[NOTE FOR UX]` markers are genuinely settled by the planned mocks.

It is not yet a complete build contract for the other pages. The "standard page layout" that the policy pages, `/support/` and 404 rely on is defined nowhere, and no mock will draw it. `/pricing/`'s Free vs Pro comparison has no spec in either spine. Fix those two, decide the Version line and define the mixed launch states before the site build story starts.

The two extra lenses shift the picture from "nearly buildable" to "not shippable as written". The honesty and conversion review found four critical strings that are false today or hide a cost: "VAT included", "a quota and a token limit cap the spend", "n8n Cloud or self-hosted, either works", and cost lines that never mention n8n's own bill. All four sit where Lena decides at A2 and where Tom reaches checkout. Renewal, cancellation and the Pro upgrade path are also missing. The accessibility review found no critical issue and a sound palette, but four high gaps that would fail WCAG 2.2 AA if built as drawn: inline links told apart by colour alone, a header that cannot reflow at 320 px or fit between 760 and about 965 px, a currency-switch mechanism whose usual recipe drops keyboard access, and a Menu panel that can hide the focused element. No reviewer questions the chosen direction. Every finding resolves as a spine line, a CSS rule, an owner decision or a build check.

Since the reviews ran, the live site's cost FAQ, which carried the same false spend-cap claim, has been fixed in postquality.com draft PR #2. "VAT included" was the facilitator's drafting error in the journeys draft and never appeared on the live site (memlog, Reviewer Gate entry). The three reviews hold 84 findings: 4 critical, 12 high, 27 medium and 41 low. After deduplication, `triage.md` classifies 70 of them.

## Category verdicts

- Flow coverage — strong
- Token completeness — strong
- Component coverage — adequate
- State coverage — adequate
- Visual reference coverage — adequate
- Bloat & overspecification — adequate
- Inheritance discipline — strong
- Shape fit — strong

Extra reviewers (no verdict scale): Accessibility, WCAG 2.2 AA floor: 0 critical, 4 high, 8 medium, 18 low. Honesty & conversion: 4 critical, 6 high, 13 medium, 9 low.

## Findings by severity

Ids follow each review's own order: rubric ids number the rubric's findings by severity in the order they appear (H1–H2, M1–M6, L1–L14); a11y and honesty ids are the reviewers' own. `triage.md` deduplicates and classifies them.

### Critical (4)

**[Honesty & conversion]** honesty C1 — "VAT included" is false; Freemius adds tax on top (EXPERIENCE § FAQ row; Flow 3 step 5; Plan card prices; the direction file's Pro card)  
Freemius shows net prices and adds EU/UK VAT for consumers and US sales tax at checkout (a $100 plan becomes $120 for a French consumer). A price page with buy buttons is an "invitation to purchase" (UCPD Art. 7(4)(c)): it must show the price with taxes or how tax is calculated. "VAT included" while 49 becomes about 58.8 at checkout is a misleading omission plus a false statement. Nothing near the buy buttons says the visitor leaves for Freemius or that tax is added there.  
Fix: FAQ "Is tax included?": prices are before tax, Freemius adds VAT or sales tax at checkout and shows the total before payment; an EU VAT number removes VAT. An after-launch note under the buy buttons. "per year, before tax" on the Pro card. Run the sandbox checkout once per currency first; Swiss VAT on CHF sales is not documented anywhere on record.

**[Honesty & conversion]** honesty C2 — "A quota and a token limit cap the spend" is not true today (EXPERIENCE What you need (third item); Voice and Tone; Flow 1 step 4; the direction file)  
No token limit exists: ADR 0009's site-wide limit is "decided, not built". The Monthly Quota covers only Authors on Free ("Editors and Administrators have no limit"). Lena is a solo Administrator, so nothing in the plugin caps her spend. The spine answers the A2 cost objection with a feature that does not exist.  
Fix: "You pay Anthropic per review, on your own key. To cap the bill, set a monthly spend limit in your Anthropic account. In the plugin, a monthly quota limits how many reviews each Author can run." Mention the token limit only once stories 25/29 ship it; add that string to the drift table.

**[Honesty & conversion]** honesty C3 — n8n's own cost is left out: a hidden cost at the A2 moment (EXPERIENCE What you need, Requirement line, Plan card, pricing FAQ; Flow 1 step 4; Flow 3 step 5)  
Terms § 2: "the price covers the plugin only. You pay your own n8n hosting and your own AI provider separately". The spines name only the Anthropic bill. Lena has no n8n; whichever route she takes, she pays for it, and that bill can be larger per month than Pro is per year. Leaving it out of every cost line is the hidden-cost pattern the owner ruled out.  
Fix: What you need: "n8n is a cost of its own: an n8n Cloud plan, or the server you run self-hosted n8n on." Requirement line: "…each paid separately." Terms § 2 word for word directly under the pack cards. FAQ "What else will I pay for?" names both bills. Never quote n8n's prices; link n8n's pricing page from Getting n8n.

**[Honesty & conversion]** honesty C4 — "n8n Cloud or self-hosted, either works" is untested, and the setup guide cannot run on an n8n Cloud trial (EXPERIENCE What you need; Flow 1 step 4; the direction file; memlog 24)  
`docs/user-paths.md` says the n8n Cloud path is untested (no Cloud instance). The setup guide's first value is an n8n API key, and n8n Cloud does not offer its public API during the free trial. Lena will pick the Cloud trial as the easy start, install, and bounce at the guide's first step: the exact A2 failure the page exists to prevent.  
Fix: Walk the Cloud path on a paid plan before launch. Until then: "Tested with self-hosted n8n. On n8n Cloud, the setup guide needs n8n's API, which Cloud includes on paid plans but not during its free trial." Restore "either works" only after the Cloud walk passes.

### High (12)

**[Component coverage]** rubric H1 — "The standard page layout" is cited but defined nowhere (EXPERIENCE § Page map; memlog 41)  
`/privacy/`, `/terms/` and `/refund/` cite it, and `/support/` and 404 rely on it implicitly. The memlog keeps these pages out of every mock. Missing: the page title off the home page (the Typography table maps every h1 to `display` 64 px, with no band and no surface named), an h3 role (`privacy.md` has three h3s), list spacing, the prose measure and the 404 heading.  
Fix: Add a "Text page" row to DESIGN § Components and EXPERIENCE § Component Patterns: header; page title (role, size, surface); body `{typography.body}` at a maximum measure; h2 `headline`; h3 `title`; lists; footer. Or say these pages reuse the `/docs/` mock's layout and name the parts they take.

**[Component coverage]** rubric H2 — `/pricing/` opens with a Free vs Pro comparison that no spine specifies (EXPERIENCE § Page map; § Responsive & Platform)  
It is the first block on the main conversion page and lies outside the `/pricing/` mock's listed scope (memlog 41: currency switch, pack cards, FAQ). Its checks carry meaning, which collides with the Accessibility Floor rule that checks are decorative and hidden from assistive technology.  
Fix: Add a "Comparison table" component: visual spec (header row, `{colors.hairline}` row rules, included and not-included marks), the phone card form, and a behaviour rule (real `<th scope>` headers, a text equivalent for every mark: "Included" / "Not in Free"). Add it to the `/pricing/` mock's scope.

**[Accessibility]** a11y H1 — Inline links are told apart by colour alone (1.4.1 Use of Color) (DESIGN § Colors `navy`; § Components Requirement line, Footer; EXPERIENCE § State Patterns "Hover and focus")  
Navy #1b2a41 link text against ink-body #2a3649 body text is 1.18:1; against ink-muted it is 1.97:1. A colour-only cue needs 3:1 to the surrounding text plus a non-colour cue on hover and focus (G183). In the requirement line and the Pro card, the link is white in white text, so it differs only by weight. No rule says links in running text are underlined at rest.  
Fix: Links inside text blocks are always underlined, on light and dark surfaces and in the Markdown output of `main` (`text-decoration: underline; text-underline-offset: .15em`). Only the header nav, the footer policy list and links styled as buttons may drop the underline.

**[Accessibility]** a11y H2 — The header does not fit at 320 px, nor in its desktop form between 760 and ~965 px (1.4.10, 1.4.4, 1.4.12) (DESIGN § Layout & Spacing, § Lockup, § Header; EXPERIENCE § Responsive `min-viewport`; tokens `header-height`, `min-viewport`, `bp-phone`)  
1.4.10 is tested at 320 CSS px. The phone row needs lockup B (~210 px), the labelled 44 px Menu (~85 px) and a gap: ~300–310 px against 288 px available. The spine's own figure is 340 px, and the rules close every way out (no F in the header, D only as a second mention, a fixed 64 px height). The desktop row needs ~865 px of content, so a ~965 px viewport, yet the Menu takes over only below 760 px. From 760 to ~965 px the header overflows or wraps inside a fixed 64 px strip and overlaps the hero: that is 200 % zoom on 1536–1920 px screens.  
Fix: Make 64 px a `min-height` and give the row `flex-wrap: wrap`. Move the switch to the Menu up to the width the desktop row really needs (probably `bp-medium`, checked with the after-launch label). Below 360 px the header wraps: lockup B on the first line, Menu on the second. Floor: "320 px with no horizontal scroll; 360 px is the design target". Test at 320 px, at 400 % on 1280 px, and at 200 % on 1600 and 1920 px.

**[Accessibility]** a11y H3 — Currency switch: the mechanism is unspecified, and the usual CSS-only recipe breaks keyboard and screen-reader use (EXPERIENCE § Component Patterns "Currency switch", § Accessibility Floor; DESIGN § Currency switch)  
"CSS tabs" recipes usually put `display:none` on the inputs, so the switch then works with a mouse only. "Hidden currencies use `display: none`" is easy to apply to the inputs by mistake. With the `~` selector, a fieldset around the radios alone is impossible. Nine buy links share three names. The Pro figures are identical in every currency, so "49" without its code is ambiguous. A tint fill for the selected tab would be 1.09:1. Target size is not set.  
Fix: `<fieldset><legend>Show prices in</legend>` with one labelled radio per currency (`checked autocomplete="off"` on USD), before the plans. Keep every input focusable (`appearance:none`, or the clip pattern with `input:focus-visible + span`); never `display:none`, `visibility:hidden`, `hidden` or a zero-size box. Drive the swap with `:has()` from a shared ancestor, guarded by `@supports selector(:has(a))` (fallback: all three currencies shown, labelled, switch hidden). `display:none` on both the price and the buy link of each hidden currency. Currency in every price's text and every buy link's name. Selected tab: navy fill, white text, plus bold or a check; unselected: white with a 1.5 px navy border. No `role="tablist"`.

**[Accessibility]** a11y H4 — The open phone Menu can cover the element that takes focus next (2.4.11 Focus Not Obscured) (DESIGN § Menu disclosure; EXPERIENCE § Component Patterns "Menu disclosure")  
The usual dropdown positions the panel over the page, and nothing can close it when focus leaves: no script, and `<details>` does not close on Escape or blur. Tabbing past the last item moves focus into `main`, under the panel; on `/docs/`, `/pricing/` and the policy pages the first focusable element sits right under the header and is hidden completely. "Closed on every page load" also fails after Back: the back/forward cache restores it open.  
Fix: Specify the panel in normal flow: the open `<details>` pushes the page down, no absolute or fixed position, no overlay. Add: "the open panel sits in flow, full width under the header row, never as an overlay".

**[Honesty & conversion]** honesty H1 — The trust line overclaims what reaches the author (EXPERIENCE Trust line; Honest nudges)  
Privacy (short version, § 2) adds "unless you opt in to usage data or buy a Pro license". With a license, Freemius gets the site address, version and key, and the author gets the buyer's name, email and license details. Dropping the condition turns a true statement into a false one, on the page that sells Pro.  
Fix: "Your content goes only to your own n8n and, through it, to Anthropic. The author receives nothing from your site unless you opt in to usage data or buy Pro."

**[Honesty & conversion]** honesty H2 — Renewal and cancellation are not disclosed enough to meet auto-renewal rules (EXPERIENCE Plan card row; the direction file's Pro card; Flow 3 step 4)  
"Renews at the same price" never says automatically, never mentions the reminder, never says how to cancel. ROSCA and state auto-renewal laws want automatic renewal, its price and the cancellation method stated before purchase; EU dark-pattern guidance flags easy-to-start, opaque-to-stop subscriptions. Terms § 4 does not say whether cancelling the renewal keeps Pro until the paid year ends.  
Fix: Beside the buy buttons: "Renews automatically every year at the price you paid. Freemius emails you before each renewal. Cancel the renewal any time in the customer portal." A FAQ "How do I cancel?". The owner first settles terms § 3/§ 4.

**[Honesty & conversion]** honesty H3 — After launch, the header's install button comes before the prerequisites on every page (EXPERIENCE § On every page, Header, Menu disclosure, What you need; memlog 31)  
The header sits above the hero in reading and tab order, so its "Install the free plugin" is the first install action on `/` and the only one on every other page, with no prerequisite near it. The spine's central A2 rule ends up false for the most visible button on the site.  
Fix: After launch the header button reads "Get the free plugin" and leads to `/#need`; the block ends with "Install from WordPress.org" and the version line. Direct install links stay only where prerequisites precede them.

**[Honesty & conversion]** honesty H4 — Upgrading to Pro means replacing the plugin, and deleting it destroys the reviews (EXPERIENCE Flow 3 step 7; `/docs/` blocks; Assurance item and ticks)  
ADR 0014: the customer downloads the Pro build from Freemius and replaces the plugin. Privacy § 1: deleting the plugin removes the table and the settings. Tom used Free for three weeks; if he deletes it before uploading Pro, which many people do, he loses every review, and the site never warned him. The same trap applies on lapse.  
Fix: Flow 3 step 7: upload the Pro build and choose "Replace current with uploaded", then enter the key. A `/docs/` section "Upgrade to Pro and manage your license" whose first line is "Don't delete the free plugin first: deleting removes your stored reviews and settings." Verify the real flow on the Local sandbox.

**[Honesty & conversion]** honesty H5 — "What counts as a site" is promised but has no source of truth (EXPERIENCE FAQ row; Flow 3 step 5; Story 57 table)  
Terms § 3 says only 1, 5 or unlimited activations, movable in the customer portal. Staging and local copies, client sites and changed addresses are not covered. Tom buys on this answer; the FAQ would be written from nothing, against the "policies first" rule.  
Fix: The owner decides, terms § 3 states it, the FAQ copies it. Add the staging/local rule only after checking how Freemius counts those activations.

**[Honesty & conversion]** honesty H6 — Launch would leave "A worked cost example is coming" at the drop-off point (EXPERIENCE What you need, Voice and Tone, Content sources, State Patterns)  
User-paths A2 names missing cost guidance as the reason "many evaluators bounce". After launch the placeholder sits beside a live install button, so the biggest known objection stays unanswered at the moment of action. No state row makes story 49 a condition of the switch.  
Fix: Make story 49 a condition of "Free installable". Until then, a figure-free line: cost depends on length, model and agents; Anthropic lists its prices on its own page.

### Medium (27)

**[Flow coverage]** rubric M1 — Getting started promises "one screenshot per step", but § Screenshots gives it only screenshot 3 (EXPERIENCE § Page map, § State Patterns, § Key Flows Flow 2, § Screenshots)  
The promise appears in three places, but § Screenshots fixes four screenshots (owner decision, Q8; memlog 27). The capture session on the Local sandbox and the `/docs/` build will disagree on how many images exist.  
Fix: Say Getting started shows screenshot 3 once and drop "per step", or add per-step captures (3a–3c) with the owner's agreement, since the four-screenshot list is an owner decision.

**[Component coverage]** rubric M2 — In-text links have no spec, and colour alone cannot mark them (DESIGN § Colors, `navy` row; § Components (no text-link row))  
Navy against `ink-body` is 1.18:1 and against `ink-muted` 1.97:1. That fails WCAG 1.4.1, and Lighthouse's link-in-text-block audit would flag it under the story's "no accessibility error on `/` and `/pricing/`" criterion. Affected: "No n8n yet?", the plan note link, the Requirement line link, and every link in the docs and policy text.  
Fix: Add a Text link row: links in running text are always underlined (give offset and thickness), with a hover rule. Name the places that drop the underline: header nav, footer nav, buttons.

**[Component coverage]** rubric M3 — The Version line needs a decision now, not a mock (DESIGN § Components → Version line; EXPERIENCE § Open Questions 1; § State Patterns first row; § Component Patterns → Hero)  
Its before-launch form, "Pre-release, not installable yet", ships in today's state on `/` but is missing from the hero order. With the action note and the Free card footnote, `/` would say "not installable yet" three times.  
Fix: Decide the position (for example directly under the actions, before the action note), the type and colour (`{typography.caption}` in `{colors.ink-muted-on-dark}` on the band), and whether the action note keeps its "Not installable yet." opening.

**[State coverage]** rubric M4 — The two mixed launch states are undefined (EXPERIENCE § State Patterns intro and rows)  
The spine says the free install and the buy buttons switch on independent triggers, yet the rows cover only "neither", "Free installable" and "Pro on sale". With Pro on sale and the free plugin not installable, the home Pro card's footnote "Pro goes on sale with the first public release" becomes false and the hero still asks for the interest email. With the free plugin installable and Pro not on sale, the `/pricing/` interest button still sends "Tell me when Post Quality Manager is available" about a plugin that already is.  
Fix: Add a 2 × 2 table (free × Pro). For each cell give the header action, hero actions, card footnotes, `/pricing/` action and the interest subject.

**[Visual reference coverage]** rubric M5 — Every composition reference lives in the facilitator's scratch folder (DESIGN and EXPERIENCE preambles; EXPERIENCE § Component Patterns → Plan card)  
All references sit in `.working/`, and so does the Plan card's content pointer ("per sources extract § Product facts"). If `.working/` is pruned at Finalize or not handed to the build story, those links break.  
Fix: At Finalize, copy `direction-navy-and-check.html` and `logo-lockups.html` into `imports/` (or `mockups/`) and relink. Write the Free and Pro feature lists into the Plan card row instead of pointing at the extract.

**[Visual reference coverage]** rubric M6 — `logo-lockups.html` contradicts the spine for the 404 page and the footer (DESIGN and EXPERIENCE preambles; EXPERIENCE § Inspiration & Anti-patterns)  
The 404 mock draws a white header with a grey rule, WP-blue (#2271b1) links and a 28 px / 600 heading, and the sheet tests a light footer on #f0f0f1. The "Overridden in the chosen direction" list covers only the direction file, and DESIGN gives the 404 nothing visual beyond icon F at 64 px. A builder will copy the sheet.  
Fix: List the sheet's overrides next to its link (header = band strip, links navy, footer white) and specify the 404 inside the Text page row.

**[Accessibility]** a11y M1 — Interest buttons do not say they open the email app (2.4.4 Link Purpose) (EXPERIENCE § IA "Where the actions lead", Component Patterns Header, Primary button, Action note; § Accessibility Floor; DESIGN § Primary button)  
The explanation sits in its own paragraph after both hero buttons, outside the link's context as 2.4.4 defines it; the mail icon is `aria-hidden`. The header copy (every page), the Free card copy and the `/pricing/` copy have no note at all. A keyboard or screen-reader user lands in a mail client, or in nothing.  
Fix: Append visually hidden text after the visible label ("Tell me when it's available<span class="vh"> (opens your email app)</span>"), or use `aria-describedby` to the action note in the hero. The Free card's footnote becomes "Not installable yet. Opens your email app."

**[Accessibility]** a11y M2 — The focus-ring colour must follow the surface, including dark blocks inside light sections (DESIGN § Focus ring; EXPERIENCE § Component Patterns "Focus ring")  
The navy Pro card, the navy-hi requirement line and a dark Menu panel sit inside light sections. A rule set per section draws a navy ring around "See Pro pricing" on the navy card: 1.00:1, invisible.  
Fix: Each surface sets a custom property: `:root { --ring:#1b2a41 } .on-dark { --ring:#fff }`, then `:focus-visible { outline:3px solid var(--ring); outline-offset:2px }`. Put `.on-dark` on the header strip, hero, Pro card, requirement line and any dark panel, and list them in DESIGN.

**[Accessibility]** a11y M3 — One alt for two different crops (1.1.1) (EXPERIENCE § Screenshots row 1, § Component Patterns "Screenshot frame"; DESIGN § Screenshot frame)  
A responsive `<picture>` carries one `alt`. The desktop image shows the 8-week trend and five Fix next rows; the phone crop shows neither. An alt written for the desktop image describes content the phone image does not have.  
Fix: Write the alt for what both crops show (average 71; 3 Major revision, 9 Needs revision, 14 Not analyzed, 22 Ready to ship; Fix next led by a post scored 42) and put the trend in the caption if it matters, or use two `<img>` elements switched by media query, each with its own alt.

**[Accessibility]** a11y M4 — Tables that "become cards on phones" lose their semantics (1.3.1) (EXPERIENCE § Page map `/pricing/`; § Responsive & Platform last bullet)  
Restyling a `<table>` with `display:block`, `grid` or `flex` removes table semantics in Safari/VoiceOver and some Chromium versions; `data-label` headers are announced inconsistently. Phone users hear a run of "Yes", "No", "49" with no header.  
Fix: Either build the comparison as plan cards at every width, or keep a real `<table>` with `<th scope>` at every width, scrolling sideways on phones inside `<div role="region" aria-labelledby="…" tabindex="0">`. Never reflow a table into cards with CSS alone.

**[Accessibility]** a11y M5 — No target size for the header button, the currency tabs or the FAQ summaries (2.5.8) (EXPERIENCE § Accessibility Floor "Targets"; DESIGN § Header, § Currency switch, § FAQ)  
Only buttons (52 px) and the Menu (44 px) have a size.  
Fix: Add "every target at least 24 × 24 CSS px; design target 44 px". Header small button 40–44 px; currency tabs at least 44 × 44; FAQ summaries full width, min-height 44 px; header nav links padded to 44 px. Keep the footer links' 8 px row gap.

**[Accessibility]** a11y M6 — "No n8n yet?" does not say where it goes (2.4.4) (EXPERIENCE § What you need block; memlog (Getting n8n); Flow 1 step 4)  
A question as link text names neither the action nor the destination; in a links list it reads "No n8n yet?", and a visitor could expect n8n's own site. It sits at the A2 drop-off point, where being clear matters most.  
Fix: "No n8n yet? <a href="/docs/#getting-n8n">How to get n8n</a>". On `/docs/`, outbound links name their destination: "n8n Cloud (n8n.io)", "Self-hosting n8n (docs.n8n.io)".

**[Accessibility]** a11y M7 — Long strings on `/docs/` and the policy pages at 320 px (1.4.10, 1.4.12) (EXPERIENCE § Page map `/docs/`; § Content sources and drift checks)  
n8n and webhook URLs, inline `code` and `pre` blocks do not wrap, so the whole page scrolls sideways at 320 px. "Documentation" at 36 px takes ~250 px of the 288 px column once text spacing is raised.  
Fix: `main { overflow-wrap: anywhere }`; `pre { white-space: pre-wrap }` or a `pre` scrolling in its box with `tabindex="0"` and a name; `h1, h2 { overflow-wrap: break-word; hyphens: auto }`.

**[Accessibility]** a11y M8 — Lighthouse-only acceptance misses most of this review (EXPERIENCE § Accessibility Floor, first bullet)  
Lighthouse runs a subset of axe on two pages. It does not test keyboard use of the currency switch, focus hidden under the Menu, reflow at 320 px, text spacing, mailto link purpose or alt quality.  
Fix: Lighthouse/axe without error on every page (404 included), with the Menu open and each currency selected; plus a manual pass: keyboard only, 320 px and 400 % at 1280 px, 200 % at 1600 px, a text-spacing bookmarklet, NVDA + Firefox and VoiceOver iOS, Windows forced colours.

**[Honesty & conversion]** honesty M1 — The ticks claim things that are not yet true, or that do not fit the visitor (EXPERIENCE Ticks, Action note; direction file hero)  
Before launch, "The free plugin is the trial" is a promise shown with a check mark, the site's sign for "confirmed". After launch, "Nothing deleted if Pro lapses" is a Pro reassurance shown to someone deciding whether to install Free. "The free plugin is the trial" also overstates: Free does not include Rewrite or Automated Reviews.  
Fix: Before launch: "A free plugin to try it first". After launch: "Free: Review and Key Takeaways, no license". Keep "Nothing deleted if your license ends" on `/pricing/` and the Pro card. After-launch note: "Start free with Review and Key Takeaways. Pro comes with a 14-day money-back guarantee."

**[Honesty & conversion]** honesty M2 — The demo Dashboard implies a result (Direction file screenshot 1 ("63 → 71"); EXPERIENCE § Screenshots row 1)  
A fictional trend climbing eight points in eight weeks, captioned only "Demo posts, fictional", reads as "use it and your scores rise": an outcome claim with no owner-verified evidence. The FTC treats demonstrations as implied typical results.  
Fix: A flat or uneven line, or crop it out. Caption: "The Dashboard in WP Admin. Demo posts and scores, fictional; not a measured result."

**[Honesty & conversion]** honesty M3 — The h1 "Every post scored." implies automatic, free, whole-catalog scoring (Direction file h1; EXPERIENCE Flow 1 step 2)  
On Free a review runs on click; scheduled reviews are Pro; each review is paid to Anthropic. The passive claim hides both the effort and the cost.  
Fix: "Score every post. Know what to fix next." Carry the note to story 60.

**[Honesty & conversion]** honesty M4 — No "who it's for / not for" (EXPERIENCE What you need; Page map `/`; Flow 1's honest exit)  
The honest exit relies on the visitor working out the fit from three requirement cards.  
Fix: Two lines at the end of What you need: a good fit if…; not a fit if you want a hosted service with no setup, run Multisite, or your site can't be reached from the internet.

**[Honesty & conversion]** honesty M5 — The reachability and HTTPS requirements are buried or missing (EXPERIENCE What you need; Plan card and pricing rows)  
The site's docs say a local or password-protected site cannot be reached from n8n Cloud, and Rewrite needs HTTPS. The spine keeps only the jargon half and drops HTTPS, though Rewrite is Tom's reason to buy and client staging sites are often HTTP or password-protected.  
Fix: What you need: "n8n must be able to reach your site: a local or password-protected site can't be reached from n8n Cloud." Pro card and the Rewrite row: "Rewrite needs HTTPS on your site."

**[Honesty & conversion]** honesty M6 — The CHF payment restriction is not disclosed (EXPERIENCE Currency switch; Flow 3 steps 2 and 6)  
`docs/distribution.md`: "PayPal is not offered in CHF, card only." Tom picks CHF and finds out only at checkout.  
Fix: Under the CHF packs: "In CHF, pay by card. PayPal is available in USD and EUR." Verify in the sandbox.

**[Honesty & conversion]** honesty M7 — The interest-email promise does not fit the pricing visitor or the privacy policy (EXPERIENCE Page map `/pricing/`; Where the actions lead; State Patterns intro; Action note)  
A `/pricing/` visitor wants to know when Pro is on sale, but the note promises "you hear once, when you can install it". If Free and Pro launch apart, the one message is early for the buyer or followed by a second one. Privacy § 3 keeps an email "as long as needed to answer you", which says nothing about holding it until launch.  
Fix: `/pricing/` note: "…you hear once, when the first public release is out." Send one message only when both are available, or say which one it announces. Privacy § 3: kept until that message is sent, then deleted.

**[Honesty & conversion]** honesty M8 — The only pre-launch action fails silently for webmail users (EXPERIENCE State Patterns "No email app"; Action note)  
With no default mail client, a `mailto:` does nothing. The address is printed only in the footer, so the site's one conversion leaks without anyone noticing, and no analytics would show it.  
Fix: Action note: "Not installable yet. The button opens your email app, or write to hello@postquality.com. You hear once, when you can install it." Prefill a body as well as the subject.

**[Honesty & conversion]** honesty M9 — "You hold the keys" leaves out the n8n API key the plugin stores (EXPERIENCE What you need lead; Steps row 1; the direction file)  
Privacy § 1 keeps the n8n API key in the site's database and warns it "opens your whole n8n instance". The site highlights the Anthropic key that is never stored and says nothing about the key that is.  
Fix: "The setup guide also asks for an n8n API key. The plugin keeps it in your site's database…, so create one for this site alone, with an expiry date. Prefer not to? Set the workflow up by hand."

**[Honesty & conversion]** honesty M10 — The Free card's support line becomes false once the plugin is listed (Direction file Free card; EXPERIENCE Plan card; State Patterns)  
Support policy and ADR 0013 D9: once listed, Free questions go to the WordPress.org forum. The card would still promise email.  
Fix: A "Listed" state: the Free card reads "Support forum on WordPress.org, best effort", linked.

**[Honesty & conversion]** honesty M11 — "No cookies, no analytics" is true but leaves out the host's logs (EXPERIENCE Foundation, Footer; privacy § 3)  
GitHub Pages logs visitors' IP addresses for security, and the privacy text names no host: incomplete under GDPR Art. 13.  
Fix: Privacy § 3 (source first): "The site is hosted on GitHub Pages; GitHub logs visitors' IP addresses for security, under GitHub's privacy statement."

**[Honesty & conversion]** honesty M12 — The guarantee wording drifts from refund.md and risks reading as a statutory right (EXPERIENCE Assurance item; the direction file)  
Refund.md promises more (no questions asked, full amount, renewals too, how to ask). Presenting the EU 14-day withdrawal right as a special feature is blacklisted (UCPD Annex I No. 10); the guarantee is safe because it goes further, so the copy should say so.  
Fix: "14-day money-back guarantee. Ask within 14 days of buying or of a renewal and get the full amount back, no questions asked. Freemius processes it to your original payment method."

**[Honesty & conversion]** honesty M13 — Trader identity on a commercial site (verify) (EXPERIENCE Footer)  
UCPD Art. 7(4)(b) and Swiss UWG Art. 3(1)(s) expect the trader's identity and address. Freemius shows its own at checkout, which may be enough; nobody has checked.  
Fix: The owner checks once and records it; if an address is required, a business or PO-box address, not a home address.

### Low (41)

**[Flow coverage]** rubric L1 — Flow 4 has no failure path; `/support/` after launch ignores the forum rule (EXPERIENCE § Page map `/support/` row; § Key Flows Flow 4)  
The support policy sends free-plugin questions to the WordPress.org forum once the plugin is listed, yet the page map gives `/support/` the same email action after launch.  
Fix: Add "Failure: no email app, so Tom copies the address from the footer." Make the after-launch cell "Email for Pro; the forum for Free once listed (policy text)".

**[Flow coverage]** rubric L2 — `Tested up to` is neither placed nor dropped (EXPERIENCE § Component Patterns: What you need block, Version line)  
Story 57's front-page list asks for a compatibility line with `Tested up to`. The spines show WordPress 6.6+ and PHP 8.0+ but never decide on `Tested up to`.  
Fix: Add it to the after-launch Version line ("Version X, updated <date>, tested up to WordPress 7.1"), or record that it is dropped.

**[Token completeness]** rubric L3 — Six phone sizes exist only as numbers in the Typography table (DESIGN § Typography; frontmatter `components.need-item.title-size: 21px`)  
Ticks and requirement line 15, caption 14, eyebrow 13, What you need card text 16, pillar names 19, What you need title 21 → 19. Other roles have `-mobile` tokens, so a builder who mirrors the frontmatter into CSS custom properties will miss these sizes.  
Fix: Add `caption-mobile`, `body-sm-mobile`, `label-caps-mobile`, `body-card-mobile` and `title-mobile`, plus a role for the 21 px item title. Or say once that phone sizes outside the tokens come from the table.

**[Token completeness]** rubric L4 — Several component values cannot be flattened by a resolver (DESIGN frontmatter `components`)  
These values are neither dimensions nor references: T-unit values in `lockup-b` / `lockup-c` / `icon-f` (`0.63 T`, `0.375 T`, `0.5 T`), `wordmark-weights: '600 + 400'`, `sharing-card.size: 1200 x 630 px` and the padding shorthands.  
Fix: Fine for a human or agent builder. If the frontmatter is to feed CSS generation, leave the T-unit specs in prose only and keep px values in the frontmatter.

**[Component coverage]** rubric L5 — The header's "small" primary button collides with the 52 px button floor (DESIGN § Components → Header; EXPERIENCE § Accessibility Floor → Targets)  
The Accessibility Floor says buttons are at least 52 px tall; the header button is "small".  
Fix: When the mock sets the size, give the header button its own floor in the Targets line, for example 44 px like the Menu.

**[Component coverage]** rubric L6 — Line icons have no named set; the `/docs/` interest email has no element (DESIGN § Components: What you need block, Assurance item, Primary button, Requirement line; EXPERIENCE § Page map `/docs/`, § State Patterns)  
The line icons (n8n flow, key, bill, shield, archive box, info, mail, download, arrows) have sizes and colours but no named set, stroke width or source. The interest email in the `/docs/` install section is specified as neither a button nor a text link.  
Fix: Name the inline SVGs in `direction-navy-and-check.html` as the source (stroke width, caps). Say the install section uses the primary button on light.

**[State coverage]** rubric L7 — "A reload shows USD again" is not guaranteed (EXPERIENCE § Component Patterns → Currency switch; State "Currency chosen"; Flow 3 failure)  
Firefox restores radio-button state on reload, and browsers restore it on back navigation, unless the inputs carry `autocomplete="off"`.  
Fix: Require `autocomplete="off"` on the radio group, or soften the claim to "nothing is stored by the site".

**[State coverage]** rubric L8 — "A reason beside each action" and "links say where they go" hold only on `/` (EXPERIENCE § Voice and Tone; § Accessibility Floor)  
The header's mailto button on every page and the `/pricing/` before-launch interest button have no action note.  
Fix: Give the `/pricing/` interest button its own note (the hero's wording). Decide whether the header button may rely on its label alone, and record that.

**[Bloat & overspecification]** rubric L9 — Draft copy is written out several times, and story 60 will replace all of it (EXPERIENCE § Voice and Tone; § Component Patterns → Action note; § State Patterns)  
The before-launch action note appears in Voice and Tone and again under Action note. The Pro footnote appears in Component Patterns and State Patterns. "not installable yet" appears nine times across the two spines.  
Fix: Keep each string once (a copy table in Voice and Tone, or the Action note row) and refer to it by name elsewhere.

**[Bloat & overspecification]** rubric L10 — The Typography table restates every desktop value in the frontmatter (DESIGN § Typography)  
The desktop column duplicates the frontmatter.  
Fix: Keep the role, token and Phone columns and drop the desktop numbers.

**[Bloat & overspecification]** rubric L11 — "Honest nudges" re-indexes components; "Success signals" is one assumption line (EXPERIENCE § Honest nudges; § Success signals)  
Both sections add little a downstream consumer would read.  
Fix: Fold Success signals into Foundation or Open Questions. Keep Honest nudges only if the owner wants the vision traceable inside the spine.

**[Inheritance discipline]** rubric L12 — Frontmatter component keys do not map one-to-one onto prose names (DESIGN frontmatter `components` vs § Components)  
`need-item` ↔ What you need block, `step-number` ↔ Steps, `flow-node` ↔ Flow diagram, `plan-card-free` / `plan-card-pro` / `plan-note` ↔ Plan card, `lockup-b/c/d` and `icon-f` ↔ Lockup.  
Fix: Rename the keys, or add one line under § Components mapping each key to its row.

**[Inheritance discipline]** rubric L13 — Plan footnotes have two owners (EXPERIENCE § Component Patterns → Action note; DESIGN § Components → Plan card)  
EXPERIENCE gives the footnotes to the Action note; DESIGN gives them to the Plan card ("a muted footnote").  
Fix: Pick one owner in both spines.

**[Inheritance discipline]** rubric L14 — Two `[ASSUMPTION]`s could be resolved from the record (EXPERIENCE § Key Flows Flow 3 step 1; § Foundation; memlog 11–12)  
Bug 59 already says Settings → "Get Pro" reaches the public pricing page while the Freemius Upgrade links lead nowhere. The two Foundation assumptions (form factor, stakes) are still "to confirm", although later owner decisions (phone Menu, 360 px floor) imply them.  
Fix: Cite bug 59 as Flow 3's dependency. Have the owner confirm the two Foundation assumptions at Finalize, or turn them into decisions.

**[Accessibility]** a11y L1 — Styled lists lose their semantics in Safari (1.3.1) (DESIGN § Components (ticks, pillar list, steps, plan features); mock `.ticks`, `.steps`)  
With `list-style:none`, VoiceOver drops list semantics, and the steps lose their numbering entirely.  
Fix: `role="list"` on every styled `ul`/`ol`; keep the step order exposed from the `ol` or as text in the numeral.

**[Accessibility]** a11y L2 — Flow diagram order lives only in decorative arrows (1.3.1) (EXPERIENCE "Flow diagram"; the mock)  
The four nodes are spans in a `role="group"`.  
Fix: An `<ol aria-label="Where your content goes">` with one node per item; arrows stay `aria-hidden`.

**[Accessibility]** a11y L3 — Page titles (2.4.2) are left to the build (EXPERIENCE § Open Questions (Discoverability); memlog)  
No title pattern is stated.  
Fix: `<title>` = "<Page> · Post Quality Manager"; home "Post Quality Manager · AI content reviews for WordPress"; 404 "Page not found · Post Quality Manager".

**[Accessibility]** a11y L4 — One h1 per page, copied pages included (1.3.1, 2.4.6) (EXPERIENCE § Accessibility Floor; § Content sources)  
The policy sources start with `# Title` and the layout prints `page.title` as the h1. On the 404 the lockup sheet draws "Page not found" as a `<p>`.  
Fix: The copy keeps removing the Markdown h1 (add it to the drift check); "Page not found" is the 404's h1.

**[Accessibility]** a11y L5 — Headings that are slogans (2.4.6) (DESIGN § Components (kickers))  
"The free plugin is the trial" and "Four pillars, any niche" carry their topic in the kicker `<p>`, which heading navigation skips.  
Fix: Move the kicker into the heading: `<h2><span class="kicker">Free and Pro</span> The free plugin is the trial</h2>`.

**[Accessibility]** a11y L6 — Lockup names (1.1.1, 2.5.3, 2.5.8) (EXPERIENCE "Lockup")  
A name on the link and on the SVG is read twice. The footer lockup D tile is 20 px and passes only through the spacing exception. The 404 icon needs no name.  
Fix: Link `aria-label="Post Quality Manager, home"` with the SVG `aria-hidden`, or the SVG named with `role="img"`, never both. Footer D not a link, or at least 24 px tall. 404 icon `alt=""` or `aria-hidden`.

**[Accessibility]** a11y L7 — Sharing card alt (1.1.1) (DESIGN § Sharing card)  
No alt for the og:image.  
Fix: Add `og:image:alt` and `twitter:image:alt` with the tagline.

**[Accessibility]** a11y L8 — Screenshot frame bar read as content (DESIGN § Screenshot frame)  
If built in HTML, "yourblog.example/wp-admin/" is read as content.  
Fix: Give the bar `aria-hidden="true"`.

**[Accessibility]** a11y L9 — External destinations unstated (2.4.4) (EXPERIENCE buy and install actions; the mock's download icon)  
Buy and install buttons do not say where they lead; the download icon on "Install the free plugin" promises a file, but the link opens a listing page.  
Fix: "Checkout opens on Freemius, the seller of record" beside the buy buttons; "Opens the plugin's WordPress.org page" after launch. Same-tab external links (say "opens in a new tab" if ever otherwise). An arrow, not a download icon.

**[Accessibility]** a11y L10 — Sticky header (2.4.11) (DESIGN § Header)  
A sticky 64 px strip would cover anchor targets (`#need`, FAQ items) and focused elements.  
Fix: State that the header is neither sticky nor fixed.

**[Accessibility]** a11y L11 — FAQ and Menu state indicators (1.4.1, 1.4.11, 4.1.2) (DESIGN § FAQ, § Menu disclosure)  
A colour change alone would not show open or closed; a heading inside `<summary>` loses its semantics.  
Fix: A chevron or +/− that changes shape, or the native marker; no heading inside `<summary>`.

**[Accessibility]** a11y L12 — Skip link and landmarks (2.4.1, 1.3.1) (EXPERIENCE "Skip link"; the mock)  
The mock puts `id="main"` on a div inside the same band as the header.  
Fix: Target `<main id="main" tabindex="-1">`; `<header>` holds only lockup, nav and action; the hero starts `main`; the skip link moves off-screen, never `display:none`.

**[Accessibility]** a11y L13 — Forced colours (not a WCAG criterion) (DESIGN § Primary button, § Currency switch)  
States drawn by background alone vanish in Windows contrast themes.  
Fix: Keep the 2 px border on every button, the primary on light included; give the selected currency tab a border or underline.

**[Accessibility]** a11y L14 — Reduced motion (2.3.3) (EXPERIENCE § Interaction Primitives)  
Nothing animates today.  
Fix: Wrap any future `scroll-behavior: smooth` or details transition in `@media (prefers-reduced-motion: no-preference)`.

**[Accessibility]** a11y L15 — User font size (best practice) (DESIGN § Typography)  
px type ignores the reader's default browser font size.  
Fix: Set type in `rem`; token values stay px figures against a 16 px root.

**[Accessibility]** a11y L16 — Multiple Ways (2.4.5) (EXPERIENCE § On every page; `/docs/`)  
Today the header and footer together list every page; `/docs/` has seven sections and no contents.  
Fix: Keep it so; if Pricing or Documentation leaves the header, add it to the footer. A short contents list at the top of `/docs/`.

**[Accessibility]** a11y L17 — The direction mock is not a markup reference (`.working/direction-navy-and-check.html`)  
It uses `<p class="h1">`, `role="img"` on divs, the green 3 px ring with a 3 px offset, the white keyline, wrapped phone links and `id="main"` on a non-landmark. The spines override all of these.  
Fix: Say so in the build ticket, so nobody copies the mock's HTML.

**[Accessibility]** a11y L18 — Text inside the phone crop (EXPERIENCE § Screenshots row 1; the mock)  
The crop is laid out for 460 px but shown at 288–358 px, shrinking the plugin's 13 px text to 8–10 px. Exempt, and the alt covers it, but illegible.  
Fix: Export the crop for the 328 px column.

**[Honesty & conversion]** honesty L1 — Provider facts need one source, because ADR 0009 will change them (Every "Anthropic" string)  
When ADR 0009's providers ship, a dozen strings go stale at once.  
Fix: Add "provider list and spend-cap wording" to the Content sources table, triggered by ADR 0009 / stories 25, 29, 30.

**[Honesty & conversion]** honesty L2 — The author line has to be literally true (EXPERIENCE Trust line)  
Both "blogs" (plural) and "own" must hold. If one is an employer's blog, "own" overclaims.  
Fix: The owner confirms; otherwise "Built and used on the author's own blog."

**[Honesty & conversion]** honesty L3 — The lapse wording should match terms § 4 (EXPERIENCE Assurance item)  
"Lapses" does not obviously cover cancellation or refund; "schedules stay" can read as "keep running"; terms also promise that Review and Key Takeaways keep working.  
Fix: "Nothing deleted if your license ends. The site returns to Free: reviews, settings, schedules and role rights are kept, and Review and Key Takeaways keep working."

**[Honesty & conversion]** honesty L4 — The header interest button has no reason beside it (EXPERIENCE Header; Accessibility Floor)  
Only the hero's action note says that the button opens the email app.  
Fix: Mail icon on the small button; accessible name "Tell me when it's available (opens your email app)".

**[Honesty & conversion]** honesty L5 — A freshness signal should not wait for the public repository (EXPERIENCE Trust line)  
A changelog is the cheapest honest signal at zero users, and the release notes exist whatever the repository's visibility.  
Fix: At launch, link "What's new" beside the version line to the WordPress.org listing's changelog.

**[Honesty & conversion]** honesty L6 — Overbuilt for a site with zero users (EXPERIENCE Currency switch (before launch); DESIGN Lockup C)  
Before any checkout link exists, the switch only swaps a currency code over identical figures. Lockup C has no spot on the site.  
Fix: Before sale: "49 / 99 / 199 a year, in USD, EUR or CHF"; ship the switch with the buy buttons. Keep C in brand.md only.

**[Honesty & conversion]** honesty L7 — Tom's and Lena's smaller questions (EXPERIENCE Pricing FAQ; What you need)  
Does three sites mean three n8n instances (no: workflow v6 gives each site its own webhook path)? How long does setup take?  
Fix: FAQ: "One n8n can serve several sites…". Time the setup in the end-of-project agentic walk, then state it.

**[Honesty & conversion]** honesty L8 — Third-party marks in imagery (DESIGN What you need block ("n8n flow" icon))  
A glyph that echoes n8n's node-chain logo would break brand.md's no-third-party-mark rule.  
Fix: A generic workflow glyph (boxes and arrows), named "workflow" in the spec.

**[Honesty & conversion]** honesty L9 — Text-only first mention on pages without a hero (EXPERIENCE Voice and Tone, Naming)  
The lockup is an SVG, so search snippets, reader modes and copy-paste see `/pricing/` and `/docs/` with no textual "Post Quality Manager".  
Fix: Each page's h1 or lead names Post Quality Manager in text once.

## Mechanical notes

- All 53 `{path}` references in DESIGN.md and all 6 in EXPERIENCE.md resolve. Both `sources:` lists resolve, including `../../backlog/story-site-content-style-checkout-best-practices.md`.
- The frontmatter parses as YAML, `&sans` / `*sans` anchors included. A DESIGN.md tool that does not expand anchors would read `*sans` as the font family.
- `spacing.gap-xs` (10 px) is defined and never used. `bp-medium`, `bp-phone`, `min-viewport` and `header-height` are breakpoints and a height filed under `spacing`.
- `typography.title-lg`, `label-caps` and `nav` have no `lineHeight`.
- No frontmatter component group exists for Menu disclosure, Version line, Currency switch or FAQ (the `[NOTE FOR UX]` set). Add them when the mocks land.
- Markers: six `[NOTE FOR UX]`, all in DESIGN.md (five wait on the mocks, one is the Version line); six `[ASSUMPTION]` in EXPERIENCE.md (Foundation ×2, Flow 1 step 1, Flow 3 step 1, Success signals, the Q10 row).
- Stale wording: EXPERIENCE § Responsive & Platform says "Comparison and price tables become cards on phones", but the price table was replaced by the pack plan cards and the currency switch.
- DESIGN § Colors cites `ink-muted` 6.43:1 on #f0f0f1, a WP canvas grey outside the site palette (left over from the lockup sheet's light-footer test).
- EXPERIENCE § Responsive & Platform, "about 340 px used" at the 360 px floor: say whether this includes the two 16 px gutters. If it does not, 340 px exceeds the 328 px content width and the header overflows.
- No Mermaid in either spine. Both spines are `status: draft`, `updated: 2026-10-22`; memlog decisions up to the green assurance edge are reflected.

## Reviewer files

- `review-rubric.md`
- `review-accessibility.md`
- `review-honesty-conversion.md`
- `triage.md` (deduplicated triage)
