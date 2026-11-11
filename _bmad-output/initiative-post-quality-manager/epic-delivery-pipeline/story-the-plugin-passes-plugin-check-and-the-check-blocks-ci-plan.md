---
title: 'The plugin passes Plugin Check and the check blocks CI'
type: 'chore'
ticket: '5'
created: '2026-10-08'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'auto'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'd64b1af'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Plugin Check reports in CI (story 4.4) but blocks nothing, and its report carries one error and eight false-positive warnings that hide anything new.

**Approach:** Resolve the triage below without changing behaviour, then make the check blocking: drop `continue-on-error`, so any Plugin Check error fails CI.

**Owner decision (2026-10-08), E1:** `readme.txt` stays in backlog story 38. The workflow passes `ignore-codes: no_plugin_readme` with a comment naming story 38, which removes the ignore when it writes the readme (and adds `Stable tag` to the version-agreement harness row).

## Boundaries & Constraints

**Always:** a real fix beats an annotation; an annotation names the exact sniff code and a reason. Warnings accepted in the triage stay as they are.

**Never:** no behaviour change, no `readme.txt`, no `strict` mode (warnings never block).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| main after this story | current code | 0 errors, only the accepted warnings (W1, W2, W6, W7) | CI green |
| Seeded unescaped output | `echo $_GET[…]` in a plugin file on a branch | Plugin Check reports an `EscapeOutput` error | the Plugin Check job fails |
| Missing readme | no `readme.txt` | ignored by code `no_plugin_readme` | — |
| Review history query | `get_reviews_for_post()` | same rows; table bound through `%i` | — |
| Score trend query | `get_score_trend()` | same rows; both tables bound through `%i` | — |
| Uninstall | `DROP TABLE IF EXISTS %i` | table dropped as before | — |

</frozen-after-approval>

## Code Map

- `.github/workflows/plugin-check.yml` -- blocking (no `continue-on-error`), job renamed `Plugin Check`, `ignore-codes: no_plugin_readme` with the story 38 comment.
- `post-quality-manager/includes/class-pqm-db.php` -- W3 fixed: `get_reviews_for_post()` and `get_score_trend()` bind table names with `%i` (WordPress 6.2+, the plugin requires 6.6). W4: `UnescapedDBParameter` added to the existing `phpcs:ignore` on the three composed queries, with a reason.
- `post-quality-manager/uninstall.php` -- W3/W4 fixed: `DROP TABLE IF EXISTS %i` through `prepare()`.
- `post-quality-manager/admin/class-pqm-admin.php` -- W5: precise `NonceVerification.Recommended` ignore on the `settings-updated` notice flag.
- `tests/harness/rest-reviews-harness.php` -- the history-query row expects `FROM %i` and args `[wp_pqm_reviews, 7, 50]`.
- `tests/harness/setup-harness.php`, `tests/harness/view-as-harness.php` -- their `$wpdb` stubs gain `prepare()` (uninstall now calls it).

## Tasks & Acceptance

**Execution:**
- [x] Workflow blocking + E1 ignore.
- [x] W3 fixed with `%i`; W4 and W5 annotated.
- [x] Harness stubs and the history-query expectation.
- [x] `memory/delivery-pipeline.md`.

**Acceptance Criteria:**
- Given this branch, when Plugin Check runs, then it reports 0 errors and the job passes.
- Given a throwaway branch with an unescaped `echo` of request input, when Plugin Check runs, then the job fails.

## Implementation Notes

- Built 2026-10-08. Local: `php -l` clean on every touched file; full harness sweep 15/15 green.
- No harness executes `get_score_trend()` or real `$wpdb->prepare()` (the stubs return the SQL unchanged), so `%i` is proven by WordPress core, not by this repo. Manual check on Local below.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green.

**Manual checks:**
- On Local: the Dashboard score sparkline and a post's review history in Post Reviews load as before (the two `%i` queries); deleting the plugin drops `wp_pqm_reviews`.

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
