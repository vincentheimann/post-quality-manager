---
title: "The provisioning lock is claimed atomically at the database (bug 82)"
type: 'bugfix'
ticket: '82'
created: '2026-10-20'
status: done
baseline_revision: '57766d60d67429ad58bd94b1b498f77d9dc5e51d'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/memory/n8n-provisioning.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `PQM_N8n::claim()` (bug 72) claims the provisioning lock with `add_option()`, which WordPress core runs as a read followed by `INSERT … ON DUPLICATE KEY UPDATE`; two requests inside that gap both get `true`, as the 2026-10-20 walk observed, and the stale-lock takeover (read `at`, then `update_option()`) has the same shape. The second simultaneous run then creates a full set of objects before n8n's path conflict stops it at activation, and the user reads bug 75's sentence for a race.

**Approach:** keep the lock option, its `{at}` value, its five-minute ceiling and its release; make the two writes atomic at the database through `$wpdb`: the claim is a plain insert that fails on the duplicate key (`INSERT IGNORE`, affected rows read), the takeover a conditional update that replaces only the exact stale value it read (compare-and-set, affected rows read). After each raw write the Options API cache is brought in line the way core does after its own insert. The n8n harness's options stand-in is made to behave like core (read, a gap a racing claim can land in, then an upsert) and its `$wpdb` stand-in serializes the lock's two statements, so a row fails on the old claim and passes on the new.

**Decisions taken at planning (owner to confirm at the checkpoint):**
- D1: `INSERT IGNORE` + a compare-and-set `UPDATE`, not MySQL `GET_LOCK()`: the option lock keeps its record, its ceiling and the rows, uninstall deletion and page reads built around it; `GET_LOCK` would bind the lock to a connection and remove the ceiling, a larger change for the same guarantee.
- D2: the autoload column is written as `'off'` (WordPress 6.6 is the plugin's floor; `wp_autoload_values_to_autoload()` governs reads), matching `add_option( …, false )`.

## Boundaries & Constraints

**Always:** the lock stays option `pqm_n8n_provisioning` with the value `{at}`; `LOCK_TTL` stays 300; the release stays `delete_option()`; every exit of `provision()` leaves the lock as bug 72 built it (gone after a finished or failed run, untouched after a refused one); raw queries go through `$wpdb->prepare()` and carry the `phpcs:ignore WordPress.DB.DirectDatabaseQuery` note the activator uses; the harness keeps `untouched()`/`lock_free()` honest.

**Never:** a second lock option, a transient, a REST or template change; `GET_LOCK()`; changes to `adopt()`'s `add_option()` (its race is pre-existing and out of this ticket); a stub that idealises the claim.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| claim, free | no lock row | `INSERT IGNORE` affects 1 row → the run proceeds; lock `{at: now}` stored with autoload off; option cache set, `notoptions` without the key | — |
| claim, held fresh | lock `at` within 300 s | insert affects 0; the read `at` is fresh → 409 `pqm_already_provisioned`, no write, zero outgoing calls | — |
| claim, held stale | lock `at` older than 300 s | insert 0; conditional update of the exact stored value affects 1 → the run proceeds with `{at: now}` | — |
| two claims, same instant | A's insert reaches the database as B's does (harness hook at the claim statement) | the statement serialized first wins; the other's insert affects 0, reads a fresh `at`, answers 409 with zero outgoing calls; one map, one set of objects, lock gone at the end | — |
| two takeovers, stale lock | both read the same stale value, both update | one update affects 1 and proceeds; the other affects 0 → 409, nothing written | — |
| takeover lost to a release | stale value read, the row deleted meanwhile | update affects 0 → 409 (the next request claims the free row) | — |
| database refuses | `$wpdb->query()` returns `false` on the insert | treated as not claimed → 409, nothing created (loud, no run without a lock) | `pqm_already_provisioned` |
| old behaviour, old code | the harness's core-like `add_option()` with a racing claim in its gap | the two-claims row fails on bug 72's `claim()` and passes on this one (the fix is proven, not assumed) | — |
| everything else | all existing provisioning, rollback, lock and leak-scan rows | unchanged and passing | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `LOCK_OPTION`/`LOCK_TTL` L75–89; `provision()` L728–734 (`claim()` → `run()` → `delete_option()`); `claim()` L736–759: replace its `add_option()` and `update_option()` with the two `$wpdb` statements; keep the docblock's honest tone, now saying the claim is atomic and the ceiling line. Add a small private cache helper (set the option cache to the new serialized value, drop the key from `notoptions`), modelled on core `add_option()`'s tail (`wp-includes/option.php`, after its INSERT). Use `maybe_serialize()` for the stored value and the compare-and-set `WHERE option_value = %s`. `global $wpdb` is used the same way in `class-pqm-activator.php` L38–41.
- `tests/harness/n8n-api-harness.php` -- `add_option` stub L84–90 (make it core-like: read; fire `$GLOBALS['tw']['on_claim']` if set; upsert, `true` unless the stored value is byte-identical); `$wpdb` stub L163–166 (add `prepare()`, `query()`, `rows_affected`; `query()` serializes the lock's two statements against `$GLOBALS['tw']['options']`, fires `on_claim` before applying the insert, records writes as `update_option()` does so `untouched()`/`lock_free()` and the "writes[0] === LOCK_OPTION" assertions hold); add `maybe_serialize()`/`maybe_unserialize()` and `wp_cache_*` stubs if missing. Rows: bug 72 section L784–818 (two-runs row with the closure on the first credential POST — keep; add the hook-driven same-instant row, the two-takeovers row, the lost-takeover row, the `query()`-returns-false row); `connected()` L540–550 resets `on_claim`.
- `tests/harness/setup-harness.php` L138–151, `caps-harness.php` L440–465, `free-build-harness.php` -- only assert the lock option is deleted on uninstall; unchanged unless their `$wpdb` stubs need `prepare()`/`query()` for a code path they load (they call `uninstall()` only: no).
- `memory/n8n-provisioning.md` -- the add_option finding line (2026-10-20): append "fixed by bug 82: INSERT IGNORE + compare-and-set UPDATE".

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `claim()`: `INSERT IGNORE` through `$wpdb->prepare()`, `1 === (int) $wpdb->query(...)` wins; else read, stale → `UPDATE … WHERE option_name = %s AND option_value = %s`, `1 === rows` wins; cache helper after each successful raw write; `false` from `query()` = not claimed -- the atomic claim
- [x] `tests/harness/n8n-api-harness.php` -- core-like `add_option`, `$wpdb` `prepare()`/`query()` for the two statements with the `on_claim` hook, serialization stubs; the matrix rows; `connected()` resets the hook -- proves the fix and keeps the race modelled
- [x] `memory/n8n-provisioning.md` -- one clause on the finding line -- DoD

**Acceptance Criteria:**
- Given a connected site without a map or lock, when two provisioning requests reach the claim at the same instant, then exactly one proceeds and the other answers 409 with zero outgoing calls.
- Given bug 72's `claim()` (temporarily restored) and the new harness, when the n8n harness runs, then the same-instant row fails; with this build it passes.
- Given a finished, failed or refused run, when the lock is read, then it is in the state bug 72 defined.

## Implementation Notes

- 2026-10-20, planning: the session runs from the workspace root, so the `bmad-build` skill was rendered by hand (route `full`) and its steps followed in order; the branch `fix/n8n-provisioning-atomic-claim` was derived from the intent and created from `main` at the baseline (the owner away, rename at the checkpoint if wanted). The bug 82 ticket file is an identical copy of the one in PR #154 (the chore), so the tree resolves here without stacking.
- 2026-10-20, build (implementer subagent, the `bmad-build` skill rendered by hand from the workspace root; branch `fix/n8n-provisioning-atomic-claim`, nothing committed): `claim()` rewritten as planned — `INSERT IGNORE INTO {$wpdb->options} … VALUES (%s, %s, 'off')` through `$wpdb->prepare()`, `1 === (int) rows` wins; `false` from `query()` returns false at once (no run without a lock); else `get_option()`, a non-array (released between the insert and the read) or a fresh `at` returns false, a stale one goes to `UPDATE … SET option_value = %s WHERE option_name = %s AND option_value = %s` with `maybe_serialize( $held )`, `1 === (int) rows` wins. New private `cache_lock( string $serialized )` after each successful raw write: `wp_cache_set( LOCK_OPTION, …, 'options' )` and the key dropped from `notoptions` (core `add_option()`'s tail); both raw queries carry the activator's `phpcs:ignore WordPress.DB.DirectDatabaseQuery` note. Docblock rewritten (atomic claim, compare-and-set, the ceiling line kept). `LOCK_OPTION`, `LOCK_TTL`, `provision()`, `run()`'s re-read and the `delete_option()` release untouched.
- Harness (`tests/harness/n8n-api-harness.php`): `add_option()` is core-like (read; `race('on_claim')` in the gap; upsert, `true` unless byte-identical); `race()` fires a `$GLOBALS['tw']` hook once; `maybe_serialize()`/`maybe_unserialize()`/`wp_cache_get()`/`wp_cache_set()` stubs (the cache under `tw.cache`); the `$wpdb` stub gained `options`, `rows_affected`, `prepare()` (statement + arguments) and `query()`, which serializes the two lock statements against `tw.options`, records writes and the autoload column (`'off'`) as `update_option()` does, answers `false` under `tw.db_refuses`, and fires `on_claim` before the insert lands and `on_takeover` before the update (a second hook, not in the plan: the takeover race must land between the read and the UPDATE, after the insert already affected 0). `connected()` resets the two hooks, `db_refuses` and the cache. The fresh-run lock assertion now expects autoload `'off'`. Rows added (7): two claims in the same instant, claim-free cache, claim-stale cache, two takeovers, takeover lost to a release (+ the next request claims the free row), the database refuses.
- Modelling decision for the same-instant row: PHP runs one request at a time and cannot resume a suspended run, so B's claim is planted as its INSERT statement through the stub inside A's gap (B's run in flight = its lock row); the row asserts A's side (409, zero calls, nothing written by A, B's `{at}` intact) and leaves "one map, one set of objects, lock gone at the end" to the existing two-runs row (B's remainder). Running B's full `provision()` from the hook instead would pass on bug 72's code too (B finishes and releases before A's upsert; A's `run()` re-read then answers 409), so it proves nothing. B's planted `at` is `time() - 1`: the first proof run passed on bug 72's `claim()` by coincidence because an identical `{at}` in the same second made the core-like upsert affect 0 rows and refuse A — recorded here as the reason for the offset.
- Deviation from the Code Map: `tests/harness/free-build-harness.php` does not call `uninstall()` only — its Free provisioning rows run `provision_n8n_callback()`, so its `$wpdb` stub needed `options`, `rows_affected`, `prepare()` and a `query()` applying the two statements (no hooks, no recording), plus `maybe_serialize`/`maybe_unserialize`/`wp_cache_*` stubs. `setup-harness.php` and `caps-harness.php` were untouched as planned. `memory/n8n-provisioning.md`: the clause appended to the 2026-10-20 finding line.
- Verification: `php -l` clean on `class-pqm-n8n.php` and both harnesses. Harness loop, all 16 green: caps 284, cron-gate 35, free-build 83, insert-block 66, license 27, **n8n-api 480 (was 473)**, quota 71, release-header 31, render-matrix 401, rest-posts 165, rest-reviews 65, sanitize-matrix 226, setup 139, template-failure 55, template-guard 62, voice 23 — each `0 failed`. `npm run test:unit` 8 suites / 124 tests passed; `npm run lint:js` clean; `npm run build` compiled successfully (no JS touched).
- AC2 proof (bug 72's `claim()` body swapped in by script, the new docblock and harness kept; then restored from a copy and re-run): on bug 72's `claim()` the n8n harness exits 1 with `472 passed, 8 failed`, among them `FAIL A: 409 pqm_already_provisioned, zero n8n calls, nothing written by A, no ping, no Application Password; B's lock {at} held as B wrote it` (the same-instant row), `FAIL A's update affects 0 rows: 409 pqm_already_provisioned, zero calls, nothing written by A; the lock B's` (two takeovers), `FAIL the stale value read, the row gone: the update affects 0 -> 409 …` and `FAIL query() false on the insert: not claimed -> 409 …`, plus the autoload/cache assertions (`autoload off` now the column value `'off'`). With bug 82's `claim()` restored: `480 passed, 0 failed`, exit 0, the same rows `ok`.
- Open for the checkpoint: D1/D2 as planned; the manual check (two `POST /n8n/provision` in the same millisecond on the Local sandbox, `wp option get pqm_n8n_provisioning` → not found) is not run here. Risk noted: a persistent object cache that still holds the lock in `notoptions` or a stale value makes a run lose (409), never win twice — as the Design Notes accept; the next request claims the free row.
- 2026-10-20, review fix: `claim()` now returns false only on `false === $held` (released meanwhile); any other non-fresh value, a scalar included, reaches the compare-and-set, so a hand-edited or corrupted lock cannot answer 409 for ever against the ceiling. Harness row added: a stored scalar lock value is taken over and the run proceeds (n8n-api 481). The memory line's parenthesis names the new mechanism, the appended clause carries the add_option history.

- 2026-10-20, agentic walk on the owner's Local sandbox (the branch's `class-pqm-n8n.php` copied over the installed main 57766d6 build, since `npm run zip` packages HEAD; REST calls with a temporary Application Password, removed after): the reproduction of bug 82 run three times — two `POST /n8n/provision` with a bogus key from processes started in the same millisecond — answered each time one 400 `pqm_provider_key_invalid` (the run that reached Anthropic) and one 409 `pqm_already_provisioned`, the lock gone afterwards; on bug 72's code the same test had let both through. A lock planted 400 s old was taken over by the compare-and-set and the run proceeded, lock gone after. The plan's manual check is met. The sandbox keeps that copied file until the next install; nothing was created in n8n (the key was refused before any outgoing call).

## Plan Change Log

## Review Triage Log

**Pass 1, 2026-10-20 (quick lens, Fable 5.1): 2 findings — high 0, medium 0, low 2, false 0, maybe-false 0.** The lens reproduced AC2 itself (bug 72's body spliced back: 472/8; this body: 480/0) and found no unmet criterion.

- `class-pqm-n8n.php` `claim()`: `! is_array( $held )` refuses a stored non-array lock value for ever (the insert affects 0 each time, the takeover is never tried), against the ceiling bug 72 promised — **low** (reachable only by a row this code did not write: a hand-edited or corrupted option), **patch**: test `false === $held` for the released case only; every other non-fresh value reaches the compare-and-set, which replaces the exact stored string once; one harness row added.
- `memory/n8n-provisioning.md` L21: the parenthesis still names `add_option` as the claim while the appended clause names INSERT IGNORE — **low** (developer-only: memory records what is true now, a reader learns the retired mechanism), **patch**: the parenthesis carries the new mechanism, the clause the history.

Patches applied by the step-03 implementer (re-engaged); full verification re-run by the coordinator: 16 harnesses 0 failed (n8n-api 481, one row added for the scalar lock value), Jest 124, lint, build. Nothing deferred. No commit (house rule): the owner commits.

## Design Notes

- `INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')`: MySQL serializes the two inserts; the duplicate key fails the second silently and `query()` returns 0 rows. `INSERT IGNORE` also ignores other errors, which for a 30-byte value cannot occur; a plain INSERT with `suppress_errors()` would be the alternative, not chosen (a logged duplicate-key error on every refused race).
- Compare-and-set: `UPDATE … SET option_value = %s WHERE option_name = %s AND option_value = %s` with the exact serialized stale value read; a stale object cache can only make the takeover lose (0 rows → refused), never win twice.
- Cache after a raw write (core `add_option()`'s tail): `wp_cache_set( LOCK_OPTION, $serialized, 'options' )`; `$notoptions = wp_cache_get( 'notoptions', 'options' )`, unset the key, `wp_cache_set( 'notoptions', … )`. Autoload off, so `alloptions` is not touched.
- Harness contract (stub-internal): `prepare()` may return the statement with its arguments attached; `query()` recognises the two statements by prefix, nothing more general. The `on_claim` hook runs once (unset before it runs) so the racing run's own claim does not recurse.
- Skipped: `GET_LOCK()` (D1); a retry of the claim after a lost takeover — the next request claims the free row.

## Verification

**Commands:**
- `php -l post-quality-manager/includes/class-pqm-n8n.php` -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed, n8n-api up by the new rows
- `git stash`-free proof of AC2: with `claim()` temporarily reverted to bug 72's body, `php tests/harness/n8n-api-harness.php` reports the same-instant row failing; restore and re-run green (record both outputs in Implementation Notes)
- `npm run test:unit && npm run lint:js && npm run build` -- expected: green, unchanged (no JS touched)

**Manual checks (if no CLI):**
- Owner or agent, Local sandbox: two `POST /n8n/provision` with a bogus key in the same millisecond → one 400 from Anthropic, one 409 at once; `wp option get pqm_n8n_provisioning` afterwards: not found.
