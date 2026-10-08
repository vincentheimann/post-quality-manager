---
title: 'The plugin passes Plugin Check and the check blocks CI'
type: 'chore'
ticket: '5'
created: '2026-10-08'
status: 'draft'
---

## Plugin Check triage (from story 4.4)

Source: the first report, Plugin Check workflow run 37782106347 on `chore/plugin-check-in-ci` (`wordpress/plugin-check-action` v1.1.9, all categories, WordPress latest, built `post-quality-manager/` folder). **1 error, 29 warnings.** The action fails the step on errors only (`dist/index.js`: `process.exitCode = 1` when `type === 'ERROR'`, warnings only with `strict`), so the error decides whether the check can block.

### Errors

| # | File | Code | Finding | Triage |
|---|------|------|---------|--------|
| E1 | `readme.txt` | `no_plugin_readme` | The plugin readme.txt does not exist. | **Owner decision.** Recommended: accept for now, via `ignore-codes: no_plugin_readme` in `plugin-check.yml`, with the reason "readme.txt is backlog story 38 (WordPress.org listing, epic boundary); story 38 removes the ignore". Alternative: 4.5 writes a minimal valid `readme.txt`, which crosses the epic boundary and pre-empts story 38's content (External services disclosure, changelog from release notes). |

### Warnings

| # | File:line | Code | Count | Triage |
|---|-----------|------|-------|--------|
| W1 | `includes/class-pqm-db.php` (10 sites: 32, 56, 82, 127, 150, 307, 332, 364, 410, 485) | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | 10 | Accepted: the plugin owns the custom table `{prefix}pqm_reviews`; there is no WordPress API to query it. |
| W2 | same sites except 32 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | 9 | Accepted: admin-only reads of live review state; an object cache would serve stale scores after a webhook write. |
| W3 | `class-pqm-db.php:58`, `:412`; `uninstall.php:15` | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | 3 | False positive: the interpolated value is the table name from `$wpdb->prefix`, which `prepare()` cannot bind. Fix in 4.5: a precise `// phpcs:ignore <code> -- <reason>` so the report stays readable and a real one stands out. |
| W4 | `class-pqm-db.php:307`, `:332`, `:364`; `uninstall.php:15` | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | 4 | False positive: every user-supplied fragment of `$count_sql` / `$items_sql` / `$sql` is built with `$wpdb->prepare()` (status, score band) or comes from a code allow-list (orderby map, status constants). The existing `phpcs:ignore WordPress.DB.PreparedSQL` does not cover this sniff. Fix in 4.5: same precise annotation as W3. |
| W5 | `admin/class-pqm-admin.php:580` | `WordPress.Security.NonceVerification.Recommended` | 1 | False positive: reads core's `settings-updated` redirect flag only to show a notice; no state changes. Fix in 4.5: precise annotation. |
| W6 | `post-quality-manager.php` header | `plugin_header_nonexistent_domain_path` | 1 | Accepted: owner decision 2026-10-07, no empty `languages/` folder ships (story 4.6). Resolves with the first bundled translation or story 38's language packs. |
| W7 | `post-quality-manager.php:58` | `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | 1 | Accepted until story 38: translate.wordpress.org language packs replace the call (`memory/delivery-pipeline.md`, translations). |

Nothing found needs a behaviour change, so no new ticket is filed.
