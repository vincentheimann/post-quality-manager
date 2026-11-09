---
name: postquality.com
description: 'The public website of Post Quality Manager. Direction "Navy and check": the brand icon''s navy, white and green carry the page; the plugin appears only inside framed screenshots.'
status: draft
updated: 2026-10-22
sources:
  - .memlog.md
  - .working/sources-extract.md
  - .working/direction-navy-and-check.html
  - .working/logo-lockups.html
  - ../../backlog/story-site-content-style-checkout-best-practices.md
colors:
  navy: '#1b2a41'
  check-green: '#00a32a'
  white: '#ffffff'
  band: '#33496b'
  navy-hi: '#263a59'
  hover-on-navy: '#e8edf3'
  ink-body: '#2a3649'
  ink-muted: '#50575e'
  ink-lead-on-navy: '#dfe6ef'
  ink-muted-on-dark: '#c3ccd9'
  tint: '#f3f5f8'
  hairline: '#d6dce5'
  frame-bar: '#f7f8fa'
  frame-line: '#cfd5de'
  frame-dot: '#c3c9d2'
  frame-text: '#4a5466'
typography:
  display:
    fontFamily: &sans 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif'
    fontSize: 64px
    fontWeight: '700'
    lineHeight: '1.05'
    letterSpacing: -0.025em
  display-mobile:
    fontFamily: *sans
    fontSize: 36px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: -0.025em
  headline:
    fontFamily: *sans
    fontSize: 40px
    fontWeight: '700'
    lineHeight: '1.15'
    letterSpacing: -0.02em
  headline-mobile:
    fontFamily: *sans
    fontSize: 29px
    fontWeight: '700'
    lineHeight: '1.15'
    letterSpacing: -0.02em
  title-lg:
    fontFamily: *sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.02em
  title:
    fontFamily: *sans
    fontSize: 22px
    fontWeight: '700'
    lineHeight: '1.3'
    letterSpacing: -0.02em
  title-mobile:
    fontFamily: *sans
    fontSize: 19px
    fontWeight: '700'
    lineHeight: '1.3'
    letterSpacing: -0.02em
  title-md:
    fontFamily: *sans
    fontSize: 21px
    fontWeight: '700'
    lineHeight: '1.3'
  title-sm:
    fontFamily: *sans
    fontSize: 19px
    fontWeight: '700'
    lineHeight: '1.3'
  lead:
    fontFamily: *sans
    fontSize: 21px
    fontWeight: '400'
    lineHeight: '1.55'
  lead-mobile:
    fontFamily: *sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.55'
  section-lead:
    fontFamily: *sans
    fontSize: 20px
    fontWeight: '400'
    lineHeight: '1.55'
  section-lead-mobile:
    fontFamily: *sans
    fontSize: 17px
    fontWeight: '400'
    lineHeight: '1.55'
  body:
    fontFamily: *sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-mobile:
    fontFamily: *sans
    fontSize: 17px
    fontWeight: '400'
    lineHeight: '1.6'
  body-card:
    fontFamily: *sans
    fontSize: 17px
    fontWeight: '400'
    lineHeight: '1.55'
  body-card-mobile:
    fontFamily: *sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.55'
  body-sm:
    fontFamily: *sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.55'
  body-sm-mobile:
    fontFamily: *sans
    fontSize: 15px
    fontWeight: '400'
    lineHeight: '1.55'
  caption:
    fontFamily: *sans
    fontSize: 15px
    fontWeight: '400'
    lineHeight: '1.5'
  caption-mobile:
    fontFamily: *sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  legal:
    fontFamily: *sans
    fontSize: 14px
    fontWeight: '400'
    lineHeight: '1.5'
  label-caps:
    fontFamily: *sans
    fontSize: 14px
    fontWeight: '700'
    lineHeight: '1.3'
    letterSpacing: 0.09em
  label-caps-mobile:
    fontFamily: *sans
    fontSize: 13px
    fontWeight: '700'
    lineHeight: '1.3'
    letterSpacing: 0.09em
  button:
    fontFamily: *sans
    fontSize: 17px
    fontWeight: '700'
    lineHeight: '1.2'
  nav:
    fontFamily: *sans
    fontSize: 16px
    fontWeight: '600'
    lineHeight: '1.2'
  price:
    fontFamily: *sans
    fontSize: 56px
    fontWeight: '700'
    lineHeight: '0.9'
    letterSpacing: -0.02em
rounded:
  none: 0px
  sm: 3px
  md: 6px
  lg: 8px
spacing:
  content-max: 1120px
  gutter: 48px
  gutter-mobile: 16px
  section: 96px
  section-mobile: 56px
  section-first: 72px
  section-first-mobile: 44px
  header-height: 64px
  gap-sm: 14px
  gap-md: 20px
  gap-lg: 24px
  gap-xl: 32px
  gap-2xl: 56px
  card-padding: 24px
  card-padding-lg: 32px
  bp-medium: 1000px
  bp-phone: 760px
  min-viewport: 360px
  reflow-floor: 320px
components:
  lockup-b:
    tile: 32px
    tile-mobile: 28px
    wordmark-size: 0.63 T
    wordmark-weights: '600 + 400'
    gap: 0.375 T
    clear-space: 0.5 T
    min-tile: 20px
    name: '{colors.navy}'
    manager: '{colors.ink-muted}'
    name-on-dark: '{colors.white}'
    manager-on-dark: '{colors.ink-muted-on-dark}'
    dark-surface: '{colors.band}'
  lockup-c:
    min-tile: 40px
    name-size: 0.46 T
    manager-size: 0.31 T
    line-gap: 0.17 T
  lockup-d:
    footer-tile: 20px
    name: '{colors.navy}'
    name-muted: '{colors.ink-muted}'
  icon-f:
    min-tile: 16px
    clear-space: 0.25 T
  header:
    background: '{colors.band}'
    min-height: '{spacing.header-height}'
    link: '{colors.white}'
    link-type: '{typography.nav}'
    action-fill: '{colors.white}'
    action-text: '{colors.navy}'
    action-min-height: 44px
  button-primary-on-dark:
    background: '{colors.white}'
    foreground: '{colors.navy}'
    border: '{colors.white}'
    hover: '{colors.hover-on-navy}'
    radius: '{rounded.sm}'
    type: '{typography.button}'
    min-height: 54px
    min-height-mobile: 52px
    padding: 14px 26px
    padding-mobile: 13px 18px
  button-primary-on-light:
    background: '{colors.navy}'
    foreground: '{colors.white}'
    border: '{colors.navy}'
    hover: '{colors.navy-hi}'
    radius: '{rounded.sm}'
    type: '{typography.button}'
  button-secondary-on-dark:
    background: transparent
    foreground: '{colors.white}'
    border: '{colors.white}'
    hover: '{colors.navy-hi}'
    radius: '{rounded.sm}'
    type: '{typography.button}'
  focus-ring:
    color-on-light: '{colors.navy}'
    color-on-dark: '{colors.white}'
    width: 3px
    offset: 2px
  hero:
    background: '{colors.navy}'
    heading: '{typography.display}'
    heading-mobile: '{typography.display-mobile}'
    eyebrow: '{colors.ink-muted-on-dark}'
    lead: '{colors.ink-lead-on-navy}'
    highlight-bar: '{colors.check-green}'
  ticks:
    icon: '{colors.check-green}'
    text: '{colors.white}'
    type: '{typography.body-sm}'
  action-note:
    text: '{colors.ink-muted-on-dark}'
    type: '{typography.caption}'
  requirement-line:
    background: '{colors.navy-hi}'
    foreground: '{colors.white}'
    radius: '{rounded.sm}'
    type: '{typography.body-sm}'
  version-line:
    type: '{typography.caption}'
    on-dark: '{colors.ink-muted-on-dark}'
    on-light: '{colors.ink-muted}'
  screenshot-frame:
    radius: '{rounded.lg}'
    radius-mobile: '{rounded.md}'
    bar: '{colors.frame-bar}'
    bar-line: '{colors.frame-line}'
    dots: '{colors.frame-dot}'
    address: '{colors.frame-text}'
    shadow: '0 30px 70px rgba(11,18,32,.38), 0 0 0 1px rgba(27,42,65,.12)'
    shadow-detail: '0 14px 40px rgba(27,42,65,.14)'
    overlap: 236px
    overlap-mobile: 104px
    caption: '{colors.ink-muted}'
  need-item:
    background: '{colors.white}'
    border: '{colors.hairline}'
    accent-top: '{colors.navy}'
    radius: '{rounded.sm}'
    padding: '{spacing.card-padding}'
    title-size: '{typography.title-md}'
  chip:
    border: '{colors.navy}'
    foreground: '{colors.navy}'
    radius: '{rounded.sm}'
  pillar-list:
    icon: '{colors.check-green}'
    text: '{colors.navy}'
    type: '{typography.title}'
  step-number:
    size: 46px
    background: '{colors.navy}'
    foreground: '{colors.white}'
    radius: '{rounded.none}'
  flow-node:
    border: '{colors.navy}'
    foreground: '{colors.navy}'
    you-background: '{colors.navy}'
    you-foreground: '{colors.white}'
    strip: '{colors.tint}'
    radius: '{rounded.sm}'
  trust-line:
    icon: '{colors.check-green}'
    text: '{colors.ink-body}'
  plan-card-free:
    background: '{colors.white}'
    border: '{colors.hairline}'
    radius: '{rounded.sm}'
    padding: '{spacing.card-padding-lg}'
  plan-card-pro:
    background: '{colors.navy}'
    foreground: '{colors.white}'
    muted: '{colors.ink-muted-on-dark}'
    rule: '{colors.navy-hi}'
    price: '{typography.price}'
    radius: '{rounded.sm}'
    padding: '{spacing.card-padding-lg}'
  plan-note:
    background: '{colors.tint}'
    foreground: '{colors.ink-body}'
    radius: '{rounded.sm}'
  currency-switch:
    selected-background: '{colors.navy}'
    selected-foreground: '{colors.white}'
    border: '{colors.navy}'
    border-width: 1.5px
    min-target: 44px
  assurance-item:
    background: '{colors.white}'
    border: '{colors.hairline}'
    accent-left: '{colors.check-green}'
    radius: '{rounded.sm}'
  footer:
    rule: '{colors.hairline}'
    text: '{colors.ink-muted}'
    link: '{colors.navy}'
    legal: '{typography.legal}'
  skip-link:
    background: '{colors.white}'
    foreground: '{colors.navy}'
  sharing-card:
    size: 1200 x 630 px
    tile: 96px
    background: '{colors.navy}'
    lockup-surface: '{colors.band}'
    tagline: '{colors.white}'
    domain: '{colors.ink-muted-on-dark}'
---

# postquality.com — Design Spine

How the public website of Post Quality Manager looks. The tokens come from the chosen direction, [Navy and check](.working/direction-navy-and-check.html) (not a markup reference; overrides in EXPERIENCE.md § Inspiration & Anti-patterns), and the lockup sheet, [logo-lockups.html](.working/logo-lockups.html) (overridden: band-strip header, navy links, white footer, 404 as a Text page), as amended by the owner's later decisions in [.memlog.md](.memlog.md). The comparison the owner picked from is [compare-home-first-screens.html](.working/compare-home-first-screens.html). This spine wins on conflict with those files. Behaviour lives in [EXPERIENCE.md](EXPERIENCE.md). Open visual points are marked `[NOTE FOR UX]`; the key-screen mocks (`/pricing/`, `/docs/`, phone Menu) settle them; Text pages stay spine-only. All page copy quoted here is draft: the final copy comes from story 60 (copywriting) once story 53's messaging exists.

**Build overrides (2026-10-23):** [build-brief.md](build-brief.md) wins over this spine where they differ: the header and footer lockups are the icon SVG plus live text in the system sans (D2; the outlined SVG stays with story 41), images ship as WebP + PNG (D14), the pack cards carry a who-it-is-for line (D13). Story 86 builds it.

**Validation:** [validation-report.html](validation-report.html), [review-rubric.md](review-rubric.md), [review-accessibility.md](review-accessibility.md), [review-honesty-conversion.md](review-honesty-conversion.md); fixes applied per [triage.md](triage.md).

## Brand & Style

Brand-led and confident. The icon's own palette carries the page: a full-bleed navy hero band, a large bold white sans headline, one white primary button, and the green check used only where something is confirmed. It is the direction closest to a product landing page, with one clear action per page and bigger type than the plugin.

The site must read as the site, never as the plugin. The plugin's WordPress admin look (WP blue, grey canvas, 13 px type) appears only inside framed screenshots, and the Dashboard screenshot floats over the band's lower edge so the visitor "lands inside the plugin" while the page around it stays in brand colours.

It stays honest: no invented evidence, no urgency, the prerequisites stated before any install action. Plain status lines ("Pre-release, not installable yet") are part of the look, not an apology.

## Colors

The palette is the icon's three colours plus cool neutrals derived from the navy.

| Token | Hex | Where | Contrast where it carries text |
|---|---|---|---|
| `{colors.navy}` | #1b2a41 | Hero band, Pro plan card, headings and links on white, primary-button text on dark, primary-button fill on light, step numbers | Navy on white 14.44:1 · on `tint` 13.22:1 · on `hover-on-navy` 12.27:1 |
| `{colors.white}` | #ffffff | Page surface, type on navy, primary-button fill on dark | White on navy 14.44:1 · on `navy-hi` 11.46:1 · on `band` 9.11:1 |
| `{colors.check-green}` | #00a32a | Check marks, the h1 highlight bar, the assurance item's left edge. Graphics only | Never text, never a fill behind text: white on green 3.35:1. As a graphic: 4.31:1 on navy, 3.42:1 on `navy-hi`, 3.35:1 on white, 3.07:1 on `tint`, **2.72:1 on `band` (fails 3:1)** |
| `{colors.band}` | #33496b | The header strip on every page, the sharing card's lockup surface, and the lockup's surface on any other dark area | White 9.11:1 · `ink-muted-on-dark` 5.62:1. Tile against the band 1.59:1, so the tile stays visible |
| `{colors.navy-hi}` | #263a59 | Hover fill of the secondary button, the requirement line's fill, the Pro card's inner rule | White 11.46:1 · `ink-muted-on-dark` 7.07:1 |
| `{colors.hover-on-navy}` | #e8edf3 | Hover fill of the primary button on dark | Navy 12.27:1 |
| `{colors.ink-body}` | #2a3649 | Body text on light surfaces | 12.19:1 on white · 11.16:1 on `tint` |
| `{colors.ink-muted}` | #50575e | Muted text on light: "Manager" in lockups B and C, the footer lockup D, kickers, section leads, captions, plan footnotes, footer text | 7.33:1 on white · 6.71:1 on `tint` |
| `{colors.ink-lead-on-navy}` | #dfe6ef | Hero lead paragraph, price period | 11.49:1 on navy |
| `{colors.ink-muted-on-dark}` | #c3ccd9 | Muted text on dark: "Manager" in B and C, the eyebrow, the status and version line, the action note, muted text on the Pro card, the sharing card's domain | 8.91:1 on navy · 7.07:1 on `navy-hi` · 5.62:1 on `band` |
| `{colors.tint}` | #f3f5f8 | Alternate section background, plan note, flow-diagram strip | Navy 13.22:1 · `ink-muted` 6.71:1 |
| `{colors.hairline}` | #d6dce5 | Decorative card borders, table row rules and the footer rule. Never on an interactive element | Non-text, decorative (1.38:1 on white) |
| `{colors.frame-bar}` / `frame-line` / `frame-dot` | #f7f8fa / #cfd5de / #c3c9d2 | The browser bar drawn around each screenshot | — |
| `{colors.frame-text}` | #4a5466 | The address text in the screenshot bar | 7.18:1 on `frame-bar` |

Rules:

- **Green is a check, not a colour field.** Ticks, the highlight bar under "fix next" and the assurance item's 4 px left edge. Nothing else, not the focus ring.
- **WordPress admin colours (#2271b1 and the status palette) appear only inside screenshots.** Site links and buttons are navy or white.
- **One grey family.** `ink-muted` and `ink-muted-on-dark` are the lockup's own greys, used for all muted text; the direction's own two greys are dropped.
- **Light scheme only, no dark mode** (decision): a static marketing site, where the navy hero carries the contrast.

## Typography

One family: the system sans stack (`{typography.body.fontFamily}`). No web font and no font CDN; if a signature face is ever wanted, a self-hosted grotesque (Inter or Public Sans, SIL OFL) served from the site itself. The wordmark does not depend on this: it is outlined SVG (see Components → Lockup). Sizes are set in rem against a 16 px root; token values stay px figures.

| Role | Token | Desktop | Phone |
|---|---|---|---|
| Home h1 | `display` / `display-mobile` | 64 / 1.05, 700 | 36 / 1.1 |
| Section heading (h2); Text page h1 | `headline` / `headline-mobile` | 40 / 1.15, 700 | 29 |
| Plan name | `title-lg` | 28 / 1.2, 700 | 28 |
| Step title, pillar name; Text page h2 | `title` / `title-mobile` | 22 / 1.3, 700 | 22 (pillar names `title-mobile` 19) |
| What you need item title | `title-md` | 21 / 1.3, 700 | 19 (`title-sm`) |
| Assurance title; Text page h3 | `title-sm` | 19 / 1.3, 700 | 19 |
| Hero lead | `lead` / `lead-mobile` | 21 / 1.55 | 18 |
| Section lead | `section-lead` / `section-lead-mobile` | 20 / 1.55 | 17 |
| Body | `body` / `body-mobile` | 18 / 1.6 | 17 |
| Card text, comparison table | `body-card` / `body-card-mobile` | 17 / 1.55 | 16 in What you need items |
| Ticks, requirement line, footer | `body-sm` / `body-sm-mobile` | 16 / 1.55 | 15 for ticks and requirement line |
| Action note, status and version line, captions, plan footnotes | `caption` / `caption-mobile` | 15 / 1.5 | 14 |
| Footer legal line | `legal` | 14 / 1.5 | 14 |
| Eyebrow and kicker | `label-caps` / `label-caps-mobile` | 14 / 1.3, 700, +0.09em, uppercase | 13 for the eyebrow |
| Buttons | `button` | 17 / 1.2, 700 | 17 |
| Header links | `nav` | 16 / 1.2, 600 | in the Menu |
| Price | `price` | 56 / 0.9, 700 | 56 |

Headings are navy with negative tracking (-0.02em; the h1 -0.025em). The h1 on the band is white. On desktop the h1 breaks into two lines, one per sentence; on phones it runs inline.

## Layout & Spacing

- **Content column:** `{spacing.content-max}` wide, side gutters `{spacing.gutter}` (desktop) and `{spacing.gutter-mobile}` (phone).
- **Sections:** `{spacing.section}` vertical padding (`{spacing.section-mobile}` on phones). The first section under the screenshot uses `{spacing.section-first}` / `{spacing.section-first-mobile}`. Sections alternate white and `{colors.tint}`.
- **Header:** `{spacing.header-height}` is a `min-height`, never a `height` (2 T for a 32 px tile: the tile plus 0.5 T clear space above and below); the row has `flex-wrap: wrap`. The desktop row shows from `{spacing.bp-medium}` up; below it, the Menu.
- **Hero:** centred on desktop (64 px above the eyebrow, 280 px below the ticks to make room for the overlapping screenshot), left-aligned on phones (30 px / 132 px). The Dashboard screenshot is pulled up by 236 px (104 px on phones) so it overlaps the band's lower edge.
- **Grids:** What you need 3 columns (gap `{spacing.gap-md}`); How it works 3 columns (`{spacing.gap-xl}`); Free and Pro 2 columns (`{spacing.gap-lg}`); pillars text + review screenshot 2 columns (`{spacing.gap-2xl}`); hero actions gap `{spacing.gap-sm}`.
- **Breakpoints:** below `{spacing.bp-medium}` the header shows the Menu and the pillars block stacks. Below `{spacing.bp-phone}` everything is one column: actions stack full width (primary first), ticks stack, plan cards and assurance items stack, the flow diagram turns vertical. (The direction file drove these with container queries; the site uses viewport widths.) Floor `{spacing.reflow-floor}` with no horizontal scroll; `{spacing.min-viewport}` is the design target.
- **Long strings wrap:** `main { overflow-wrap: anywhere }`; `pre { white-space: pre-wrap }`, or a `pre` scrolling in its box with `tabindex="0"` and a name; `h1, h2 { overflow-wrap: break-word; hyphens: auto }`.

## Elevation & Depth

Flat by default: hierarchy comes from the navy band, the tint sections and type size. Depth is reserved for the plugin:

- The hero Dashboard screenshot carries the one strong shadow (`{components.screenshot-frame.shadow}`): it floats over the band.
- The review-detail screenshot carries a softer one (`{components.screenshot-frame.shadow-detail}`).
- Shadows use only rgba tints of the navy (27, 42, 65) and of #0b1220 (11, 18, 32).
- Cards use a hairline border, not a shadow.

## Shapes

- `{rounded.sm}` (3 px) on buttons, cards, chips, notes, the flow nodes and the focus ring.
- `{rounded.lg}` (8 px) on the screenshot frame, `{rounded.md}` (6 px) on phones.
- `{rounded.none}`: the icon tile (never rounded) and the step numbers, which echo the tile's square.
- No pills and no circles for surfaces. Check marks have round caps and joins.

## Components

Visual specs. Behaviour lives in EXPERIENCE.md § Component Patterns under the same names. Frontmatter keys: `need-item`, `chip` = What you need block; `step-number` = Steps; `flow-node` = Flow diagram; `plan-card-free`, `plan-card-pro`, `plan-note` = Plan card; `lockup-b/c/d`, `icon-f` = Lockup.

- **Line icons:** the direction file's SVGs redrawn on a 24 px grid, 2 px stroke, round caps and joins, at 34, 28 and 20 px; none echoes a third-party logo.
- **Kickers** sit inside their h2 as a span (`<h2><span class="kicker">Free and Pro</span> …</h2>`), styled as today.

### Lockup

One fixed brand asset, independent of the site layout, in sans to match the plugin's screens. Final artwork: the wordmark converted to SVG outlines, source typeface Inter (SIL OFL 1.1), so it renders the same on every system. Recorded in `docs/brand.md` at Finalize.

| Variant | Status | Anatomy | Where |
|---|---|---|---|
| **B** | **Primary** | Icon + "Post Quality" 600 `{colors.navy}` + "Manager" 400 `{colors.ink-muted}`. On dark: `{colors.white}` + `{colors.ink-muted-on-dark}`, on `{colors.band}` | Header (every page, desktop and phone), the sharing card. It is the full name for the first-mention rule and stresses the short form, which is the domain |
| **C** | Responsive | Stacked: "Post Quality" 0.46 T over "Manager" 0.31 T, 0.17 T between lines | Not used on the site; kept in `docs/brand.md` for square spots elsewhere (tile 40 px+) |
| **D** | Responsive | Icon + "Post Quality" 600 | Only where the full name is already on the page: the footer (20 px tile, text in `{colors.ink-muted}`), a second mention |
| **F** | Responsive | Icon alone | Favicon (16, 32), the 404 page (64), avatars, the 128 / 256 / 512 px files, the Freemius product image, anything under a 20 px tile |
| E | **Rejected** | B set in a serif | A second wordmark would read as a second brand |

Specs, in units of T (the tile's side):

- Wordmark size 0.63 T, so the cap height (about 0.44 T) matches the outer diameter of the Q's ring; caps centred on the tile.
- Gap icon → text 0.375 T (12 px at a 32 px tile, 10.5 px at 28, 7.5 px at 20; round to whole pixels).
- Clear space 0.5 T on all four sides; F alone keeps 0.25 T where a platform adds no padding of its own.
- Sizes in use: header 32 px (desktop), 28 px (phone); footer 20 px; 404 icon 64 px; sharing card 96 px; favicon 16 and 32 px; plugin and Freemius files 128, 256, 512 px.
- Minimums: one-line wordmarks 20 px tile (text 12.6 px); C 40 px. Below that, or where the width does not fit (B needs about 210 px at a 28 px tile), only F remains, down to 16 px. The header never drops to F: below 360 px it wraps, lockup B on the first line, the Menu on the second.

**The icon on navy.** The tile is #1b2a41; on a #1b2a41 surface it disappears (1.00:1). Rule: **the tile never sits directly on navy.** On any dark surface the lockup sits on `{colors.band}` (tile vs band 1.59:1, white text 9.11:1). The icon file stays identical everywhere. A 1 px white keyline (outside the tile) is the fallback only for a dark surface that cannot be recoloured, and only from a 48 px tile upward.

**The icon itself (story 51), fixed:** square navy tile with sharp corners, white Q, green check rising to the right. Never mirrored, stretched, recoloured, rounded, or given a third colour. Files `assets/icon.svg` and `icon-128/256/512x512.png`.

### Page components

| Component | Visual spec |
|---|---|
| **Header** | A full-width `{colors.band}` strip on every page, neither sticky nor fixed; on `/` it sits on top of the navy hero. Lockup B left (32 px tile; 28 px on phones). From `{spacing.bp-medium}` up: links Features, Pricing, Docs (the Documentation page), Support in `{typography.nav}` white, 44 px tall, underlined on hover (offset 4 px), then the site-wide primary action as a 44 px primary button: `{colors.white}` fill, `{colors.navy}` text (14.44:1), mail icon before launch. |
| **Menu disclosure** | Below `{spacing.bp-medium}`: a "Menu" button right of the lockup, 44 px tall, label visible, its marker changing shape when open. The open panel sits in flow, full width under the header row, pushing the page down; never absolute, fixed or an overlay. It ends with the site-wide primary action. `[NOTE FOR UX]` Its look on the band is settled in the phone Menu mock. |
| **Skip link** | "Skip to content", off-screen until focused, then shown top left: `{colors.white}` fill, `{colors.navy}` text. |
| **Primary button** | On dark: `{colors.white}` fill, `{colors.navy}` text (14.44:1), hover `{colors.hover-on-navy}`. On light: `{colors.navy}` fill, white text (14.44:1), hover `{colors.navy-hi}`. Every button keeps a 2 px border in its fill colour (forced colours). `{rounded.sm}`, min-height 54 px (52 on phones; header 44), padding 14 × 26 px (13 × 18), `{typography.button}`, optional 20 px leading icon: mail or arrow, no download icon. Full width on phones. |
| **Secondary button** | On dark only: transparent, white text and 2 px white border (14.44:1), hover fill `{colors.navy-hi}`. Same size as the primary. |
| **Text link** | In running text, copied Markdown included, always underlined: `text-underline-offset: .15em; text-decoration-thickness: 1px`, 2 px on hover. Navy on light, white on dark; colour alone cannot mark them (navy on `ink-body` 1.18:1). Only the header nav, the footer policy list and button-styled links drop the underline. |
| **Focus ring** | 3 px outline, 2 px offset, `{rounded.sm}`, its colour a custom property set per surface: `:root { --ring: #1b2a41 } .on-dark { --ring: #fff }`. `.on-dark`: the header strip (skip link included), the hero, the Pro plan card, the requirement line, a dark Menu panel. The ring contrasts with the surface around the element: navy 14.44:1 on white, 13.22:1 on `tint`; white 14.44:1 on navy, 9.11:1 on `band`, 11.46:1 on `navy-hi`. |
| **Hero** | `{colors.navy}` band. Eyebrow in `{typography.label-caps}` `{colors.ink-muted-on-dark}`; h1 in `{typography.display}` white, with a green bar under the words "fix next" (0.11 em tall, at 92 % of the line); lead in `{typography.lead}` `{colors.ink-lead-on-navy}`, max 720 px; actions; status or version line; action note; ticks. |
| **Ticks** | Three short claims in a row (stacked on phones), each with a 20 px green check, `{typography.body-sm}` white. |
| **Action note** | One line under the status or version line, `{typography.caption}` `{colors.ink-muted-on-dark}`, max 620 px. |
| **Requirement line** | After launch, above the hero actions: `{colors.navy-hi}` fill, white text (11.46:1), 20 px info icon, a bold white Text link "What you need". Max 680 px, `{rounded.sm}`. |
| **Version line** | Under the hero actions, before the action note: the status line before launch, the version line after, `{typography.caption}` `{colors.ink-muted-on-dark}`. Repeated beside **Install from WordPress.org** in `{colors.ink-muted}`. |
| **Screenshot frame** | The screenshot inside a drawn browser bar (32 px, `{colors.frame-bar}`, three `{colors.frame-dot}` dots, address "yourblog.example/wp-admin/" in 12 px `{colors.frame-text}`), `{rounded.lg}`, shadow per Elevation. The drawn bar is `aria-hidden="true"`, or part of the image. Caption below in `{typography.caption}` `{colors.ink-muted}`. Phone: a narrower second crop of the same screen, `{rounded.md}`, bar 26 px. |
| **What you need block** | Kicker "Before you install" in the h2, section lead. Three items: white, hairline border, 4 px `{colors.navy}` top edge, `{rounded.sm}`, padding `{spacing.card-padding}`, a 34 px navy line icon (workflow: boxes and arrows; key; bill), title `{typography.title-md}`, text `{typography.body-card}`. Below: chips (1.5 px navy border, navy 700 text, `{rounded.sm}`) for "WordPress 6.6+" and "PHP 8.1+", then a muted note "Single sites; Multisite is not supported." |
| **Pillar list** | The four pillar names in a 2 × 2 grid, `{typography.title}` navy with a 24 px green check, on `{colors.tint}` (check 3.07:1). Beside it the review-detail screenshot. |
| **Steps** | Three numbered steps: a 46 px navy square with a white numeral (21 px, 700), a `{typography.title}` heading, `{typography.body-card}` text. |
| **Flow diagram** | A `{colors.tint}` strip with hairline border: four nodes joined by navy arrows. Nodes white with a 1.5 px navy border and navy 700 text; the two ends ("Your site", "Back in WP Admin") filled navy with white text. Vertical on phones (arrows rotate 90°). |
| **Trust line** | One sentence with a green check, `{colors.ink-body}`, max 760 px. The data line sits centred under the flow diagram; the author line ("Built by the author and used on real blogs.") under the Free vs Pro teaser, before the footer. |
| **Plan card** | Free: white, hairline border, padding `{spacing.card-padding-lg}`, feature list with green checks, a plan note (`{colors.tint}` fill, info icon, `{colors.ink-body}` text) above the button. Pro: `{colors.navy}` fill, white text, price in `{typography.price}` with the period in `{colors.ink-lead-on-navy}`, the tax line under it, a `{colors.navy-hi}` rule under the price line, feature list with green checks (4.31:1), secondary button. Each card owns its muted footnote, `{typography.caption}` in `{colors.ink-muted}` (`{colors.ink-muted-on-dark}` on Pro). Pricing page: three pack cards, without buttons until checkout links exist. `[NOTE FOR UX]` Settled in the `/pricing/` mock. |
| **Comparison table** | A real table at every width: caption, header row, `{colors.hairline}` row rules, `{typography.body-card}`. Marks: green check (3.35:1 on white) or `{colors.ink-muted}` dash (7.33:1). Never reflowed into cards. |
| **Currency switch** | USD / EUR / CHF as three radio tabs above the pack cards, at least 44 × 44 px. Selected: `{colors.navy}` fill, white bold text (14.44:1), a check glyph. Unselected: white, 1.5 px `{colors.navy}` border, navy text. Every tab keeps its border (forced colours). |
| **Assurance item** | White, hairline border, 4 px `{colors.check-green}` left edge, `{rounded.sm}`, a 28 px navy line icon (shield with a green check; archive box), `{typography.title-sm}` title, `{typography.body-sm}` text. Two side by side, stacked on phones. |
| **FAQ** | One disclosure per question, the question as the summary (no heading inside), all closed. Summaries full width, min-height 44 px, the marker changing shape when open. `[NOTE FOR UX]` The summary's look is settled in the `/pricing/` mock. |
| **Text page** | `/support/`, the policies, 404. h1 `{typography.headline}` (`headline-mobile` on phones), navy on white, no band; body `{typography.body}` `{colors.ink-body}`, measure max 720 px; h2 `{typography.title}`, h3 `{typography.title-sm}`; standard bullets `{spacing.gap-sm}` apart. 404: icon F at 64 px, h1 "Page not found", three links, a primary button on light. |
| **Footer** | White, hairline top rule. Left: lockup D at 20 px, muted, not a link; the tagline verbatim in `{typography.body-sm}` `{colors.ink-muted}`, max 460 px. Right: policy links (Privacy, Terms, Refund, Support) in navy 600, the address hello@postquality.com in bold. Bottom row under a hairline, `{typography.legal}` muted: the no-cookies line and "© 2026 Vincent Heimann · GPL-2.0-or-later". |
| **Sharing card** | og:image, 1200 × 630, built once as a static PNG, dark version: `{colors.navy}` card, lockup B at a 96 px tile on `{colors.band}`, the tagline verbatim at 40 px in white, the domain in `{colors.ink-muted-on-dark}`, a 60 px safe margin; no screenshot, no third-party mark. (The sheet drew lockup A, not adopted.) |

## Do's and Don'ts

| Do | Don't |
|---|---|
| Green only for checks, the highlight bar and the assurance edge; focus ring navy on light, white on dark | Green as a fill behind text (3.35:1) or as the focus ring (2.72:1 on the band) |
| Put the lockup on `{colors.band}` on every dark surface | Set the tile directly on #1b2a41, or draw a keyline under 48 px |
| Keep the icon identical everywhere, in its three colours | Mirror, stretch, recolour or round the icon, or add a third colour |
| Use lockup B as the first mention, D only as a second mention | Use the serif wordmark E, or any second wordmark |
| Keep WordPress admin colours inside screenshots | Use WP blue for site links or buttons |
| Frame every plugin picture as a screenshot with a caption that says the demo content is fictional | Third-party logos or marks (WordPress "W", n8n, Anthropic, Claude) in any imagery |
| System fonts; outlined SVG for the wordmark | Web fonts from a CDN, or any third-party request |
| Shadows only on the screenshots | Shadows on cards, buttons or the header |
| A plain status line beside an action ("Pre-release, not installable yet") | Countdowns, scarcity badges, ratings, counts or quotes that do not exist |
