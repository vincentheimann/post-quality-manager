---
title: 'Uninstall strips per-user pqm_* capability grants too'
type: 'bugfix'
ticket: '20'
created: '2026-10-06'
status: done
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'ac0f944b0264933610be193d146576304c08d154'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `PQM_Features::remove_capabilities()`, called only from `uninstall.php`, walks role objects. A `pqm_*` capability granted to a user directly (`WP_User::add_cap`, the quota-lift path the quota docblock names as supported) survives uninstall in the user's capabilities meta, and a reinstall silently re-activates it.

**Approach:** After the role strip, the same function also sweeps users: `get_users( [ 'capability__in' => <pqm caps> ] )` finds every user whose own capabilities meta names a pqm cap (core's query matches the serialized key, grants and explicit denies alike; once roles are stripped, no role match remains), and `WP_User::remove_cap()` drops each pqm key. Non-pqm user grants are untouched.

</frozen-after-approval>

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/includes/class-pqm-features.php` -- `remove_capabilities()`: add the user sweep after the role loop, update the docblock
- [ ] `post-quality-manager/uninstall.php` -- header comment says users too
- [ ] `tests/harness/caps-harness.php` -- Row 3b: `WP_User` + `get_users` stubs. Users with direct pqm grants and denies end with none, non-pqm direct grants survive, the query asks for exactly the five caps
- [ ] `memory/freemium-and-roles.md` -- "only `uninstall.php` strips the caps" → from every role and every user's direct grants

**Acceptance Criteria:**
- Given users holding direct `pqm_*` grants, when the plugin is uninstalled, then no `pqm_*` key remains in any user's capabilities.
- Given a user with non-pqm direct grants, when uninstalled, then those grants survive untouched.

## Implementation Notes

Oneshot: about 30 lines in 4 files, no schema, REST or template change. Single-site scope, same as the role strip today. Skipped: a multisite sweep across every site. Add it when uninstall handles roles network-wide.

## Verification

**Commands:**
- `php -l` on touched PHP; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

## Review Triage Log

Quick pass (1 lens): no AC, rule or bug findings. 2 low harness notes.
- low, patch: Row 3b's "untouched author" could never fail (the stub never returns a user without a pqm key). The fixture user and its assertion were deleted. AC2 stays covered by the quota-lifted author's surviving `edit_others_posts`.
- low, reject: the `get_users` stub models only direct-grant matches, so moving the sweep before the role loop would go unnoticed. Rejected: in real WordPress the order is harmless (extra users get no-op `remove_cap()`), and modeling role matches adds stub complexity for no defect.
