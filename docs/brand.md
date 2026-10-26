# Post Quality Manager — Brand Guidelines

_This file is the single source of truth for brand, copy, and tone decisions in the WordPress admin panel. The agent must read this file before writing any UI text, labels, or copy._

---

## Brand Identity & Tone

**Name:** Post Quality Manager (full name) — short form **Post Quality**; see [Naming](#naming) for which surface uses which
**Slug / text domain:** `post-quality-manager` (permanent)
**Tagline (readme short description):** Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin.
**Category:** WordPress content quality dashboard
**Target audience:** Professional Bloggers, Content Editors, SEO Specialists

### Voice & Tone
- **Professional & Analytical:** Users are here to work and optimize content. Be precise and clear.
- **Casual & Encouraging:** Don't be robotic or overly corporate. Use a friendly "colleague" tone.
- **Action-Oriented:** Focus on what they can do next to improve quality.

---

## Value Proposition

> Streamline your content quality control. Spot issues instantly, automate the review process, and maintain high standards effortlessly.

Promotional wording and the KPI figures behind it: story 53 (needs refinement with the owner's real KPIs) writes them to `docs/messaging.md`, which then replaces this line as the canon for public surfaces.

---

## Copy Rules (Agent Instructions)

### Do
- Use clear, actionable verbs for CTAs (e.g., "Review Post", "Rewrite Content", "Apply Changes").
- Frame everything as **optimization and enhancement**.
- Be concise. Real estate in the WP admin panel is valuable. Focus on the actual data (scores, issues, takeaways).
- Explain errors gently with actionable solutions (e.g., "Could not connect to n8n. Check your Webhook URL in Settings."). The Settings step is for administrators; everyone else is told to ask an administrator.

### Don't
- Never use accusatory or purely negative framing (e.g., "Your post is terrible", "Failed completely"). Replace with constructive feedback (e.g., "Requires revision").
- Do not use overly complex jargon unless standard for SEO/blogging.
- Avoid patronizing praise. They are professionals.

### UX Writing Examples

| Context | Good Copy | Bad Copy |
|---|---|---|
| No reviews yet | "No reviews found. Trigger a review to get started." | "You haven't done anything yet!" |
| Successful review | "Review complete. View takeaways." | "Success! Your post was successfully reviewed!" |
| Action button | "Review Post" | "Click here to run n8n" |

---

## Visual Design (Admin Panel)

- Stick closely to the native **WordPress Admin UI** design language.
- Use standard WordPress CSS classes (`.button`, `.button-primary`, `.notice`, `.notice-success`, `.dashicons`).
- Avoid reinventing the wheel with custom complex CSS unless necessary to organize large datasets (like tabs for review details).
- Visual hierarchy should prioritize:
  1. The overall score or status
  2. Actionable takeaways/issues
  3. Action buttons (Review/Rewrite)
- The logo lockup, the imagery direction and the WordPress.org assets: see Lockup and Imagery below.

### Icon

Owner decision 2026-10-12 (story 51).

- **Mark:** a geometric Q whose tail continues into a check: "quality, verified".
- **Construction:** a perfect ring (R = 7, stroke 2.5 on a 20-unit grid). Ring diameter : check long arm : check short arm = φ² : φ : 1 (φ = 1.618). The tail starts just inside the ring's inner edge and descends at 45° to the corner; the long arm rises at 45°. One stroke weight, round terminals, bounding box centred, the whole scaled to a 19-unit width.
- **Colours:** tile `#1b2a41`, mark `#ffffff`, check `#00a32a` (the dashboard's Ready status green). The admin menu shows the mark in one colour, built from filled shapes only so WordPress can recolour it for every admin color scheme.
- **Files:** `assets/icon.svg`, `assets/icon-128x128.png`, `assets/icon-256x256.png`, `assets/icon-512x512.png` (Freemius product image); the postquality.com favicon is `icon.svg` with `icon-128x128.png` (story 85); `post-quality-manager/admin/img/post-quality-manager.png` (Freemius opt-in screens); the menu icon is the base64 SVG constant `PostQuality_Admin::MENU_ICON`.
- **Rules:** never mirror it (the check must rise to the right), never stretch it, never add a third colour, the wordmark lockup is in § Lockup below (its outlined-SVG artwork: `assets/lockup-b.svg`, `assets/lockup-b-dark.svg`), no WordPress, Claude, Anthropic or n8n trademark.

### Lockup

Owner decisions 2026-10-22/23 (story 57's UX pass, picked on the band; story 86 build brief D2). Specs from the site's design spine (`_bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md` § Lockup); first applied on postquality.com (story 86).

| Variant | Anatomy | Where |
|---|---|---|
| **B** (primary) | Icon + "Post Quality" 600 + "Manager" 400, one line. On light: `#1b2a41` + `#50575e`. On dark: `#ffffff` + `#c3ccd9`, on the band `#33496b` | Every header, the sharing card. It is the full name for the first-mention rule |
| **C** | Stacked: "Post Quality" 0.46 T over "Manager" 0.31 T, 0.17 T between the lines; tile 40 px or more | Square spots off the site; not used on postquality.com |
| **D** | Icon + "Post Quality" 600 | Only where the full name is already on the page: the site footer (20 px tile, text `#50575e`), a second mention |
| **F** | The icon alone | Favicon (16, 32), the 404 page (64), avatars, the 128/256/512 px files, the Freemius product image, anything under a 20 px tile |
| E | Rejected: B set in a serif | A second wordmark would read as a second brand |

- **Units of T** (the tile's side): wordmark 0.63 T, so its cap height matches the Q's ring; gap icon → text 0.375 T (12 px at a 32 px tile, 10.5 at 28, 7.5 at 20, rounded to whole pixels); clear space 0.5 T on all four sides (F alone 0.25 T where a platform adds no padding).
- **Sizes in use:** site header 32 px (28 px on phones), footer 20 px, 404 icon 64 px, sharing card 96 px. Minimum tile 20 px for one-line wordmarks (C: 40 px); below that, or where B does not fit (about 210 px wide at a 28 px tile), only F, down to 16 px. A header never drops to F: on a narrow screen it wraps instead.
- **Live text, not artwork (D2):** on the website the wordmark is HTML text in the system sans next to the icon SVG, sized in T-units, so it is accessible and needs no asset pipeline. The outlined-SVG wordmark (source typeface Inter, SIL OFL 1.1) for places that cannot set text is `assets/lockup-b.svg` (light) and `assets/lockup-b-dark.svg` (on the band), generated by `bin/outline-wordmark.py` (story 41, 2026-10-24); the rendered lockup assets are the sharing card PNG and the WordPress.org banners, see § Imagery and the WordPress.org assets.
- **Dark surfaces:** the tile never sits directly on `#1b2a41` (1.00:1, it disappears). On any dark surface the lockup sits on the band `#33496b` (tile vs band 1.59:1, white text 9.11:1). A 1 px white keyline outside the tile is the fallback only for a dark surface that cannot be recoloured, and only from a 48 px tile up. The icon file itself stays identical everywhere.

### Imagery and the WordPress.org assets

Owner delegation 2026-10-24 (story 41).

- **Imagery direction:** the brand's own artwork only — the lockup on the band, the tagline, and captures of the plugin in use on a demo site (fictional posts and scores, captioned as such). No stock photo, no illustration, no third-party mark, no screenshot inside a banner. The banners are the sharing card's composition at the directory's sizes; the WordPress.org screenshots are the postquality.com captures, copied as they are.
- **Artwork face:** Inter (SIL OFL 1.1), installed locally, for the rendered PNGs and the outlined SVGs. The website keeps the system sans as live text (D2). Never a web font request or a font CDN.
- **No localised or RTL banner:** one English set; the directory shows it for every locale.
- **Where:** `assets/` at the repository root, the WordPress.org assets folder. `bin/zip.js` zips only `post-quality-manager/`, so nothing here ships in the plugin. Only these files go to the SVN `assets/` (story 38's deploy syncs those and nothing else): `icon-128x128.png`, `icon-256x256.png`, `icon.svg`, `banner-772x250.png`, `banner-1544x500.png`, `screenshot-N.png`; `assets/src/`, the lockup SVGs, `icon-512x512.png` and `portfolio/` stay in the repository.

| File | Size | Source | Re-render |
|---|---|---|---|
| `assets/banner-772x250.png` | 772 × 250 | `assets/src/banner.html` | headless Edge with a fresh throwaway `--user-data-dir`: `--headless=new --hide-scrollbars --disable-lcd-text --force-color-profile=srgb --window-size=772,250 --screenshot=<file>` (the full command is in the HTML's comment; `--disable-lcd-text` keeps the text free of ClearType colour fringes). Then the font check: render a copy of the page with `font-family: Inter, monospace` and compare; identical PNG bytes mean Inter resolved, a different file means Edge fell back to system-ui |
| `assets/banner-1544x500.png` | 1544 × 500 | the same page | the same with `--force-device-scale-factor=2` (the window size stays 772,250) |
| `assets/screenshot-1.png` | 1440 × 911 | `postquality.com/assets/img/review-list.png` | copy, never re-encode; `readme.txt` caption 1 (Post Reviews) |
| `assets/screenshot-2.png` | 960 × 824 | `postquality.com/assets/img/review.png` | copy; caption 2 (the review) |
| `assets/screenshot-3.png` | 2048 × 1488 | `postquality.com/assets/img/dashboard.png` | copy; caption 3 (the Dashboard) |
| `assets/lockup-b.svg` | viewBox 278.77 × 64 (T = 32) | `bin/outline-wordmark.py`, `assets/icon.svg`, Inter | `uv run --no-cache --with fonttools python -I bin/outline-wordmark.py` |
| `assets/lockup-b-dark.svg` | the same, the band `#33496b` behind | the same | the same run writes both |

- **Banner composition:** the band `#33496b` full-bleed, a 40 px safe margin, lockup B at a 64 px tile (gap 24 px, wordmark 40 px: "Post Quality" 600 white, "Manager" 400 `#c3ccd9`), under it the tagline verbatim in white at 22 px over a 620 px measure (three balanced lines: one sentence per line does not fit at 772 px), the right side quiet. No domain line: the listing names the plugin. PNGs under 4 MB (banners) and 10 MB (screenshots).
- **Lockup SVGs:** paths only (no `<text>`, no font reference), the icon's tile rect and mark group copied byte for byte from `assets/icon.svg` inside a `translate(16 16) scale(1.6)` group; the wordmark at 0.63 T with its cap height centred on the Q ring's centre line, gap 0.375 T, clear space 0.5 T on all four sides, GPOS pair kerning applied. Light: `#1b2a41` + `#50575e` on transparent. Dark: `#ffffff` + `#c3ccd9` on the band. Re-run the script after any change to the icon or the wordmark; it reads `Inter-VariableFont_opsz,wght.ttf` from the user's Windows fonts (or `--font`).
- **Captions:** `readme.txt` § Screenshots numbers the files; a new capture replaces the file with the same number and keeps the caption.

---

## Naming

_Owner decision 2026-10-08 (backlog story 10). The WordPress.org slug is a one-way door: it is derived from the Plugin Name header at submission, it becomes the plugin folder, the text domain and the directory URL, and it can never change once the listing is approved. The display name can change later; the slug cannot. These rules are applied by story 41 (copy sweep). The admin menu label, the Dashboard and Settings headings and the not-connected message were brought in line on 2026-10-23, ahead of the website screenshots (story 86); the rest of the sweep ran 2026-10-24 (story 41, two fixes: the cron schedule label lost its `(PQM)`, the plugin header's Description became the tagline); the commands and counts are in that story's plan._

| Surface | Use | Example |
|---|---|---|
| Plugin Name header, `readme.txt` name, README title, GitHub repo, release notes, first mention in any text | Full name | **Post Quality Manager** |
| WP admin top-level menu label; in-copy cross-references; anywhere space is tight | Short form | **Post Quality**, "Post Quality → n8n Workflow" |
| Submenu labels | Screen name, unchanged | Dashboard, Post Reviews, n8n Workflow, Settings |
| Page headings (`<h1>`, React app shells) | Screen name only, never the product | "Dashboard", not "Content Quality Dashboard" |
| Slug, plugin folder, text domain, zip root, release asset | `post-quality-manager` | `post-quality-manager-3.1.0.zip` |
| `readme.txt` short description | The tagline above, verbatim (124 chars, limit 150) | — |
| Code names: classes, functions, hooks, nonces, AJAX and admin-post actions, the option group, query args, constants, admin page slugs, script and style handles, JS globals (story 88, 2026-10-26; `tests/harness/prefix-harness.php` enumerates them) | `PostQuality_` / `postquality_` / `POSTQUALITY_` / `postquality-` / `postquality…` camel case | code-only: `PostQuality_Admin`, `postquality_fs()`, `POSTQUALITY_VERSION`, `admin.php?page=postquality-dashboard`, `postqualityAdmin` |
| Stored names: options, user meta keys, capabilities, the cron hook and schedule, the `pqm_reviews` table, the `pqm_settings[…]` form fields | `pqm` / `PQM` until story 91 moves them with a one-time upgrade | code-only |
| Kept as they are (owner, 2026-10-26): the `X-PQM-Token` header, the REST namespace `post-quality/v1`, the `WP_Error` codes in REST responses (`pqm_forbidden`, `pqm_feature_gated`…), CSS classes, DOM ids and `data-pqm-*`, element expandos (`el.pqmBusy`…), the per-site webhook path prefix `pqm-execute-`, the template's `__PQM_*__` placeholders and node ids, the `metadata.pqmInsert` block marker, file names | `pqm` / `PQM` | code-only |
| Domain, support mailbox, `Plugin URI` / `Author URI` | `postquality.com` (owner, 2026-10-09, ADR 0013) | https://postquality.com, hello@postquality.com |

### Rules

- **Submit WordPress.org with the full name in the Plugin Name header**, so the directory derives `post-quality-manager` by itself and no slug edit is needed during review. Checked 2026-10-08: the slug is free and no listed plugin carries "post quality" or "content quality" in its name.
- **"Content Quality" is retired as a name.** Lowercase "content quality" stays as the generic concept in prose ("your blog's content quality"). Never capitalise it as a product or menu name.
- **"PQM" never reaches a user.** Not in admin copy, `readme.txt`, release notes or user docs. "PQ" is not a short form either.
- **The tagline is provider-neutral.** It names no workflow engine and no model vendor; "AI" stays as the discovery keyword. The long description may mention n8n and the AI providers as integrations ("through your own n8n workflow"), never as part of the name.
- **No third-party trademark in the name, slug, tagline or imagery** — not "WordPress", "Claude", "n8n", "OpenAI", "Google" (story 38 AC5). The pattern "X for Y" is acceptable in a description; "Y – X" is not.
- **One name per sentence.** Do not pair full name and short form in the same sentence; introduce with the full name, continue with the short form.
- **The domain is the short form.** `postquality.com` is a URL, not a third spelling of the name: pages on it still introduce the product as Post Quality Manager.

