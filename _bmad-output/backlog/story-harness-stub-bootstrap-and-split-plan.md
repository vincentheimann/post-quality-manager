---
title: 'The CLI harnesses share one WordPress stub bootstrap (story 78, build 1 of 2)'
type: 'refactor'
ticket: '78'
created: '2026-10-21'
status: 'done'
baseline_revision: '90179a2c763963b4c5782ba8c9feb04ffa5edb19'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['_bmad/custom/minimal-code-ladder.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Each of the 16 plain-PHP harnesses in `tests/harness/` re-declares its own WordPress stub world and runner, and they drift: three sanitizer behaviours, three option-store globals, three runner tails, two error-reporting levels. Two retrospectives asked for one bootstrap; none landed.

**Approach:** Build 1 of story 78 (the n8n harness split is build 2, deferred-work entry of 2026-10-21): one shared include holds the runner, the WordPress classes and the stubs whose meaning is the same everywhere; each harness loads it and keeps only the stubs that carry its own meaning. Behaviour of every check unchanged, counts equal.

## Boundaries & Constraints

**Always:** Every harness asserts exactly what it asserted; total check count unchanged (16 harnesses, 2,124 checks: caps 284, cron-gate 35, free-build 83, insert-block 66, license 27, n8n-api 581, quota 71, release-header 31, render-matrix 409, rest-posts 165, rest-reviews 65, sanitize-matrix 226, setup 141, template-failure 55, template-guard 62, voice 23). The shared include lives in `tests/harness/lib/`, which CI's non-recursive `tests/harness/*.php` loop never executes on its own. Every stub in the include is guarded (`if (!function_exists(...))` / `if (!class_exists(...))`), so a harness declares its specific stubs before the require and wins. Plain PHP, no framework (ADR 0004). `php -l` on every touched file. If an existing check passes only because of a stub the bootstrap normalises (the cast sanitizer, a `?`-only query builder, an option default inside `get_option`), HALT and show it to the owner; never adjust the check.

**Never:** Change plugin code. Touch `n8n-api-harness.php` beyond swapping its common stubs for the bootstrap (its split is build 2). Move `fs-stub.php` (14 requires point at it; the bootstrap requires it itself). Rewrite harness-specific stubs (the pro filter, `current_user_can`, `wp_remote_*`, dates and time zones, URLs, nonces, user ids, the plugin-function stubs of caps, cron-gate, license and quota).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Harness runs alone | `php tests/harness/<any>.php` | Same "N passed, 0 failed" line, exit 0; a failure prints `  FAIL <label>` and exits 1 | — |
| Include run by CI loop | `tests/harness/lib/*.php` | Never executed by the loop (subfolder); run by hand it defines and exits 0 | — |
| Sanitizer | `' <b>x</b> '` through `sanitize_text_field` in any harness | `'x'` (trim + strip tags, WordPress-like) everywhere | HALT rule above if a check depended on the cast |
| Options store | `update_option` then `get_option` in any harness | One global `$GLOBALS['tw']['options']`; a harness seeds defaults into it instead of branching in the stub | — |
| Local override | A harness declares `get_option` before the require | The harness's version is used, the bootstrap's skipped, no redeclare fatal | — |
| Hoisted mid-file stubs | caps and setup declare stubs after the require (PHP hoists them) | Still no fatal: the guard sees them | — |

</frozen-after-approval>
## Code Map
- `tests/harness/lib/wp-stubs.php` (new) -- the bootstrap: `error_reporting`, `require __DIR__ . '/../fs-stub.php'`, the runner (`$pass`/`$fail`, `check($label, $cond)` printing `  ok  ` / `  FAIL `, `harness_exit()` printing `"\n$pass passed, $fail failed\n"` and `exit($fail === 0 ? 0 : 1)`), the classes `WP_Error`, `WP_REST_Server` (READABLE, CREATABLE, EDITABLE, DELETABLE), `WP_REST_Request`, `WP_REST_Response`, `WP_Role` (empty shell), and the stubs whose bodies agree or where one WordPress-faithful form exists: `__`, `_x`, `esc_html__`, `esc_attr__`, `_e`, `esc_html_e`, `esc_attr_e`, `esc_html`, `esc_attr`, `esc_url`, `esc_url_raw` (trim), `sanitize_text_field` and `sanitize_textarea_field` (trim + strip_tags), `sanitize_key` (lowercase + `[^a-z0-9_-]` stripped), `absint`, `wp_parse_url`, `wp_parse_args`, `maybe_serialize`/`maybe_unserialize`, `is_wp_error`, `wp_remote_retrieve_response_code`, `wp_remote_retrieve_body` (`?? ''`), `wp_json_encode` (flags passed), `add_action`, `add_filter` (no-op), `do_action`, `register_activation_hook`/`register_deactivation_hook`, `wp_kses`, `wp_generate_uuid4` (`$GLOBALS['tw']['uuid'] ?? 'stub-uuid'`), `trailingslashit`/`untrailingslashit`, and the options store `get_option`/`update_option`/`add_option`/`delete_option` on `$GLOBALS['tw']['options']` (`update_option` records `tw.autoload[k]` and appends to `tw.writes`; `delete_option` unsets and appends `-k`). `error_reporting(E_ALL)`; if a plugin-loading harness then emits a deprecation on PHP 8.3, use `E_ALL & ~E_DEPRECATED` for all and name the deprecation in Implementation Notes.
- Current stub matrix (recon 2026-10-21, keep local = stays in the harness, declared before the require): `apply_filters` pro switch (caps, license, cron-gate, insert-block, render-matrix, sanitize-matrix; n8n-api reads `tw.filters`); `current_user_can` (all variants); `wp_get_current_user`, `get_current_user_id`; `wp_remote_post`, `wp_remote_request`, `wp_remote_get`; `home_url`, `admin_url`, `rest_url`, `plugin_dir_url`, `is_admin`; `wp_create_nonce`, `wp_nonce_url`, `add_query_arg`; `current_time`, `wp_date`, `wp_timezone`, `get_date_from_gmt`, `human_time_diff`; `get_locale`/`switch_to_locale`/`restore_previous_locale` (voice keeps a stack); n8n-api's `add_option` (`race('on_claim')`), its `$wpdb`, and `update_option` byte-identity check; insert-block's `htmlspecialchars` escapers; caps' full `WP_Role`/`WP_User`; the plugin-function stubs (`pqm_get_settings`, `pqm_n8n_headers`, `pqm_n8n_payload`, `PQM_DB`) in caps, cron-gate, license, quota.
- Option-store migrations: `render-matrix-harness.php` :29 (`tw_options` + `tw_settings` merged into `pqm_settings`), `sanitize-matrix-harness.php` :34 and `voice-harness.php` :49 (`pqm_test_options`), `quota-harness.php` :37 (`date_format` only), `rest-posts` :54 / `rest-reviews` :34 (defaults), `insert-block` :46 (`tw.settings`) -- seed `tw.options` at setup where a default is static; keep a local `get_option` only where the harness mutates a second global the checks read (render-matrix's `tw_settings`), and list each kept override in Implementation Notes.
- Runner drift to normalise: `FAIL  ` label without indent (free-build :28, sanitize-matrix :118, voice :68); `exit($fail ? 1 : 0)` / `exit($fail > 0 ? 1 : 0)`; the `──────` rule line before the summary (sanitize-matrix, voice); `$pass`/`$fail` on two lines. Every harness ends with `harness_exit();`.
- `free-build-harness.php` -- defines its stubs at :182 after the build steps and loads the stripped tree in-process: require the bootstrap at the same point, keep its route-keyed `register_rest_route`.
- `release-header-harness.php`, `template-failure-harness.php`, `template-guard-harness.php` -- load no plugin code: they take the runner only (the bootstrap's stubs are harmless there).
- `.github/workflows/ci.yml` :157 harness loop, `CONTRIBUTING.md` :21, `AGENTS.md` :34 -- loop unchanged; add one sentence that shared includes live in `tests/harness/lib/` and are never run alone (AGENTS.md hand edit inside the managed block, noted in its "Verified" comment).

## Tasks & Acceptance
**Execution:**
- [ ] `tests/harness/lib/wp-stubs.php` -- write the bootstrap as the Code Map lists it, every declaration guarded -- one world
- [ ] each of the 16 `tests/harness/*-harness.php` -- `require __DIR__ . '/lib/wp-stubs.php'` after the harness-specific stubs; delete the stubs and runner the bootstrap now provides; migrate the option-store global; end with `harness_exit()` -- declare only what is specific
- [ ] `AGENTS.md`, `CONTRIBUTING.md` -- name `tests/harness/lib/` -- the loop's reader knows the folder
- [ ] `memory/` -- one line in the harness-related memory file (grep `memory/` for "harness") that story 78 build 1 landed the bootstrap and what stays local -- durable fact

**Acceptance Criteria:**
- Given the sweep runs locally, when every harness ran, then each prints the same pass count as the baseline table and 0 failed, total 2,124
- Given `grep -ln "function sanitize_text_field\|function get_option\|function check(" tests/harness/*.php`, when the sweep is done, then only the harnesses with a listed local override match, and the Implementation Notes list them with the reason
- Given a harness is run alone from any working directory, when it loads, then the bootstrap resolves through `__DIR__`

## Implementation Notes
Agent-owned. Append-only.

**Build 1 implemented 2026-10-21 (implementer subagent at the session model, Fable 5.1; branch `chore/harness-bootstrap-and-split`, nothing committed).**

- **Counts, before → after (every harness run before the first edit and after the last):** caps 284 → 284, cron-gate 35 → 35, free-build 83 → 83, insert-block 66 → 66, license 27 → 27, n8n-api 581 → 581, quota 71 → 71, release-header 31 → 31, render-matrix 409 → 409, rest-posts 165 → 165, rest-reviews 65 → 65, sanitize-matrix 226 → 226, setup 141 → 141, template-failure 55 → 55, template-guard 62 → 62, voice 23 → 23; 0 failed everywhere, exit 0. The per-harness numbers of the Boundaries table are exact; their sum is **2,324**, not the 2,124 the table states (an arithmetic slip in the plan, not a count change). `tests/harness/lib/wp-stubs.php` alone: no output, exit 0. `voice-harness.php` run from `/tmp`: 23 passed. `php -l` clean on the 17 PHP files touched. `grep -c "harness_exit()"`: 1 per harness. Net: 17 files, −598/+92 lines in the harnesses plus the 132-line bootstrap.
- **HALT rule:** never triggered. The cast sanitizers (quota, rest-posts, rest-reviews, render-matrix, setup, free-build), the `strtolower`-only `sanitize_key` (render-matrix, free-build), the cast `esc_url_raw` (render-matrix, free-build) and the `?`-only option defaults were all replaced by the bootstrap's WordPress-like forms and every count held — none was load-bearing.
- **`error_reporting(E_ALL)` for all:** the five harnesses that ran under `E_ALL & ~E_DEPRECATED` (rest-posts, rest-reviews, render-matrix, setup, n8n-api) run clean under `E_ALL` on PHP 8.3; no deprecation surfaced, so the fallback in the Code Map was not needed.
- **Local overrides kept (declared before the require, or hoisted), with the reason:**
  - `get_option` in `render-matrix-harness.php` (with its `update_option`/`delete_option` on the same `tw_options` global): the rows mutate two globals the checks read, `tw_settings` merged over the stored `pqm_settings` and `tw_options` for the other options (the plan's named case).
  - `get_option` in `rest-posts-harness.php` and `rest-reviews-harness.php`: the rows replace `$GLOBALS['tw']['options']` wholesale (5 and 9 times) and still expect the site's `date_format`, `time_format` and an empty `pqm_settings`; seeding at setup would be wiped by the first replacement, and rewriting those assignments is a check-site change. Their `update_option`/`delete_option` were dropped (the bootstrap's write the same store).
  - `WP_Error` in `insert-block-harness.php`: its checks read `->error_data` (WordPress's property name); the bootstrap's class exposes `->data`. Its `esc_html`/`esc_attr` (htmlspecialchars) stay as the plan says.
  - `WP_REST_Request` in `free-build-harness.php`: its rows build a JSON-body request from one array (`get_json_params()` and `get_param()` read the same array); the bootstrap's constructor takes `(array $params, $json)` and would answer `get_json_params()` with null.
  - `delete_option` hoisted in `caps-harness.php` (records `tw_option_deletes`, never unsets) and in `setup-harness.php` (records `tw_deleted`): the uninstall checks read those lists in order.
  - `wp_cache_get`/`wp_cache_set` in n8n-api (a real cache) and free-build (always a miss): not in the bootstrap's list, different meanings.
  - Everything the plan names as local (the pro switch, `current_user_can`, users, `wp_remote_*`, dates, URLs, nonces, the plugin-function stubs, n8n-api's `add_option` race hook and `$wpdb`, voice's locale stack and locale-tagging `__`) stayed untouched.
- **Option-store migrations done:** sanitize-matrix and voice (`pqm_test_options` → `tw.options`, 3 assignment sites), quota (`'options' => ['date_format' => 'F j, Y']` seeded in the world, `reset_world()` leaves it), insert-block (`tw.settings` → `tw.options['pqm_settings']`, seeded `[]`, 5 assignment sites), caps (its counting `update_option` dropped; the three "writes" checks now count `tw.writes`, the global the bootstrap records: `count($GLOBALS['tw']['writes'] ?? []) === 1|0`, and the two resets set `tw.writes = []` — `reset_world()` replaces `$GLOBALS['tw']` wholesale, hence the `?? []`).
- **Deviation, free-build:** the bootstrap is required at the top of the file (after `ABSPATH`), not at the stub block (:182) the Code Map names: the harness runs ~80 checks before that point, and the bootstrap's `$pass = 0; $fail = 0;` would have reset them. The stubs at the block are hoisted, so the order is equivalent for them; its own `require fs-stub.php` (:309) went, the bootstrap's `require_once` already loaded it. The bootstrap uses `require_once` for `fs-stub.php` so a harness that still required it first would not redeclare `pqm_fs()`.
- **Code Map additions:** `plugin_dir_path` (same body in all 13 harnesses that had it) joined the bootstrap; `wp_remote_retrieve_response_code` answers `?? ''` like core; `add_option` writes through `update_option` (records `tw.autoload`/`tw.writes`). `WP_REST_Request` in the bootstrap carries `set_param()` (quota) and `get_json_params()` (setup); `WP_REST_Response` carries `header()` (the CORS header of the webhook callback).
- **Docs:** `AGENTS.md` :34 (inside the managed block, noted in the Verified comment), `CONTRIBUTING.md` :17, `memory/delivery-pipeline.md` (harness bootstrap paragraph). CI's loop unchanged.
- **Workflow deviation:** implemented by a subagent from the plan alone (the skill's own dispatches replaced, per the workspace rule); no commit, push or remote call.
- Correction to the first bullet: the bootstrap is 143 lines (`wc -l`), not 132. Final sweep after the doc edits: 2,324 passed, 0 failed, every harness exit 0.
- Review fixes (2026-10-21): the local `get_option` overrides in rest-posts and rest-reviews are gone — each harness seeds the three site defaults through `site_options(array $extra = []): array`, used in the initial `$GLOBALS['tw']` literal and at every `tw.options` replacement (the bootstrap's `get_option` reads the store). The kept-override list is therefore: render-matrix's `get_option`/`update_option`/`delete_option` plus a local `add_option` (added: the bootstrap's would have tested `tw.options` and written `tw_options`), insert-block's `WP_Error`, free-build's `WP_REST_Request`, the hoisted `delete_option` of caps and setup, `wp_cache_*` in n8n-api and free-build. The bootstrap header now says a harness whose checks start before its stub block (free-build) requires it at the top and relies on hoisting. Counts after the fixes: rest-posts 165, rest-reviews 65, render-matrix 409.

- 2026-10-21 (session, step 3 diff read + matrix audit): diff staged and read in full (22 files, +340/-601). Matrix rows covered by the sweep itself: every harness alone (16 × same count, exit 0), the include run alone (exit 0, silent), the sanitizer (sanitize-matrix, voice and n8n-api already used the WP-like form; the nine cast harnesses pass unchanged under it, so no check depended on the cast), the options store (migrated harnesses pass), local overrides (render-matrix, rest-posts, rest-reviews, insert-block, free-build run), hoisted stubs (caps, setup run). The frozen block's "2,124" total is the session's arithmetic slip; the per-harness figures sum to 2,324 before and after; the frozen text is left for the owner. Deviation accepted: free-build requires the bootstrap at the top (the runner must exist before its first check).

## Plan Change Log
Append-only.

## Review Triage Log
Append-only.

- 2026-10-21, review pass 1 (lens: quick, pinned; 4 findings): verdicts 0 high / 0 medium / 3 low / 0 false / 0 maybe-false, plus 1 rejected. Route: 3 patch, 0 defer, no loopback.
  1. Frozen block (Boundaries, AC1) states "total 2,124" while its per-harness figures sum to 2,324 — verified (both sweeps print 2,324). **Rejected** by the rule that a finding whose fix edits this build's plan is not patched; the owner corrects the frozen text or accepts the Implementation Notes' record. There is one reading of the intent (counts equal, per harness), so no intent gap.
  2. `rest-posts-harness.php` :47-50 and `rest-reviews-harness.php` :27-30 keep a local `get_option` with the site defaults branched inside, the pattern the matrix row forbids; the stated reason concerns fixture assignments, where a seed is available — **low** (developer-only: the drift the story removes survives in two harnesses), verified. Route **patch**: seed the defaults at the fixture sites through one tiny helper per harness, drop the overrides.
  3. `render-matrix-harness.php` keeps its option stubs on `tw_options` but inherits the bootstrap's `add_option` on `tw.options` (mixed store, latent: no caller reaches `adopt()` there) — **low**, verified. Route **patch**: a local `add_option` on `tw_options`.
  4. `lib/wp-stubs.php` header says every harness requires it after its stubs; free-build requires it first (hoisting) — **low**, cosmetic, verified. Route **patch**: one clause.

## Design Notes
(D1) Guards instead of ordering: the bootstrap never wins over a harness; the harness's local stub is the override by being declared first (or hoisted). No `function_exists` wrappers are needed in the harnesses themselves. (D2) The include sits in a subfolder because CI's loop runs every file at the folder's top level; `fs-stub.php` stays where it is and keeps exiting 0 alone. (D3) The sanitizer normalises to the WordPress-like form because the three harnesses that test sanitizing already use it; a check that only passed under the cast is a finding for the owner, not a thing to paper over (HALT rule). (D4) `n8n-api-harness.php` is touched minimally: its HTTP stub, `$wpdb`, `add_option` race hook and world builders stay in place for build 2 to move. Skipped: a shared `apply_filters` with a pro flag — six harnesses switch pro three different ways, and unifying them is a semantic change outside "counts equal". Checkpoint 1 (owner, 2026-10-21): approved and continued, full plan kept above the 1,600-token guideline for the stub inventory. Workflow deviation: run from the workspace root with the skill rendered by hand; the branch `chore/harness-bootstrap-and-split` was created under the owner's go-ahead of 2026-10-21; the ticket's two goals were split at step 1 on the owner's decision (deferred-work entry).

## Verification
**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 16 harnesses, the baseline counts above, 0 failed; `php tests/harness/lib/wp-stubs.php` alone exits 0 with no output
- `php -l` on every touched PHP file -- expected: clean
- `grep -c "harness_exit()" tests/harness/*-harness.php` -- expected: 1 per harness
- `(cd /tmp && php "C:/Git/Post Quality/post-quality-manager/tests/harness/voice-harness.php")` -- expected: runs from another directory
