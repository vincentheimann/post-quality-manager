---
title: 'License provider behind is_pro()'
type: 'feature'
ticket: '3'
created: '2026-10-02'
status: 'built'
baseline_revision: 'd09642ddb41d08cb629496c32a7592cd0b876b41'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Plan state is a filter-only stub — `PQM_Features::is_pro()` returns false unless a mu-plugin flips `pqm_is_pro` — so the owner cannot drive the free/paid split from WP Admin and nothing exercises the "license key unlocks the same zip" contract (spec CAP-2, epic Done-when 2).

**Approach:** Confine the license provider to one new class, `PQM_License`, that `PQM_Features::is_pro()` delegates to (the `pqm_is_pro` filter stays as dev override), fed by an admin-only License Key setting. **Owner decisions (2026-10-02):** Freemius is STUBBED — no SDK, plugin-side development only, third parties at the end of the epic; stub validity = **any non-empty key flips the site to pro** (nothing to protect until a paid product exists; real validation later replaces `PQM_License`'s internals only). The real Freemius integration — SDK, product creation (owner), and the release-notes/migration path for installs the licensing flip turns free — is recorded in `deferred-work.md`, not built here.

## Boundaries & Constraints

**Always:** The provider is swappable: gate callers and `why_not()`/`plan_allows()` keep reading only `PQM_Features::is_pro()`, whose body becomes `apply_filters( 'pqm_is_pro', PQM_License::is_pro() )` — the mu-plugin `__return_true` demo keeps working. `license_key` lives inside the existing `pqm_settings` option (no new option, no schema change), sanitized `sanitize_text_field` + trim in the admin-only sanitizer branch and added to the `$admin_only_keys` carry list (story 5 rule: a saver who never saw the section must never wipe it). The License section renders only for `pqm_manage_settings` (role-hidden, like n8n Connection) with a plain Free/Pro status line; copy per `docs/brand.md`. KISS, `PQM_` prefix, escape everything.

**Never:** No Freemius SDK or Composer dependency, no new REST route or AJAX handler, no change to `why_not()`/`plan_allows()`/gate callers, to cron, to story 5's locked-section upgrade copy, to the n8n template, or to `uninstall.php` (the key rides the wholesale `pqm_settings` delete). No key-format validation, no remote calls, no license caching/transients. No migration or release-notes work now — that travels with the deferred Freemius entry.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Key entered | Admin saves non-empty key on free site | `is_pro()` true from next request; reload shows sections unlocked, paid commands run | No error expected |
| Key removed | Admin empties the field, saves | Site reverts to free: paid commands refused, sections lock; stored cron settings carried, batch keeps running free commands (story 6 gate) | No error expected |
| Whitespace key | `"   "` saved | Trimmed to empty → stays free | No error expected |
| Filter override | No key, `pqm_is_pro` filtered true | Pro (filter wraps the provider, dev path preserved) | No error expected |
| Non-admin save | Editor (no `pqm_manage_settings`) saves the form | Stored `license_key` carried untouched | No error expected |
| Same-save interplay | Free site: admin enters key while cron/overrides sections render locked | This save carries cron/overrides from stored (sanitizer reads pre-save plan state — correct: sections rendered per old state); they are editable after reload | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-license.php` -- new all-static `PQM_License`, the confined provider: `is_pro(): bool` = `trim( (string) ( pqm_get_settings()['license_key'] ?? '' ) ) !== ''`. The only file real Freemius will rewrite.
- `post-quality-manager/includes/class-pqm-features.php` -- `is_pro()` :92-94: body becomes `return (bool) apply_filters( 'pqm_is_pro', PQM_License::is_pro() );`. Docblock updated (no longer "stub until licensing exists"). Nothing else — registry, `plan_allows()`, `why_not()`, quota untouched.
- `post-quality-manager/post-quality-manager.php` -- `require_once` the license class after `class-pqm-db.php` :32 (before features, which calls it); `pqm_default_settings()` :67: add `'license_key' => ''`.
- `post-quality-manager/admin/class-pqm-admin.php` -- `sanitize_settings()`: admin branch :258-275 adds `$clean['license_key'] = trim( sanitize_text_field( $input['license_key'] ?? '' ) );`; `$admin_only_keys` :278-282 gains `license_key`. `render_settings()`: new "License" section first inside the `$can_manage_settings` block :734 — one `form-table` row (key input, `regular-text`) plus a Free/Pro status line (`$is_pro` :710 is already computed); copy per `docs/brand.md`.
- `tests/harness/sanitize-matrix-harness.php` -- extend: license_key sanitize + trim (admin saver), carry for non-admin saver, whitespace-only → empty.
- `tests/harness/license-harness.php` -- new, patterned on `cron-gate-harness.php`: `PQM_License::is_pro()` from settings; `PQM_Features::is_pro()` delegation + filter override wins; key present → `gated_commands()` empty / `plan_allows('seo')` true; key removed → reverts.
- `_bmad-output/initiative-post-quality-manager/deferred-work.md` -- append the real-Freemius entry (SDK behind `PQM_License`, owner creates the product, release notes/migration for installs flipped to free — ticket note of 2026-10-01).
- `memory/freemium-and-roles.md` -- record provider mechanics once built.
- Don't touch: `class-pqm-rest-api.php`, `class-pqm-cron.php`, `admin/js/pqm-admin.js` (gate data is computed server-side per request), `templates/n8n-workflow.json`, `uninstall.php`.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-license.php` -- new confined provider -- CAP-2's swappable seam.
- [x] `post-quality-manager/includes/class-pqm-features.php` -- `is_pro()` delegates, filter kept -- gate callers unchanged.
- [x] `post-quality-manager/post-quality-manager.php` -- bootstrap require + `license_key` default -- single source of truth.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- sanitizer (admin branch + carry list) and License section with status line -- admin-only key entry.
- [x] `tests/harness/license-harness.php` + extend `tests/harness/sanitize-matrix-harness.php` -- cover all six matrix rows -- lock in the seam.
- [x] `_bmad-output/initiative-post-quality-manager/deferred-work.md` -- real-Freemius entry -- owner's "third parties at the end".
- [x] `memory/freemium-and-roles.md` -- provider mechanics -- DoD.

**Acceptance Criteria:**
- Given a free site, when an Admin saves a non-empty license key, then on the next page load the Overrides and Automated Reviews sections are unlocked and a paid command reaches n8n — same zip, no other change.
- Given a keyed (pro) site, when the Admin clears the key, then paid commands are refused again and the sections re-lock, while stored cron settings persist.
- Given a mu-plugin filtering `pqm_is_pro` to true and no key, when any gate caller asks, then the site is pro (override preserved).
- Given any non-`pqm_manage_settings` saver, when settings save, then the stored key survives byte-identical.

## Implementation Notes

Built 2026-10-02, exactly per the Code Map; three small deviations, all mechanical:

- `_bmad-output/initiative-post-quality-manager/deferred-work.md` did not exist yet — "append" became "create" (file header + the real-Freemius entry).
- `tests/harness/cron-gate-harness.php` and `tests/harness/quota-harness.php` load `class-pqm-features.php` directly, and their `apply_filters()` stubs evaluate the filter's default argument — `is_pro()`'s new body references `PQM_License`, so both harnesses gained a one-line `require` of `class-pqm-license.php` (behavior unchanged: their stubbed settings have no `license_key`, so the provider reads free, and the forced/pass-through filter semantics are what each harness already asserted). `render-matrix-harness.php` and `sanitize-matrix-harness.php` load the full bootstrap and needed nothing.
- The n8n Connection `<h2>` gained `class="pqm-section-gap"` — License is now the first section, and every non-first section heading on the page carries that class. Copy and locked-section markup untouched.

Sanitize-matrix fixture gained `'license_key' => 'PQM-STORED-KEY'` and `$ADMIN_ONLY` gained `license_key`, so the existing carry loops (Cases 1/2/2b) now also prove the non-admin byte-identical rule; new Case 5b covers trim, whitespace-only → empty, tag stripping, admin clear, and a non-admin saver attempting to post a key.

- 2026-10-02, matrix audit (orchestrator): matrix row 6's distinctive claim — the sanitizer reads the *pre-save* plan state during the save that changes the key — was not exercised, because the sanitize-matrix `pqm_is_pro` stub always forced a fixed value and the real chain (filter pass-through → `PQM_License` → stored key) never drove a sanitize run. Fixed: the stub now treats `null` as pass-through, and new Case 5c proves both directions (key entered while free → posted cron ignored, cron carried from stored; key cleared while pro → posted cron still applied). Sanitize-matrix went 82 → 86 checks.

## Plan Change Log

## Review Triage Log

### 2026-10-02 — quick lens, pass 1 (verdicts: 0 high, 1 medium, 0 low, 0 false, 0 maybe-false)

- **medium / surfaced to owner (HITL)** — the DoD's real-WP manual leg (AC1 "a paid command reaches n8n", AC2 relock) is proven only by CLI stub harnesses; the plan's Results line records it as pending. Accurate, but not a code defect: this story is `hitl` by ticket, and the manual pass is the owner's step before it closes — same flow as story 1.1 (owner ran the pass 2026-10-01 before done). Checklist handed over at presentation; no code change.
- No other findings. The lens independently re-verified all four ACs, the boundaries (10 files, nothing extra vs baseline `d09642d`), brand/i18n/escaping rules, the AJAX merge-save path carrying the key, and re-ran lint + the full harness suite itself (201/201).

## Design Notes

`PQM_License` reads the key per call (no caching): `pqm_get_settings()` is already an option read WordPress caches per request, and a stale pro flag across a save would contradict the "save is the commit point" rule. The filter wraps the provider (`apply_filters( 'pqm_is_pro', PQM_License::is_pro() )`) rather than the reverse so a dev override always wins — same contract as today. The status line states the current plan plainly (brand voice: concise, honest about the stub era — no fake "validating…" theater).

## Verification

**Commands:**
- `php -l` on the four touched/added plugin PHP files + both harnesses -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all harnesses green (gate + settings code touched)

**Manual checks (if no CLI):**
- Real site: enter any key → paid buttons unlock and a paid command reaches n8n; clear it → relock, cron batch still runs `review`; mu-plugin `pqm_is_pro` override still unlocks with no key.

**Results (2026-10-02):** `php -l` clean on all 8 touched/added PHP files; full harness suite green — cron-gate 24, license 21, quota 32, render-matrix 38, sanitize-matrix 86 (after the matrix-audit Case 5c addition) — 201 passed, 0 failed.
- 2026-10-02 — owner ran the real-site manual pass (mu-plugin override file removed for the key steps): key entry → Pro status, sections unlock, paid command reaches n8n; key cleared → relock with cron settings intact; mu-plugin filter restored → unlocks with empty key: **all green**.
