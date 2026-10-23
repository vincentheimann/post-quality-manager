---
title: 'Build 1: the site gets its own layouts, the Navy and check home page and the policy edits'
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
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/build-brief.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** postquality.com is the stock `minima` theme: no layout, no brand, no picture, no call to action. The chosen design (Navy and check) and its spines exist but nothing was built.

**Approach:** Replace `minima` with the site's own layouts and one stylesheet built from DESIGN.md's tokens; build the home page to the build brief's copy deck in both launch states behind `site.launched`; apply the three decided policy edits in `docs/policies/` first and copy them; keep every other page on the new text-page layout. Pricing and docs content, the real screenshots and discoverability are builds 2 and 3.

**Decisions (owner, 2026-10-22/23; brief D1–D16, § 4):** Jekyll on GitHub Pages, own layouts; lean copy deck over the spines' strings; headline "Score every post. Know what to fix next."; lockup as icon SVG + live text; both launch states, flag `launched: false`; home order value first (pillars, What you need, How it works, Free and Pro, closing action); the interest email's body names the page it came from; interim illustration until the captures exist; implementer Opus 5.5; branch `feat/site-navy-and-check` in both repositories, draft PRs, the owner merges.

## Boundaries & Constraints

**Always:** the seven URLs and `/404.html` as today; DESIGN.md tokens as CSS custom properties, contrast figures as listed there; no JavaScript, no web font, no third-party request, no cookie; `lang="en"`, one `h1` per page, skip link first, `:focus-visible` ring navy on light and white on dark; the icon file untouched and never directly on `#1b2a41`; WordPress admin colours only inside screenshots; every plugin picture framed and captioned; policy copies drop the Markdown `# Title`.

**Never:** a `minima` include or `@import` left; green behind text or as the focus ring; countdowns, counts, ratings, testimonials; n8n Cloud named; prices or IDs changed; "A worked cost example is coming" on the home page; a merge.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Home, before launch | `launched: false`, 1280 px | band strip header with lockup B and the interest button; navy hero, h1 with the green bar under "fix next", white primary + outline secondary, one status-and-note line, three ticks; the Dashboard picture overlapping the band; then in this order: pillars, What you need (`#need`), steps + flow, Free/Pro cards + assurance, closing action, author line (brief D10, D11) | — |
| First screen, cold read | the 1280 px screenshot shown for five seconds to a fresh-context subagent | it answers what the product is, who it is for and what to do next, from the hero alone | a miss is fixed in the copy or layout before the owner's review (D15) |
| Home, after launch | `launched: true` local build | requirement line above the buttons; Get the free plugin → `/#need`; See Pro pricing; version line; What you need ends with Install from WordPress.org | — |
| Phone | 390 px and 320 px | lockup B + Menu (details/summary, in flow); hero left-aligned, buttons full width, ticks stacked; the phone crop of the picture; one column everywhere; no horizontal scroll at 320 px | — |
| Keyboard | Tab from the top | skip link first, then lockup, nav, action, hero buttons…; ring visible on each; Menu and FAQ summaries toggle with Enter/Space | — |
| Policy page | `/terms/`, `/privacy/` | text-page layout, h1 = `page.title`, body = `docs/policies/*.md` after the three edits | — |
| No email app | interest button | the address is printed in the action note and the footer | — |

</frozen-after-approval>

## Code Map

Site repository `C:/Git/Post Quality/postquality.com` (`main` = `2e1d851`), plugin repository `C:/Git/Post Quality/post-quality-manager` (`main` = `b41ba05`). Local build recipe: story 85's plan, Implementation Notes (`C:/Ruby32-x64`, `env -u MSYSTEM -u MSYS`, `JEKYLL_ENV=production PAGES_REPO_NWO=vincentheimann/postquality.com bundle exec jekyll build --baseurl ""`; gems already in `vendor/`).

Where to write: the site work happens in `C:/Git/Post Quality/postquality.com` on branch `feat/site-navy-and-check`. The plugin-side edits of this build (`docs/policies/terms.md`, `docs/policies/privacy.md`, `docs/brand.md`) happen in the worktree `C:/Users/vhe/AppData/Local/Temp/claude/c--Git-Post-Quality/791539b7-e82f-436f-8344-969669871d84/scratchpad/pqm-feat` (branch `feat/site-navy-and-check` from plugin `main`), never in the main plugin tree, which stays on `chore/ux-postquality-website` where this plan lives; Implementation Notes go into this plan file at its usual path. Copy the policies to the site from the worktree's `docs/policies/`. Scratch files and screenshots go under that scratchpad. Nothing is committed or pushed by the implementer.

Headless Edge (machine rule, shared with other sessions): start each run with its own `--user-data-dir` under the scratchpad, let `--screenshot` / `--dump-dom` runs exit by themselves, and never stop Edge or any process by name, pattern or command line; a script may stop only a PID it started itself.

- `postquality.com/_config.yml` -- drop `theme`, `minima`, `header_pages`, `show_excerpts`; add `launched: false`, `version: ""`, `version_date: ""`, `tested_up_to: ""`, `wporg_url: ""`, `changelog_url: ""`; keep the three plugins and excludes.
- `postquality.com/_layouts/default.html` -- `<!doctype html>`, `<html lang="en">`, `{% include head.html %}`, skip link, `{% include header.html %}`, `<main id="main" tabindex="-1">{{ content }}</main>`, `{% include footer.html %}`.
- `postquality.com/_layouts/page.html` -- text page: `<h1>{{ page.title }}</h1>` then content in a 720 px measure; used by the policies, support and 404.
- `postquality.com/_includes/head.html` -- charset, viewport, `{% seo %}`, `assets/css/site.css`, icon links as today (PNG then SVG), `{% feed_meta %}`.
- `postquality.com/_includes/header.html` -- `<header class="band on-dark">`: `{% include lockup.html variant="b" %}` as the home link (`aria-label="Post Quality Manager, home"`, SVG `aria-hidden`); `<nav aria-label="Main">` Pricing, Documentation, Support + the site action (`{% if site.launched %}Get the free plugin → /#need{% else %}Tell me when it's available → mailto{% endif %}`); the same links inside `<details class="menu"><summary>Menu</summary>` shown below 1000 px (D6 look).
- `postquality.com/_includes/lockup.html` -- parameters `variant` (b | d) and `tile` (32 default): inline icon SVG (copy of `assets/icon.svg`) + `<span class="name">Post Quality</span><span class="manager">Manager</span>` (d: name only); sizes per DESIGN.md `components.lockup-b/d` (text 0.63 T, gap 0.375 T, clear space 0.5 T; 28 px tile below 760 px, 20 px in the footer).
- `postquality.com/_includes/footer.html` -- lockup D (not a link), tagline verbatim, `<nav aria-label="Policies">`, mailto address, no-cookies line, legal line (brief § 3).
- `postquality.com/_includes/screenshot.html` -- `<figure class="shot">`: a drawn browser bar (`aria-hidden`, address "yourblog.example/wp-admin/"), `<picture>` with a phone source below 760 px and WebP sources before the PNG fallback, `width`/`height` attributes on the `img`, parameters `priority` (hero: `fetchpriority="high"`; others: `loading="lazy"`), `alt` and caption; shadow per DESIGN.md Elevation (brief D14).
- `postquality.com/_includes/icons.html` -- inline SVG symbols on a 24 px grid, 2 px stroke, round caps: check, mail, arrow, info, workflow, key, bill, shield-check, archive; used with `<svg aria-hidden="true"><use href="#i-check"/></svg>`.
- `postquality.com/index.html` -- layout default, the home sections in the brief's order (hero, pillars, What you need, How it works, Free and Pro, closing action, author line), each `<section>` with one `h2` (kicker as a span inside), `role="list"` on styled lists, `<ol aria-label="Where your content goes">` for the flow; the closing action as a tint section with its own h2, text and button(s) per the copy deck; interest `mailto:` with the subject from EXPERIENCE.md § Where the actions lead and, if the owner takes D16, the body's placement phrase ("from the home page"); after-launch branches on `site.launched`.
- `postquality.com/assets/img/*.webp` -- interim illustrations also exported as WebP (Pillow, quality 80), each file under 250 KB; the PNG stays as the fallback.
- `postquality.com/assets/css/site.css` -- plain CSS, no Sass: `:root` custom properties from DESIGN.md's `colors`, `spacing`, `rounded`; type roles as classes at desktop and phone sizes (rem on a 16 px root); components per DESIGN.md § Components (band header, menu, buttons, hero, ticks, shot frame with the 236/104 px overlap, need-item + chips, pillar list, steps, flow, plan cards, assurance, text page, footer, skip link, focus ring `--ring`); breakpoints 1000 / 760 px; `main { overflow-wrap: anywhere }`; `@media (forced-colors: active)` keeps the 2 px borders.
- `postquality.com/assets/main.scss` -- delete.
- `postquality.com/assets/img/dashboard.png`, `dashboard-phone.png`, `review.png`, `setup.png` -- interim: rendered from the direction file's HTML mocks (the `.shot` Dashboard block and the review-detail block, 2× via headless Edge `--screenshot` on an extracted page), captions say "illustration" per the brief; build 3a replaces them with captures under the same names.
- `postquality.com/404.html`, `support.md`, `privacy.md`, `terms.md`, `refund.md` -- `layout: page`; 404 text per the copy deck with icon F at 64 px and a primary button on light.
- `postquality.com/pricing.md`, `docs.md` -- `layout: page` only (content is build 2); keep their text.
- `postquality.com/README.md` -- describe the own layouts, the flag and the release edit.
- `post-quality-manager/docs/policies/terms.md` -- § 3: what counts as a site (each production site; staging and local copies of the same site do not, once verified against Freemius's documentation on localhost/staging activations; client sites allowed, the license stays the buyer's); § 4 or the renewal paragraph: cancelling the renewal keeps Pro until the paid year ends; date bumped.
- `post-quality-manager/docs/policies/privacy.md` -- § 3: "An email asking to hear about the launch is kept until that one message is sent, then deleted."; GitHub Pages named as host, GitHub logging visitors' IP addresses for security (check GitHub's current privacy statement wording first); date bumped.
- `post-quality-manager/docs/brand.md` -- replace the "not decided yet" lockup line: lockup B/D/F rules and T-unit specs from DESIGN.md § Lockup, live-text rule (D2), band rule for dark surfaces; the outlined SVG noted as story 41's.
- Verification tools (scratchpad): a `check_site.py` over `_site/` (one h1, skip link first, no `minima` string, no external host but mailto, icons order, policy bodies equal to `docs/policies/` minus the heading, every `img` with `width`/`height`, image files under 250 KB); headless Edge screenshots of `/` at 1280, 390, 320 and of `/terms/` at 1280, both flag states, saved under the scratchpad and listed by absolute path in Implementation Notes for the owner.
- Cold reads (brief D15), after the build passes the crawler and before the owner's review: (1) five-second test: a fresh-context subagent is shown only the 1280 px first-screen screenshot and asked what the product is, who it is for and what to do next; (2) persona read: a fresh-context subagent reads the built `index.html` text as Lena (solo tech blogger, 240 posts, no n8n yet, follows setup guides) and lists every sentence that confuses, over-promises or stops them. Both findings go into Implementation Notes; copy or layout misses are fixed and the screenshots retaken.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/docs/policies/terms.md`, `privacy.md`, `docs/brand.md` -- the three policy edits and the lockup section -- source first (D5, D2).
- [ ] `postquality.com/_config.yml`, `_layouts/*`, `_includes/*`, `assets/css/site.css`, delete `assets/main.scss` -- the foundation -- AC1.
- [ ] `postquality.com/assets/img/*` -- interim illustrations -- D4.
- [ ] `postquality.com/index.html` -- the home page, both states -- AC1, AC2.
- [ ] `postquality.com/404.html`, policies, `support.md`, `pricing.md`, `docs.md` -- text-page layout, policy copies -- AC3.
- [ ] Verify per Code Map's tools, run the two cold reads, fix their misses; record the evidence and the screenshot paths in Implementation Notes.

**Acceptance Criteria:**
- Given the built site, when `/` is rendered at 1280 px, then the first screen matches the direction's composition (band strip, hero, white button, the picture over the band) with the brief's copy and lockup B.
- Given the five-second test and the persona read, when their findings are recorded, then every miss has been fixed or is listed with a reason for the owner.
- Given the built site at 320 px, when any page is scrolled, then there is no horizontal scroll and the header wraps lockup B over the Menu.
- Given the three policy sources, when the site copies are diffed against them minus the `#` heading, then they are identical.
- Given the owner's screenshot review (hitl), when they approve, then the draft PRs are opened in both repositories.

## Implementation Notes

## Plan Change Log
