---
title: 'Every Upgrade link the Freemius SDK renders opens the public pricing page'
type: 'bugfix'
ticket: '59'
created: '2026-10-24'
status: 'done'
baseline_revision: 'a663e67dff270c438cd8b722928001efca440bb4'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** the SDK's Upgrade links (the Account page's Upgrade tab and plan buttons, the expired-license and expired-trial notices, the updater's renewal line) point at `admin.php?page=pqm-dashboard-pricing`, which the plugin never registers because its Freemius init sets `menu.pricing` to false, so every one opens WordPress's "Sorry, you are not allowed to access this page".

**Approach:** one constant holds the public pricing page; the plugin hooks the SDK's own `pricing_url` filter to return it (beside the existing `plugin_icon` filter, vendor untouched), the Settings "Get Pro" link reads the same constant, and a harness row proves that with the in-admin pricing page off the SDK's pricing URL is filtered to that external address, failing on today's code. Decision (owner delegation 2026-10-24): option (a) of the ticket, the public page, not Freemius's in-admin pricing page.

</frozen-after-approval>

## Implementation Notes

- Oneshot: three code files (main plugin file, `admin/class-pqm-admin.php`, `tests/harness/render-matrix-harness.php`), under 30 lines, no DB, REST, payload or template change. Built in a worktree from main `a663e67` (story 41 occupies the main clone); the git-ignored `admin/build/` copied in from the main clone so the render-matrix harness runs (without it five n8n page rows fail on a clean main: an environment artifact, not code).
- Correction to the Intent's Problem sentence (review, same day; the frozen block is left as approved): `menu.pricing` false does not leave the page unregistered. Once the SDK adds its submenu items it registers `pqm-dashboard-pricing` as a hidden page, so outside activation mode the Upgrade links opened Freemius's in-admin pricing page, which shows no plans until story 44 releases them; WordPress's "not allowed" screen appears only in activation mode (Pro waiting for its key, Free before the opt-in choice) or for a user without `manage_options`. The Approach is unchanged: one public pricing page for every Upgrade link (option (a)). SDK facts recorded in `memory/freemium-and-roles.md`.
- The filter answers the SDK's `null` probe with `null` (review finding 1): a filter that always returned the public URL made `add_submenu_items()` swap the hidden pricing page for a link item, which drops the in-admin checkout `checkout_url()` builds for renewals; a lapsed customer's "Renew" would then have landed on the public page and bought a new license instead of renewing.
- AC2 (a licensed site's Account page shows no dead link), from the source: the Account page stays registered (`menu.account` true); its Upgrade links now open the public page; its renewal and checkout links keep targeting the hidden pricing page, still registered thanks to the `null` answer. Not walked; story 44's owner pass covers a licensed site.
- Files: the main plugin file (constant, filter, init comment), `admin/class-pqm-admin.php` (Settings Get Pro reads the constant), `src/setup/config-steps.js` (the setup dialog's copy of the URL gains the trailing slash; a harness row keeps it equal to the constant; `admin/build/` is rebuilt by the release workflow), `tests/harness/render-matrix-harness.php` (five rows), `memory/freemium-and-roles.md`, `memory/lessons.md`.
- Verification: `php -l` clean; every harness 0 failed (render-matrix 421 passed); mutation runs: a callback that ignores its argument fails the `null` probe row, no filter fails two rows; the file restored byte-identical.

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-10-24): 8 findings — high 0, medium 1, low 6, false 1; 7 → patch (applied by the orchestrator, who built this oneshot), 1 rejected.**

1. medium → patch — the filter ignored its argument, so the SDK's `null` probe in `add_submenu_items()` (SDK 2.13.4) turned the hidden pricing page into a link item and dropped the in-admin renewal checkout. Verified in `class-freemius.php` (`get_pricing_url_with_filter( null )`, the link-item branch, `_redirect_on_clicked_menu_link()`). The reviewer's "invalid callback" outcome is softened by the SDK's `admin_init` redirect of link items, but the renewal still lost its license-aware checkout. Fixed: `null` in, `null` out; a harness row asserts it.
2. low → patch — the stated root cause ("never registers", "not allowed") did not match the SDK. Corrected in the code comment, the ticket's refinement note, this plan's notes and memory.
3. low → patch — AC3 covered `pricing_url` only and two checks grepped the main file. The row now asserts the hook, the external URL, the `null` probe, the Settings link and the setup dialog's copy; the greps are gone. `checkout_url()` stays on the registered hidden page by design (finding 1).
4. low → patch — AC2 had no recorded evidence; source reading recorded above.
5. low → patch — memory not updated and the code comment restated SDK behaviour. `memory/freemium-and-roles.md` gains the dated SDK findings and the Get Pro line; the comment names the memory file.
6. low → patch — the new lessons entry and the harness `echo` line carried real control characters where escapes were meant. Both rewritten through the Edit tool; no CR byte left in any touched file.
7. low → patch — `src/setup/config-steps.js` kept its own pricing URL without the trailing slash. Aligned, and a harness row fails when the two differ.
8. false → reject — the Free build's contact URL is not dead: its link item is redirected on `admin_init` to Freemius's standalone contact form (`_redirect_on_clicked_menu_link()`).
