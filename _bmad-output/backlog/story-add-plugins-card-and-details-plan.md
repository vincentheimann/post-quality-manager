---
title: 'Story 70 — the Add Plugins card and More Details, previewed on the showcase and approved by the owner'
type: 'feature'
ticket: '70'
created: '2026-11-10'
status: 'ready-for-dev'
route: 'full'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
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
- github.com/WordPress/wordpress.org, `trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/` -- `class-markdown.php` (extends `\Michelf\MarkdownExtra`; includes `libs/michelf-php-markdown-1.6.0/Michelf/MarkdownExtra.inc.php` beside itself: mirror that layout; GPLv2, Michelf BSD-3-Clause); `api/routes/class-plugin.php` L463–495 -- the answer to mirror: FAQ `dt`→`h4`, `dd`→`p`; the screenshots section `<ol><li><a href=SRC><img src=SRC alt=CAP></a><p>CAP</p></li></ol>`; `screenshots` `{n: {src, caption}}`; `icons` with an SVG `{1x: svg, svg: svg}`; read there how sections the parser does not expect (External services) reach the answer before mapping them.
- Freemius SDK `class-fs-plugin-updater.php:85` -- filters `plugins_api` at 10 for this slug and overwrites; not registered on a Free build, but the preview runs at `PHP_INT_MAX`.
- `tests/harness/lib/wp-stubs.php` -- the shared world for the new harness.
- `docs/local-verification.md` § Other checks -- the recipe goes here; Rules and walk steps 1, 2, 10, 11 are its frame.
- `docs/distribution.md` L46–64 -- the approval line goes after the Assets table.
- `post-quality-manager/readme.txt`, the header of `post-quality-manager/post-quality-manager.php` -- the only files a wording fix touches; `release-header-harness.php` Row 7, `free-build-harness.php` and postquality.com's cost-copy check read them. Not to change: `assets/`, `bin/zip.js`, `bin/build-free.php`.

## Tasks & Acceptance

**Execution:**
- [ ] `bin/directory-preview.php` -- the must-use plugin: a pure routing function (the matrix) and the record builder (parser and Markdown on demand, header through `get_plugin_data()`, image URLs under `WPMU_PLUGIN_URL`); a header comment: development-only, where the recipe is -- AC1, AC2.
- [ ] `tests/harness/directory-preview-harness.php` -- the matrix's routing rows on the preview file with the shared stubs -- proves the pass-through.
- [ ] `docs/local-verification.md` -- subsection "Add Plugins preview (story 70)" under Other checks: the four Markdown files fetched at one pinned commit into `$S`; the preview, the eight images and the Markdown copied into the showcase's mu-plugins (created if absent, removed if created); the screens opened; the preview removed; restore; one line proving it gone -- re-runnable for story 60.
- [ ] Sitting (hitl) -- the recipe on a Free build of this branch; the agent checks AC1/AC2 on the rendered pages (Playwright DOM reads: card fields, tab list, no `*`, `**` or `=` markup, no empty tab, three captions, sidebar values) and `debug.log`; the owner looks in their browser; wording changes go to the readme or the header; every other change is filed through bmad-ticket and added to story 38's `after`.
- [ ] `docs/distribution.md` -- "Card preview approved <date>" with what was approved -- AC3.
- [ ] `memory/local-verification.md` -- one dated line: the preview's gotchas (percent-encoded `s`, point-release `tested`, the parser without Markdown, the SDK filter).

**Acceptance Criteria:**
- Given the recipe run on the showcase, when it ends, then step 11 prints "same" three times, the preview's file and folder are gone, and `debug.log` held only WP-CLI's own lines while the preview ran.
- Given a wording change from the sitting, when it lands, then the harness sweep and the site's cost-copy check stay green.

## Implementation Notes

## Plan Change Log

## Review Triage Log

## Design Notes

- Matching: the search term is decoded (`rawurldecode`), lowercased, `-` read as a space; the preview answers when it is the slug, the name, or the name's first two or more words.
- `tested`: the readme's x.y; when the site runs an x.y.z of that x.y, the site's version (the directory sends the latest x.y.z).
- `version`: the installed header's: equal keeps the card's button at "Active"; a higher one makes core start an update check and hide it.
- `last_updated` and `added`: now, in the directory's format. Author: a link to the Author URI.
- Contributors: from the readme's Contributors line, profile `https://profiles.wordpress.org/<user>/`, display name the user, avatar core's default (the listing shows the profile's Gravatar).
- Skipped: a harness for the record builder -- it needs Plugin Check's parser, which CI lacks; the sitting proves it. Skipped: Upgrade Notice and Reviews -- the readme has none, and core hides Reviews at 0 ratings.

## Verification

**Commands:**
- `php -l bin/directory-preview.php` and `php -l tests/harness/directory-preview-harness.php` -- no syntax errors.
- `for f in tests/harness/*.php; do php -d sys_temp_dir="C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/f2f96085-9354-473d-ba80-e2f5849942d5/scratchpad" "$f" || break; done` -- every harness green, the new one included.
- `npm run test:unit` and `git diff --check` -- green, clean.

**Manual checks:**
- The sitting (hitl): the owner approves the card and More Details on the showcase or names changes; the AC3 record and the tickets follow.
