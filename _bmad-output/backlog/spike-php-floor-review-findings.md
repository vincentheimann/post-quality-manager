# Spike 104 — findings: the PHP floor

Research run on 2026-11-09 by two agents (Opus 5.5): one read the public sources (each read that day; URLs below), one read this repository and the website repository (paths and lines below). The orchestrator re-derived the share figures from the raw API and the support dates from endoflife.date, and listed Local's PHP builds on this PC. The owner decides; the draft ADR is section 6.

## Verdict (proposed, for the owner)

Move `Requires PHP` from 8.0 to **8.1** before the first public release. From then on, the floor is the oldest PHP version whose share on WordPress.org is at least 5 %, which is the rule WordPress core uses for itself. It also has to be a version the project actually runs its checks on.

- **Why not keep 8.0:** WordPress's own 5 % criterion would drop it (3.36 %), and nothing in the project ever runs the plugin on it. CI only lints it, the harnesses already need 8.1, and Local offers no 8.0 build. The floor we claim is one we cannot test.
- **Why 8.1:** it keeps 76.8 % of reporting sites, 3.4 points fewer than 8.0. Local can run it, so the floor becomes a version we execute (CI sweep, the pre-production pass). It also adds a little to the language (`never`, enums where useful).
- **Why not 8.2 or 8.3:** 8.2 reaches end of life on 2026-12-31, before the expected directory launch, and costs 12.5 points of reach. 8.3 is what WordPress recommends and the only candidate with upstream support through 2027, but it keeps only 45.0 % of sites, leaving out more than half of WordPress before the plugin has a single user. The code gains almost nothing from 8.2 or 8.3.
- **Why now:** no site runs the plugin yet, so moving now strands nobody. Once sites run it, any raise leaves sites below the new floor on their last compatible version: WordPress blocks the update, and auto-update skips it (section 3).
- **ADR 0017:** its PHP rule raises the floor for review whenever it is past end of life and WordPress recommends higher. For any floor below 8.3, that fires every month. The draft ADR replaces it with the share rule.

## 1. What decides

| | 8.0 kept | 8.1 | 8.2 | 8.3 |
|---|---|---|---|---|
| Sites the floor keeps (WordPress.org, all reporting sites) | 80.2 % | 76.8 % | 67.7 % | 45.0 % |
| Reach lost against today | — | 3.4 pts | 12.5 pts | 35.2 pts |
| Upstream security support | ended 2023-11-26 | ended 2025-12-31 | ends 2026-12-31 | ends 2027-12-31 |
| WordPress core's 5 % rule | below (3.36 %) | above (9.17 %) | above (22.63 %) | above (27.90 %) |
| The project can run its checks on it | no (lint only; no Local build) | yes (Local 8.1.9/.23/.29; CI) | yes | yes (CI's sweep runs on 8.3 today) |
| What the code gains | — | small: 4 `never` returns, enums for a few value sets | very small on top: a `true` type twice, locale-safe `strtolower` | almost none on top: typed constants only |
| ADR 0017's monthly trigger fires | yes | yes, unless the rule changes | yes, from January 2027 | no, until 2027-12-31 |

## 2. Evidence: what WordPress sites run, and WordPress's position

- **Shares** (https://api.wordpress.org/stats/php/1.0/, read 2026-11-09; the payload has no date, and the response expires 2026-11-10): 7.x total 17.866 % (7.4: 14.466 %), below 7.0 1.939 %, 8.0 3.355 %, 8.1 9.166 %, 8.2 22.633 %, 8.3 27.901 %, 8.4 10.667 %, 8.5 6.449 %, 8.6 0.024 %. Kept by floor: ≥ 8.0 80.195 %, ≥ 8.1 76.840 %, ≥ 8.2 67.674 %, ≥ 8.3 45.041 %, ≥ 8.4 17.140 % (re-derived from the raw JSON). The API gives no PHP breakdown per WordPress version, so the share among sites on WordPress 6.6 or later (87.204 % of sites, https://api.wordpress.org/stats/wordpress/1.0/), the ones that could install the plugin, is unknown.
- **Support dates** (https://endoflife.date/api/php.json, matching https://www.php.net/eol.php and https://www.php.net/supported-versions.php):

  | PHP | Active support ends | Security support ends |
  |---|---|---|
  | 8.0 | 2022-11-26 | 2023-11-26 |
  | 8.1 | 2023-11-25 | 2025-12-31 |
  | 8.2 | 2024-12-31 | 2026-12-31 |
  | 8.3 | 2025-12-31 | 2027-12-31 |
  | 8.4 | 2026-12-31 | 2028-12-31 |
  | 8.5 | 2027-12-31 | 2029-12-31 |

- **WordPress's requirements page** (https://wordpress.org/about/requirements/, modified 2026-05-07) says: "PHP version 8.3 or greater". It adds that WordPress "also works with PHP 7.4+", which is past its end of life.
- **Core's own minimum:** 7.2.24 for WordPress 6.6 to 6.9 and 7.4 since 7.0 (`$required_php_version`, https://core.svn.wordpress.org/tags/). The current release is 7.1.4, and the Hosting Handbook calls 7.0 and 7.1 "fully compatible with PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4 and 8.5" (https://make.wordpress.org/hosting/handbook/compatibility/version/7-1/, updated 2026-11-04). For new installs, the Hosting Team recommends PHP 8.4.x or 8.5.x.
- **WordPress core's drop rule** ("Dropping support for PHP 7.2 and 7.3", https://make.wordpress.org/core/2026/01/09/dropping-support-for-php-7-1-2/, 2026-01-09):
  - "The 5% usage threshold will continue to be used as the standard for the foreseeable future."
  - "There are no plans to bump the minimum supported PHP version on a set schedule."
- **Managed hosting:** WP Cloud made 8.4 its default on 2025-12-04 and removed 8.1 on 2025-12-31 (https://wp.cloud/important-php-updates-8-5-now-available-8-4-becoming-default-8-1-removal-reminder/). Other hosts were not checked.

## 3. Evidence: what a site below the floor meets

From WordPress 7.1.4's source (https://core.svn.wordpress.org/tags/7.1.4/), compared through `is_php_version_compatible()` (since 5.2):

- **Install from the directory:** "This plugin does not work with your version of PHP.", and the Install button is disabled (since 5.1).
- **Any install, a zip upload included:** `Plugin_Upgrader::check_package()` reads `Requires PHP` from the package's main file and refuses with "The PHP version on your server is %1$s, however the uploaded plugin requires %2$s." (since 5.5).
- **Activation:** `validate_plugin_requirements()` refuses, and the plugins list shows "Cannot Activate" (check since 5.2).
- **Updates:**
  - The offer shows "There is a new version of %1$s available, but it does not work with your version of PHP.", with no update link (since 5.2).
  - `bulk_upgrade()` refuses the update (since 6.4).
  - Auto-update skips it silently (`WP_Automatic_Updater::should_update()`, since 5.2).
  - So a site below a raised floor keeps its last compatible version. That is read from the code, not tested on a site.
- **Pro updates through Freemius** (SDK 2.13.5):
  - The SDK sets `$update->requires_php = $new_version->requires_programming_language_version`. In the vendored copy, that is `vendor/freemius/includes/class-fs-plugin-updater.php` L702–710, which injects it into the core update transient (L588, L602–604). Core's gates above then apply.
  - The SDK never reads our header. It does not send the site's PHP with the update request (`includes/class-freemius.php` L22108–22122). Its minimum is PHP 5.6 (`vendor/freemius/composer.json` L14).
  - Whether Freemius fills that field from our zip's header is not verified (section 5).
- **The directory:**
  - It reads `Requires PHP` from the main file, stores it and shows "PHP version: X or higher" (https://github.com/WordPress/wordpress.org, `plugin-directory/cli/class-import.php`, `widgets/class-meta.php`).
  - No guideline sets or limits a minimum (https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/).
  - Plugin Check (2.2.0) checks only the value's format and that the readme matches the header (`plugin_header_invalid_requires_php`, `readme_mismatched_header_requires_php`).
  - No search ranking or filter on the value was found in the public code.

## 4. Evidence: the plugin's own PHP

- **The plugin is 8.0 code today.** Its 11 files under `post-quality-manager/` (without `vendor/`) and its 3 `bin/` scripts were linted clean and scanned with the 8.3 tokenizer and greps. They contain no syntax and no function newer than 8.0.
  - They need 8.0: 34 union return types and one `match` across 5 files (e.g. `includes/class-pqm-n8n.php` L631, L1789).
  - The three spread operators in `includes/class-pqm-blocks.php` (L164–177, L270) only spread integer-keyed arrays, so they are safe on 8.0.
- **The harnesses already need 8.1 at run time:**
  - `tests/harness/caps-harness.php` L374–378 and `license-harness.php` L98 use reflection on private members without `setAccessible`.
  - `render-matrix-harness.php` L184 does the same.
  - `prefix-harness.php` L132 uses `T_ENUM`.
  - CI allows this by design: `.github/workflows/ci.yml` L23–26 says harnesses may use newer PHP.
- **Nothing runs the plugin on 8.0:**

  | Where | What PHP it runs |
  |---|---|
  | CI (`ci.yml`) | 8.0 for `php -l` only (L134–141); the 8.3 lint and the harness sweep (L145–162); the Free build on 8.3 (L194–196). Run 42241926891 used 8.0.30 and 8.3.36. |
  | The showcase | 8.5.12 (`memory/local-verification.md` L15) |
  | Local | builds 8.1, 8.2, 8.3 and 8.5 (no 8.0), read from `%APPDATA%\Local\lightning-services` on this PC |
  | The dev CLI | 8.3 |

- **What 8.1 and later would add.** The plugin's 9 classes are static-only: no instances, no inheritance, data moves as arrays. So most new features have nowhere to land.

  | PHP | What the code could use |
  |---|---|
  | 8.1 | `never` on 4 functions that end in `exit` or `wp_send_json_*` (`admin/class-pqm-admin.php` L2086, L2386, L2429; `bin/build-free.php` L34). Enums for the review statuses and setup steps, though each crosses SQL, REST or JS, where an enum needs `->value`. A commands enum would leak Pro names into the Free build, since `bin/build-free.php` cannot strip an enum case. `readonly`: no eligible property. First-class callables would make the 75 hook callbacks impossible for other plugins to unhook. |
  | 8.2 | A `true` return type in 2 places, and locale-independent `strtolower` in 6 calls. `#[\SensitiveParameter]` already parses on 8.0 and is ignored there. |
  | 8.3 | Typed class constants (38, documentation only). Nothing else applies. |

## 5. What a move touches (the follow-up story's list) and what is unverified

**To change when the floor moves:**
- **The value itself:**
  - `post-quality-manager/post-quality-manager.php` L10 and `post-quality-manager/readme.txt` L6. `tests/harness/release-header-harness.php` checks only that the two match.
  - `README.md` L38 and `docs/distribution.md` L14, L41.
  - `AGENTS.md` L8 and L30. These sit inside the managed `bmad:context` block, so they are refreshed through `bmad-project-context` or a recorded hand edit.
- **CI:** in `.github/workflows/ci.yml`, the 8.0 lint job (L134–141) and its comments (L23–26, L133). The sweep should also run on the floor version once it passes there; that was not run.
- **Records:**
  - `memory/lifecycle.md` L10.
  - `memory/delivery-pipeline.md` L31. It also claims Plugin Check covers 8.1-only functions; nothing configures that.
  - `.claude/skills/lcm/SKILL.md` step 4, which carries the PHP rule.
  - ADR 0017's status line, cross-linked to the new ADR.
- **The website repository:** `docs.md` L25 ("PHP 8.0 or later") and `index.html` L84 (the "PHP 8.0+" chip). Its README L19 requires `docs.md` to match `readme.txt` word for word.
- **The release notes:** one line in `docs/release-notes/unreleased.md`. The plugin has no real users today, but a pre-release test site on 8.0 would stop receiving updates.
- **The pre-production pass** (story 38): run it on the floor version. Local offers 8.1.

**Not verified:**
1. Whether Freemius fills `requires_programming_language_version` from the deployed zip's header. Read it from the Freemius tag after the first deployment with the new header.
2. The PHP share among sites on WordPress 6.6 or later. Not published; likely higher than among all sites.
3. What exactly the stats API measures, and its snapshot date.
4. Whether the harness sweep passes on 8.1. Not run, because three harnesses write temporary files.
5. Whether Freemius's deployment, which rewrites every PHP file, parses 8.1 syntax such as enums. It only matters if the code adopts them.
6. Which PHP the Plugin Check action and the release runner use.
7. The PHP defaults of hosts other than WP Cloud.
8. The labels WordPress's compatibility table showed at each release from 6.6 to 6.9. The table and the May 2026 post disagree on 6.7 × 8.4; this does not change the verdict.

## 6. Draft ADR (recorded only after the owner decides)

> **0018. The PHP floor is 8.1 and follows WordPress core's 5 % rule**
>
> - Status: proposed (spike 104) — becomes accepted, with ADR 0017's PHP rule marked superseded by it, once the owner decides
> - Date: 2026-11-09
>
> **Context.** The plugin requires PHP 8.0. Upstream security support ended for 8.0 on 2023-11-26 and for 8.1 on 2025-12-31; it ends for 8.2 on 2026-12-31 and for 8.3 on 2027-12-31.
>
> - **WordPress's position:** it recommends 8.3 or later and requires 7.4 since 7.0. Core drops a PHP version when that version falls below 5 % of sites.
> - **Reach, on WordPress.org's share data (2026-11-09):** a floor of 8.0 keeps 80.2 % of sites, 8.1 keeps 76.8 %, 8.2 keeps 67.7 % and 8.3 keeps 45.0 %. PHP 8.0 alone is 3.4 %.
> - **Testing:** nothing runs the plugin on 8.0. CI only lints it there, the harnesses need 8.1, and Local has no 8.0 build.
> - **Code:** later versions add little. The classes are static-only and the data is arrays.
> - **Users:** no site runs the plugin yet. After the launch, a site below a raised floor keeps its last compatible version.
> - **ADR 0017:** raises the floor for review whenever the floor is past end of life and WordPress recommends higher, which any floor below 8.3 triggers monthly.
> - **Alternatives:** keep 8.0; 8.1; 8.2 (end of life before the launch); 8.3 (WordPress's recommendation, 35 points of reach lost).
>
> **Decision.**
> - `Requires PHP` becomes 8.1, before the first public release.
> - The floor is the oldest PHP version that both holds at least 5 % of WordPress.org's PHP share and runs the project's checks: CI's lint and harness sweep, and the pre-production pass.
> - `/lcm` reads the share every month. When the floor version falls below 5 %, it files a ticket that moves the floor by this rule and cites this ADR.
> - Moving the floor for any other reason (a language feature, a security fault) needs a new ADR.
> - This replaces ADR 0017's PHP rule.
>
> **Consequences.**
> - **Easier:** the floor is a version the project actually runs; CI's 8.0-only lint job goes; `never` and enums are available.
> - **Harder:**
>   - sites on PHP 8.0 (3.4 % of those reporting) cannot install the plugin;
>   - the floor stays past upstream end of life by design, as WordPress core's own does;
>   - each later move leaves sites below it on an older version.
> - **Committed to:**
>   - a follow-up story: header, readme, CI, docs, records, the website, a release-notes line;
>   - a share reading in each `/lcm` run.
