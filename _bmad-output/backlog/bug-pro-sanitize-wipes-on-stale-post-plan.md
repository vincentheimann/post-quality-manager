---
title: 'A pro-site save without the cron/overrides sections carries them'
type: 'bugfix'
ticket: '19'
created: '2026-10-06'
status: done
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'e5534167f69772083617905472b0d6a946e79919'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On a pro site, `PQM_Admin::sanitize_settings()` rebuilds the cron keys and `agent_prompts` from `$input` unconditionally. A save that posts neither section, such as a form rendered while the site was free or a direct `options.php` POST, flips `cron_enabled` to false, resets the other cron knobs to their defaults and wipes every per-agent override.

**Approach:** Use the free branch's rule, keyed on what the request posted instead of on the plan. If no cron key posted, the whole cron section carries its stored values (never per field: an absent checkbox alone must not read as "unchecked"). In the pro `agent_prompts` loop, an agent the request did not post carries its stored entry. A save that posts the sections behaves exactly as today, including the per-knob stored fallback.

</frozen-after-approval>

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/admin/class-pqm-admin.php` -- `sanitize_settings()`: hoist `$cron_keys`. Apply the cron input only when `is_pro()` and `$input` holds any cron key, otherwise carry stored. In the pro agent loop, an unposted agent carries `$existing['agent_prompts'][$agent]` -- root cause: one carry rule for both plans
- [ ] `tests/harness/sanitize-matrix-harness.php` -- Case 8: pro site (Admin and Editor), POST with only `license_key` / empty / null: every cron key and `agent_prompts` `===` stored; cron posted without `cron_enabled` → false (the unchecked semantics survive); one agent posted, another absent → the absent one carries stored -- AC proof
- [ ] `memory/freemium-and-roles.md` -- carry-rule sentence: from `$input` only when the site is pro *and* the request posted the section (per agent for overrides) -- durable fact changed

**Acceptance Criteria:**
- Given a pro-site save whose POST lacks the cron and `agent_prompts` sections entirely, when it sanitizes, then every key of those sections carries the stored value byte-identically.
- Given a pro-site save that posts the sections, when it sanitizes, then today's behavior (including the per-knob stored fallback) is unchanged: harness Cases 1, 4, 4b, 5c and 7 stay green.

## Implementation Notes

Oneshot: about 40 lines in 3 files, no schema, REST or template change. On a pro site both sections render for every Settings viewer (no role-hiding), so the only triggers are a stale form or a direct POST. `cron_command` is in the presence check for simplicity (it renders disabled and never posts from the real form). Skipped: a hidden "section rendered" sentinel field. Add it if a section ever gains only checkbox-like controls that can all be absent.

## Verification

**Commands:**
- `php -l post-quality-manager/admin/class-pqm-admin.php` -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness exits 0

## Review Triage Log

Quick pass (1 lens): 0 high, 0 medium, 4 low, 0 false. All four patched.
- low, patch: `class-pqm-admin.php` unposted-agent carry lacked the `?? $defaults` fallback its sibling line has. A non-array stored `agent_prompts` plus a direct POST would fatal. Fallback added.
- low, patch: Case 8 covered 3 of the 6 caps × POST combinations. Now loops Admin and Editor over `license_key` only, empty and null.
- low, patch: the "checkbox absent" case posted cron values equal to stored, so it could not tell applied from carried. It now posts different values and asserts they are applied.
- low, patch: the "fixture sanity" label described a check on sanitizer output. Relabelled as the stored-not-default assertion it is.
