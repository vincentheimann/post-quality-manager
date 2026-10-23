# Accessibility review: postquality.com UX spines

Reviewer Gate, ad-hoc lens: accessibility, with WCAG 2.2 AA as the floor. Reviewed on 2026-10-22: DESIGN.md, EXPERIENCE.md, .memlog.md (up to 16:52), `.working/direction-navy-and-check.html`, `.working/logo-lockups.html`. Contrast was recomputed with WCAG relative luminance (`python -I`); the script was kept outside the workspace.

## Verdict

The spines are close, but not yet safe to build to AA from. The palette is sound: every stated ratio recomputes exactly. The navy/white focus ring was the right call, and native `<details>`, radios and links are the right base without JavaScript. Four high gaps would fail WCAG 2.2 AA if built as drawn or with the usual CSS-only recipes:

- inline links told apart by colour alone (1.18:1)
- a header that cannot reflow at 320 px and does not fit between 760 and about 965 px
- a currency switch whose mechanism is not specified, where the common recipe removes keyboard access
- a Menu panel that can hide the focused element

There are no critical findings. Every fix is a spine line plus CSS.

## Critical

None.

## High

### H1. Inline links are told apart by colour alone (1.4.1 Use of Color)

- **Where:** DESIGN § Colors, row `navy` ("headings and links on white"). DESIGN § Components, rows Requirement line ("a bold white link") and Footer. EXPERIENCE § State Patterns, row "Hover and focus" (only the header links get an underline, on hover). No rule says that links in running text are underlined at rest.
- **What fails:** navy #1b2a41 link text against ink-body #2a3649 body text is 1.18:1. Against ink-muted #50575e it is 1.97:1. A colour-only link cue needs 3:1 to the surrounding text, plus a cue other than colour on hover and focus (G183). In the requirement line and the Pro card, the link is white in white text, so it differs only by weight.
  - Affected links: "What you need" in the plan note and the requirement line, "No n8n yet?", the n8n links on `/docs/`, and the links inside the copied policies (support.md's "leave your email").
- **Fix:** add a rule to DESIGN § Colors / Components: links inside text blocks are always underlined, on light and dark surfaces and in the Markdown output of `main` (`text-decoration: underline; text-underline-offset: .15em`). Only these may drop the underline, because their position identifies them:
  - the header nav
  - the footer policy list
  - links styled as buttons

### H2. The header does not fit at 320 px, nor in its desktop form between 760 and ~965 px (1.4.10 Reflow, 1.4.4 Resize Text, 1.4.12 Text Spacing)

- **Where:**
  - DESIGN § Layout & Spacing ("Header: header-height tall"; "Floor min-viewport, no horizontal scroll").
  - DESIGN § Components → Lockup ("B needs about 210 px at a 28 px tile… The header never drops to F") and → Header.
  - EXPERIENCE § Responsive & Platform, row `min-viewport` ("Lockup B and the Menu fit with 16 px gutters (about 340 px used)").
  - Tokens `spacing.header-height: 64px`, `spacing.min-viewport: 360px`, `spacing.bp-phone: 760px`.
- **Phone width:** 1.4.10 is tested at 320 CSS px, which is a 1280 px window at 400 % zoom. The phone header row needs lockup B (about 210 px), the labelled 44 px Menu (about 85 px) and a gap. That is about 300–310 px; at 320 px there are only 288 px. The spine's own figure is 340 px. The rules also close every way out: no F in the header, D only as a second mention, a fixed 64 px height.
- **Desktop width:** the desktop row needs about 865 px of content:
  - lockup B at 32 px: about 255 px
  - the three links with their gaps: about 300 px
  - the small "Tell me when it's available" button: about 260 px
  - two group gaps

  With 48 px gutters, that is a viewport of about 965 px. The Menu only takes over below 760 px. So from 760 to about 965 px the header overflows sideways, or wraps inside a fixed 64 px strip and overlaps the hero. That range is 200 % zoom on 1536–1920 px screens. These widths are estimated from the spines' own sizes; measure them with real text.
- **Fix (CSS only):**
  1. Make 64 px a `min-height`, never a `height`. Give the header row `flex-wrap: wrap`, so the Menu (phone) or the nav (desktop) drops to a second line instead of overflowing.
  2. Move the switch to the Menu up to the width the desktop row really needs. That is probably `bp-medium` (1000 px); check it with the after-launch label too.
  3. Add a rule below 360 px to DESIGN § Lockup: "below 360 px the header wraps: lockup B on the first line, Menu on the second". Or, with a real-text wordmark: "'Manager' wraps to a second line".
  4. Change the floor in DESIGN § Layout and EXPERIENCE § Responsive to "320 px with no horizontal scroll; 360 px is the design target".
  5. Test at 320 px, at 400 % on 1280 px, and at 200 % on 1600 and 1920 px.

### H3. Currency switch: the mechanism is not specified, and the usual CSS-only recipe breaks keyboard and screen-reader use (2.1.1 Keyboard, 1.3.1 Info and Relationships, 2.4.7 Focus Visible, 2.4.4 Link Purpose, 1.4.1 / 1.4.11)

- **Where:** EXPERIENCE § Component Patterns, row "Currency switch", and § Accessibility Floor, bullet "Currency switch". DESIGN § Components → Currency switch (`[NOTE FOR UX]`). Memlog, currency decision of 2026-10-22.
- **What fails or is missing:**
  1. **Hiding the radios.** The spines do not say how the radios are hidden. "CSS tabs" recipes usually put `display:none` or `visibility:hidden` on the inputs. That takes them out of the tab order and the accessibility tree, so the switch works with a mouse only. The sentence "Hidden currencies use `display: none`" is easy to apply to the inputs by mistake.
  2. **Grouping.** A labelled group is required, but the CSS that swaps the prices limits the markup. With the `~` sibling selector, the radios must be siblings placed before the plan cards. A `<fieldset>` around the radios alone is then impossible: the group either goes unlabelled or the fieldset wraps all the plan cards.
  3. **Names.** Each pack has three buy links, one per currency, all with the same name ("Buy for 5 sites"). Without CSS (reader mode, a failed stylesheet) or in a links list, that is nine links with three names and different destinations. The Pro figures are the same in every currency (49 / 99 / 199). So a price read without its currency code ("49") is ambiguous to a screen-reader user who missed the switch.
  4. **Selected state.** The selected-tab look is still open. A tint fill would fail: #f3f5f8 against white is 1.09:1. So would a change of colour only.
  5. **Target size** of the tabs is not set (see M5).
- **Fix:**
  - **Markup:** place `<fieldset><legend>Show prices in</legend>` with one `<label><input type="radio" name="cur" value="usd" checked autocomplete="off"> USD</label>` per currency, before the plans. A screen reader then announces "Show prices in, group. USD, radio button, checked, 1 of 3".
  - **Keep every input focusable.** Either style the input itself (`appearance:none` with the tab look, so the ring and the selected state sit on the input), or hide it visually with the clip pattern and style `input:focus-visible + span` and `input:checked + span`. The clip pattern is `position:absolute; width:1px; height:1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap`. Never use `display:none`, `visibility:hidden`, `hidden`, or a zero-size transparent box.
  - **CSS swap:** drive it with `:has()` from a shared ancestor, so the fieldset can wrap just the radios. For example: `.pricing:has(input[value=eur]:checked) [data-cur]:not([data-cur=eur]) { display:none }`.
    - Guard it with `@supports selector(:has(a))`. Without `:has()`, show all three currencies, each labelled, and hide the switch.
  - **Hidden currencies:** put `display:none` on both the price and the buy link of each currency not shown, so a hidden link can never take focus.
  - **Text:** every price carries its currency in text ("49 CHF per year, 1 site"). Every buy link carries it in its name: visible "Buy for 5 sites" with a visually hidden " in CHF" after it (this keeps 2.5.3 Label in Name), or the currency visible on the button.
  - **Selected tab:** navy fill with white text (14.44:1; the fill is 14.44:1 against the white page), plus bold weight or a check glyph. Unselected tabs: white with a 1.5 px navy border (14.44:1). Write this into DESIGN § Currency switch.
  - **Roles:** do not add `role="tablist"` or `role="tab"`. These are radios and must be announced as radios.
  - **Reload (not WCAG):** Firefox restores form state on reload. "A reload shows USD again" only holds with `autocomplete="off"` on the inputs.

### H4. The open phone Menu can cover the element that takes focus next (2.4.11 Focus Not Obscured, Minimum)

- **Where:** DESIGN § Components → Menu disclosure (`[NOTE FOR UX]` "the open panel"). EXPERIENCE § Component Patterns, row "Menu disclosure" ("A link loads a new page, so the menu never has to close itself").
- **What fails:** the usual dropdown look positions the open panel over the page (`position:absolute`). Nothing can then close it when focus leaves: there is no script, and `<details>` does not close on Escape or on blur.
  - Tabbing past the last item (the site-wide action) moves focus into `main`, under the panel. On `/docs/`, `/pricing/` and the policy pages, the first focusable element sits right under the header, so it is hidden completely.
  - "Closed on every page load" also fails after Back: the back/forward cache restores the panel open.
- **Fix:** specify the panel in normal flow. The open `<details>` pushes the page down: no absolute or fixed position, no overlay. Then nothing can be covered, a panel restored open by Back does no harm, and focus stays on the summary when it toggles. Add to DESIGN § Menu disclosure: "the open panel sits in flow, full width under the header row, never as an overlay". See also L10 (no sticky header).

## Medium

### M1. Interest buttons do not say they open the email app (2.4.4 Link Purpose, In Context)

- **Where:**
  - EXPERIENCE § Information Architecture, "Where the actions lead" → Interest email.
  - EXPERIENCE § Component Patterns, rows Header, Primary button and Action note.
  - EXPERIENCE § Accessibility Floor, last bullet.
  - DESIGN § Primary button (mail icon).
- **What fails:** the explanation sits in its own paragraph after both hero buttons. That is outside the link's context as 2.4.4 defines it: the same sentence, paragraph, list item or cell, or an associated description. The mail icon is `aria-hidden`.
  - Three copies of the button have no note at all: the one in the header (on every page), the one on the Free card (whose footnote is only "Not installable yet."), and the one on `/pricing/`.
  - A keyboard or screen-reader user activates "Tell me when it's available" and lands in a mail client, or in nothing on a machine without one.
- **Fix:** add the mechanism to every interest link, in its name or its description:
  - append visually hidden text after the visible label: "Tell me when it's available<span class="vh"> (opens your email app)</span>"; or
  - in the hero, use `aria-describedby` to point at the action note.

  The header copy uses the appended text. The visible label stays first, for 2.5.3. The Free card's footnote becomes "Not installable yet. Opens your email app."

### M2. The focus-ring colour must follow the surface, including dark blocks inside light sections (2.4.7 Focus Visible, 1.4.11)

- **Where:** DESIGN § Components → Focus ring. EXPERIENCE § Component Patterns, row "Focus ring".
- **What fails:** the rule (navy on light, white on dark) is correct. But dark blocks sit inside light sections:
  - the navy Pro card, inside a tint section on `/` and on `/pricing/`
  - the navy-hi requirement line
  - a dark Menu panel, if it has one

  A rule set per section, such as `.sec-tint :focus-visible { outline-color: navy }`, draws a navy ring around "See Pro pricing" on the navy card: 1.00:1, invisible.
- **Fix:** specify how the colour is set. Each surface sets a custom property: `:root { --ring:#1b2a41 } .on-dark { --ring:#fff }`, then `:focus-visible { outline:3px solid var(--ring); outline-offset:2px }`.
  - Put `.on-dark` on the header strip, the hero, the Pro card, the requirement line and any dark panel. List these surfaces in DESIGN.
  - With a 2 px offset the ring is drawn on the surface around the element. Its colour must contrast with that surface, not with the element.

### M3. One alt for two different crops (1.1.1 Non-text Content)

- **Where:** EXPERIENCE § Screenshots, row 1 ("plus a phone crop: tiles and the top of Fix next, no admin menu"), and § Component Patterns, row "Screenshot frame". DESIGN § Screenshot frame.
- **What fails:** a responsive `<picture>` carries one `alt`. The desktop image shows the 8-week trend and five Fix next rows; the phone crop shows neither. An alt written for the desktop image ("rising from 63 to 71 over 8 weeks") describes content the phone image does not have. The mock gave each crop its own label, which `<picture>` cannot do.
- **Fix:** write the alt for what both crops show: average 71; 3 Major revision, 9 Needs revision, 14 Not analyzed, 22 Ready to ship; Fix next led by a post scored 42. If the trend matters, put it in the caption. The other option is two `<img>` elements switched by media query, each with its own alt; test whether both download. The same rule applies to any later crop per breakpoint.

### M4. Tables that "become cards on phones" (1.3.1 Info and Relationships)

- **Where:** EXPERIENCE § Information Architecture → Page map, row `/pricing/` ("Free vs Pro comparison (cards on phones)"). EXPERIENCE § Responsive & Platform, last bullet.
- **What fails:** restyling a `<table>` with `display:block`, `grid` or `flex` removes the table semantics in Safari/VoiceOver and in some Chromium versions. Headers rebuilt with `data-label` and `::before` are announced inconsistently. Screen-reader users on a phone then hear a run of "Yes", "No", "49" with no row or column header.
- **Fix:** choose one of these:
  - (a) Build the comparison as plan cards from the start, at every width: a heading per plan and a feature list.
  - (b) Keep a real `<table>` with `<th scope>` at every width. On phones it scrolls sideways inside `<div role="region" aria-labelledby="…" tabindex="0">`; 1.4.10 allows data tables to do this.

  Do not reflow a table into cards with CSS alone.

### M5. No target size for the header button, the currency tabs or the FAQ summaries (2.5.8 Target Size, Minimum)

- **Where:** EXPERIENCE § Accessibility Floor, bullet "Targets" (only buttons at 52 px and the Menu at 44 px). DESIGN § Header (`[NOTE FOR UX]` small button), § Currency switch, § FAQ.
- **Fix:** add to the Accessibility Floor: "every target at least 24 × 24 CSS px; design target 44 px". Then:
  - the header's small button: min-height 40–44 px (the 64 px strip has room for it and its ring)
  - currency tabs: at least 44 × 44
  - FAQ summaries: full row width, min-height 44 px
  - header nav links: padded to 44 px tall

  The footer policy links already pass at the limit: 16 px at 1.55 line height is 24.8 px, with an 8 px row gap. Keep that gap.

### M6. "No n8n yet?" does not say where it goes (2.4.4 Link Purpose, In Context)

- **Where:** EXPERIENCE § Component Patterns, row "What you need block". Memlog decision ("'No n8n yet?' links to a Getting n8n section on /docs/"). Flow 1, step 4.
- **What fails:** a question used as link text names neither the action nor the destination, and the sentence around it ("Self-hosted or n8n Cloud…") does not either. In a links list it reads "No n8n yet?", and a visitor could expect n8n's own site. The link sits at the A2 drop-off point, where being clear matters most.
- **Fix:** write "No n8n yet? <a href="/docs/#getting-n8n">How to get n8n</a>". On `/docs/`, the outbound links name their destination: "n8n Cloud (n8n.io)", "Self-hosting n8n (docs.n8n.io)".

### M7. Long strings on `/docs/` and the policy pages at 320 px (1.4.10 Reflow, 1.4.12)

- **Where:** EXPERIENCE § Information Architecture → Page map, row `/docs/` (Getting started, Set up by hand). EXPERIENCE § Content sources and drift checks (copied Markdown).
- **What fails:** n8n and webhook URLs, inline `code` and `pre` blocks do not wrap, so the whole page scrolls sideways at 320 px. Long headings at phone size also overflow once text spacing is raised: "Documentation" at 36 px takes about 250 px of the 288 px column.
- **Fix:** in the layout CSS:
  - `main { overflow-wrap: anywhere }`
  - `pre { white-space: pre-wrap }`, or a `pre` that scrolls inside its box, with `tabindex="0"` and an accessible name
  - `h1, h2 { overflow-wrap: break-word; hyphens: auto }`

### M8. Lighthouse-only acceptance misses most of this review (process, for 1.4.1, 1.4.10, 2.1.1, 2.4.4, 2.4.11, 1.1.1)

- **Where:** EXPERIENCE § Accessibility Floor, first bullet ("Lighthouse reports no accessibility error on / and /pricing/").
- **What fails:** Lighthouse runs a subset of axe and covers two pages. It does not test any of these:
  - keyboard use of the currency switch
  - focus hidden under the Menu
  - reflow at 320 px
  - text spacing
  - link purpose of mailto links
  - quality of the alt text
- **Fix:** extend the criterion in two parts.
  - Lighthouse/axe report no error on every page, 404 included, with the Menu open and with each currency selected.
  - A manual pass:
    - keyboard only: Tab, Shift+Tab, arrows in the currency group, Enter and Space on summaries
    - 320 px, and 400 % zoom on a 1280 px window
    - 200 % zoom at 1600 px
    - a text-spacing bookmarklet
    - NVDA with Firefox, and VoiceOver on iOS Safari (Menu and radios)
    - Windows forced colours

  This fits the owner's rule that manual checks are re-run by an agent on Local.

## Low

- **L1. Styled lists lose their semantics in Safari (1.3.1).** Affects the ticks, the pillar list, the steps and the plan feature lists (DESIGN § Components; mock `.ticks` and `.steps` with `list-style:none` and `aria-hidden` step numbers). With `list-style:none`, VoiceOver drops the list semantics, and the steps then lose their numbering entirely. Fix: `role="list"` on every styled `ul`/`ol`. Keep the step order exposed, either from the `ol` or as text in the numeral.
- **L2. Flow diagram (1.3.1).** In EXPERIENCE row "Flow diagram" and the mock, the four nodes are spans in a `role="group"`, and only the decorative arrows show the order. Fix: an `<ol aria-label="Where your content goes">` with one node per item; the arrows stay `aria-hidden`.
- **L3. Page titles (2.4.2) are left to the build.** See EXPERIENCE § Open Questions (Discoverability) and the memlog. Fix: one line in the spine: `<title>` = "<Page> · Post Quality Manager"; the home page "Post Quality Manager · AI content reviews for WordPress"; the 404 "Page not found · Post Quality Manager". jekyll-seo-tag already does this if it is kept.
- **L4. One h1 per page, copied pages included (1.3.1, 2.4.6).** EXPERIENCE § Accessibility Floor sets one h1; § Content sources copies the policies. The sources in `docs/policies/` start with `# Title`, and the layout prints `page.title` as the h1. The copy must keep removing the Markdown h1, as the live site does today; add this to the drift check. On the 404 page, "Page not found" is the h1 (the lockup sheet draws a `<p>`).
- **L5. Headings that are slogans (2.4.6).** "The free plugin is the trial" and "Four pillars, any niche" carry their topic in the kicker `<p>` above them, which heading navigation skips. Fix: move the kicker into the heading, for example `<h2><span class="kicker">Free and Pro</span> The free plugin is the trial</h2>`, styled as today.
- **L6. Lockup names (1.1.1, 2.5.3, 2.5.8).** See EXPERIENCE row "Lockup".
  - If the link carries `aria-label="Post Quality Manager, home"`, the SVG inside it is `aria-hidden="true"`. If the name comes from the SVG, the SVG needs `role="img"` and an `aria-label`. Never both, or the name is read twice.
  - The footer lockup D is either not a link, or a link at least 24 px tall. Its tile is 20 px; today it passes only through the 2.5.8 spacing exception.
  - The 404 icon (F, 64 px) takes `alt=""` or `aria-hidden`.
- **L7. Sharing card (1.1.1).** Add `og:image:alt` and `twitter:image:alt` with the tagline.
- **L8. Screenshot frame bar.** If the browser bar is built in HTML, give it `aria-hidden="true"`, so "yourblog.example/wp-admin/" is not read as content. Its 7.18:1 contrast is fine visually.
- **L9. External destinations (2.4.4, predictability).**
  - Say where the buy and install buttons lead: "Checkout opens on Freemius, the seller of record" beside the buy buttons; after launch, "Opens the plugin's WordPress.org page".
  - Open external links in the same tab. If `target="_blank"` is ever used, say "opens in a new tab".
  - The mock's download icon on "Install the free plugin" promises a file. The link opens a listing page, so use an arrow instead.
- **L10. Sticky header (2.4.11).** State that the header is neither sticky nor fixed. A sticky 64 px strip would cover anchor targets (`#need`, FAQ items) and focused elements.
- **L11. FAQ and Menu state indicators (1.4.1, 1.4.11, 4.1.2).** Use a chevron or a +/− that changes shape when open, not a change of colour. Either keep the native marker or replace it. Put no heading inside `<summary>`: its children lose their semantics.
- **L12. Skip link and landmarks (2.4.1, 1.3.1).**
  - Target `<main id="main" tabindex="-1">`.
  - `<header>` holds only the lockup, the nav and the action; the hero starts `main`. The mock puts `id="main"` on a div inside the same band as the header.
  - The skip link is moved off-screen, never `display:none`.
- **L13. Forced colours (Windows contrast themes; not a WCAG criterion).** Keep the 2 px border on every button, the primary on light included. Give the selected currency tab a border or underline, so states survive when backgrounds are dropped.
- **L14. Reduced motion (2.3.3).** Nothing animates today. If `scroll-behavior: smooth` or a details transition is ever added, wrap it in `@media (prefers-reduced-motion: no-preference)`.
- **L15. User font size (best practice, not a failure).** Set type in `rem`, not `px`, so the reader's default browser font size applies. The token values can stay as px figures against a 16 px root.
- **L16. Multiple Ways (2.4.5).** Today the header and the footer together list every page; keep it that way. If Pricing or Documentation ever leaves the header, add it to the footer. Add a short contents list at the top of `/docs/`, which has seven sections.
- **L17. The direction mock is not a markup reference.** It uses:
  - `<p class="h1">`
  - `role="img"` on divs
  - the green 3 px ring with a 3 px offset
  - the white keyline
  - wrapped phone links
  - `id="main"` on a non-landmark element

  The spines override all of these. Say so in the build ticket, so nobody copies the mock's HTML.
- **L18. Text inside the phone crop.** The mock lays out the crop for a 460 px width, but it is shown at 288–358 px. The plugin's 13 px text then shrinks to 8–10 px. A screenshot is exempt and the alt covers it, but exporting the crop for the 328 px column keeps the text in the image legible.

## Verified OK

- **Contrast.** Every ratio stated in DESIGN § Colors recomputes exactly:
  - navy on white 14.44
  - navy on tint 13.22
  - navy on hover-on-navy 12.27
  - white on navy-hi 11.46
  - white on band 9.11
  - ink-body 12.19 / 11.16
  - ink-muted 7.33 / 6.71 / 6.43
  - ink-lead-on-navy 11.49
  - ink-muted-on-dark 8.91 / 7.07 / 5.62
  - frame-text 7.18
  - tile on band 1.59
  - green 4.31 (navy) / 3.42 (navy-hi) / 3.35 (white) / 3.07 (tint) / 2.72 (band)
- **Contrast of unstated pairs, all passing:**
  - hover-on-navy button edge: 7.74 on the band, 12.27 on navy
  - primary-on-light hover fill: 11.46 on white, 10.50 on tint
  - white text on the hover fills: at least 11.46
  - ink-lead on the band: 7.24
- **Green.** Green is never text and never sits behind text. Where it appears (ticks, the highlight bar, the assurance edge) it is decorative, at 3:1 or more, and the spines forbid it on the band. Dropping the green focus ring (2.72:1 on the band) was correct. The bar under "fix next" is decorative emphasis: the sentence reads complete without it, so no `<mark>` is needed (some screen readers would announce "highlight").
- **Focus ring.** A 3 px outline with a 2 px offset, navy on light (13.22:1 or more) and white on dark (9.11:1 or more), on `:focus-visible` and never removed. It meets 2.4.7 and would also meet 2.4.13 (AAA, not claimed). Being an `outline`, it survives forced colours.
- **Status badges in the screenshots.** Each shows an icon, a label and a colour. Label contrast: Major 4.73, Needs 7.17, Ready 4.74, Not analyzed 7.33 (its outline 3.24). Colour is never the only cue.
- **Native elements, no JavaScript.** Links for every action; `<details>`/`<summary>`, whose expanded state is native and whose summary keeps focus; native radios, with arrow keys built in. There is no custom ARIA widget to get wrong.
- **Structure.** Landmarks: header, nav "Main", main, and a footer with nav "Policies". One h1 per page, one h2 per section, `lang="en"`, the skip link first.
- **Reading order.** The DOM order matches the visual order: skip link, lockup, nav, then the header action (desktop) or the Menu (phone), then the hero, the content and the footer. The prerequisites come before any install action.
- **Hero overlap.** The screenshot overlaps only the band's bottom padding: it stays 44 px clear of the ticks on desktop and 28 px on phones. It holds nothing focusable, and growing text pushes the band down rather than under the image.
- **Menu.** It has a visible "Menu" label, which overrides the lockup sheet's icon-only variant at 360 px, and is 44 px tall. Buttons are 52–54 px tall.
- **No disabled-looking controls.** Before Pro is on sale, the pack cards carry no button rather than a greyed-out one.
- **Consistency (3.2.3, 3.2.4, 3.2.6).** The header, the footer and the action label are the same on every page. The support address is printed as text in the footer, for visitors without an email app.
- **Decorative icons** (ticks, line icons, arrows) are hidden from assistive technology, and their claims are text.
- **No moving or hidden content.** No timing, motion, auto-play, video, hover-only content, third-party embeds or forms.
