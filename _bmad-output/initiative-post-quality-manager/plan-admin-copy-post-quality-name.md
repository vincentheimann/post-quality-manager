---
title: 'The plugin calls itself Post Quality in WP Admin, never Content Quality'
type: 'chore'
ticket: ''
created: '2026-10-23'
status: 'built'
baseline_revision: '68ebb918ef8eccff6f8c069ba51fc5895e9b83a0'
route: 'oneshot'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The plugin still shows the retired name "Content Quality": the admin menu label, the Dashboard and Settings page headings, and the not-connected message's path. brand.md § Naming (story 10, owner decision 2026-10-08) retired it, and the website's screenshots (story 86 build 3a) must show the real name.

**Approach:** Apply brand.md § Naming to those strings only: the top-level menu and its page title read "Post Quality"; the two headings read the screen name alone ("Dashboard", "Settings"); the message path reads "Post Quality → n8n Workflow". Update the harness that asserts the message, the README's install step, one release-notes line for existing sites, and the naming records. The rest of story 41 (imagery, banners, the full copy sweep) stays in story 41.

**Decisions:** owner, 2026-10-23 ("Ok, do it"): pull this slice out of story 41 now, before the build 3a captures.

</frozen-after-approval>

## Implementation Notes

- Route: oneshot, pinned by the session. The house threshold is at most 3 files; this touches 7, of which 3 are code or test (`admin/class-pqm-admin.php`, `includes/class-pqm-rest-api.php`, `tests/harness/quota-harness.php`) and 4 are records (README, release notes, brand.md, memory). About 15 changed lines, no schema, REST route shape or n8n template change (one error message string changes). The owner approved the change directly. Built in a scratch worktree on `fix/admin-post-quality-name` from main `68ebb91`. Implemented by the session (Opus 5.5).

- Verified: `php -l` clean on the three touched PHP files; all 19 CLI harnesses pass (quota 71/0 with the new message). Four of them first failed in the scratch worktree because the git-ignored `post-quality-manager/admin/build/` was absent there; with the main tree's build copied in (built after the last `src/` commit, no JS touched by this change) they pass as on main (free-build 83, n8n-adoption 88, n8n-managed 169, render-matrix 416). Jest not run: no JS changed.

## Review Triage Log

**Quick lens (Opus 5.5, 2026-10-23): 5 findings — 1 process note for the owner, 3 → patch (applied by the session), 1 → defer.**

1. process note — the route breaks the house rule: more than 3 files, and the `pqm_not_configured` message is part of a REST response, both of which call for the full route and its checkpoint. True; no code fix. The owner approved the slice, not the route; surfaced to the owner for confirmation.
2. low → patch — `admin/class-pqm-admin.php` L30 docblock and four `render-matrix-harness.php` fixtures still used the hook suffix `content-quality_page_…`; WordPress derives it from the menu title, now `post-quality_page_…`. Verified: nothing keys on the suffix at runtime (only `pqm-` substring checks and the captured hooks). Fixed; render-matrix 416/0.
3. low → patch — `n8n/workflow-diagram.md` L5 named "Content Quality → n8n Workflow". Fixed to "Post Quality → n8n Workflow".
4. low → defer — bug 59's reproduction step (`_bmad-output/backlog/bug-upgrade-links-lead-nowhere.md` L24) says "Content Quality → Account". A ticket file; this workflow does not edit tickets. Entry in deferred-work.md.
5. low → patch — the release-notes line omitted the Account page and the two heading changes. Fixed.

Re-verified after the patches: `php -l` clean; all 19 harnesses pass; `git grep "Content Quality"` outside `_bmad-output/` finds only the records of its retirement (brand.md, memory/naming.md, the release note, PORTFOLIO.md's decision table).
