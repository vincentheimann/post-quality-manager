---
title: 'A free site can switch leftover scheduled reviews off'
type: 'feature'
ticket: '13'
created: '2026-10-06'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '95144b0c55d6a580c6b40dac6cabd27218285a49'
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Stored cron settings keep running on a free site (decision kept, retro A7). But the Enable checkbox sits inside the plan-locked disabled fieldset, so a site that lapsed to free with `cron_enabled = true` keeps spending scheduled AI runs, with no supported way to stop them.

**Approach:** Exempt the Enable toggle, and only it, from the lock, as an off-switch. On a free site whose stored `cron_enabled` is true, the toggle renders outside the disabled fieldset. A hidden `cron_enabled = 0` input before it makes the unchecked state post. The sanitizer's free branch takes `cron_enabled` from the request only to switch it off: stored true and posted `0` → false. Every other cron key, and `cron_enabled` in every other case, carries the stored value as today. A free site can never switch scheduled reviews on. `PQM_Cron::schedule()` (already hooked on `updated_option`) clears the event when it goes false.

**Decisions (owner's standing approval of default proposals, 2026-10-06):**
- The off-switch follows the section's own gate, `pqm_manage_agents` (story 16), rather than Admin only: whoever may see Automated Reviews may stop it.
- When stored `cron_enabled` is already false on a free site, the toggle stays inside the locked fieldset (there is nothing to switch off, and switching on is Pro).

## Boundaries & Constraints

**Always:** the pro path is unchanged. The carry stays wholesale for the other cron keys. Copy per `docs/brand.md`: one short line under the toggle explaining it can only be switched off on the free plan. Save is the commit point (no `confirm()`).

**Never:** let a free-site POST set `cron_enabled` true. Unlock any other cron or override control. Change the gate on cron runs (`plan_allows`).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Switch off | free, stored true, agents-holder posts `cron_enabled=0` (other cron keys absent) | `cron_enabled` false; every other cron key and `agent_prompts` carried byte-identically; event cleared | — |
| Leave on | free, stored true, posts `cron_enabled=1` | stays true | — |
| Smuggle on | free, stored false, posts `cron_enabled=1` | stays false | — |
| Other knobs smuggled | free, stored true, posts `cron_batch_size=99` with `cron_enabled=0` | batch size carried; only enabled flips | — |
| No agents cap | free, settings-only saver posts `cron_enabled=0` | carried (true) | — |
| Render free, stored true | agents-holder | Enable checkbox + hidden 0 outside the fieldset; rest of the section still locked | — |
| Render free, stored false | agents-holder | Today's fully locked section | — |
| Pro | any | Today's behavior | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` `sanitize_settings()` -- the cron block after story 16: `if ($is_pro && <agents cap> && array_intersect_key(...)) {…} else { carry $cron_keys }`. In the carry branch, after the carry: if not pro, saver has `pqm_manage_agents`, stored `cron_enabled` true and `isset($input['cron_enabled'])` and `empty($input['cron_enabled'])` → `$clean['cron_enabled'] = false`.
- `post-quality-manager/admin/class-pqm-admin.php` `render_settings()` -- the Automated Reviews block (`<h2 id="pqm-cron-settings">`, then `if (!$is_pro)` locked note + `<fieldset disabled class="pqm-locked-section">`, then the table whose first row is the Enable checkbox `name="pqm_settings[cron_enabled]"`). When `!$is_pro && $settings['cron_enabled']`, render the Enable row (hidden `value="0"` + checkbox `value="1"` checked) as its own small table before the fieldset, with a `description` line, and skip that row inside the fieldset.
- `PQM_Cron::schedule()` (`includes/class-pqm-cron.php:28`) -- clears, then returns when disabled; no change.
- `tests/harness/sanitize-matrix-harness.php` -- one case per sanitizer matrix row (Case 5 already covers "free save without the key keeps false").
- `tests/harness/render-matrix-harness.php` -- free + stored true: the checkbox is outside the locked fieldset, exactly one `pqm_settings[cron_enabled]` checkbox, the hidden 0 precedes it, the other cron inputs remain inside. Free + stored false: unchanged (two locked fieldsets, checkbox inside).
- `memory/freemium-and-roles.md` -- carry-rule sentence: the one exception (free-site off-switch).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- sanitizer exception + render
- [x] `tests/harness/sanitize-matrix-harness.php`, `tests/harness/render-matrix-harness.php` -- the matrix rows
- [x] `memory/freemium-and-roles.md` -- the exception recorded

**Acceptance Criteria:**
- Given a free site with stored `cron_enabled = true`, when an agents-holder unchecks Enable and saves, then cron stops and every other locked key is carried.

## Implementation Notes

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 1 high, 1 medium, 2 low. All patched, then verified (all 10 harnesses exit 0).
- high, patch: the off-switch always posts `cron_enabled`, so a stale free-rendered form saved after the site turned pro counted as "section posted" and reset the other cron knobs (bug 19 regression). "Section posted" now means any of frequency, batch size, min score or max age, which a real pro form always posts. The off-switch applies in the carry branch on any plan, so a stale uncheck still switches cron off. Pro-site cases added for both.
- low, patch: the "event cleared" matrix row had no check. cron-gate-harness Row 7 now asserts `PQM_Cron::schedule()` clears `pqm_cron_review_posts` and schedules nothing once disabled.
- low, patch: README said Automated Reviews stay locked until Pro. One clause names the off-switch exception.
- medium, patch: in the off-switch state the locked note still said "Upgrade to let reviews run on a schedule" above a live, running toggle. That state now reads "Available with Pro. Your scheduled reviews keep running; upgrade to change their settings."; every other state is unchanged (render-matrix checks both).

## Verification

**Commands:**
- `php -l` on touched PHP; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

**Manual checks (if no CLI):**
- Free site with cron on: Settings shows the Enable toggle active, the rest locked. Uncheck and save: the toggle disappears into the locked section unchecked, and `wp cron event list` no longer shows `pqm_cron_review_posts`.
