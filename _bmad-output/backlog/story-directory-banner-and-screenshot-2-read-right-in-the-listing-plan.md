---
title: 'Story 122 — a banner clear of the More Details name label, and screenshot 2 with today''s tabs'
type: 'chore'
ticket: '122'
created: '2026-11-10'
status: 'built'
baseline_revision: '54bdfde6c7fa8bce88c8254b72f32bd88d775c97'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/story-directory-banner-and-screenshot-2-read-right-in-the-listing.md', '{project-root}/docs/local-verification.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** In More Details, WordPress lays the plugin's name in a dark label over the banner's lower left, on top of the tagline's last line; screenshot 2 (and postquality.com's `review` picture it copies) shows "✓Review" with no space, fixed in the plugin on 2026-10-29.

**Approach:** Re-lay the banner source so the lockup and the tagline end above the label (owner's option A: content top-aligned, tagline 16 px under the lockup, 20 px over the 692 px measure), re-render both sizes by the brand recipe; recapture the review frame on the showcase from a Free build of main, replace the site's PNG and WebP through a site PR, copy the PNG to screenshot 2; the owner approves both in the story 70 preview.

## Boundaries & Constraints

**Always:** the tagline verbatim and the lockup unchanged; the render recipe of `docs/brand.md` § Imagery (fresh throwaway Edge profile, font check); captures only from the showcase, demo post and fictional review, no admin bar; every showcase change inside the walk (backup, build, restore to step 11's three "same"); temporary files only in the session scratchpad; screenshot 2 a byte copy of the site's new `review.png`.

**Never:** a change to the name, the tagline's words, the icon, the sharing card (`_tools/og-card.html`, `og-card.png`), screenshots 1 and 3, the plugin's code or readme; the narrow view (window under 792 px, out of scope by the owner); a site file on the showcase for the capture (CSS goes in through the browser); Playwright or Edge processes this session did not start stopped; a merge.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Label clear, 1× | `banner-772x250.png` in More Details, window ≥ 792 px | label box top at 174 px; every non-band pixel of the banner sits above it, with a strip of band between | a pixel at or below → re-lay, never under 20 px type |
| Label clear, 2× | `banner-1544x500.png`, device pixel ratio 2 | the same in CSS px | same |
| Re-render | the recipe run twice, font check | pixel-identical PNGs; Inter resolved | font check differs → STOP: Inter missing |
| Tabs | Free build of main, the demo post's details open | "✓ Review", "✓ Takeaways" with a space; History (n) | — |
| Frame | the old crop | 960 × 824; post row, tabs, pillars, top edits, buttons; figures as the site's alt | layout differs → match the window width to the old frame |
| Export | PNG and WebP | each under 250 KB | PNG over → 256-colour palette (build 3a's fallback) |
| Restore | after each walk | step 11's three "same" | the recipe's STOP lines |

</frozen-after-approval>

## Code Map

- `assets/src/banner.html` -- the banner page: `body` centres content (`justify-content: center`), `.tagline` 22 px over 620 px with `text-wrap: balance`; the header comment carries the render command. Mock A (scratchpad `b122/v1.html`) adds `body{justify-content:flex-start}` and `.tagline{margin-top:16px;max-width:692px;font-size:20px;line-height:1.25}`.
- `assets/banner-772x250.png`, `assets/banner-1544x500.png` -- re-rendered; `assets/screenshot-2.png` -- replaced by the copy.
- WordPress 7.1.4 `wp-admin/css/common.css` on the showcase -- `#plugin-information-title.with-banner` (250 px, `background-size: cover`, padding 0 26 px) and its `h2` (margin-top 174, 30 px, line-height 1.68, glow); `plugin-install.js` sizes the frame `792 < width ? 772 : width - 20`. Read only.
- `docs/local-verification.md` -- Variables, walk steps 1, 2, 9–11, and "Add Plugins preview (story 70)" Preview 0–4 (copies the eight files from `assets/`).
- `memory/local-verification.md` L24 -- the capture tooling: Playwright (`node_modules/playwright-core`) driving Edge, a persistent profile in the scratchpad, a WP-CLI-minted session cookie for user 1, 2× device scale; a crop must stay inside the viewport.
- `../postquality.com/assets/img/review.png`, `review.webp` -- replaced; `index.html` L57 include (960 × 824, alt quotes 70, Needs revision, 68/74/59/81, the first top edit) stays unless the figures change; README "Captures" is the recipe.
- `docs/brand.md` L95 ("The banners are the sharing card's composition…") and L110 (banner composition) -- rewritten; L102–103 render rows stay. `tests/harness/release-header-harness.php` reads brand.md, so CI runs.
- `docs/distribution.md` L64–65 (asset rows) and L67 (card preview line) -- story 122's re-render, recapture and approval.
- The showcase still holds the demo post with its Review (70, 2026-10-19 11:50) and Takeaways results; its plugin is 3.9.0.

## Tasks & Acceptance

**Execution:**
- [ ] `assets/src/banner.html` -- mock A's layout, the comment saying why (the label's box) -- AC1.
- [ ] `assets/banner-*.png` -- render by the recipe, font check, render again and compare pixels (Pillow), measure the lowest non-band row of each against the label's top -- AC1, AC2.
- [ ] One live wordpress.org listing, screenshotted -- confirm the directory page lays no label over its banner -- ticket assumption.
- [ ] Capture walk on the showcase -- walk steps 1–2 (Free build of main), a placeholder webhook URL on a `.example` host, the review frame captured at 2×, exported as PNG and WebP, steps 9–11 -- AC3.
- [ ] `../postquality.com` branch `chore/122-review-capture` -- the two files, alt checked against the frame; commit, push, draft PR -- AC3.
- [ ] `assets/screenshot-2.png` -- byte copy of the new `review.png` -- AC3.
- [ ] The sitting -- a second walk with Preview 0–4: the agent reads the label box in the DOM at both device ratios; the owner looks in their browser and approves or names changes -- AC4.
- [ ] Records -- `docs/brand.md` § Imagery, `docs/distribution.md`, `memory/local-verification.md` (the review frame's window width), this plan -- AC2, AC4.

**Acceptance Criteria:**
- Given the new pair in the preview at device ratios 1 and 2, when More Details opens in a window ≥ 792 px, then the label covers nothing of the banner and band shows between them.
- Given the recipe, when both files are rendered again, then the PNGs are pixel-identical and brand.md describes the new layout.
- Given the new capture, when it replaces screenshot 2 and the site's pair, then the tabs read "✓ Review" with the space and the site's include and alt match the frame.
- Given the sitting, when the owner approves, then `docs/distribution.md` records it with the date.

## Implementation Notes

- 2026-11-10, coordinator (before dispatch): plan approved by the owner, full plan kept (~2,100 tokens). The implementer does execution tasks 1 to 6 and stops: the banner, the live listing check, the capture walk (restored to step 11's three "same"), the site files and `assets/screenshot-2.png`. In the site clone it creates branch `chore/122-review-capture` from main and leaves the two files uncommitted. It makes no commit and no push in either repository. The sitting, the records, the commits and the PRs are the coordinator's. Rules for the run: temporary files only in the session scratchpad `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/82437d6f-3377-401e-b39b-be548011fed4/scratchpad` (TEMP and TMP point there for Playwright and Edge; PHP runs with `-d sys_temp_dir=<scratchpad>`); the site is the Local site "Post Quality Showcase" and no other; a fresh Edge profile folder per render, removed after; stop only the processes the run started, by their PID; never print the showcase's credentials, `wp-config.php`, or a session cookie. The scratchpad's `b122/` folder holds the refinement's mocks (`v1.html` is mock A).
- 2026-11-10, implementer: tasks 1 to 6 built, nothing committed. `assets/src/banner.html`: content top-aligned, tagline `margin: 16px 0 0; max-width: 692px; font-size: 20px` (`text-wrap: balance` kept), the header comment says why (the label's box) and no longer calls the page derived from the sharing card. Renders by the recipe, a fresh profile each: lowest non-band row 167 in the 772 file and 337 in the 1544 file (169 CSS px), 0 non-band pixels at or below y 174 in both, content x 40–666, greyscale text; re-rendered twice, the last after the final comment edit: byte- and pixel-identical (Pillow); font check identical bytes (Inter resolved). Live listing: wordpress.org/plugins/akismet/ lays no label over its banner (banner ends at y 573, title at 620): the ticket's assumption holds. Capture walk on the showcase: free beforehand (port 8765 unused, no `pq-smoke-*` users, no mu-plugins folder), step 1 backup, `npm run build`, step 2 Free build of main (3.13.2), placeholder webhook `https://n8n.example/webhook/demo`, Playwright driving Edge at 2× with a WP-CLI-minted session for user 1, admin bar and footer hidden by CSS added in the browser; step 9 `debug.log` 53 lines all WP-CLI's; step 10 restore; step 11 three "same", mu-plugins still absent, 3.9.0 back. Frame: "✓ Review", "✓ Takeaways", "History (2)"; 70, Needs revision, 68/74/59/81, the same three top edits, Open in Editor, Re-run Review; the site's include (960 × 824) and alt match; caption 2 holds. Exports: PNG 171.8 KB (RGB), WebP 55.8 KB (quality 90). Site clone: branch `chore/122-review-capture` from main f0f4407, the two files uncommitted, `index.html` unchanged. `assets/screenshot-2.png` = the new `review.png` (`cmp`). `release-header-harness.php` 33 passed; `git diff --check` clean in both. Found: the old frame cannot be had at a straight 2× (the table's `min-width: 51em`); the match is a 900 px window at 2×, crop 1058 × 908 device px from the row's left and top edges to the details row's bottom, resized to 960 × 824 with Lanczos; the toggle blurred and the pointer moved off first. `msedge --screenshot` returns at once: wait for the file, then for the profile's `lockfile` to go, before removing the profile. Scratchpad: walk folder deleted; `b122/` keeps the mocks, the render and capture scripts (`capture-tools/`, no secrets) and the check outputs in `r/`. No process stopped.
- 2026-11-10, coordinator (step 03): diff read (banner.html layout and comment only; three PNGs), images looked at; matrix audit: every row has a check that ran (pixel measure ×2, re-render and font check, the frame, the sizes, the restore's three "same"); the label's DOM box is read at the sitting.
- 2026-11-10, coordinator (after review pass 1's patches: `release-header-harness.php` 33 passed, `git diff --check` clean): **the sitting.** The second walk followed the recipe on the showcase (WordPress 7.1.4, Plugin Check 2.1.0). Preview 0: the site was free (port 8765 unused, no `pq-smoke-*` users, no preview in mu-plugins). Step 1 backup (13 tables, `wp_pqm_reviews` 30 rows, 1 user, 215 checksum lines). Step 2: the Free build of this branch (3.13.2; plugin code as main) and the debug constants. Preview 1: Markdown 5 files. Preview 2: the eight files copied from this branch, the three new ones `cmp`-equal; `plugins_api` answered 1 card (3.13.2, tested 7.1.4), five tabs, 3 screenshots, both banners. AC1 in the real dialog (Playwright driving headless Edge, a WP-CLI-minted session, the card's More Details opened from the search; script `b122/label-check.js` in the scratchpad), at device ratios 1 and 2: frame 772 px wide, title area 772 × 250, `background-size: cover`, `banner-772x250.png` at ratio 1 and `banner-1544x500.png` at ratio 2, label "Post Quality Manager" at 26, 174 → 362.75, 224.39 (CSS px; Helvetica Neue falls back to Arial here), no page error; the banner's text ends at y 168, so 6 px of band shows above the label. The owner looked in their browser, was told about R3 (the clear-space exception now recorded in brand.md), and **approved both images**. `docs/distribution.md` records the approval. Preview 4: preview removed, mu-plugins folder removed. Step 9: no `debug.log`. One existed by the end of step 10, whose WP-CLI runs still ran under `WP_DEBUG` until the `wp-config.php` copy, and step 10 deleted it unread. Step 10 restore: 13 tables. Step 11: three "same", plus `mu-plugins: same, the preview is gone`. Walk folder deleted. Seen on the way: Plugin Check 2.2.0 is available on the showcase (2.1.0 installed); left for `/lcm`.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-11-10): 4 findings — high 0, medium 2, low 2, false 0, maybe-false 0; 4 → patch, applied by the coordinator (the plan's Records task is the coordinator's, Implementation Notes line 1). The lens re-rendered both banners and the font check with fresh profiles: byte- and pixel-identical; clearance rows 167 / 337 confirmed against 7.1.4's `margin-top: 174px`; screenshot 2 the same blob as the site's `review.png`.**

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| R1 | AC2's second clause unmet: `docs/brand.md` L95 still calls the banners the sharing card's composition, L110 describes the 22 px, 620 px, three-line layout | medium | patch | Read: both lines unchanged, so brand.md contradicted the new files. L95 now says the banners carry the card's elements in a layout of their own; L110 describes the new layout, the label's box and the narrow-view limit. `release-header-harness.php` re-run: 33 passed. |
| R2 | `docs/distribution.md` L58, L64, L65 credit the images to story 41 only; no dated approval (AC4) | medium | patch | Read: true. L58 and both rows now name story 122's re-lay and recapture; the approval line waits for the sitting (AC4, hitl). |
| R3 | The tagline sits 16 px (0.25 T) under the tile, inside § Lockup's 0.5 T clear space (32 px at a 64 px tile) | low | patch | brand.md L86 confirms 0.5 T; story 41's banner already used 20 px; 32 px would end the text at y 184, under the label's top (174). The owner picked mock A at 16 px (frozen Approach); the L110 rewrite records it as an exception the 250 px height forces. Told to the owner at the sitting. |
| R4 | The recapture recipe (900 px window, crop, Lanczos resize) is only in the plan, not in `memory/` | low | patch | Read: true; the plan's Records task names `memory/local-verification.md`. One line added after the story 70 preview line. |

## Design Notes

- Two walks, each restored: the implementer's capture walk, then the coordinator's sitting (story 70's split: the sitting is hitl).
- Clearance by pixels, not by eye: the band is `#33496b`; any other pixel in the 772 px file (or the 1544 px file at half scale) is banner content. Record the lowest content row and the label's top.
- Skipped: a harness for the images -- no code reads them; the sitting and the pixel checks prove them.

## Verification

**Commands:**
- `php -d sys_temp_dir=<scratchpad> tests/harness/release-header-harness.php` -- passes (it reads brand.md).
- `git diff --check` in both repositories -- clean; `cmp assets/screenshot-2.png ../postquality.com/assets/img/review.png` -- identical.

**Manual checks:**
- The sitting (hitl): the owner approves the banner and screenshot 2 in the preview, or names changes.
