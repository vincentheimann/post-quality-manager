---
title: 'Story 70 — the Add Plugins card and More Details, previewed on the showcase and approved by the owner'
type: 'feature'
ticket: '70'
created: '2026-11-10'
status: 'built'
baseline_revision: 'd62516b6a0cbb7f10688502294a936e75520f66c'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context: ['{project-root}/_bmad-output/backlog/story-add-plugins-card-and-details.md', '{project-root}/docs/local-verification.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Nobody has seen the plugin's Add Plugins card and its More Details view assembled from the readme, the plugin header and the directory images, and story 38 submits them as they are.

**Approach:** A development-only must-use plugin answers `plugins_api` on the showcase, for a search for the plugin and for its slug's details, with a record built from the installed Free build's readme and header (Plugin Check's copy of the directory's parser, with the directory's own Markdown fetched at the sitting) and the eight directory images. The owner reviews both renderings; wording fixes land in the readme or the header, every other change becomes a ticket story 38 waits on.

## Boundaries & Constraints

**Always:** the preview answers only the plugin's own search and details, and returns every other request's incoming result untouched; it lives in `bin/` and ships in no build; the walk keeps the `docs/local-verification.md` frame (backup, Free build, restore, step 11's three "same") plus a check that the preview's files are gone; the Markdown loads only on a preview request, since Plugin Check's own checks use the same parser; the record mirrors the directory's answer, with a new listing's values (rating 0, no ratings, active installs 0, last updated now); captures stay in the scratchpad and are deleted after the sitting.

**Never:** no new image, no name, slug or tagline change, no Description rewrite (story 60); no new Composer or npm dependency; the downloaded Markdown never enters the repository; no plugin code change; no capture from any site but the showcase.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Search for the plugin | `query_plugins` with `search` "Post Quality Manager", "post quality", `post%20quality` (the AJAX form), or the slug | one card: the plugin's record | No error expected |
| Any other list | `query_plugins` with another term, or `tag`, `author`, `browse` | the incoming result, unchanged | No error expected |
| Details for the slug | `plugin_information`, slug `post-quality-manager` | the full record: sections, banners, screenshots, sidebar values | No error expected |
| Anything else | `plugin_information` for another slug; any other action | the incoming result, unchanged | No error expected |
| Preview not set up | Plugin Check inactive, or the Markdown folder missing | the incoming result, unchanged | one `error_log` line naming what is missing; no fatal |

</frozen-after-approval>

## Code Map

- `bin/directory-preview.php` -- new; copied by the walk to the showcase's `wp-content/mu-plugins/postquality-directory-preview.php`, its images and Markdown in the folder `postquality-directory-preview/` beside it.
- WordPress 7.1.4 (on the showcase): `wp-admin/includes/class-wp-plugin-install-list-table.php` -- the card reads `name`, `short_description`, `version`, `author`, `slug`, `icons`, `last_updated`, `rating`, `num_ratings`, `active_installs` (an int) without a check; `info` must be an array holding `results`; "Compatible" only when the site version is `<=` `tested`. The AJAX search passes `s` still percent-encoded (`updates.js` 3003, list table L161). `wp-admin/includes/plugin-install.php` `install_plugin_information()` -- one tab per `sections` key (`reviews` hidden at 0 ratings), kses allow-list without `dl`/`dt`/`dd`/`table`, relative URLs rebased onto wordpress.org (so absolute URLs), `banners` `low`/`high`, contributors as `user => {display_name, profile, avatar}`, read without a check.
- Plugin Check 2.1.0 `includes/Lib/Readme/Parser.php` -- namespace `WordPress\Plugin_Check\Lib\Readme`, `use WordPressdotorg\Plugin_Directory\Markdown`; takes a file path; fills `name`, `short_description`, `sections`, `screenshots` (captions), `faq`, `requires`, `tested`, `requires_php`; drops contributors on Local (`get_user_by`). Autoloaded while Plugin Check is active; load the Markdown before constructing it.
- github.com/WordPress/wordpress.org, `trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/` -- `class-markdown.php` (extends `\Michelf\MarkdownExtra`; includes `libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.inc.php` beside itself: mirror that layout; GPLv2, Michelf BSD-3-Clause); `api/routes/class-plugin.php` L463–495 -- the answer to mirror: FAQ `dt`→`h4`, `dd`→`p`; the screenshots section `<ol><li><a href=SRC><img src=SRC alt=CAP></a><p>CAP</p></li></ol>`; `screenshots` `{n: {src, caption}}`; `icons` with an SVG `{1x: svg, svg: svg}`; read there how sections the parser does not expect (External services) reach the answer before mapping them. Same file L106–117: `author` is the submitting WordPress.org account, `<a href="https://profiles.wordpress.org/<nicename>/">display name</a>`, never the plugin header's Author or Author URI (live API 2026-11-10: wordpress-seo, header "Team Yoast", answers "Yoast" linked to its profile); L141–148: each contributor's `display_name` is the profile's and its avatar a Gravatar with `d=monsterid`. The `postquality` profile's title reads "postquality (@postquality)" on 2026-11-10: display name `postquality`.
- Freemius SDK `class-fs-plugin-updater.php:85` -- filters `plugins_api` at 10 for this slug and overwrites; not registered on a Free build, but the preview runs at `PHP_INT_MAX`.
- `tests/harness/lib/wp-stubs.php` -- the shared world for the new harness.
- `docs/local-verification.md` § Other checks -- the recipe goes here; Rules and walk steps 1, 2, 10, 11 are its frame.
- `docs/distribution.md` L46–64 -- the approval line goes after the Assets table.
- `post-quality-manager/readme.txt`, the header of `post-quality-manager/post-quality-manager.php` -- the only files a wording fix touches; `release-header-harness.php` Row 7, `free-build-harness.php` and postquality.com's cost-copy check read them. Not to change: `assets/`, `bin/zip.js`, `bin/build-free.php`.

## Tasks & Acceptance

**Execution:**
- [x] `bin/directory-preview.php` -- the must-use plugin: a pure routing function (the matrix) and the record builder (parser and Markdown on demand, header through `get_plugin_data()`, image URLs under `WPMU_PLUGIN_URL`); a header comment: development-only, where the recipe is -- AC1, AC2.
- [x] `tests/harness/directory-preview-harness.php` -- the matrix's routing rows on the preview file with the shared stubs -- proves the pass-through.
- [x] `docs/local-verification.md` -- subsection "Add Plugins preview (story 70)" under Other checks: the four Markdown files fetched at one pinned commit into `$S`; the preview, the eight images and the Markdown copied into the showcase's mu-plugins (created if absent, removed if created); the screens opened; the preview removed; restore; one line proving it gone -- re-runnable for story 60.
- [x] Sitting (hitl) -- the recipe on a Free build of this branch; the agent checks AC1/AC2 on the rendered pages (Playwright DOM reads: card fields, tab list, no `*`, `**` or `=` markup, no empty tab, three captions, sidebar values) and `debug.log`; the owner looks in their browser; wording changes go to the readme or the header; every other change is filed through bmad-ticket and added to story 38's `after`.
- [x] `docs/distribution.md` -- "Card preview approved <date>" with what was approved -- AC3.
- [x] `memory/local-verification.md` -- one dated line: the preview's gotchas (percent-encoded `s`, point-release `tested`, the parser without Markdown, the SDK filter).

**Acceptance Criteria:**
- Given the recipe run on the showcase, when it ends, then step 11 prints "same" three times, the preview's file and folder are gone, and `debug.log` held only WP-CLI's own lines while the preview ran.
- Given a wording change from the sitting, when it lands, then the harness sweep and the site's cost-copy check stay green.

## Implementation Notes

- 2026-11-10, coordinator (before dispatch): the implementer does the first three tasks and proves the preview with one dry run of the recipe on the showcase, the agent's part only (set up, the DOM checks of AC1 and AC2, `debug.log`, remove, restore to step 11's three "same"), then stops and reports; the sitting with the owner and the last three tasks are the coordinator's, after review. Rules for that run: temporary files only in the session scratchpad `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/f2f96085-9354-473d-ba80-e2f5849942d5/scratchpad` (TEMP and TMP pointed there for Playwright, `-d sys_temp_dir=` for PHP), deleted at the end; never print a file that holds secrets; stop only processes you started, by PID; one walk on the showcase at a time; Playwright is `node_modules/playwright-core` driving Edge (`memory/local-verification.md`, the story 100 and 2026-11-06 lines).

- 2026-11-10, implementer: tasks 1 to 3 built. The preview mirrors `api/routes/class-plugin.php` and `cli/class-import.php` at wordpress.org commit `d0621733` (trunk, 2026-11-09): requirements from the header when well formed, else the readme; sections in the parser's order (External services lands at the end of Description under an h3, as the directory's own parser, identical to Plugin Check's copy on this point, does), then Screenshots; Reviews left out. Deviations: the Markdown is five files, not four (the class plus the four Michelf files `MarkdownExtra.inc.php` loads, in the `libs/` layout `class-markdown.php` includes); the record carries only the fields the two screens read plus `added`, `screenshots` and `download_link` (the dialog's footer button), not tags or downloads; the directory's `the_content` pass (wptexturize, wpautop) is left out so the showcase's own content filters do not shape the record, so quotes stay straight; the Michelf 1.6.0 library ran on PHP 8.5.12 with no notice. Dry run (agent part only) on the showcase, WordPress 7.1.4, Plugin Check 2.1.0, a Free build of this branch (3.13.1) over the site's 3.9.0: the recipe as written; `plugins_api` answered `1 card: Post Quality Manager 3.13.1, tested 7.1.4` and the five tabs with 3 screenshots; Playwright (Edge, headless) read every AC1 field on the search page and through the live search (which sent `s` as `post%20quality`), and every AC2 item from the card's More Details (banner low and high 200 image/png, tabs Description, Installation, FAQ, Changelog, Screenshots, no empty section, no `*`, `=`, tag or entity text, the three captions verbatim and loaded, sidebar 3.13.1 / 6.6 / 7.1.4 / 8.1 / Less Than 10 / homepage, contributor `postquality`, no notice, no console error); no `debug.log` while the preview ran; removed, restored, step 11's three "same" and `mu-plugins: same, the preview is gone`, `wp-config.php` byte-identical; scratchpad emptied. For the sitting: in More Details WordPress lays the name over the banner's lower left, where the banner's own tagline text runs, so the two overlap (a new image is a ticket); the readme's URLs (FAQ pricing and source, External services terms) show as plain text, not links, as the directory's parser leaves them.

- 2026-11-10, coordinator (review pass 1, loopback 1): F1 routed bad_plan; the first derivation's code saved in the scratchpad (Change Log 1, KEEP), then reverted: `docs/local-verification.md` restored, the two new files removed. Re-derivation by a fresh implementer from the amended plan.

- 2026-11-10, implementer (re-derivation): tasks 1 to 3 rebuilt from the KEEP files with the amended Design Notes. Author: the readme's first Contributors user, `<a href="https://profiles.wordpress.org/postquality/">postquality</a>`, the header's Author and Author URI read nowhere; contributors unchanged (display name the user, the site's default avatar, the Gravatar difference named in the code and the recipe). Not set up: all five Markdown files checked (`POSTQUALITY_PREVIEW_MARKDOWN`), the log line names the missing ones. Harness: 40 rows kept, 11 added (the no-Markdown line names all five; four partial downloads, each `null` with one line naming exactly the missing files; the temporary folder removed); its log reader now splits on `\R`, since `error_log()` ends lines with `\r\n` on Windows. Recipe: every stop runs as a stop (`|| { echo "STOP: …"; exit 1; }`): Plugin Check inactive, fewer than five non-empty Markdown files (`-size +0`, so an empty file left by a failed download counts as missing), a preview's file or folder already in mu-plugins (checked before anything is written); `$S/preview-created` marks the walk's own preview and Preview 4 removes nothing without it; "What a new listing shows" corrected (By postquality, the sidebar's Author, the avatar difference). No dry run, per Change Log 1; checked offline only: the record's author and contributors with a stand-in parser in the scratchpad (deleted), the stop lines on a dummy folder (deleted).

- 2026-11-10, coordinator (after review pass 2's patches; verification re-run: harness sweep 25 files 0 failed, the new harness 51 passed, Jest 211, `php -l` and `git diff --check` clean): **the sitting.** The recipe on the showcase (WordPress 7.1.4, Plugin Check 2.1.0), a Free build of this branch (3.13.1) over the site's 3.9.0: Preview 0 clear, backup, Free build, Markdown 5 files, preview in (mu-plugins created), `plugins_api` answered 1 card by `postquality` and five tabs with 3 screenshots. Playwright (headless Edge) read AC1: one card, `icon.svg`, the tagline verbatim, "By postquality" linked to its profile, 0.0 and (0), "Last Updated: 5 seconds ago", "Less Than 10 Active Installations", "Compatible with your version of WordPress", "Active"; the live search (`post quality`) gave the same single card. AC2: banner `banner-772x250.png`; tabs Description, Installation, FAQ, Changelog, Screenshots; no section empty or with raw markup; the three screenshots loaded with their captions; sidebar 3.13.1, `postquality`, 6.6, 7.1.4, 8.1, Less Than 10, homepage, contributor `postquality`; no console error. The owner looked in their browser and decided: the account's display name will be set on WordPress.org before submission (criterion 1's wording corrected in the ticket, a row in `docs/distribution.md`); the changes delegated to best practice, all four taken: caption 2 drops "the issues" the image does not show, the readme's ten URLs become Markdown links (the directory's own Markdown renders them, checked: 10 links in the sections; readme 9,456 bytes; `release-header-harness.php` 33 passed, `free-build-harness.php` 100 passed, the site's `check_cost_copy.py` NO PROBLEMS), and story 122 filed (a banner that leaves its lower left to the name label, a recapture of screenshot 2's tabs) with story 38's `after` gaining 122; approved. Then Preview 4 (the removals written `${M:?}`, which the recipe now uses too: the safety check refuses a bare-variable target), `debug.log` 53 lines all WP-CLI's, restore, step 11's three "same", `mu-plugins: same, the preview is gone`; the walk folder deleted. `docs/distribution.md` records the approval and the display-name row; `memory/local-verification.md` the preview's gotchas.

## Plan Change Log

**1. 2026-11-10, review pass 1, bad_plan (F1): the Design Note had the card's author from the header.**
- Trigger: F1. The directory's `author` is the submitting WordPress.org account (display name linked to its profile), not the header's Author and Author URI; the first derivation followed the Design Note and showed "By Vincent Heimann" linked to postquality.com, a line the listing never shows. Ticket AC1 ("the author linked to the author's site") shares the wrong premise; its title ("as a new listing would") and the frozen Boundary ("the record mirrors the directory's answer") give the one reading, so the code follows the listing and the owner hears it at the sitting.
- Amended: Code Map (the directory's author and contributor rules, the `postquality` display name); Design Notes (Author; Contributors' avatar difference; two notes from F2 and F4, folded in so the next pass does not meet them again: the recipe's stop conditions run as stops, the readiness check covers all five Markdown files).
- Known-bad state avoided: an owner approving a "By" line, and a header wording fix for it, that the real listing ignores; a recipe that writes over or deletes another walk's preview; a fatal on the Add Plugins screen after a partial download.
- KEEP: everything else of the first derivation, saved in the session scratchpad `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/f2f96085-9354-473d-ba80-e2f5849942d5/scratchpad/` (`derivation-1-directory-preview.php`, `derivation-1-directory-preview-harness.php`, `derivation-1-local-verification.patch` beside `story70-derivation-1.patch`): the routing function and its matrix (40 harness rows, extend them for the five-file check), the filter at `PHP_INT_MAX`, the record's other fields (the review confirmed the FAQ rewrite, the screenshot markup, the icons, the section order, the header-over-readme requirements and the date formats against the directory's source at `d0621733`), the recipe's four steps, its pinned commit and its "What a new listing shows" list (with the author line and the avatar difference corrected). The first derivation's dry run passed every other AC1 and AC2 check: the re-derivation runs no second dry run; the coordinator's sitting checks AC1 and AC2 on the rendered pages before the owner looks.

## Review Triage Log

**Pass 1, 2026-11-10, Quick lens (Opus 5.5, one context-free subagent; it ran in the background, the Agent tool's only mode here, and was awaited before triage): 4 findings, 0 high, 3 medium, 1 low, 0 false, 0 maybe-false. One bad_plan, so this pass loops back.**

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| F1 | `author` (and contributors' display name and avatar) do not mirror the directory | medium | bad_plan | Checked on the live API: wordpress-seo answers `<a href="https://profiles.wordpress.org/yoast/">Yoast</a>` (header "Team Yoast"), akismet and classic-editor the same form; classic-editor's contributor carries `display_name` "WordPress.org" and a `d=monsterid` Gravatar. The code followed the Design Note, so the plan is the root cause. |
| F2 | The recipe's stop conditions are prose only: Preview 2 goes on over another walk's folder, and Preview 4 would delete it | medium | bad_plan (folded into Change Log 1) | `mkdir "$M/postquality-directory-preview"` and the `plugin is-active` line had no `|| exit 1` in the first derivation's patch; the Rules and the owner's deletion rule forbid deleting what this session did not create. |
| F3 | Preview 2's check line fatals (`array_keys( null )`) when the preview is not set up | low | rejected | Reached only after a failed setup the stops of F2 and the five-file check of F4 now catch; the fix adds a guard to a line nobody meets in a normal run. |
| F4 | The readiness check covers `class-markdown.php` only; a missing Michelf file fatals on the Add Plugins request | medium | bad_plan (folded into Change Log 1) | `class-markdown.php` includes `libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.inc.php` and extends `\Michelf\MarkdownExtra`; the first derivation's check read only the class file, against the matrix's "no fatal" row. |

**Pass 2, 2026-11-10, Quick lens on the re-derivation (Opus 5.5, context-free, background as in pass 1): 3 findings, 0 high, 1 medium, 2 low, 0 false, 0 maybe-false. No loopback: two patches, one for the owner.**

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| G1 | A preview walk leaves none of the signs the Rules use to spot a running walk, and Preview 2's clash check runs after walk steps 1 and 2 changed the site | medium | patch | The Rules bullet names port 8765 and `pq-smoke-*` users only; the frame skips walk steps 3 to 8, which make them; Preview 2 runs after walk step 2's `--force` install and the debug constants. Fix: the preview in mu-plugins becomes a third sign, and its check runs before walk step 1. |
| G2 | Row 5's `$lines[0] === $lines[1]` compares two timestamped log lines | low | patch | Checked: `error_log()` to a file writes `[10-Nov-2026 11:41:00 UTC] probe`; two calls across a second boundary differ. A direct correction (compare the text after the prefix), and CI runs the row on 8.1 and 8.3. |
| G3 | Ticket AC1's "the author linked to the author's site" stays unmet as written | low | rejected as a build finding | Carried from F1's premise: the code mirrors the directory (`author` = the submitting account, confirmed at `d0621733` and on the live API). Its fix is the ticket's wording, the owner's: the sitting asks it explicitly before the approval record is written. |

## Design Notes

- Matching: the search term is decoded (`rawurldecode`), lowercased, `-` read as a space; the preview answers when it is the slug, the name, or the name's first two or more words.
- `tested`: the readme's x.y; when the site runs an x.y.z of that x.y, the site's version (the directory sends the latest x.y.z).
- `version`: the installed header's: equal keeps the card's button at "Active"; a higher one makes core start an update check and hide it.
- `last_updated` and `added`: now, in the directory's format.
- Author: the submitting account, which `docs/distribution.md` names as the readme's Contributors account: the first Contributors user, display name the username (the profile's display name is the username until the owner sets one on WordPress.org), linked to `https://profiles.wordpress.org/<user>/`. The card and the sidebar then read "By postquality", as the listing will; the plugin header's Author and Author URI feed neither.
- Contributors: from the readme's Contributors line, profile `https://profiles.wordpress.org/<user>/`, display name the user, avatar core's default; the listing shows the profile's Gravatar (`d=monsterid`): the recipe names that difference.
- Recipe: every "stop" condition the recipe names runs as a stop in its block (`|| exit 1`, as walk and n8n steps do): Plugin Check inactive, a Markdown download that failed (the file count is not 5), and the preview's folder already in mu-plugins (another walk's: never overwrite or delete it).
- Not set up: the preview checks all five Markdown files (the class and the four Michelf files it loads), not the class alone, so a partial download keeps the incoming result with its log line instead of a fatal.
- Skipped: a harness for the record builder -- it needs Plugin Check's parser, which CI lacks; the sitting proves it. Skipped: Upgrade Notice and Reviews -- the readme has none, and core hides Reviews at 0 ratings.

## Verification

**Commands:**
- `php -l bin/directory-preview.php` and `php -l tests/harness/directory-preview-harness.php` -- no syntax errors.
- `for f in tests/harness/*.php; do php -d sys_temp_dir="C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/f2f96085-9354-473d-ba80-e2f5849942d5/scratchpad" "$f" || break; done` -- every harness green, the new one included.
- `npm run test:unit` and `git diff --check` -- green, clean.

**Manual checks:**
- The sitting (hitl): the owner approves the card and More Details on the showcase or names changes; the AC3 record and the tickets follow.
