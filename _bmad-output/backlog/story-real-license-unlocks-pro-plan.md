---
title: 'A customer buys Pro and a real license unlocks it'
type: 'feature'
ticket: 44
created: '2026-10-09'
status: 'built'
baseline_revision: 'a307fe9098672700ed73381ea8590c7e156acae5'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/docs/adr/0013-pro-sold-through-freemius-before-public-release.md'
  - '{project-root}/docs/adr/0014-two-builds-from-one-source.md'
  - '{project-root}/docs/distribution.md'
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `PQM_License::is_pro()` is the stub of ADR 0006: any non-empty key unlocks Pro, so a public release would hand Pro to anyone and ask for a key nobody can buy. ADR 0013 D1 puts real licensing before the first public release.

**Approach:** Vendor the Freemius WordPress SDK (tag 2.13.4) and initialise it from the main plugin file with the product from `docs/distribution.md`; `PQM_License::is_pro()` becomes a read of the SDK's license state. The SDK's own activation screen and Account page carry the key (the Settings key field goes); the License section shows the plan, a lapse notice with a renew link, and on Free the one "Get Pro" link to https://postquality.com/pricing. Uninstall cleanup moves behind the SDK's `after_uninstall` hook. Harnesses get one shared `pqm_fs()` stub.

## Boundaries & Constraints

**Always:** `PQM_Features::is_pro()`, the `pqm_is_pro` filter, gate callers, `why_not()`, `plan_allows()` and the sanitizer carry rules stay as they are (ADR 0006: only `PQM_License` internals change). The SDK folder is committed unmodified at a tagged release and loads before `plugins_loaded`, from the top of the main plugin file, with the settings array inline in `fs_dynamic_init()`. Nothing leaves the site before the SDK's opt-in consent (`is_org_compliant`). Stored settings keys unchanged: `license_key` stays in defaults and the carry list, no longer rendered or read. Every harness runs without WordPress and without the SDK.

**Never:** no in-dashboard pricing, contact, support or affiliation submenu (`menu` flags off; Account on); no second upsell beyond the License section: REST gate copy, React locked copy and `.pqm-locked-note` texts unchanged; no `__premium_only` markers, Free template or CI check on the Free build (story 54); no REST route, payload, DB schema or `PQM_WORKFLOW_VERSION` change; no secret key, API token or sandbox constant in the repository; no edit inside `vendor/freemius/`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Valid license | SDK `can_use_premium_code()` true | `PQM_License::is_pro()` true; paid agents, overrides, Automated Reviews, Roles tab unlock | No error expected |
| No license / Free build | SDK registered or skipped, no license | `is_pro()` false; License section: plan Free + "Get Pro" link to postquality.com/pricing | No error expected |
| Expired or cancelled | SDK has a license, `can_use_premium_code()` false | `is_pro()` false; overrides, schedules, role rights untouched; License section says the license lapsed, link to the SDK Account page | No error expected |
| Stub-era key stored | `license_key` = 'anything', no SDK license | Free; key never read; next admin save clears it (field absent) | No error expected |
| SDK absent (harness) | `pqm_fs()` predefined by the stub | plugin loads, `is_pro()` follows the stub's flag; `pqm_is_pro` filter still wins | No error expected |
| Uninstall | Plugin deleted from WP Admin | table, options, meta, caps removed as today, through `after_uninstall` | Cleanup also runs when the SDK was never opted in |

</frozen-after-approval>
## Code Map
- `post-quality-manager/post-quality-manager.php:19-43` -- SDK bootstrap goes between the `ABSPATH` check and the constants: `if ( ! function_exists( 'pqm_fs' ) ) { function pqm_fs() {…require vendor/freemius/start.php; fs_dynamic_init([…])…} pqm_fs(); do_action( 'pqm_fs_loaded' ); }` with `id` 41144, `slug` post-quality-manager, `public_key` pk_ca2d449d0ee2b01bbdbdeb9bf3789, `is_premium` true, `premium_suffix` 'Pro', `has_premium_version`/`has_paid_plans` true, `has_addons` false, `is_org_compliant` true, `menu` => `['slug' => 'pqm-dashboard', 'account' => true, 'pricing' => false, 'contact' => false, 'support' => false, 'affiliation' => false, 'addons' => false]` (`pqm-dashboard` is the top-level slug, `admin/class-pqm-admin.php:51-56`); then `pqm_fs()->add_action( 'after_uninstall', [ 'PQM_Activator', 'uninstall' ] )` after the includes. `function_exists` guard = the harness seam.
- `post-quality-manager/vendor/freemius/` -- SDK 2.13.4 from https://github.com/Freemius/wordpress-sdk/archive/refs/tags/2.13.4.zip, folder contents unmodified (start.php, require.php, config.php, includes/, templates/, assets/, languages/, LICENSE.txt). Not in `.gitignore`, so it is committed.
- `post-quality-manager/includes/class-pqm-license.php:25-27` -- `is_pro()` → `function_exists( 'pqm_fs' ) && pqm_fs()->can_use_premium_code()`; add `has_lapsed()` → `function_exists( 'pqm_fs' ) && pqm_fs()->has_any_license() && ! pqm_fs()->can_use_premium_code()`; rewrite the file header (stub era over). SDK facts: `can_use_premium_code()` = trial or a features-enabled license (`class-freemius.php:20908`); `has_any_license()` at 11497; `get_account_url()` at 16343.
- `post-quality-manager/admin/class-pqm-admin.php:700-717` -- License section: drop the key `<table>`; status line reads Pro / lapsed (`PQM_License::has_lapsed()`, renew link = `pqm_fs()->get_account_url()`) / Free with the "Get Pro" link; read `docs/brand.md` before writing the copy. `:354` sanitizer: keep `license_key` in the admin branch (`trim( sanitize_text_field( $input['license_key'] ?? '' ) )`) and in `$admin_only_keys` `:378`; add a one-line comment that the key is unused since story 44.
- `post-quality-manager/includes/class-pqm-activator.php` -- new `public static function uninstall(): void` holding today's `uninstall.php` body (table drop, options, meta, `PQM_Features::remove_capabilities()`, cron clear). Delete `post-quality-manager/uninstall.php` (WordPress runs it instead of the SDK's `register_uninstall_hook`, `wp-admin/includes/plugin.php` `uninstall_plugin()`).
- `tests/harness/fs-stub.php` -- new shared stub: `class PQM_FS_Stub { public $pro = false; public $has_license = false; can_use_premium_code(); has_any_license(); get_account_url(); get_upgrade_url(); add_action(); }` and `function pqm_fs()` returning one global instance. Required (before the plugin files) by every harness that reaches `class-pqm-license.php`: license, caps, cron-gate, quota, voice, rest-reviews, sanitize-matrix, insert-block, render-matrix, rest-posts, setup.
- `tests/harness/license-harness.php` -- rows become: valid license → pro; no license → free; expired (`has_license` true, `pro` false) → free + `has_lapsed()` true; filter override wins (keep Row 4); stub-era key ignored.
- `tests/harness/caps-harness.php:24,59,94` and `rest-posts-harness.php:600,672` -- pro switch via `pqm_fs()->pro` instead of `license_key`; `caps-harness.php:452` Row 10 calls `PQM_Activator::uninstall()` instead of requiring `uninstall.php`.
- `tests/harness/sanitize-matrix-harness.php:252-271` -- Case 5b keeps the sanitize/carry checks on `license_key` (storage contract unchanged); `$stored_free` and any Pro-dependent case use the stub flag.
- `.github/workflows/ci.yml:75,98-104` -- `php -l` loop skips `*/vendor/*`; plugin-check gets `exclude-directories: vendor`. `release.yml:80` zips the folder whole: vendor ships.
- `docs/release-notes/unreleased.md` -- AC6 line. `docs/distribution.md:65-90` -- SDK row → 2.13.4; new rows: Pro plan expiration set to "block features" (D7), opt-in screen as shipped.
- `docs/policies/readme-external-services.md:16`, `privacy.md:35` -- already describe the Freemius check and opt-in; re-read, change only if the built behaviour differs.

## Tasks & Acceptance
**Execution:**
- [x] `post-quality-manager/vendor/freemius/` -- add SDK 2.13.4 unmodified -- the provider
- [x] `post-quality-manager/post-quality-manager.php` -- bootstrap `pqm_fs()` + `after_uninstall` hook -- SDK before everything, harness seam
- [x] `post-quality-manager/includes/class-pqm-license.php` -- `is_pro()` reads the SDK, `has_lapsed()` -- the seam filled
- [x] `post-quality-manager/includes/class-pqm-activator.php` + delete `uninstall.php` -- cleanup behind the SDK hook -- WordPress skips the hook when uninstall.php exists
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- License section copy, key field removed, sanitizer comment -- AC1–AC3
- [x] `tests/harness/fs-stub.php` + the 11 harnesses -- stub, pro switches, license rows, uninstall row -- AC4
- [x] `.github/workflows/ci.yml` -- vendor excluded from lint and Plugin Check -- AC5
- [x] `docs/release-notes/unreleased.md`, `docs/distribution.md`, `memory/freemium-and-roles.md` -- AC6, data sheet, memory
**Acceptance Criteria:**
- Given the Pro build on Local with `WP_FS__DEV_MODE` and the owner's `WP_FS__post-quality-manager_SECRET_KEY` in wp-config, when a sandbox-bought key is activated on the SDK screen, then `is_pro()` is true and the License section says Pro (owner-run, hitl)
- Given a fresh activation, when the SDK opt-in screen is skipped, then the plugin works on Free and no request left the site before the choice
- Given Plugin Check in CI, when it runs on the plugin folder minus vendor, then no new error
- Given the Freemius dashboard, when the Pro plan's expiration behaviour is set to block features and the plans are released, then the story is done (owner, last step)

## Design Notes
Key field dropped, not kept: in the Pro build the SDK's activation screen and Account page own the key (Plugins-page "Activate License" too); in the Free build there is nothing to activate, the customer downloads Pro after checkout. A second key path would be two sources of truth for ~15 lines of activation code (`activate_migrated_license()` exists if ever needed). "Get Pro" targets the public pricing page, not the SDK pricing page, so `menu.pricing` can stay off and the upsell has one place (AC3, ADR 0014). `has_lapsed()` is the only new public method; the lapse copy sends users to the SDK Account page, which renews and syncs.
Skipped: the SDK pricing page and in-dashboard checkout — add when story 50's funnel shows the website checkout losing buyers.

## Verification
**Commands:**
- `php -l` on every touched PHP file; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass
- `npm run test:unit` and `npm run lint:js` -- expected: green (`lint:js` covers `src tests/js` only, so `vendor/` is never linted)
- `git diff --stat -- post-quality-manager/vendor` after vendoring -- expected: only additions, no file differs from the tag
**Manual checks (no CLI):**
- Local site, Pro build: activate → opt-in screen → skip → Free; Settings → License shows Free + "Get Pro"; sandbox key on the SDK screen → Pro; expire the test license in the Freemius dashboard → lapse copy, settings intact.
- Delete the plugin from WP Admin: `pqm_reviews` table, `pqm_*` options, `pqm_monthly_usage` meta and the `pqm_` caps are gone.

## Review Triage Log
**Pass 1 (quick lens, 2026-10-09):** 7 findings: high 0 · medium 3 · low 3 · false 0 · maybe-false 0 · (1 deferred as agent-context).
| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | `has_lapsed()` reports "lapsed" when the Freemius user owns a license used on another site | medium | patch | `has_any_license( $including_foreign = true )` (`class-freemius.php:11497`) returns true for any license in the user's list; the site's own license is `_get_license()` (`:12678`). A paying customer opted in on a second site would be told to renew. Fix: `has_lapsed()` reads `is_object( pqm_fs()->_get_license() )`; the stub models it. |
| 2 | Cleanup skipped on `wp plugin uninstall` and after a deactivation without an admin user or under 3.3.0 | low | reject | Verified: `_uninstall_plugin_hook()` returns unless `current_user_can( 'activate_plugins' )` (`:9861`); `_deactivate_plugin_hook()` registers the WP hook only after `is_user_admin()` (`:8205`). Harm is leftover table/options/caps on rare paths (CLI, test-build sites deactivated before the update), no data loss or exposure; the everyday WP Admin deactivate → delete path works. Any fix means leaving the SDK hook (intent: "through after_uninstall"). Ceiling reported to the owner. |
| 3 | Pre-consent Freemius API request (and a file write into `vendor/`) for the opt-in icon on localhost | medium | patch | `get_local_icon_url()` (`:25587`) finds no `post-quality-manager.*` in `assets/img`; on `WP_FS__IS_LOCALHOST` with direct FS it calls `fetch_remote_icon_url()` before the choice, contradicting matrix/AC2 on the Local check (and review teams test locally). Fix: `plugin_icon` filter → the SDK's bundled `assets/img/plugin-icon.png` until story 51 ships ours. |
| 4 | Release note and data sheet send skipped installs to an Account page that does not exist | low | patch | `add_submenu_items()` adds Account only when `is_registered()` (`:18913`); after Skip the later path is the Plugins-screen "Activate License" link. Direct text correction. |
| 5 | Policy texts understate the opt-in data | medium | patch | `class-fs-permission-manager.php`: Basic Profile (WP user's name and email) is not optional (`:378-389`), site title and language are sent (`:191-192`), the plugins-and-themes list is on by default in opt-in mode (`:353-370`). `readme-external-services.md:16,26` and `privacy.md:35` say "anonymous usage data"; the plan required re-reading them against the built behaviour. |
| 6 | `memory/freemium-and-roles.md` Roles-tab bullet still says `uninstall.php` deletes the View-as meta | low | patch | Line unchanged by the diff while the file's other `uninstall.php` mentions were updated; one-word correction. |
| 7 | `AGENTS.md` edit sits inside the managed `bmad:context` block | low | defer | The block is regenerated by `bmad-project-context`; the fix edits an agent-context file. |
