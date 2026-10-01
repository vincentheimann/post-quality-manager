---
title: 'Role capabilities replace binary checks'
type: 'feature'
ticket: '2'
created: '2026-10-01'
status: 'draft'
route: 'full'
route_source: 'pinned'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Every enforcement point is a flat `edit_posts` / `manage_options` check: a Contributor can trigger any agent over REST or AJAX, Authors and Editors are indistinguishable, and nothing is remappable by a role editor (spec CAP-3, epic Done-when 3 and 4).

**Approach:** Register five `pqm_` capabilities on activation (and once on upgrade) with the access matrix's default role map, point the `PQM_Features` registry at them, and replace every `edit_posts` / `manage_options` check in REST permission callbacks, AJAX handlers, menus and the settings form. The gate distinguishes role refusals (hidden in UI) from plan refusals (visible-but-locked).

## Boundaries & Constraints

**Always:** Default map — `pqm_view_reviews` (Admin, Editor, Author: Dashboard, Post Reviews, `GET /reviews`, `GET /post-score`); `pqm_run_free_agents` (Admin, Editor, Author: review, takeaways); `pqm_run_paid_agents` (Admin, Editor: rewrite, sources, seo, audit); `pqm_manage_agents` (Admin, Editor: overrides + Automated Reviews sections, first used by story 5); `pqm_manage_settings` (Admin: Settings and n8n Workflow pages, options save, workflow download, webhook save, ping, cron nudge). Contributor: none. Caps added via `WP_Role::add_cap()` on activation and by a versioned `init` upgrade so upgraded installs are never locked out; removed in `uninstall.php`. Role refusal → 403 with role copy; plan refusal → 403 with the existing upgrade copy. Every route keeps a `permission_callback`; `PQM_` prefix; copy per `docs/brand.md`.

**Never:** No quota (story 4), no settings-section gating or Editor access to Settings (story 5), no cron change (story 6), no license provider (story 3), no in-plugin role editor, no option beyond the caps version, no change to the n8n template, payload, `/save-review` token path or `ping`. No `remove_cap` on deactivation (uninstall only). Multisite network activation out of scope, as for table creation today.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Contributor REST | `POST /trigger-review`, any command | `permission_callback` fails before the callback | `rest_forbidden` 401/403 |
| Contributor UI | Logs in | No Content Quality menu; AJAX trigger 403 | `Insufficient permissions.` |
| Author paid agent | `rewrite`, licensed or not | 403 role copy, no webhook; button not rendered | `pqm_feature_gated` |
| Editor paid, free site | `rewrite`, unlicensed | 403 upgrade copy; button locked (as today) | `pqm_feature_gated` |
| Remap by role editor | Author granted `pqm_run_paid_agents`, licensed | Author runs paid agents, no code change | No error expected |
| Upgrade without reactivation | Files replaced, `pqm_caps_version` missing | First `init` registers caps; Admin keeps full access | No error expected |
| Outside registry | `score`, `apply_*` | Fallback `pqm_run_free_agents` (1:1 for `edit_posts`) | Unchanged |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-features.php` — `registry()` :24-33 (swap capability values), `can()` :49-61 (the only check reached by AJAX :1218 and the GET fallback :377, which call `trigger_review_callback()` directly and skip `permission_callback`), `gated_commands()` :67-72 (JS bridge; keep = plan-locked only). Put the cap map + register/remove here so activation, upgrade and uninstall share one list.
- `post-quality-manager/includes/class-pqm-activator.php` — `activate()` :15-24; add caps call + public `maybe_upgrade()` keyed on option `pqm_caps_version` vs `PQM_Features::CAPS_VERSION`.
- `post-quality-manager/post-quality-manager.php` — hooks :38-60; add `add_action('init', ['PQM_Activator', 'maybe_upgrade'])`.
- `post-quality-manager/includes/class-pqm-rest-api.php` — routes :24-67; `check_edit_posts()` :72 → `check_view_reviews()` for `/post-score`, `/reviews`; new public `check_can_trigger()` (any registry cap) for `/trigger-review`; WP-auth fallback :82 → `pqm_run_free_agents`, :103 → `pqm_manage_settings`; gate error :344-354 branches role/plan.
- `post-quality-manager/admin/class-pqm-admin.php` — menus :26,37,46 → `pqm_view_reviews`; :55,64 → `pqm_manage_settings`; `register_settings()` :196 add filter `option_page_capability_pqm_settings_group` → `pqm_manage_settings`; cron nudge :274,:344, `download_workflow()` :1113, `ajax_save_webhook_url()` :1143, `ajax_test_connection()` :1169 → `pqm_manage_settings`; `ajax_trigger_command()` :1218 → `PQM_REST_API::check_can_trigger()`; Run Review button :545-548 only when `PQM_Features::can('review')`; localize :133 add `hiddenCommands`.
- `post-quality-manager/admin/js/pqm-admin.js` — `actionBtn()` :826 return `''` for `pqmAdmin.hiddenCommands` before the locked branch; callers :486, :510, :569, :823 concatenate strings, so empty is safe.
- `post-quality-manager/uninstall.php` — remove the caps from every role, delete `pqm_caps_version`.
- Don't touch: `pqm_n8n_payload()`, `PQM_Cron`, `/save-review` token path, template, `sanitize_settings()`.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/includes/class-pqm-features.php` -- `CAPS_VERSION`, `capabilities()` (cap → default roles), `register_capabilities()`, `remove_capabilities()`, `why_not( $command ): ?string` (`'role'` | `'plan'` | null, role first), `can()` via it, `hidden_commands()`; registry values → `pqm_run_free_agents` / `pqm_run_paid_agents`; unknown-command fallback `pqm_run_free_agents` -- CAP-3 in one place.
- [ ] `post-quality-manager/includes/class-pqm-activator.php` -- register caps + store version in `activate()`; `maybe_upgrade()` when behind -- no lock-out on upgrade.
- [ ] `post-quality-manager/post-quality-manager.php` -- `init` hook -- upgrade runs for REST and cron requests too.
- [ ] `post-quality-manager/includes/class-pqm-rest-api.php` -- permission callbacks, token-route fallbacks, role/plan gate copy -- REST enforcement.
- [ ] `post-quality-manager/admin/class-pqm-admin.php` -- menu caps, options-page filter, AJAX/admin-post checks, Run Review button, `hiddenCommands` -- admin enforcement.
- [ ] `post-quality-manager/admin/js/pqm-admin.js` -- hidden branch in `actionBtn()` -- role-gated buttons disappear.
- [ ] `post-quality-manager/uninstall.php` -- remove caps + version option -- clean uninstall.
- [ ] `memory/freemium-and-roles.md` -- cap names, default map, upgrade mechanism, hidden/locked split -- DoD.

**Acceptance Criteria:**
- Given a fresh activation, when roles are inspected, then Admin holds all five caps, Editor all but `pqm_manage_settings`, Author `pqm_view_reviews` + `pqm_run_free_agents`, Contributor none.
- Given a role editor adds or removes a `pqm_` cap, when the user acts, then menus, REST and AJAX follow the new mapping with no code change.
- Given an Author on a licensed site, when the tabs render, then paid buttons are absent (not locked) and a direct paid trigger is 403 with role copy.

## Implementation Notes

## Plan Change Log

## Review Triage Log

## Design Notes

Tier-level caps (free / paid), not one per agent: the matrix groups by tier, Editors get the paid four as a block (owner, 2026-10-01), and five names are what a role-editor user can reason about. `pqm_manage_agents` ships now so the activation map is complete and story 5 needs no second registration. Role before plan in `why_not()`: nothing to upsell to a user without the cap, so hidden wins over locked. A dedicated `pqm_caps_version` (not `PQM_VERSION`) drives the upgrade so the owner's zip-replacement test registers caps before release-please bumps the version.

Role refusal copy: `%s is not available for your role. Ask an administrator to extend it.`

## Verification

**Commands:**
- `for f in post-quality-manager/post-quality-manager.php post-quality-manager/uninstall.php post-quality-manager/includes/class-pqm-{features,activator,rest-api}.php post-quality-manager/admin/class-pqm-admin.php; do php -l $f; done` -- expected: no syntax errors
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no syntax errors
- CLI harness with WP stubs (as in story 1.1) -- expected: `why_not()` matrix per role × command × plan; `hidden_commands()` and `gated_commands()` disjoint; `capabilities()` matches the access matrix.

**Manual checks (if no CLI):**
- Real site, reactivated: Contributor has no menu and `POST /trigger-review` → 403; Author runs Review, sees no paid buttons, direct `rewrite` → 403 role copy; Editor sees paid buttons locked on a free site and runs them with `pqm_is_pro` true; Settings/Workflow menus Admin-only; a role editor granting `pqm_run_paid_agents` to Author makes paid buttons appear.
- Upgrade path: delete option `pqm_caps_version` (WP-CLI), load any page, confirm caps are back.
