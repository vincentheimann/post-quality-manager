---
title: "Translations load from the plugin's languages folder"
type: 'feature'
ticket: '6'
created: '2026-10-08'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'auto'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '3d5c520883379724b426b591d9e75f209eb49cc5'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The header declares the text domain but no Domain Path and no loading call, so PHP strings only load from WordPress's global language directory, while the React bundle already reads the plugin's `languages/` folder.

**Approach:** Add the Domain Path header and register the folder with `load_plugin_textdomain()` on `init` (WordPress 6.7+ loads just in time, so no "too early" notice). Ship hand-written fixtures in `tests/i18n/` so the check takes minutes without WP-CLI.

## Boundaries & Constraints

**Always:** text domain `post-quality-manager` (story 10). No tracked `languages/` folder, so none ships. WordPress 6.6 floor. Fixtures stay outside the plugin folder.

**Never:** real translations, `.pot` generation, a `.po` → `.mo` compile step, a new dependency, copy changes (story 41).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| PHP page | fixtures in `languages/`, site language de_DE | Settings submenu and Post Reviews heading in German | — |
| React page | same | Dashboard tile "Average score" in German | — |
| Fixtures removed | folder absent | English everywhere | no notice with WP_DEBUG on |
| Default install | no folder | behaves as before | no notice |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php` -- header `Domain Path: /languages`; `pqm_load_textdomain()` on `init`.
- `post-quality-manager/admin/class-pqm-admin.php:233` -- `wp_set_script_translations('pqm-app', …, languages)` already wired; verified, unchanged.
- `tests/i18n/` (new) -- `post-quality-manager-de_DE.l10n.php` (PHP, preferred over `.mo` since WP 6.5), `post-quality-manager-de_DE-pqm-app.json` (handle-named Jed JSON, tried before the md5 name), README with the site check.
- `tests/harness/release-header-harness.php` -- rows 4–5.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- Domain Path + init loader -- the PHP half of CAP-5.
- [x] `tests/i18n/*` -- fixtures + README -- resolves the ticket's unknown without WP-CLI.
- [x] `tests/harness/release-header-harness.php` -- header parses, loader hooked on init, no empty folder, fixture strings still exist in PHP and JS sources -- keeps the manual check valid after copy changes.
- [x] `memory/delivery-pipeline.md` -- record the built facts.

**Acceptance Criteria:**
- Given the fixtures copied into `languages/` on a de_DE site, when the owner opens the plugin pages, then the PHP and React strings above are German and WP_DEBUG shows no notice; removing them restores English.

## Implementation Notes

- `load_plugin_textdomain()` is reported as discouraged by Plugin Check for directory-hosted plugins (language packs replace it). It stays while releases come from GitHub; entry 4.5 triages it, story 38 may drop it.
- Script translations try the plugin's `languages/` before `WP_LANG_DIR/plugins`, so a bundled JSON would shadow a language pack; nothing is bundled, so no conflict.
- The only translated string outside a page request is the cron schedule label on `cron_schedules`, which runs after `init`.
- Skipped: a second locale and a `.mo` fixture -- add when a real translation arrives.

## Plan Change Log

## Review Triage Log

- Quick self-review: 1 finding, patched. The loader comment claimed language packs win over the folder; true for PHP on 6.7+, false for scripts. Claim removed.

## Verification

**Commands:**
- `php -l` on the touched PHP files -- expected: no syntax errors (done).
- `for f in tests/harness/*.php; do php "$f"; done` -- expected: all pass (done: 14 harnesses, release-header 18/18).

**Manual checks:**
- Owner: follow `tests/i18n/README.md` on a Local site with WP_DEBUG on; walk the four matrix rows.
