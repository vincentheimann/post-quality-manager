---
title: 'Build 3: real screenshots from a demo site, the sharing card, discoverability and the acceptance pass'
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
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/build-brief.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** the site shows illustrations instead of the plugin, shares with no card, has no descriptions or structured data, and has never been measured for accessibility.

**Approach:** (3a) Capture the four screenshots once on a dedicated Local demo site with fictional posts and seeded reviews, at the sizes the site shows them, and swap them in under build 1's file names; (3b) render the dark sharing card once, add per-page descriptions, canonical URLs, `og:image` with alt and a `SoftwareApplication` JSON-LD; (3c) run Lighthouse's accessibility category on every page in both flag states, fix what it finds, re-crawl the live site after the owner merges, and record the site's state in the plugin repository.

**Decisions (owner, 2026-10-22/23; brief D4, D14, § 4):** four screenshots in the decided order, no video; fictional demo content only, no real blog, post or domain in any frame; the demo site is a fresh Local site created by the owner; WebP + PNG under 250 KB each; the sharing card is the dark version with lockup B on the band; no tracking.

## Boundaries & Constraints

**Always:** demo posts and scores are fictional and the captions say so; the WordPress admin bar and its logo are outside the frame (hidden with a capture-time CSS rule or cropped); the plugin reads "Post Quality" everywhere in the frame (no pre-naming drift); every image exported at the display width ×2 as WebP plus a PNG fallback, each file under 250 KB, the phone crop a second export, `width`/`height` recorded for the markup (brief D14); the icon identical; the demo site's data stays on the demo site (nothing on the owner's n8n, no API key entered: the setup screen is captured before any connection).

**Never:** a capture of the owner's production site or the production-copy sandbox's real posts; a third-party logo in any image; a `target="_blank"` without "opens in a new tab"; an analytics or font request; a merge.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Dashboard capture | demo site, ~25 fictional posts, reviews seeded over 8 weeks | `dashboard.png` (desktop frame: tiles, Fix next, the trend) and `dashboard-phone.png` (tiles + top of Fix next, no admin menu); figures noted for the alt | the trend line must be uneven or flat, never a staged climb |
| Review capture | one demo post with a seeded review | `review.png`: the four pillar scores and Top edits visible | — |
| Setup capture | n8n Workflow page, "Set up n8n" opened, nothing entered | `setup.png`: the dialog's values step with its three fields (n8n address, n8n API key, Anthropic API key) | no key typed, no connection made |
| Post Reviews capture | the demo list | `review-list.png`: scores and statuses as icon + label + colour | — |
| Share a page | any URL pasted into a chat tool | `og:title`, `og:description`, `og:image` 1200 × 630 with `og:image:alt`, `twitter:card summary_large_image` | — |
| Accessibility | Lighthouse (headless Edge via `CHROME_PATH`) on the eight pages, both flag states | accessibility category with no failing audit; the report paths recorded | each failing audit fixed in `site.css` or the markup, then re-run |
| Live | after the owner merges | the eight URLs 200/404 as expected; no request to a third host in the page source | — |

</frozen-after-approval>

## Code Map

- Demo site (Q1 default): a fresh Local site created by the owner; the agent installs the plugin zip built from `main` (`npm run build` + `bin/zip.js` in `post-quality-manager/`), activates it, and runs WP-CLI through Local's PHP (the wrapper pattern in the session memory: Local's `php.exe` with the site's `php.ini` and `wp-cli.phar --path=<site>`). Site title set to a fictional name (e.g. "Yourblog"); admin user "Demo".
- Seeding: `wp post generate` or `wp post create` ×25 with fictional, plausible tech-blog titles (five of them the direction mock's: "Five backup mistakes I made so you don't have to", "A calm guide to home network VLANs", …), dates spread over two years; reviews via `wp eval` calling `PQM_DB::save_review( $post_id, 'review', $score, $status, $result, $reviewed_at )` (`post-quality-manager/includes/class-pqm-db.php` L25) with `reviewed_at` spread over the last 8 weeks and scores giving roughly 3 major / 9 needs revision / 22 ready, some posts left unreviewed; `$result` keys as the detail panel reads them (`src/post-reviews/detail-panel.js`: pillar scores, issues, top edits, takeaways) with fictional edits from the mock. Status values from `src/kit/copy.js` (`ship`, `needs_revision`, `major_revision`).
- Capture: headless Edge `--screenshot` at 1600 × 1000 (Dashboard, Post Reviews, review detail) and 820 × 1200 (phone crop source), logged in via a cookie jar from `wp user session create` or a one-time login URL; a capture-time stylesheet (`wp option` is not needed: pass `--user-stylesheet` is unavailable in Edge headless, so inject via a mu-plugin printing `#wpadminbar{display:none} html{margin-top:0!important}` only on this demo site, removed after). Crop with Python Pillow to the frame; export 2× PNGs to `postquality.com/assets/img/`; record each image's figures for its alt text.
- `postquality.com/index.html`, `docs.md` -- captions switch from "illustration" to the capture captions; alts filled with the real figures; `review-list.png` added to Using it.
- `postquality.com/assets/og-card.png` -- an HTML card (navy, lockup B on a 96 px tile over the band, tagline at 40 px white, domain muted, 60 px margin) rendered once at 1200 × 630 by headless Edge; source HTML kept at `postquality.com/_tools/og-card.html` (excluded from the build).
- `postquality.com/_config.yml` -- `defaults` with `image: /assets/og-card.png`; `twitter: { card: summary_large_image }`; `jekyll-seo-tag` emits canonical, og and twitter tags; per-page `description:` front matter on every page (one sentence each, written from the copy deck); `og:image:alt` and `twitter:image:alt` added in `_includes/head.html` with the tagline.
- `postquality.com/_includes/head.html` -- a `SoftwareApplication` JSON-LD: name, applicationCategory, operatingSystem "WordPress 6.6+", offers only when `site.launched` (price 49, currencies), url, author Person.
- `postquality.com/README.md` -- the capture recipe in short (where the demo site lives, how to re-export), the release edit (flag, version fields).
- Acceptance tools: `npx --yes lighthouse <url> --only-categories=accessibility --chrome-flags="--headless=new" --output=json` with `CHROME_PATH` set to `msedge.exe`, `_site/` served by `python -m http.server`; the crawler from build 1 extended to assert no `http(s)://` host other than postquality.com, freemius.com, n8n.io, wordpress.org, anthropic.com, github.com in `href`/`src`, and none at all in `src`.
- Records (plugin repository): `docs/distribution.md` Website section (own layouts, flag, captures, sharing card); `memory/` line in the website-related memory file; the ticket's Notes; `docs/user-paths.md` A1 if the site's state is named there.

## Tasks & Acceptance

**Execution:**
- [ ] Demo site + seeding (WP-CLI, `wp eval`) -- fictional content -- 3a.
- [ ] The four captures + phone crop, exported to `postquality.com/assets/img/` -- 3a.
- [ ] `postquality.com/index.html`, `docs.md` -- captions, alts, `review-list.png` -- 3a.
- [ ] `postquality.com/_tools/og-card.html`, `assets/og-card.png`, `_config.yml`, `_includes/head.html`, page `description:` -- 3b.
- [ ] Lighthouse on every page in both states; fixes -- 3c.
- [ ] After the owner's merge: live crawl; records in the plugin repository -- 3c.

**Acceptance Criteria:**
- Given the four images, when the owner views them, then no real post, blog name, domain or third-party logo appears and the plugin's name reads "Post Quality" in every frame (hitl).
- Given each built page, when its head is read, then it has a unique title, a description, a canonical URL, `og:image` with alt, and the JSON-LD parses.
- Given Lighthouse's accessibility run on the eight pages in both flag states, when the reports are read, then no audit fails.
- Given the live site after the merge, when crawled, then every page answers as expected and no request leaves to a third host.

## Implementation Notes

## Plan Change Log
