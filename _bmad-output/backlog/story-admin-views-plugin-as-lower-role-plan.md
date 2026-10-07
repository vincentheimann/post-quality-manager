---
title: 'View as: an Administrator sees the plugin as Editor, Author or Contributor'
type: 'feature'
ticket: '34'
created: '2026-10-07'
status: 'built'
baseline_revision: 'f05d556f1e5c5554f5c2b2c797d922096677cfc5'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** An Administrator holds all five `pqm_` capabilities, so the only way to check what an Editor, Author or Contributor sees (menus, sections, buttons, quota note, locks, refusals) is to sign in as one.

**Approach:** A per-Administrator "View as" choice, stored as user meta, narrows that user's five `pqm_` capabilities to those the chosen role holds on the site right now (a `user_has_cap` filter that can only remove). Every existing gate then renders and refuses as for that role, unchanged. A WordPress toolbar node offers the switch and, while a view is on, names it and offers a one-click Exit on every wp-admin screen.

## Boundaries & Constraints

**Always:** "Administrator" = the user holds core `manage_options` (never a role-name check). Only the five keys of `PQM_Features::capabilities()` are touched; a key stays granted only if the Administrator already holds it AND the chosen role holds it now (`get_role()->has_cap()`, so a role editor's changes are followed). Listed roles: `editor`, `author`, `contributor`, only those that exist on the site, labelled with their translated display names. The switch handler checks a nonce and `manage_options`, never a `pqm_` capability, so Exit works from the Contributor view. The view is real: allowed actions run (quota spent on the Administrator's own counter). All copy through i18n, per brand.md.

**Never:** No change to `PQM_Features`, any gate, REST route, page callback, the stored settings or any WordPress role. No WordPress core capability changes. No plan simulation. No custom roles. No React, no new dependency, no n8n template change. No role-name checks in `admin/class-pqm-admin.php` (render-matrix harness).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| No view | Administrator, no meta | Five `pqm_` caps unchanged; toolbar shows "View as" with the three roles | No error expected |
| Author view | meta `author`, default map | `pqm_view_reviews`, `pqm_run_free_agents` kept; the other three false; `manage_options`, `edit_others_posts` unchanged | No error expected |
| Contributor view | meta `contributor` | All five false; toolbar shows "Viewing as Contributor" + Exit; Exit handler accepted, meta deleted | No error expected |
| Role edited | Author role granted `pqm_manage_agents` | Author view keeps `pqm_manage_agents` | No error expected |
| Never adds | Administrator role lacks `pqm_run_paid_agents`, Editor holds it | Editor view: `pqm_run_paid_agents` stays false | No error expected |
| Role deleted | meta `author`, role gone | All five false; bar still shows the view (raw key as label) and Exit | Fail closed |
| Not an Administrator | Editor user with forged meta `author` | Caps unchanged; no toolbar node | Handler answers 403 |
| Bad meta / bad request | meta `administrator` or `shop_manager`; `role=administrator` request | Meta read as no view; request treated as Exit | No error expected |
| No nonce | Handler request without a valid nonce | Nothing stored | `check_admin_referer` refusal |
| Uninstall | Several users with meta | No `pqm_view_as` meta remains | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-features.php` -- `capabilities()` is the one list of the five keys; reuse, do not change. Every gate (`why_not`, `is_quota_subject`, `hidden_commands`) reads `current_user_can()`, so the filter alone drives them.
- `post-quality-manager/admin/class-pqm-admin.php` -- menus (`register_pages`, L48), `settings_page_capability` (L329, also the options.php save cap), section gates: all `current_user_can('pqm_*')`. Untouched; the harness at `render-matrix-harness.php` L338-339 forbids role names here.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- permission callbacks and gates use `pqm_` caps; `edit_post`/`read_post` checks are core (out of scope per story Boundaries). Untouched.
- `post-quality-manager/post-quality-manager.php` -- includes (L31-38), global hooks, `is_admin()` hook block (L50-58).
- `post-quality-manager/uninstall.php` -- deletes user meta by exact key (L25 pattern).
- `tests/harness/caps-harness.php` -- stub style to copy (`WP_Role`, `get_role`, `$GLOBALS['tw']` test world, `check()` runner).
- `README.md` "Admin pages & capabilities" (L65-82) -- access truth for users.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-view-as.php` -- new `PQM_View_As`: `META_KEY = 'pqm_view_as'`, `ROLES`; `current( int $user_id ): string` (valid role or ''); `filter_caps( $allcaps, $caps, $args, $user )` for `user_has_cap` (return unchanged unless `$allcaps['manage_options']` and a view is set); `admin_bar( WP_Admin_Bar $bar )` (`manage_options` only; nodes per Design Notes); `handle_switch()` for `admin_post_pqm_view_as` -- the whole feature in one file
- [x] `post-quality-manager/post-quality-manager.php` -- require the class; `add_filter('user_has_cap', …, 10, 4)` globally (REST requests too); `admin_bar_menu` and `admin_post_pqm_view_as` inside the `is_admin()` block -- wiring
- [x] `post-quality-manager/uninstall.php` -- `delete_metadata( 'user', 0, 'pqm_view_as', '', true );` -- criterion 7
- [x] `tests/harness/view-as-harness.php` -- new harness covering every matrix row (filter, toolbar nodes, handler incl. nonce/403/redirect, uninstall line present) -- risk-medium proof the story asks for
- [x] `README.md` -- one paragraph under "Admin pages & capabilities": the View as switch, Administrators only, plugin capabilities only, real actions (quota) -- docs truth
- [x] `memory/freemium-and-roles.md` -- add the View as fact (filter narrows only, meta key, uninstall) -- durable fact

**Acceptance Criteria:**
- Given an Administrator in the Author view, when they open Post Reviews, then paid buttons are hidden, the quota note shows, and Settings/n8n Workflow are absent from the menu.
- Given the Editor view, when Settings is opened, then only the Editor sections show and a save goes through options.php.
- Given any view, when the Administrator opens Posts or Users, then nothing differs except the toolbar node.

## Implementation Notes

- Built by a fresh implementation subagent from this plan; files: `includes/class-pqm-view-as.php` (new), `post-quality-manager.php`, `uninstall.php`, `tests/harness/view-as-harness.php` (new, 65 checks), `README.md`, `memory/freemium-and-roles.md`.
- `admin_bar_menu` runs at priority 100 so the node follows core's New/Edit menus instead of landing before the WordPress logo.
- Review pass 1 patches: `tabindex` 0 on the link-less top node; README and memory state the core-capability exception (Insert into Post, `/post-score`); the wiring check is bounded by the `is_admin()` block's braces.
- Verification (2026-10-07): `php -l` clean on the four touched PHP files; all 12 harnesses green. Manual check on Local not yet done (owner).
- No commit (house rule).

## Review Triage Log

**Pass 1 (quick, 2026-10-07):** high 0, medium 1, low 2, false 1, maybe-false 0.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `class-pqm-view-as.php` `admin_bar()`: top node `pqm-view-as` has no href/tabindex, so keyboard users cannot open the role choices | medium | patch | Core `WP_Admin_Bar::_render_item()` renders a link-less node as `div.ab-item.ab-empty-item` and prints `tabindex` only from `meta['tabindex']`. Accessibility basics are never simplified away. Fix: `meta.tabindex = 0`, asserted in the harness. |
| 2 | README and memory say REST refusals / every gate follow the view; Insert into Post (`edit_post`, rest-api L178) and post-score (`read_post`, L303) check core caps | low | patch | The story's Boundaries name the Insert exception; the docs omit it and state the opposite. Direct wording fix. |
| 3 | `view-as-harness.php` wiring check does not bound the hooks by the `is_admin()` block's closing brace | low | patch | Verified: `strpos(...) > $admin_block_at` only. One-line tightening. |
| 4 | Plan ACs 1–3 have no automated proof | false | — | Not a defect: every gate involved reads `current_user_can('pqm_*')` and is unchanged (reviewer confirmed by reading the code); the plan assigns these to the manual check on Local, which stays open for the owner. |

## Design Notes

Toolbar (top-level nodes, default toolbar group):
- No view: `pqm-view-as` "View as" (no link) with one child per listed role, linking to the handler.
- View on: `pqm-view-as` "Viewing as %s" with children for the other listed roles, plus a separate top-level `pqm-view-as-exit` "Exit" node (one click, never hidden in a dropdown).

Handler URL: `wp_nonce_url( admin_url( 'admin-post.php?action=pqm_view_as&role=' . $role ), 'pqm_view_as' )`, with `role=''` for Exit. Redirect after the switch: entering a view lands on the plugin Dashboard when the view may open it (`current_user_can('pqm_view_reviews')`, which already reads the new meta), otherwise the wp-admin Dashboard. Staying on the referring page could land on WordPress's refusal screen, which has no toolbar. Exit returns to `wp_get_referer()`, falling back to `admin_url()`.

Skipped: the toolbar on the front end -- add if the owner wants it there (the story asks for wp-admin). Skipped: a highlight colour for the bar -- add if the plain node is missed.

## Verification

**Commands:**
- `php -l` on the four touched PHP files -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green, `view-as-harness.php` included

**Manual checks (if no CLI):**
- On Local: switch to each of the three views, check the plugin menu, pages, buttons and quota note against README's access tables; Exit from the Contributor view via the toolbar on the wp-admin Dashboard; a second Administrator is unaffected.
