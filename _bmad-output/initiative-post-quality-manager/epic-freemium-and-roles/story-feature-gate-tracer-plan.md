---
title: 'Feature gate tracer'
type: 'feature'
ticket: '1'
created: '2026-10-01'
status: done
baseline_revision: 'e682f152de30e7677c62b67761e3c76058c0da5f'
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

**Problem:** Every command trigger is a flat `edit_posts` check and there is no notion of plan, so nothing can be sold as paid and nothing proves the gate architecture (spec CAP-1, CAP-5).

**Approach:** Introduce `PQM_Features::can( $command )` backed by a command registry (tier + capability per agent) and a stub `is_pro()`, called once at the trigger chokepoint so paid commands are refused in PHP before the webhook; the UI shows paid commands visible-but-locked with upgrade copy (decision 2026-10-01).

## Boundaries & Constraints

**Always:** One gate call at the chokepoint `trigger_review_callback()` — it already serves REST, AJAX and the GET fallback. Registry declares all six agents with their real tiers (review, takeaways = free; rewrite, sources, seo, audit = paid); capability values stay `edit_posts` in this story. `is_pro()` returns false through `apply_filters( 'pqm_is_pro', false )` so the paid path is demoable before licensing exists. Refusal is `WP_Error` status 403 with user-readable copy per `docs/brand.md`. All-static class, `PQM_` prefix, `require_once` in the bootstrap block.

**Never:** No role/capability remapping (story 2), no license provider or settings UI (story 3), no quota (story 4), no cron changes (story 6 — cron is hard-locked to `review` today). No change to `/save-review`, `check_webhook_token`, `pqm_n8n_payload()`, the n8n template, or the `ping` test. Commands outside the registry (`score`, `apply`, `apply_*`) keep today's behavior.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Free command | Unlicensed site, `edit_posts` user triggers `review` | Webhook called, behavior unchanged | No error expected |
| Paid via REST | Unlicensed site, `rewrite` to `trigger-review` route | 403 `pqm_feature_gated` before `wp_remote_post` | WP_Error with upgrade copy |
| Paid via AJAX/UI | Unlicensed site, paid button or direct `pqm_trigger_command` | Button locked (no request); direct call gets the same 403 surfaced in the notice area | JSON error shows gate message |
| Licensed | `pqm_is_pro` filtered true, `rewrite` triggered | Reaches webhook exactly as today | No error expected |
| Outside registry | `score` / `apply_*` / `ping` | Unchanged | Unchanged |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` — chokepoint `trigger_review_callback()` :328-376; gate goes after `$command = sanitize_key(...)` :340, before payload build :352; webhook call :355. AJAX (:1227-1230 in admin) and GET fallback (admin :377-380) both call this directly — AJAX bypasses the REST `args` enum, so the gate must not rely on it.
- `post-quality-manager/post-quality-manager.php` — flat bootstrap; `require_once` block :31-35 (add features class after :32); de-facto command list = `pqm_default_settings()['agent_prompts']` keys :81-88; helpers all-static pattern.
- `post-quality-manager/admin/class-pqm-admin.php` — `wp_localize_script` `pqmAdmin` array :118-142 (add gated-commands key here, precedent: `agentDefaults`, `modelRegistry`); server-rendered "Run Review" button :544-547 (free command, untouched); `ajax_trigger_command` :1212-1240.
- `post-quality-manager/admin/js/pqm-admin.js` — generic button builder `actionBtn()` :826-830 (emits `data-pqm-cmd`); click dispatch :43-48 → `triggerCommand()` :599 → `executeCommand()` :611; render the locked state in `actionBtn()` from `pqmAdmin` data so every paid button is covered in one place.
- Reuse, don't touch: `/save-review` return path, `pqm_n8n_payload()` contract, `pqm_test_connection`. No new option key → no `uninstall.php` change.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-features.php` -- new all-static `PQM_Features`: `registry()` (six agents, tier + capability), `is_pro()` (stub behind `pqm_is_pro` filter), `can( $command )` (tier ∧ capability; unknown commands return current `edit_posts` behavior), `gated_commands()` (list the current user/site cannot run) -- the CAP-1/CAP-5 contract.
- [x] `post-quality-manager/post-quality-manager.php` -- `require_once` the new class -- bootstrap.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- gate call in `trigger_review_callback()` returning 403 `WP_Error` -- refusal before webhook for REST, AJAX and GET at once.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- add gated commands to the `pqmAdmin` localize array -- data bridge to JS.
- [x] `post-quality-manager/admin/js/pqm-admin.js` -- locked rendering in `actionBtn()` (disabled, lock marker, upgrade copy title) and surface the 403 message in `executeCommand()`'s error path -- visible-but-locked decision.

**Acceptance Criteria:**
- Given an unlicensed site and an `edit_posts` user, when any paid command is requested over REST or AJAX, then the response is 403 with the gate message and no webhook request is made.
- Given an unlicensed site, when the Post Reviews agent tabs render, then paid command buttons are visible, locked, carry upgrade copy, and clicking fires no request.
- Given `pqm_is_pro` filtered to true, when `rewrite` is triggered, then the webhook receives the same payload as before this change.
- Given `review` or `takeaways`, when triggered on any site, then behavior is byte-for-byte today's.

## Implementation Notes

- 2026-10-01 — Implemented as planned. `PQM_Features` (all-static, `includes/class-pqm-features.php`) required right after `class-pqm-db.php` in the bootstrap; gate sits in `trigger_review_callback()` immediately after `sanitize_key()`, before the post lookup and payload build, returning 403 `pqm_feature_gated` ("%s is a Pro agent. Upgrade to unlock it — Review and Takeaways stay free.").
- JS bridge: `pqmAdmin.gatedCommands` (from `PQM_Features::gated_commands()`) + `i18n.lockedTitle`. Locked buttons render disabled with a `dashicons-lock`, `pqm-locked` class, the upgrade-copy `title`, and **no** `data-pqm-cmd`, so the delegated click handler can never fire a request.
- Deviation (within intent): the hand-rolled "Regenerate Rewrite" button in `renderRewrite()` (pqm-admin.js, rewrite-applied branch) bypassed `actionBtn()`; it now calls `actionBtn()` so the single-place locked rendering really covers every paid button. Markup emitted for the unlocked case is identical to before.
- No change needed in `executeCommand()`'s error path: `ajax_trigger_command` forwards the gate `WP_Error` via `wp_send_json_error( get_error_message(), status )`, and the existing `toast('err', … (resp.data || 'Failed.'))` fallback already surfaces that string, so a direct `pqm_trigger_command` call shows the gate message.
- Review fix 2026-10-01: `ajax_trigger_command` now passes the `WP_Error`'s status through — `wp_send_json_error( $msg, (int) ($response->get_error_data()['status'] ?? 400) )` — so a gated AJAX call answers HTTP 403 (other trigger errors keep their own status, e.g. 400/404/502, instead of a flat 200). `fetch` does not reject on 4xx, so the JS toast path is unaffected.
- Verified: `php -l` clean on all four touched PHP files; `node --check` clean on pqm-admin.js; CLI harness with WP stubs confirmed the matrix (free allowed, paid refused when unlicensed, `pqm_is_pro` true unlocks all, unknown commands = plain `edit_posts`, `gated_commands()` = the four paid agents). Manual WP Admin / n8n pass still pending.

## Plan Change Log

## Review Triage Log

### 2026-10-01 — quick lens, pass 1 (verdicts: 2 medium, 1 low, 0 high/false/maybe-false)

- **medium / patch** — `admin/class-pqm-admin.php` `ajax_trigger_command()`: gate `WP_Error` forwarded via `wp_send_json_error()` without a status, so AJAX refusals are HTTP 200, not the 403 the AC requires. Verified at :1234-1236; the 403 pattern exists four lines up. Patch sent to the implementation agent: pass the `WP_Error`'s status through.
- **medium / patch (applied by orchestrator)** — DoD requires durable facts updated in `memory/` in the same change; the diff adds the gate chokepoint, tier registry and public `pqm_is_pro` filter but touches nothing under `memory/`. Fix edits a context file, which step-04 normally defers, but the repo's own Definition of Done (AGENTS.md, carried as a persistent fact) mandates the update in the same change — house rule wins; `memory/freemium-and-roles.md` updated directly rather than through the implementer.
- **low / handled at commit** — the `.gitignore` hunk (`_bmad/render/`) rides the story diff but is a separate user-requested hygiene change from earlier in the session, not part of this plan. No code fix; it will be proposed as its own commit so the story commit matches the plan.

## Design Notes

Registry, not scattered checks: story 2 only swaps capability values to `pqm_` caps, story 3 only replaces `is_pro()`'s body — gate callers never change (spec constraint: provider swappable). Tiers for all six agents land now because one mechanism covers them at identical cost; the story's demo command is Rewrite.

## Verification

**Commands:**
- `php -l post-quality-manager/includes/class-pqm-features.php && php -l post-quality-manager/includes/class-pqm-rest-api.php && php -l post-quality-manager/post-quality-manager.php && php -l post-quality-manager/admin/class-pqm-admin.php` -- expected: no syntax errors
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no syntax errors

**Manual checks (if no CLI):**
- WP Admin, no license: Review runs end to end; Rewrite/Sources/SEO/Audit buttons locked with upgrade copy; direct REST `trigger-review` with `command=rewrite` returns 403 and n8n shows no execution.
- With `add_filter( 'pqm_is_pro', '__return_true' )` in a mu-plugin: Rewrite reaches n8n unchanged.
- 2026-10-01 — owner ran the full manual pass on the real site (free path, locked UI incl. Regenerate Rewrite, REST 403 with no n8n execution, AJAX 403 via copy-as-fetch, pro unlock via mu-plugin and relock, ping): **all passed**. Marked done.
