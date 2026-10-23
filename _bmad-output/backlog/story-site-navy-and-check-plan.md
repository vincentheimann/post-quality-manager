---
title: 'Build 1: the site gets its own layouts, the Navy and check home page and the policy edits'
type: 'feature'
ticket: '86'
created: '2026-10-23'
status: 'built'
baseline_revision: 'b41ba057809a81013fa285f8a5c2dd39175d1a76'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
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
- [x] `post-quality-manager/docs/policies/terms.md`, `privacy.md`, `docs/brand.md` -- the three policy edits and the lockup section -- source first (D5, D2).
- [x] `postquality.com/_config.yml`, `_layouts/*`, `_includes/*`, `assets/css/site.css`, delete `assets/main.scss` -- the foundation -- AC1.
- [x] `postquality.com/assets/img/*` -- interim illustrations -- D4.
- [x] `postquality.com/index.html` -- the home page, both states -- AC1, AC2.
- [x] `postquality.com/404.html`, policies, `support.md`, `pricing.md`, `docs.md` -- text-page layout, policy copies -- AC3.
- [x] Verify per Code Map's tools, run the two cold reads, fix their misses; record the evidence and the screenshot paths in Implementation Notes.

**Acceptance Criteria:**
- Given the built site, when `/` is rendered at 1280 px, then the first screen matches the direction's composition (band strip, hero, white button, the picture over the band) with the brief's copy and lockup B.
- Given the five-second test and the persona read, when their findings are recorded, then every miss has been fixed or is listed with a reason for the owner.
- Given the built site at 320 px, when any page is scrolled, then there is no horizontal scroll and the header wraps lockup B over the Menu.
- Given the three policy sources, when the site copies are diffed against them minus the `#` heading, then they are identical.
- Given the owner's screenshot review (hitl), when they approve, then the draft PRs are opened in both repositories.

## Implementation Notes

- Baselines: plugin repository `b41ba057809a81013fa285f8a5c2dd39175d1a76` (main; the plugin-side edits sit in the scratchpad worktree on `feat/site-navy-and-check`, the plan on `chore/ux-postquality-website`, draft PR #176); site repository `2e1d85165f6bb488f54012bc92e214b817238063` on `feat/site-navy-and-check`. Approved by the owner 2026-10-23 ("Approve and continue"); session and implementer on Opus 5.5 (owner's word). Rendered by hand from the workspace root (owner rule 2026-10-16).
- Implemented 2026-10-23 (implementer, Opus 5.5). Nothing staged, committed or pushed; `assets/main.scss` and `index.md` are working-tree deletions.
- Plugin worktree (`pqm-feat`, `feat/site-navy-and-check`):
  - `docs/policies/terms.md`, dated 2026-10-23:
    - § 3: each production site counts, yours or a client's, and the license stays yours. Staging and local copies do not count when Freemius recognises the address (`localhost`, `.local`, `.test`, `staging.`); a copy at any other address counts until it is deactivated. Checked against Freemius's License Utilization page: this is the default for new plans (`is_free_localhost` per license). The renewal sentence now reads "cancel the renewal".
    - § 4 opens with "Cancelling the renewal does not end the license: Pro keeps working until the end of the year you paid for" and names a refund and § 6 as the ways a license is cancelled.
  - `docs/policies/privacy.md`, dated 2026-10-23. § 3:
    - names GitHub Pages as the host;
    - says "GitHub logs and stores each visitor's IP address for security purposes", wording from GitHub Pages' docs (What is GitHub Pages → Data collection);
    - links the GitHub General Privacy Statement, which does not mention Pages;
    - adds the launch-email sentence verbatim.
  - `docs/brand.md`: the "not decided yet" line now covers banners, screenshots and imagery only. A new § Lockup holds the B/C/D/F/E table, the T-unit specs and sizes, the live-text rule (D2; the outlined SVG is story 41's) and the band rule for dark surfaces.
- Site repository (`feat/site-navy-and-check`):
  - `_config.yml`: `theme: null`; the minima keys removed; the launch keys added.
  - `_layouts/default.html` and `_layouts/page.html` (`icon: true` adds icon F, used by the 404).
  - `_includes/`:
    - `head.html`, `header.html` (band strip, Main nav, Menu below 1000 px), `footer.html`;
    - `lockup.html`: variants B and D; `tile` 32 or 20; the header's 32 becomes 28 below 760 px;
    - `site-action.html` and `interest-href.html`: one builder for the interest mailto. The subject is EXPERIENCE's. The body is "Please tell me once when Post Quality Manager can be installed." and then "Sent from <the page>.";
    - `screenshot.html`, `icons.html`.
  - `assets/css/site.css`: plain CSS with the DESIGN.md tokens as custom properties.
  - `assets/img/*`.
  - `index.html` (replaces `index.md`).
  - `404.html`: copy deck, icon F, a primary button on light.
  - Policy copies: `privacy.md` and `terms.md` synced. `support.md` and `refund.md` were already identical.
  - `pricing.md` and `docs.md`: unchanged (they already used `layout: page`).
  - `README.md`: the layouts, the launch flag and the release edit.
- Deviations, kept small:
  1. `theme: null` instead of dropping the key. When no theme is named, github-pages 232 falls back to `jekyll-theme-primer` (`configuration.rb` DEFAULTS), which would add an unused Primer stylesheet. `theme: null` is the documented way to switch that off.
  2. A fifth image, `setup-phone` (the step panel only). The 640 px setup illustration was unreadable at 320–390 px.
  3. Two extra includes, `site-action` and `interest-href`: the header row and the Menu share one action.
  4. The check icon uses a 2.75 stroke on the 24 grid, because 2 px looked thin at 20 px. Every other icon uses 2 px.
  5. Section backgrounds: the hero-shot area and the pillars are tint, What you need white, How it works tint (its flow strip is white so it shows), Free and Pro white, the closing action tint. This keeps the alternation and gives D11 its tint closing.
  6. A navy-outline secondary-on-light button for the closing "See Pro pricing" after launch. DESIGN only has a secondary button on dark.
  7. The header names the current page in the email body ("the pricing page", and so on). Every home-page button says "the home page".
- Illustrations, built with `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\illus\make_pages.py` and `render.py` from the direction file's `.wp`/`.wpd` mocks (headless Edge at 2×, cropped on a magenta background, PNG optimized, WebP quality 80):
  - Changes from the mock:
    - the admin heading reads "Dashboard" (brand.md naming);
    - the menu gains "n8n Workflow";
    - the 8-week line is uneven around 70;
    - the review shows a post that is not in Fix next, so the scores stay consistent;
    - the setup guide uses the plugin's own labels (`src/setup/steps.js`, `src/n8n-connection/index.js`).
  - Sizes (PNG / WebP): dashboard 2048×1097 (196 / 100 KB), dashboard-phone 800×1162 (96 / 52), review 880×783 (64 / 40), setup 1280×708 (76 / 40), setup-phone 800×777 (48 / 28).
  - Captions say "An illustration of …", with fictional data, post or values.
- Cold reads (D15), each by a fresh-context subagent:
  - Five-second test 1 (the 1280 px first screen): product and audience answered with high confidence, the next step with medium.
  - Persona read as Lena: 19 items.
  - Fixed after the cold reads:
    1. Lead: "older posts quietly go stale" → "older posts quietly fall behind your best work". The review scores structure, readability, engagement and credibility (evidence and tone, per the template), not dates, so "stale" promised a freshness check.
    2. Hero note → "**Pre-release.** The button opens your email app: one email when it's installable, then your address is deleted. Or write to hello@postquality.com." The status was easy to miss, and nothing visible said the button opens an email app.
    3. Tick "Nothing is sent anywhere else" → "Your posts go only there". The old tick claimed more than the data line; EXPERIENCE words this tick "content goes only there". Confirmed by five-second test 2.
    4. n8n item → "The workflow tool your reviews run in. Self-hosted; your site and n8n must reach each other." The page never said what n8n is, and "reachable … and back" read as jargon.
    5. Fit line: "for n8n" added.
    6. Step 2 → "Click Review on a post, or select several at once. Your site sends each one …". Bulk "Review selected" is gated on the `review` capability, so Free has it.
    7. The closing action gets the footnote "Opens your email app.".
    8. The price markup gets spaces, so screen readers no longer read "49per year".
  - Listed for the owner, not changed:
    - "Check what you need" read as vague in both five-second tests. It is a decided label and jumps to `#need`.
    - n8n in the hero tick reads as setup work. Stating that prerequisite up front is the point of the page.
    - "Pricing" in the nav next to "Free plugin", and the same button in the header and the hero: decided structure.
    - The hero note is longer now, a trade-off made for the email-app finding.
    - "Free plugin" vs paying for AI: the bill is named in What you need.
    - Fictional pictures vs "used on real blogs": real captures come in build 3a, and the blogs stay unnamed (EXPERIENCE).
    - "Blog Profile" and "Editorial Standards" are the plugin's own names.
    - The sample edit is generic: it is illustration content.
    - No cost figure: story 49 is a launch gate (D7).
    - No n8n install step among the three steps: What you need comes first and links Getting n8n.
    - "Step 2 of 5" is the plugin's own step count.
    - "The agents" and the bare feature names: the pricing page (build 2) carries the detail.
  - Five-second test 2, on the fixed page: product, audience and "not installable today" answered with high confidence, the next step ("Check what you need") with medium. Its other points repeat the decided items above.
- Verification evidence (final build); the scripts are in `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad`:
  - Builds: `build.sh` runs `JEKYLL_ENV=production PAGES_REPO_NWO=… jekyll build --baseurl ""` (`C:/Ruby32-x64`, `MSYSTEM`/`MSYS` unset) and finishes without errors in both states. The after-launch build uses `--config _config.yml,launched.yml` (fictional 1.0.0 values) and writes to `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\site-launched`, never into the repository.
  - `check_site.py`: NO PROBLEMS for `_site`, and for `site-launched` with `--launched`. It checks:
    - Every page: `lang="en"`; one `h1`; the skip link as the first focusable element; `<main id="main" tabindex="-1">`; no "minima"; icons PNG then SVG; `site.css` as the only stylesheet; no script except JSON-LD.
    - Landmarks: Main nav twice (row and Menu) and a Policies nav; the lockup link's aria-label; the Menu `details`.
    - Markup: one `h2` per section; width, height and alt on every `img`; `role="list"` on styled lists; no `target=_blank`; the lockup only in the header or footer.
    - Requests and links: no external `href`/`src`/`srcset` except mailto to hello@ (plus wordpress.org links after launch); internal links and in-page fragments resolve.
    - Home before launch: five interest links with the subject and "Sent from the home page."; no install, buy or `/#need` link; the address printed.
    - Home after launch: no interest link; `/#need`, Install from WordPress.org, See Pro pricing, the requirement line and the third tick are present.
    - Home in both states: no "A worked cost example is coming" and no n8n Cloud; the order pillars → need → how → plans → closing; exactly one `fetchpriority="high"`.
    - Policies: bodies equal `docs/policies/` minus the `#` heading.
    - Images: every file under 250 KB, each PNG paired with a WebP.
    - CSS: no `@import` or `url()`; green only on the check, the highlight bar and the assurance edge; the focus ring never green.
    - Sitemap: exactly the seven URLs.

    A negative test on a mutated copy raised 13 problems, as expected.
  - `sync_policies.py --check`: the four policy bodies are identical to `docs/policies/`.
  - `harness.py` (same-origin iframes in headless Edge):
    - Overflow: none on any of the 8 pages at 320, 360, 390, 760, 999, 1000 and 1280 px, with the Menu closed and open, in both states (56 of 56 ok each; at 320 px the content width is 305 because of the iframe's scrollbar).
    - Focus order: skip link → lockup → (Menu →) Pricing → Documentation → Support → action → hero buttons.
    - Focus rings show in white on the band, the hero and the Pro card.
  - `git diff --check` is clean in both repositories; the site CSS has no `#2271b1`; `assets/icon.svg` is unchanged.
- Screenshots for the owner's review, in `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final` (`before-*` = `launched: false`, `after-*` = `launched: true`):
  - Before launch: `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-home-1280-first.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-home-1280-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-home-390-first.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-home-390-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-home-320-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-terms-1280-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-terms-320-full.png`.
  - After launch: `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-home-1280-first.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-home-1280-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-home-390-first.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-home-390-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-home-320-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-terms-1280-full.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-terms-320-full.png`.
  - Menu open: `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-menu-open-390.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-menu-open-320.png`, and the same two `after-*` files.
  - Focus rings: `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-focus-skip-1280.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-focus-header-1280.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-focus-hero-1280.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-focus-free-1280.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-focus-pro-1280.png`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-focus-menu-390.png`, and the same six `after-*` files.
  - Overflow reports: `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\before-overflow.txt`, `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\shots\final\after-overflow.txt`.
  - Cold-read inputs: `C:\Users\vhe\AppData\Local\Temp\claude\c--Git-Post-Quality\791539b7-e82f-436f-8344-969669871d84\scratchpad\coldread\`.
- Open or risky, for the owner:
  1. The `/docs/#getting-n8n` and `/docs/#cost` links land at the top of `/docs/` until build 2 adds those ids (check_site WARN).
  2. `docs.md` still names n8n Cloud. The existing text was kept per the Code Map; build 2 rewrites it.
  3. Terms § 3 assumes the three Freemius plans keep the default of free localhost and staging activations. Confirm in the Freemius dashboard before Pro goes on sale.
  4. The cold-read fixes change deck strings the owner approved; the originals are listed above if the owner prefers them.
  5. Lighthouse was not run (a ticket AC, part of build 3's pass).
  6. No physical key presses: tab order was checked in the DOM and the rings through `focus()`. Opening the Menu with Enter or Space is native `details` behaviour.
  7. Only Chromium (Edge 155) rendered the pages. The Menu's in-flow panel relies on a grid overlap with `pointer-events: none` on the `details`, which is worth one look in Firefox and Safari.
  8. `support.md` (policy text) and `docs.md` keep the old interest mailto, without the page phrase.

- Owner request 2026-10-23, added by the orchestrating session after the implementer returned: the author's signature at the foot of every page, as on the owner's other sites. `_includes/signature.html` (new: the Material Symbols "fingerprint" glyph, Apache 2.0, inlined with `fill="currentColor"`; a link to https://heimvin.me, the owner's personal site, not one of the blogs the plugin runs on; `aria-label` "Created by heimvin"), `_includes/footer.html` (included after the legal row), `assets/css/site.css` (centred; amber `#f7b733` at opacity .45, full on hover and focus; a CSS tooltip "Created by heimvin" above the mark in the navy, covering nothing; the scale only under `prefers-reduced-motion: no-preference`; `LinkText` in forced colours), `README.md` (the glyph's licence credit). Deviations from the owner's other site, by this site's rules: the link opens in the same tab; the focus ring is the site's navy one. Known trade-off told to the owner: the amber at .45 is far below the 3:1 non-text contrast the Accessibility Floor asks of a link's graphic (about 1.8:1 even at full opacity); kept identical as asked, alternative offered (muted grey at rest). Checker: `check_site.py` now allows the host heimvin.me for `<a href>` only (links load nothing; `src` stays strict). Verified: production build, `check_site.py` NO PROBLEMS (the two known build-2 warnings), screenshots `scratchpad/shots/signature/`, the hover state rendered from a forced-hover copy of the 404 page (deleted after).

- Review patches applied 2026-10-23 (implementer re-engaged, Opus 5.5; triage pass 1 rows 1–9): type sizes as `--fs-*` custom properties (desktop and `-mobile` twins, phone roles reassigned in the 760 px query; 42 before/after full-page pairs pixel-identical), `.shot-url` in rem; the stacked review figure capped at 480 px below 1000 px, the hero and setup phone crops at 480 px below 760 px (overlap kept); the signature tooltip hidden by `visibility` with an 8 px transparent bridge (WCAG 1.4.13) and the link named "Created by heimvin, heimvin.me"; the mailto body with `%0D%0A`, the placement phrase url-encoded; `/404.html` matched explicitly in the header, `else` = "the " + page title; the README's full local-check command; plugin worktree: `memory/workspace-layout.md` and `docs/distribution.md` no longer name minima, `docs/brand.md` § Icon points to § Lockup, dated lines for the github-pages 232 Primer fallback (`workspace-layout.md`) and Freemius's free localhost/staging activations, SDK 2.13.4 (`freemium-and-roles.md`). Re-verified by the orchestrating session after the patches: production builds in both states; `check_site.py` NO PROBLEMS in both (WARNs only for build 2's docs page); overflow harness 56/56 in both states; `git diff --check` clean in both repositories. Owner screenshots retaken in `scratchpad/shots/final/`. Nothing committed: commit, push and the two draft PRs wait for the owner's screenshot approval (frozen AC4).

- Owner approved the screenshots 2026-10-23 ("Approved, go ahead"). Committed and pushed: site `6900f0600de0e24fcc7c33985664bfd1c58adf56` on `feat/site-navy-and-check`, draft PR vincentheimann/postquality.com#3; plugin `a9899bcffec1fc667a20a5e2740234889af2c536` on `feat/site-navy-and-check`, draft PR #177. Builds 2 and 3 add commits to these two PRs instead of stacking new ones (stacked PRs need a force push after the first merge, which the push rules deny); the docs anchors then land with the home page.

- The owner merged the site PR #3 on 2026-10-23 (merge `00875a18859035dfacd82e7d5081de764f446296`); GitHub Pages built it the same minute, so build 1 is live. Build 2 therefore moved to its own site branch and PR (`feat/site-pricing-docs`, #4) instead of adding commits to #3; the plugin side stays on #177.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-10-23): 10 findings — high 0, medium 0, low 9, false 0, maybe-false 0, plus 1 out of this build's intent; 9 → patch (the step-03 implementer re-engaged), 1 → defer. The matrix audit before review held: `check_site.py` NO PROBLEMS in both states, the overflow and focus harness 56/56 in both states, the cold reads recorded.**

1. low → patch — `site.css` has no type roles: the type scale is repeated as literals (`1.0625rem` ×12, `.9375rem` ×10, `1.1875rem` ×5) and `.shot-url` is in px (12/11), against the Code Map ("type roles … rem on a 16 px root") and DESIGN § Typography. Verified by count. Harm: build 2 extends this stylesheet and stories 53/60 re-sweep the copy, so one size change means hunting every literal. Not a loopback: the plan was clear; the fix is mechanical and adds no surface.
2. low → patch — `memory/workspace-layout.md` L10 and `docs/distribution.md` L127 still say the site runs on `minima` 2.5.1; this build removes it. Verified by reading both lines. Durable facts (DoD).
3. low → patch — `docs/brand.md` L72 (§ Icon rules) still says "no wordmark lockup here (story 41)" while the new § Lockup says it is decided. Verified.
4. low → patch — two external-system facts live only in comments or the terms (lessons 2026-10-16): github-pages 232 falls back to `jekyll-theme-primer` without `theme: null`; Freemius plans count `localhost`, `.local`, `.test` and `staging.` addresses free by default (`is_free_localhost`, `FS_Site::is_localhost_by_address`), which terms § 3 now relies on. Verified: no `memory/` file holds either. The site repository's own comment and README keep their one-line local why (public repository; its readers cannot open the plugin's `memory/`).
5. low → patch — `_includes/signature.html` / `.signature-link::after`: `pointer-events: none` makes the tooltip vanish when the pointer moves onto it (WCAG 1.4.13 Hoverable), and the 8 px gap ends the hover too; the link's name does not say it leaves for heimvin.me (EXPERIENCE: links that leave the page say where they go). Verified in the CSS. The amber contrast is the owner's disclosed choice, not re-filed.
6. low → patch — between 760 and 999 px the stacked review figure (880 px PNG) is drawn up to ~903 CSS px, and the phone crops (800 px) up to ~727 CSS px: soft on tablets and on 2× screens (EXPERIENCE § Screenshots, exported at the sizes shown). Verified: `.pillars` goes to one column below 1000 px with no width cap on the figure.
7. low → patch — `_includes/interest-href.html`: the mailto body breaks lines with `%0A`; RFC 6068 § 5 requires `%0D%0A`. Verified.
8. low → patch — `_includes/header.html` L9: `{% else %}` labels every unlisted URL "the page-not-found page", so a page added later would report itself as the 404 in interest emails (D16's signal). Verified. Fix: match `/404.html` explicitly, `page.title` otherwise.
9. low → patch — site `README.md` launch-flag paragraph: the local check command omits `JEKYLL_ENV=production PAGES_REPO_NWO=… --baseurl ""`, which the next paragraph says the build needs. Verified.
10. out of this build's intent → defer — `/docs/` names n8n Cloud, lacks the `#getting-n8n` and `#cost` ids the home page links to, and its and `/support/`'s interest links have no body. Verified; pre-existing text the Intent assigns to build 2 ("Pricing and docs content … are builds 2 and 3"); build 2's plan already covers the docs rewrite, the anchors and the docs interest link. `/support/`'s link is policy text (`docs/policies/support.md`). Merge advice for the owner: ship build 1's site PR with or right after build 2's, so the two home links never land on a page without their anchors.
