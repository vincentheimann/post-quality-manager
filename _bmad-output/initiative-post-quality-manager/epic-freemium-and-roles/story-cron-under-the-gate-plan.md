---
title: 'Cron under the gate'
type: 'feature'
ticket: '6'
created: '2026-10-02'
status: 'done'
baseline_revision: 'fb97112ef540e3709f9448667bf8ae0f8d39dc81'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `PQM_Cron::run_scheduled_review()` posts whatever `cron_command` the settings hold straight to n8n — it never consults the feature gate (spec CAP-1, epic Done-when 1). Today the sanitizer pins the setting to `review`, but a stored gated command must never fire on a free site: story 3 (licensing) will flip existing installs to free with their cron settings intact, and backlog story 3 will open the command choice to `seo`/`audit`.

**Approach:** Give the gate a site-level half — `PQM_Features::plan_allows( $command )` (free tier or `is_pro()`; no user half, cron runs with no user per ADR 0003) — and have the cron batch consult it once, skipping the batch before any DB query or webhook call when the stored command is plan-gated. Quota neutrality (CAP-4) already holds by construction — cron never passes `trigger_review_callback()` — and gets locked in by a harness.

## Boundaries & Constraints

**Always:** `plan_allows()` lives in `PQM_Features` beside the registry and is the single source for the plan decision — `why_not()`'s plan branch is refactored to call it, so licensing (story 3) keeps touching only `is_pro()`. Unknown commands keep the existing fallback semantics (treated as free tier, like `why_not()`). The skip is silent and early, matching the existing missing-webhook return. A cron batch must never read or write `pqm_monthly_usage`. KISS, `PQM_` prefix, WP core APIs.

**Never:** No change to `cron_command` sanitization or the Settings UI (the disabled select stays — backlog story 3), to `trigger_review_callback()` or any manual-trigger path, to the capability map / `CAPS_VERSION`, to the n8n template or payload, to the schedule/clear logic. No per-user logic in cron, no logging/notification framework for skipped batches, no new settings.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Free command on free site | `cron_command=review`, `pqm_is_pro` false | Webhook fires for each batch post | No error expected |
| Gated command on free site | stored `cron_command=seo`, free | Batch skipped before the DB query; zero webhook calls | Silent return |
| Gated command on pro site | `cron_command=seo`, `pqm_is_pro` true | Webhook fires for each batch post | No error expected |
| Non-registry command | stored `cron_command=score`, free | Runs (free-tier fallback, mirrors `why_not()`) | No error expected |
| Quota neutrality | Any batch; users hold usage meta | `pqm_monthly_usage` identical before/after | No error expected |
| No webhook URL | `n8n_webhook_url` empty | Early return, unchanged behavior | Silent return (existing) |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-features.php` -- add `plan_allows( string $command ): bool` beside `is_pro()` :92 (registry lookup with the free fallback of `why_not()` :104, true when tier free or `is_pro()`); refactor `why_not()` :110 plan branch to `! self::plan_allows( $command )`. Registry :76, caps, quota section untouched.
- `post-quality-manager/includes/class-pqm-cron.php` -- `run_scheduled_review()` :58: after resolving `$command` :68, `if ( ! PQM_Features::plan_allows( $command ) ) { return; }` before `PQM_DB::get_posts_needing_review()` :70, with a comment naming the license-lapse case. `trigger_single_review()` :80, schedule/clear untouched.
- `tests/harness/cron-gate-harness.php` -- new, patterned on `quota-harness.php` (mutable `$GLOBALS['tw']` world, WP stubs): stub `PQM_DB::get_posts_needing_review()`, `wp_remote_post` counter, `apply_filters` honoring a `pqm_is_pro` toggle; require `class-pqm-features.php` + `class-pqm-cron.php`; assert every matrix row.
- `memory/freemium-and-roles.md` -- gate mechanics bullet: cron is gated site-level through `plan_allows()`.
- Don't touch: `class-pqm-rest-api.php`, `admin/class-pqm-admin.php`, `templates/n8n-workflow.json`, `post-quality-manager.php` defaults.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-features.php` -- `plan_allows()` + `why_not()` refactor -- one source for the plan decision (CAP-1).
- [x] `post-quality-manager/includes/class-pqm-cron.php` -- batch-level skip before the DB query -- gated commands never reach n8n from a free site.
- [x] `tests/harness/cron-gate-harness.php` -- exercise all six matrix rows -- locks in skip + quota neutrality (CAP-4).
- [x] `memory/freemium-and-roles.md` -- record the site-level cron gate -- DoD.

**Acceptance Criteria:**
- Given a free site whose stored `cron_command` is a paid-tier command, when the cron batch runs, then no webhook call is made and no post is queried.
- Given the `pqm_is_pro` filter returns true, when the same batch runs, then every batch post is posted to n8n exactly as before this story.
- Given users with quota usage, when any cron batch runs, then every user's `pqm_monthly_usage` meta is byte-identical afterwards.
- Given `why_not()` callers (REST, AJAX, settings sections), when this story lands, then their hidden/locked behavior is unchanged (pure refactor).

## Implementation Notes

- 2026-10-02 — Implemented exactly per Code Map. `plan_allows()` sits directly after `is_pro()`; its free fallback uses `?? [ 'tier' => 'free' ]` (capability key irrelevant to the plan half). `why_not()`'s plan branch now reads `if ( ! self::plan_allows( $command ) )` — pure refactor, registry/caps/quota untouched. Cron skip is a silent `return` between command resolution and `PQM_DB::get_posts_needing_review()`, with a comment naming the license-lapse case. Load order verified: `post-quality-manager.php` requires features (:33) before cron (:35).
- Harness `tests/harness/cron-gate-harness.php` covers all six matrix rows plus extras: every paid registry command (`rewrite`/`sources`/`seo`/`audit`) skips on free, quota neutrality asserted byte-identical (serialize) across free-run, skipped and pro-run batches, static check that cron source never names quota helpers, and a `why_not()` matrix (role-before-plan, free fallback, pro) locking the refactor's purity.
- Verification: `php -l` clean on all three touched PHP files; full harness sweep green — cron-gate 24/24, quota 32/32, render-matrix 38/38, sanitize-matrix 74/74.
- `memory/freemium-and-roles.md`: the Gate bullet's stale "cron is outside the gate by design" clause was corrected in the same edit that records the site-level cron gate (the plan's DoD line), since leaving it would contradict the new fact.

## Plan Change Log

## Review Triage Log

### 2026-10-02 — quick lens, pass 1 (verdicts: 0 high, 0 medium, 0 low, 0 false, 0 maybe-false)

- No findings. The lens verified all four acceptance criteria against the diff and surrounding code, re-ran `php -l` and the full harness sweep itself (cron-gate 24/24, quota 32/32, render-matrix 38/38, sanitize-matrix 74/74), and confirmed the boundaries (no change to sanitization, Settings UI, schedule/clear, caps, template; exactly the four planned files touched). One below-bar observation, not a finding: `why_not()` now builds the six-entry registry literal twice per call via `plan_allows()` — negligible and consistent with KISS.

## Design Notes

The gate stays one class with two halves: `why_not()`/`can()` answer "may this user, on this plan" at user-facing chokepoints; `plan_allows()` answers the plan half alone for site-level runs (cron has no user — ADR 0003 made cron site-level and quota-free by decision). Checking once per batch, before `get_posts_needing_review()`, is correct because the command is uniform across the batch, and free — no wasted query on a skipped run. Skipping (not falling back to `review`) is the ticket's wording: a free site must not silently run a different agent than the owner configured.

## Verification

**Commands:**
- `php -l` on `class-pqm-features.php`, `class-pqm-cron.php`, `tests/harness/cron-gate-harness.php` -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all harnesses green (gate + quota code touched)

**Manual checks (if no CLI):**
- Real site, free: set a gated `cron_command` via option tweak, run `pqm_cron_review_posts` with WP Crontrol — nothing reaches n8n; with the `pqm_is_pro` mu-plugin filter, the same batch fires; quota counters unchanged either way.
