---
title: 'Story 122 — a banner clear of the More Details name label, and screenshot 2 with today''s tabs'
type: 'chore'
ticket: '122'
created: '2026-11-10'
status: 'ready-for-dev'
route: 'full'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
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

## Plan Change Log

## Review Triage Log

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
