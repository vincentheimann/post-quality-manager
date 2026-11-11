---
title: 'Automated Reviews can schedule seo and audit, not only review'
type: 'feature'
ticket: '3'
created: '2026-10-06'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '5f7014caf10d7cb1a55bf42fca7d6e57b287b7f9'
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `cron_command` is locked to `review`: the select renders disabled with one option, and the sanitizer forces the default. Real usage runs `seo` and `audit` regularly by hand (docs/user-paths.md B1, UV6). Their results already render through the generic `result_data` renderer.

**Approach:** On a pro site the Automated Reviews section offers one command per schedule: Review, SEO or Audit, labelled from the registry (`PQM_Features::label()`). The sanitizer accepts exactly those three from a posted section and falls back to `review` for anything else. The carry rules from bug 19 and stories 13/16 stay as they are. The batch selects candidate posts by the scheduled command's own latest result (`PQM_DB::get_posts_needing_review()` gains a `$command` parameter, default `review`), so a scheduled audit moves on to other posts instead of picking the same ones every run.

**Decisions (owner's standing approval of default proposals, 2026-10-06):**
- One command per schedule, not multi-select (the ticket's KISS default).
- Schedulable commands are `review`, `seo` and `audit`, the ones the ticket names. `rewrite` (it changes the post), `takeaways`/`sources` (insert-oriented), `score` and `apply` stay off the list.
- Dashboard tiles, chips counts and the score trend stay review-only: they measure editorial quality on one scale, and seo/audit scores use other scales. A scheduled audit shows in the post's Audit tab and in its row command chips (both already read every command). This reads the ticket's "dashboard counts them" as "results show where results of that command show". Flagged to the owner.
- A paid command scheduled on a site that later lapses to free is skipped by the existing cron gate (`plan_allows`), and the off-switch (story 13) still works.

## Boundaries & Constraints

**Always:** the pro path's carry for absent sections (bug 19), the `pqm_manage_agents` gate (story 16) and the free off-switch (story 13) are unchanged. Copy per `docs/brand.md`. The n8n template is unchanged (it already accepts seo/audit; cron sends the same payload as the REST trigger).

**Never:** schedule `rewrite`. Write a `processing` placeholder from cron (not in scope). Change dashboard or list aggregates. Add a DB column.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Pick audit | pro, agents-holder posts section with `cron_command=audit` | stored `audit` | — |
| Smuggle rewrite | pro, posts `cron_command=rewrite` (or `foo`) | stored `review` | — |
| Absent section | pro, POST without the cron knobs | `cron_command` carried (bug 19) | — |
| Free site | posts `cron_command=seo` | carried (locked) | — |
| Render pro | agents-holder | enabled select: Review, SEO, Audit (registry labels), stored one selected; old "Only review" line gone | — |
| Render free | agents-holder | select inside the locked fieldset, stored one selected | — |
| Batch selection | `cron_command=audit` | `get_posts_needing_review(…, 'audit')`: the latest audit row per post decides (score, age); posts never audited are candidates | — |
| Batch default | `cron_command` missing | `review`, as today | — |
| Free site, seo scheduled | lapsed site | batch skipped by `plan_allows` (existing) | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` render ~L910-918 -- the disabled `pqm_cron_command` select with one `review` option and the "Only "review" is available in this version." line. Make it an enabled select over `['review','seo','audit']` labelled via `PQM_Features::label()`, `selected($settings['cron_command'], …)`, plus a short description. It stays inside the locked fieldset on free sites as today.
- Same file, `sanitize_settings()` pro branch ~L381 `$clean['cron_command'] = $defaults['cron_command'];` → whitelist the three commands, else the default. `$cron_knobs` (the "section posted" detector, ~L373) stays without `cron_command`.
- `post-quality-manager/includes/class-pqm-db.php:440-474` `get_posts_needing_review( $min_score, $max_age_days, $limit )` → add `string $command = 'review'`. Replace the hard-coded `command = 'review'` in the inner subquery (L453-456) with a `%s` placeholder, and add `AND r1.command = %s` to the outer r1 join (L457). Today that join matches post_id + reviewed_at only, so a same-timestamp row of another command could be taken. Keep the `$wpdb->prepare` arg order right.
- `post-quality-manager/includes/class-pqm-cron.php:68-78` -- pass `$command` to `get_posts_needing_review()`.
- `tests/harness/sanitize-matrix-harness.php` -- L199/L210 and L404-408 assert `rewrite` → `review` (keep). Add `audit` and `seo` kept on pro, `foo` → review, free carry.
- `tests/harness/render-matrix-harness.php:187-190` -- the select-inside-the-fieldset check (keep). Add the pro options/labels/selected row, and check the old line is gone.
- `tests/harness/cron-gate-harness.php` -- its DB stub ignores the command (L63). Record the received `$command` and assert `audit` / default `review` reach `get_posts_needing_review()`. Also add a SQL-shape check (via a wpdb stub, in whichever harness already stubs `$wpdb->prepare`, e.g. `rest-posts-harness.php`) that the command is a prepared `%s` in both the subquery and the r1 join.
- `memory/freemium-and-roles.md` (cron bullet) or `memory/llm-options-architecture.md` -- one line: the schedulable commands, selection per command, dashboard stays review-only.
- `docs/user-paths.md` -- if UV6 / B1 records this as Unrealized Value, move the relevant line to Current Value (DoD).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- select + sanitizer whitelist
- [x] `post-quality-manager/includes/class-pqm-db.php` + `class-pqm-cron.php` -- selection per command
- [x] `tests/harness/*` -- the matrix rows
- [x] `memory/…`, `docs/user-paths.md` -- facts and value

**Acceptance Criteria:**
- Given a pro site whose schedule is set to Audit, when the cron batch runs, then it triggers `audit` for posts chosen by their latest audit, and the results appear in each post's Audit tab and row chips.

## Implementation Notes

- The whitelist is one private constant, `PQM_Admin::CRON_COMMANDS`, shared by the select and the sanitizer. The select lost its `disabled` attribute: the locked fieldset already keeps it out of a free site's POST.
- Description copy under the select: "The agent that runs on each schedule. Posts are picked by their latest result from that agent."
- SQL-shape check sits in `rest-posts-harness.php` (it already stubs `$wpdb->prepare`); it defines `DAY_IN_SECONDS` when missing.
- `docs/user-paths.md` B1: the old UV line moved into CV as a pending-validation note; the remaining UV is one command per schedule and review-only dashboard counts. EBM row 6 annotated, not removed.

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 1 medium, 2 low. Two patched, one rejected. All 10 harnesses exit 0.
- medium, patch: on a free site with stored cron on and a paid command scheduled (seo/audit), the off-switch note promised runs that `plan_allows` already skips. The off-switch state now also requires `plan_allows(cron_command)`; otherwise the section is fully locked with the normal note (render row added).
- low, reject: SEO's coarse 85/55 score against the one Min Score knob re-picks non-passing posts every run. That is the knob's existing meaning ("re-run posts scoring below this value"), the same as for Review. The defect this story fixed was selection never moving at all, and it is fixed.
- low, patch: the section's labels still said "review" though it now schedules SEO or Audit. Reworded to the scheduled agent (Enable, batch, min score, max result age).

## Verification

**Commands:**
- `php -l` on touched PHP; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

**Manual checks (if no CLI):**
- Pro site: set Automated Reviews to Audit, run `wp cron event run pqm_cron_review_posts`; audit rows appear in Post Reviews' Audit tab for the batch's posts; a second run picks different posts.
