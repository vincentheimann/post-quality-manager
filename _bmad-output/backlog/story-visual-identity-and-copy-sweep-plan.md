---
title: 'The WordPress.org banners and screenshots, the outlined lockup, the imagery direction and the copy-sweep record'
type: 'feature'
ticket: '41'
created: '2026-10-24'
status: 'done'
baseline_revision: '853d2184348b7da89c72a7828e3843e40e5be0c2'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** WordPress.org's listing (story 38) needs two banners and the numbered screenshots the readme already captions; the brand canon promises a fixed lockup artwork with the wordmark as SVG outlines and still says the imagery direction is "not decided yet"; the copy sweep against the naming rules was never recorded as done.

**Approach:** render the banners from one HTML source derived from the site's sharing card (band colour, lockup B, the tagline, Inter) with headless Edge at 1× and 2×; copy the three story 86 captures as `screenshot-1..3.png` in the readme's order; generate `lockup-b.svg` and `lockup-b-dark.svg` with the wordmark outlined from the installed Inter variable font by a small fontTools script run through `uv run --with fonttools`; write the imagery direction and the asset set into `docs/brand.md` § Visual Design; run the naming-rule checks over README, readme.txt, admin strings and docs, fix what they find, record the commands in this plan.

**Decisions (owner delegation 2026-10-24, story 41 Notes):** banners derive from the sharing card; Inter is the artwork face for the PNGs and the outlines; screenshots copied as they are; no localised or RTL banner; the outliner script is kept in `bin/`.

## Boundaries & Constraints

**Always:** `assets/` at the repository root only (the zip is `post-quality-manager/`); the icon artwork byte-identical to `assets/icon.svg`'s paths; the tile never on navy (the band `#33496b` under every lockup on dark); white text on the band, "Manager" in `#c3ccd9`; the tagline verbatim from `docs/brand.md`; no third-party mark, no screenshot in a banner, no `<text>` in the lockup SVGs; PNG under 4 MB (banners) and 10 MB (screenshots); throwaway Edge profiles under the session scratchpad, closed and removed by their exact paths; nothing fetched from the network except what `uv` installs.

**Never:** a change inside `post-quality-manager/`; the icon files; a web font request or font CDN; a stock photo or illustration; a change to the readme captions; a commit or push.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Banner 1× | `assets/src/banner.html` at 772×250, scale 1 | `assets/banner-772x250.png`, exactly 772×250, under 4 MB | wrong size: re-render, never resize |
| Banner 2× | same source, `--force-device-scale-factor=2` | `assets/banner-1544x500.png`, exactly 1544×500 | — |
| Screenshots | site captures `review-list.png`, `review.png`, `dashboard.png` | `assets/screenshot-1.png` (1440×911), `-2.png` (960×824), `-3.png` (2048×1488), byte-identical to the sources | a source missing: halt, name it |
| Lockup light | Inter 600 "Post Quality" + 400 "Manager", tile 32 units, T-units per brand.md | `assets/lockup-b.svg`: paths only, navy + `#50575e` text, icon paths from `icon.svg` | font not found: halt with the path it looked in |
| Lockup dark | same geometry | `assets/lockup-b-dark.svg`: band rect behind, white + `#c3ccd9` text | — |
| Copy sweep | README.md, readme.txt, admin/includes/src strings, docs/ | zero hits for "Content Quality" as a name and user-facing "PQM"; no full-name/short-form pair in one sentence; the commands and counts in Implementation Notes | a hit: fix it in the same build and say where |

</frozen-after-approval>

## Code Map

- `docs/brand.md` L53–62 (§ Visual Design) -- the bullet "WordPress.org banners/screenshots and imagery direction: not decided yet — story 41 extends this section…" is replaced by a subsection `### Imagery and the WordPress.org assets`; § Icon L64–72 and § Lockup L74–91 give the geometry and colours (T-units: wordmark 0.63 T, gap 0.375 T, clear space 0.5 T); § Naming L93–118 the wording rules; the tagline is at L13 ("Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin.", check the exact string there).
- `assets/icon.svg` -- the icon: `<rect>` tile `#1b2a41` + `<g transform="translate(3 3) scale(0.7)">` with the Q ring path (white) and the green check rects/circles; copy the `<g>` content verbatim into the lockup SVGs and the banner HTML (inline SVG, as `postquality.com/_tools/og-card.html` does).
- `C:/Git/Post Quality/postquality.com/_tools/og-card.html` + its README § "Sharing card" -- the composition and the render recipe: `msedge --headless=new --hide-scrollbars --user-data-dir=<new folder> --window-size=W,H --screenshot=<out.png> <file URL>`; Edge at `C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe`; add `--force-device-scale-factor=2` for the 2× file (window size stays 772×250 CSS px); `--disable-features=msEdgeSidebarV2,msUndersideButton,EdgeCollections` avoids side panels. A fresh `--user-data-dir` under the scratchpad per render; remove it after.
- `assets/src/banner.html` (new) -- body 772×250, background `#33496b`, 40 px safe margin; left: lockup B at a 64 px tile (gap 24 px, wordmark 40 px: "Post Quality" 600 white, "Manager" 400 `#c3ccd9`); below it the tagline in white at 22–24 px, two balanced lines, max width ~620 px; `font-family: Inter, system-ui, sans-serif` (Inter is installed in the user's Windows fonts: `Inter-VariableFont_opsz,wght.ttf`, so Edge finds it by family name). No image, no screenshot.
- `bin/outline-wordmark.py` (new) -- run as `uv run --no-cache --with fonttools python -I bin/outline-wordmark.py`; opens `%LOCALAPPDATA%/Microsoft/Windows/Fonts/Inter-VariableFont_opsz,wght.ttf` (or a `--font` path), instantiates `wght` 600 and 400 with `fontTools.varLib.instancer.instantiateVariableFont` (`opsz` at the wordmark's px size, 20), draws each glyph with `fontTools.pens.svgPathPen.SVGPathPen` through the `glyf` glyph set, advances by `hmtx`, applies kerning only if simple (GPOS pair lookups may be skipped: say so in the comment), scales units-per-em to the 0.63 T cap height (Inter's cap height from the `OS/2` table), and writes the two SVGs with the icon `<g>` from `assets/icon.svg`, a `<!-- Wordmark: Inter (SIL OFL 1.1), outlined; regenerate with bin/outline-wordmark.py -->` comment, and `viewBox` sized to tile + gap + wordmark + 0.5 T clear space on each side. Light: text `#1b2a41` / `#50575e` on transparent. Dark: a `#33496b` rect behind, text `#ffffff` / `#c3ccd9`.
- Screenshots -- copy `C:/Git/Post Quality/postquality.com/assets/img/review-list.png` → `assets/screenshot-1.png`, `review.png` → `assets/screenshot-2.png`, `dashboard.png` → `assets/screenshot-3.png` (readme.txt § Screenshots order); verify sizes with PIL or a PNG header read; do not re-encode.
- Copy sweep commands (record counts): `grep -rn "Content Quality" README.md post-quality-manager/readme.txt post-quality-manager/admin post-quality-manager/includes src docs --include=*.php --include=*.js --include=*.md --include=*.txt` (allowed hits: `docs/brand.md`'s two rule lines that name the retired term); `grep -rnE "__\(\s*['\"][^'\"]*PQM" post-quality-manager/admin post-quality-manager/includes src` and `grep -rn "PQM" README.md post-quality-manager/readme.txt docs --include=*.md --include=*.txt | grep -v -E "PQM_|pqm_|X-PQM-Token|\`"` (0 user-facing hits); the sentence pairing check from story 49's session (a Python split on sentence ends over README.md and readme.txt: no sentence with both "Post Quality Manager" and a bare "Post Quality").
- `bin/zip.js` -- zips only `post-quality-manager/`; the root `assets/` never ships (story 51 note). Read only.

## Tasks & Acceptance

**Execution:**
- [x] `bin/outline-wordmark.py` -- the outliner as the Code Map says; run it -- criterion 3
- [x] `assets/lockup-b.svg`, `assets/lockup-b-dark.svg` -- generated; open each in Edge headless at 400 px wide and screenshot to the scratchpad to check by eye (ring and check intact, baseline aligned, "Manager" lighter) -- criterion 3
- [x] `assets/src/banner.html` -- the banner source -- criterion 1
- [x] `assets/banner-772x250.png`, `assets/banner-1544x500.png` -- rendered, sizes verified by PNG header, files under 4 MB -- criterion 1
- [x] `assets/screenshot-1.png`, `-2.png`, `-3.png` -- copied, sizes verified -- criterion 2
- [x] `docs/brand.md` -- the new subsection replacing the "not decided yet" bullet: imagery direction, the asset table (file, size, source, how to re-render), the no-localised-banner decision, the outlined-wordmark rule now fulfilled (adjust § Lockup's "is story 41's" sentence to name the files) -- criterion 4
- [x] copy sweep -- run the commands, fix any hit, record counts here -- criterion 5
- [x] `memory/naming.md` -- one line: the WordPress.org asset set and the lockup artwork exist (2026-10-24), where, how to regenerate

**Acceptance Criteria:**
- Given the two banner PNGs, when their headers are read, then 772×250 and 1544×500 exactly, both under 4 MB, and a side-by-side look shows the same composition
- Given the lockup SVGs, when grepped, then no `<text`, no `font-family`, no `@import`, no `http`
- Given the repository, when `node bin/zip.js` would run, then nothing under the root `assets/` is in the zip (read `bin/zip.js`; no need to build a zip)
- Given the sweep, when the commands run, then the counts are as the matrix says

## Implementation Notes

- Plan written 2026-10-24 (Fable 5.1, bmad-build by hand from the workspace root). Checkpoint 1 taken under the owner's advance approval of this session ("already approve and continue"). Branch `feat/wporg-assets` from main `853d218`. Implementer subagent on Fable 5.1 (owner's 2026-10-16 decision).
- Built 2026-10-24 (implementer subagent, Fable 5.1). Files: `bin/outline-wordmark.py`, `assets/lockup-b.svg`, `assets/lockup-b-dark.svg`, `assets/src/banner.html`, `assets/banner-772x250.png` (31,198 B after the review re-render), `assets/banner-1544x500.png` (68,653 B), `assets/screenshot-1..3.png` (96,445 / 173,981 / 210,734 B, `cmp` byte-identical to the site captures), `docs/brand.md`, `memory/naming.md`, `docs/distribution.md`, the story file, and two copy-sweep fixes inside the plugin folder: `post-quality-manager/includes/class-pqm-cron.php` (cron label) and `post-quality-manager/post-quality-manager.php` (header Description = the tagline).
- Renders: `msedge --headless=new --hide-scrollbars --disable-lcd-text --force-color-profile=srgb --disable-features=msEdgeSidebarV2,msUndersideButton,EdgeCollections --user-data-dir=<scratchpad>/edge-profile-<ns> --window-size=772,250 --screenshot=assets/banner-772x250.png file:///C:/Git/Post%20Quality/post-quality-manager/assets/src/banner.html`, then the same with `--force-device-scale-factor=2 --screenshot=assets/banner-1544x500.png`; each profile folder was created for that one render and removed by its exact path (none left). A control render with `font-family: Inter, monospace` produced a byte-identical PNG, so Edge resolved the installed Inter, not a fallback. The lockup SVGs were rendered at 400 × 92 to the scratchpad and checked by eye: ring and check intact, cap height on the ring's centre line, "Manager" lighter, the dark one on the band.
- Reading of the geometry: the Design Notes' "cap height equals 0.63 T" is taken as brand.md states it, wordmark (font-size) 0.63 T "so its cap height matches the Q's ring" (the plan's `opsz` 20 is that em at T = 32). Cap height comes out at 14.67 units against the ring's 14.1; a cap height of 0.63 T would make the wordmark 1.4× the ring. The cap height is centred on the ring's centre line (y 31.5, the tile centre is 32), which also equals Inter's line-box centre at `line-height: 1`, so the SVG matches the HTML lockups.
- Deviation, `http`: the SVGs keep `xmlns="http://www.w3.org/2000/svg"`, the one `http` in each file; without it a standalone `.svg` does not render in a browser, on GitHub or in an `<img>`. `grep -c "<text\|font-family\|@import"` is 0 and 0; the licence comment names no URL.
- Deviation, banner tagline: three balanced lines at 22 px over a 620 px measure, not two. The first sentence alone measures about 800 px at 22 px in Inter, wider than the 692 px content width, so one sentence per line cannot hold at 772 px; a single `text-wrap: balance` paragraph gives three even lines (the 2× file breaks identically). Recorded in brand.md's subsection.
- Copy sweep (counts): (1) `grep -rn "Content Quality" README.md post-quality-manager/readme.txt post-quality-manager/admin post-quality-manager/includes src docs --include=*.php --include=*.js --include=*.md --include=*.txt` = 4 before, 3 after: the two rule lines in `docs/brand.md` (allowed), `docs/release-notes/unreleased.md` L5 quoting the old menu label in the rename announcement (kept: the note must name what changed), and `docs/brand.md` L12 "Category: WordPress Content Quality Dashboard", fixed to lowercase "content quality dashboard". (2) `grep -rnE "__\(\s*['\"][^'\"]*PQM" post-quality-manager/admin post-quality-manager/includes src` = 1 before, 0 after: `includes/class-pqm-cron.php` L20 `'Once Weekly (PQM)'` → `'Once Weekly (Post Quality)'` (the cron schedule label admins see in cron tools; `php -l` clean; no .pot in the repo). This one-line fix is inside `post-quality-manager/`, which the Boundaries' Never bullet excludes while the matrix's sweep row says "fix it in the same build": the row won; the reviewer can drop the hunk if the Never bullet is meant to cover copy fixes. (3) `grep -rn "PQM" README.md post-quality-manager/readme.txt docs --include=*.md --include=*.txt | grep -v -E "PQM_|pqm_|X-PQM-Token|\`"` = 0. (4) Sentence pairing (Python, split on `(?<=[.!?])\s+` and blank lines; a sentence with "Post Quality Manager" and a bare "Post Quality"): README.md 0, readme.txt 0. The brand.md § Naming intro now says the sweep ran (2026-10-24).
- `bin/zip.js` read: it archives the `post-quality-manager` folder only (`tar -a -cf … post-quality-manager` / `zip -rq … post-quality-manager`), so the root `assets/` never ships. Nothing new is git-ignored (`git check-ignore` empty).
- Review fixes 2026-10-24 (implementer, after the coordinator's review). Banners re-rendered with `--disable-lcd-text --force-color-profile=srgb`: fringe pixels (non-band pixels outside the tile whose per-channel blend factors against the band and white / `#c3ccd9` differ by more than 0.2) 6,586 → 0 at 1× and 0 at 2×. `assets/src/banner.html`: tagline margin 26 → 20 px, line-height 1.3 → 1.25; ink bounds now x 40..540, y 42..205 at 1× (margins L40 T42 R231 B44) and x 80..1079, y 84..413 at 2× (L80 T84 R464 B86, the exact double). Font check re-run: the `Inter, monospace` control is byte-identical. PNG headers still (772, 250) and (1544, 500); 31,198 B and 68,653 B. The recipe in the HTML comment and brand.md's re-render column now carry the flags and the font-check step; brand.md § Imagery "Where" lists the six files that go to SVN `assets/`; the sweep sentence in § Naming backticks `(PQM)`. Plugin header `Description` set to the tagline verbatim (`docs/distribution.md` L11 had handed it to story 41); `php tests/harness/release-header-harness.php` run, see Verification. `docs/distribution.md` L11, L50 (README rewrite handed to story 60 by name), L54, L57, L63–64, L150 updated to the produced files. Story file: Boundaries line 1 and AC6 reworded, a dated Notes line records the decision that copy-sweep fixes inside the plugin folder are in scope.
- Wider sweep (AC5), 2026-10-24: (5) first mention, Python over README.md, readme.txt and every `docs/**/*.md` (the first match of `Post Quality( Manager)?` must be the full name): 12 files name the product, 1 short-form first match, `docs/adr/0010-one-token-authenticates-both-directions.md` L12, the n8n credential name literal `` `Post Quality webhook - <site host>` `` in backticks, a code identifier, not a mention; kept. (6) pairing (the sentence split of check 4) over README.md, readme.txt, `docs/**/*.md`, `post-quality-manager/admin`, `post-quality-manager/includes`, `src` (`.php .js .jsx .md .txt`, no build/node_modules/vendor): 70 files, 3 hits, all table or definition lines the splitter joins, none a sentence: `docs/brand.md` L9 (the naming rule that defines both forms), `docs/brand.md`'s Naming table, `docs/distribution.md` L78 (the Freemius store title value "Post Quality" in a settings record); kept. (7) `grep -rnw "PQM" post-quality-manager src --include=*.php --include=*.js --include=*.jsx --include=*.txt --include=*.md --include=*.json --exclude-dir=vendor --exclude-dir=build --exclude-dir=node_modules | grep -v "X-PQM-Token"` (whole word, every gettext function and untranslated literals alike) = 0.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-10-24): 11 findings — high 0, medium 3, low 6, rejected 1, open by design 1; 8 → patch (the step-03 implementer re-engaged), 1 intent gap resolved under the owner's delegation, 1 rejected by rule, 1 left to the owner (hitl). Verified after the patches by the orchestrator: every harness 0 failed, `php -l` clean on both PHP files, no CR byte in any touched file, the banners looked at.**

1. medium → intent gap, resolved by the owner's delegation of 2026-10-24 ("make choices based on best practices") — the cron label fix inside `post-quality-manager/` contradicted the frozen Never ("a change inside `post-quality-manager/`") and AC6, while AC5 and the original story ask for the copy sweep's fixes. Decision: copy-sweep fixes inside the plugin folder are in scope; the assets never go into the zip. Ticket Boundaries and AC6 reworded, a dated Notes line added; the frozen block left as approved.
2. low → patch — sweep command (3) found the bare "(PQM)" this diff added to `docs/brand.md`'s Naming intro. Backticked; (3) = 0.
3. medium → patch — the banners carried LCD sub-pixel colour fringes (6,586 fringed pixels at 1×). Re-rendered with `--disable-lcd-text --force-color-profile=srgb`: 0 fringed pixels at 1× and 2×; the recipe in `assets/src/banner.html` and `docs/brand.md` carries the flags.
4. low → patch — the content overflowed the 40 px safe margin (ink at y 37). Tagline gap 20 px and line-height 1.25: ink at L40 T42 B44 (1×), exactly double at 2×.
5. low → rejected by rule — the plan's Tasks & Acceptance and Verification wording (`http` in the SVGs, "no PHP touched") contradict the build; the only fix edits this plan. The `xmlns` URL is required for a standalone SVG (Implementation Notes), and the PHP touched is ruled in scope by row 1.
6. low → patch — AC5's record lacked the first-mention, wide pairing and whole-word PQM checks. Run and recorded in Implementation Notes ("Wider sweep"): no copy fix missing.
7. medium → patch — `docs/distribution.md` still handed story 41 the plugin header Description (L11) and the README rewrite for site owners (L50), and called the banners and screenshots not produced (L54, L57, L63–64, L150); the refinement had dropped both hand-offs (lessons 2026-10-12 and 2026-10-13). The header Description is now the tagline (release-header harness 31/31); the README rewrite is handed to story 60 by name and date; the asset rows name the produced files.
8. low → patch — `docs/brand.md` called `assets/` the WordPress.org folder although it holds repository-only files. One sentence lists the six files story 38's deploy syncs to SVN.
9. low → patch — a re-render without Inter installed would fall back silently. The recipe gains the control render (`Inter, monospace`, identical bytes = Inter resolved).
10. low → patch — the ticket's Boundaries line 1 was garbled. Reworded with row 1's decision.
11. open by design (hitl) — the owner approves the banners and the lockup artwork at the PR; the approval now looks at the re-rendered files.

## Design Notes

Banner composition at 772×250 (CSS px; the 2× file is the same page at device scale 2): band `#33496b` full-bleed; padding 40 px; row 1 = lockup B, tile 64 px (`T`), gap 24 px (0.375 T), wordmark 40 px (0.63 T) with cap height aligned to the ring; row 2 = the tagline, 22 px, line-height 1.3, white, `text-wrap: balance`, two sentences as two spans (one per line) like the sharing card, max width 620 px so the right side stays empty (the directory overlays nothing there, but a quiet right side reads as the brand's restraint). No domain line: the listing page already names the plugin. The screenshots stay the captures' native sizes; the directory scales them into its viewer.

Outlining: Inter's `OS/2.sCapHeight` (about 1490/2048 em) sets the scale so the wordmark's cap height equals 0.63 T; the two words are set as separate runs ("Post Quality" at wght 600, a word space, "Manager" at wght 400) with the advance widths from each instance's `hmtx`; kerning through GPOS is optional (Inter's pair adjustments are small at this size); round coordinates to 2 decimals.

## Verification

**Commands:**
- `uv run --no-cache --with fonttools python -I bin/outline-wordmark.py` -- expected: writes the two SVGs, prints their viewBox and the glyph count, exit 0
- `grep -c "<text\|font-family\|@import\|http" assets/lockup-b.svg assets/lockup-b-dark.svg` -- expected: 0 and 0 (the licence comment names no URL)
- `python -I -c "import struct;[print(f,struct.unpack('>II',open(f,'rb').read(24)[16:24])) for f in ['assets/banner-772x250.png','assets/banner-1544x500.png','assets/screenshot-1.png','assets/screenshot-2.png','assets/screenshot-3.png']]"` -- expected: (772,250), (1544,500), (1440,911), (960,824), (2048,1488)
- the copy-sweep greps from the Code Map -- expected: the allowed hits only
- `php -l` on nothing (no PHP touched); `git status --short` -- expected: only the files this plan names

**Results (2026-10-24):**
- `uv run --no-cache --with fonttools python -I bin/outline-wordmark.py` → `assets\lockup-b.svg viewBox=0 0 278.77 64`, `assets\lockup-b-dark.svg viewBox=0 0 278.77 64`, `glyphs outlined: 20 (cap height 1490/2048 em, scale 0.00984)`, exit 0.
- `grep -c "<text\|font-family\|@import" assets/lockup-b.svg assets/lockup-b-dark.svg` → 0, 0; `grep -o 'http[^" ]*'` → only `http://www.w3.org/2000/svg`, once per file (the xmlns).
- PNG headers → `banner-772x250.png (772, 250)`, `banner-1544x500.png (1544, 500)`, `screenshot-1.png (1440, 911)`, `screenshot-2.png (960, 824)`, `screenshot-3.png (2048, 1488)`; banners 31,198 B and 68,653 B (the greyscale-antialiased re-render; fringe count 0 / 0, ink margins ≥ 40 px at 1×, the 2× file the exact double).
- `php tests/harness/release-header-harness.php` after the header Description change → `31 passed, 0 failed`, exit 0 (the header parses as WordPress reads it; readme.txt headers agree); `php -l post-quality-manager/post-quality-manager.php` → No syntax errors.
- Copy sweep → see Implementation Notes (3 allowed "Content Quality" hits, 0 PQM strings, 0 PQM in docs, 0 sentence pairs).
- `php -l post-quality-manager/includes/class-pqm-cron.php` → No syntax errors. `git status --short` → the files this plan names plus the story file `story-visual-identity-and-copy-sweep.md`, modified before the build started.

**Manual checks (if no CLI):**
- Owner (hitl): the banners at 1× and 2× and the two lockup SVGs, opened from the branch on GitHub; approve or ask for a re-render.
