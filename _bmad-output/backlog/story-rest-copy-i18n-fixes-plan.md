---
title: 'REST copy is translated, labeled from one source and correct'
type: 'feature'
ticket: '11'
created: '2026-10-06'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '42f5be458a0b7553b2224b6a6836381a973b6777'
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** REST copy has three defects (retro A5). User-facing strings in `class-pqm-rest-api.php` are untranslated. Command labels come from three places: `ucfirst($command)` in `gate_error()`, `PQM_Blocks::label()`, and `COMMAND_LABELS` in `src/kit/copy.js`. The quota refusal prints the limit twice (`%1$d of %1$d`), understating usage when an admin lowers the limit mid-month. The trigger success `message` also says "Review triggered…" when n8n answered non-2xx (`success: false`).

**Approach:** One label source. Each `PQM_Features::registry()` entry carries a translated `label`, keyed on story 14's whitelist, read through `PQM_Features::label( $command )` (raw key for anything unknown). `gate_error()` and the insert-block success copy use it, and `PQM_Blocks::label()` goes. The React pages get the same labels from PHP: `enqueue_app()` adds an inline `window.pqmCommandLabels` before `pqm-app`, and `copy.js`'s `commandLabel()` reads it, so the JS map goes. Every user-facing REST string goes through `__()`. The quota refusal prints `used` of `limit`. The trigger message names the command label and tells the truth on a non-2xx.

**Decisions (owner's standing approval of default proposals, 2026-10-06):**
- Labels live in the registry rather than a separate map: one table, so a command can't exist without a label.
- `/save-review` responses (`Review saved…`, `Post not found.`, `Failed to save review.`) stay untranslated. Only n8n reads them, never a person.

## Boundaries & Constraints

**Always:** copy follows `docs/brand.md` (gentle, actionable). `__()` with the `post-quality-manager` domain and a `translators:` comment on every placeholder. Status codes, error codes and response keys stay unchanged. `COMMAND_ORDER`, `FREE_COMMANDS` and every other export of `copy.js` stay.

**Never:** change gate or quota logic. Add a dependency. Duplicate a label in JS.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Role refusal | `seo`, role lacks cap | "SEO is not available for your role…" (label from the registry) | 403 `pqm_feature_gated` |
| Plan refusal | `sources`, free site | "Sources is a Pro agent…" | 403 |
| Unknown command, direct callback | `foo` | Label falls back to raw `foo`, no `ucfirst` | 403 |
| Quota | limit lowered to 10, used 12 | "Monthly limit reached (12 of 10 AI actions used)…" | 429 |
| Trigger accepted | n8n 2xx | `message` "<Label> started for "<title>"." | 200 `success: true` |
| Trigger non-2xx | n8n 500 | `message` says n8n answered 500 and the run did not start | 200 `success: false` (unchanged) |
| JS label | `commandLabel('seo')` with the inline labels present | "SEO"; unknown key → raw key | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-features.php` -- `registry()` (8 entries after story 14): add `'label' => __( '…', 'post-quality-manager' )` to each (Review, Takeaways, Rewrite, Sources, SEO, Audit, Score, Apply). New `label( string $command ): string`.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `gate_error()` L153-171 (`ucfirst`); `/post-score` errors L249-263 (user-facing to API callers: translate); trigger errors L455-460 (not configured), L493-497 (invalid post), L511-515 (n8n unreachable, concatenated message → sprintf + `__()`); quota L479-488 (`$quota['used']` vs `$quota['limit']`, translators comment updated); trigger response `message` L541; insert-block success L775-778 (`PQM_Blocks::label` → `PQM_Features::label`). `/save-review` strings stay untranslated.
- `post-quality-manager/includes/class-pqm-blocks.php:115-120` -- delete `label()` after moving its callers. Grep all callers first.
- `post-quality-manager/admin/class-pqm-admin.php:194-211` `enqueue_app()` -- `wp_add_inline_script( 'pqm-app', 'window.pqmCommandLabels = ' . wp_json_encode( <command => label> ) . ';', 'before' )`.
- `src/kit/copy.js:21-39` -- drop `COMMAND_LABELS`. `commandLabel()` reads `( window.pqmCommandLabels || {} )[ command ] || command`. Update the file docblock (labels come from PHP).
- `tests/harness/insert-block-harness.php:425` asserts the plan-refusal copy and must stay green. `tests/harness/quota-harness.php` drives the quota refusal, so add the used-vs-limit case there. `caps-harness.php` Row 6 asserts registry keys/tiers, so add: every entry has a non-empty label, and `label('foo') === 'foo'`.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-features.php` -- labels in the registry + `label()`
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- translate, label, quota used/limit, truthful trigger message
- [x] `post-quality-manager/includes/class-pqm-blocks.php` -- remove `label()`, callers moved
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- inline labels before the app bundle
- [x] `src/kit/copy.js` -- `commandLabel()` reads the inline labels
- [x] `tests/harness/*` -- the I/O matrix rows: gate copy for `seo`/`sources`/`foo`, quota 12-of-10, trigger message on 2xx and non-2xx, registry labels present
- [x] `memory/admin-ui-rebuild.md` or `memory/freemium-and-roles.md` -- one line: command labels come from the PHP registry, shipped to JS as `window.pqmCommandLabels`

**Acceptance Criteria:**
- Given any user-facing REST string, when the plugin is translated, then it goes through `__()`.
- Given any command label shown by REST or the React pages, when it renders, then it comes from the registry.

## Implementation Notes

- The non-2xx trigger `message` reuses the msgid `src/kit/runs.js` `requestRun()` already shows ("%1$s could not start — n8n answered HTTP %2$d…"), so REST and the React toast read the same and share one translation.
- `registry()` now calls `__()`; every caller (REST routes, cron, admin enqueue) runs after `init`, so no early-textdomain notice.
- caps-harness Row 6 compares tier/capability only (`array_intersect_key`) and adds the label checks; the quota-harness carries the 12-of-10, gate-copy (`seo`/`sources`/`foo`) and trigger-message rows.
- Left untranslated on purpose: `/save-review` responses (decision), its stored fallback `An unknown error occurred in n8n.` (stored data, not REST copy), and route arg `description`s (developer-facing schema).

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 0 medium, 2 low. Verified: all 10 harnesses green, `lint:js` clean, `npm run build` compiles.
- low, reject: the plan-refusal copy (`rest-api.php:166`) and the React `LOCKED_TITLE` name "Review and Takeaways stay free" inside the sentence. That is plan copy stating ADR 0003's free tier, not a label render. Composing it from the registry needs i18n list composition in PHP and JS for a fact that changes only with an ADR. Revisit if the free tier ever changes.
- low, patch: three new section `echo`s in `quota-harness.php` held a literal line break instead of `
`. Fixed to the file's convention.

## Verification

**Commands:**
- `php -l` on touched PHP; `node --check src/kit/copy.js` is not valid for ESM, so use `npm run lint:js` instead -- expected: clean
- `npm run build` -- expected: builds
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green
