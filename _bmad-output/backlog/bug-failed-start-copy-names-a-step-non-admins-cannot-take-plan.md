---
title: 'A failed-start message names a step the clicking user can take'
type: 'bugfix'
ticket: '33'
created: '2026-10-06'
status: 'done'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '17d19d19e6d250c8e93b8189f04b43fc6da09937'
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** When n8n does not answer (502 `pqm_n8n_error`) or answers anything but 2xx (200 `success:false`), the failed-start notice tells every user to check the webhook URL in Settings or that the workflow is active in n8n. Authors and Editors can do neither. The non-2xx text also exists twice: the server builds it and the client run helper builds its own copy, which is the one the pages show.

**Approach:** Make both server messages role-aware with the split bug 32 already uses (`pqm_manage_settings`: today's step for admins, "ask an administrator" for everyone else), and have the client show the server's message for the non-2xx case instead of building its own, so each outcome has one wording. The trigger route's shape stays unchanged.

**Decisions:**
- The client's non-2xx copy is deleted, not made role-aware a second time. It shows the server's `message`, falling back to a generic "could not start" line only when the message is empty.
- Connection test on the n8n Workflow page (owner, 2026-10-06, default proposal): left unchanged. It is admin-only and tests the URL the admin just typed, so its step already fits its reader.
- Brand guide (owner, 2026-10-06, default proposal): the "Do" example about connecting to n8n gets a one-line role note: the Settings step is for administrators, everyone else is told to ask an administrator.

## Boundaries & Constraints

**Always:** copy per `docs/brand.md` (gentle, says what failed, names a step the reader can take); the `/trigger-review` response keeps `{success, message, n8n_status, quota_remaining}` and its status codes; the admin wording of both messages stays as it is today; `requestRun()` keeps resolving `{ ok, message }` and never rejects; translator comments on every changed string.

**Never:** change the not-connected copy from bug 32, the gate / quota refusals, the n8n template or payload, or the cron path (it never enters this callback); add a new role split or a new viewer field; touch the PHP admin banner.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Unreachable, admin | `wp_remote_post` returns `WP_Error`, user has `pqm_manage_settings` | 502 `pqm_n8n_error`, message unchanged: "Could not connect to n8n (<error>). Check your Webhook URL in Settings." | Inline notice (bug 32) shows it |
| Unreachable, non-admin | same, no `pqm_manage_settings` | 502 `pqm_n8n_error`, "Could not connect to n8n (<error>). Ask an administrator to check the n8n connection, then try again." | Inline notice shows it |
| Non-2xx, admin | n8n answers 500 | 200 `success:false`, `n8n_status` 500, message unchanged: "<Agent> could not start — n8n answered HTTP 500. Check that the workflow is active in n8n, then try again." | Client shows the server's message verbatim |
| Non-2xx, non-admin | same, no `pqm_manage_settings` | 200 `success:false`, "<Agent> could not start — n8n answered HTTP 500. Ask an administrator to check the workflow in n8n, then try again." | Client shows the server's message verbatim |
| Non-2xx, empty message | `success:false` with no `message` (older server) | client fallback "<Agent> could not start. Ask an administrator to check the n8n connection, then try again." | — |
| 2xx | n8n accepts | unchanged: processing row, "requested" toast, bulk line "Review requested" | — |
| Bulk report | any failed start in Review selected | the line prints "<title>: <server message>" with the role-aware text | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` -- `trigger_review_callback()`: read `current_user_can( 'pqm_manage_settings' )` once near the top (the not-configured branch L463-467 already branches on it; reuse the variable there). The `pqm_n8n_error` branch L520-530 and the non-2xx `message` L563-568 get the admin / non-admin pair. Keep `sprintf` args and translator comments; keep `n8n_status` and `quota_remaining`.
- `src/kit/runs.js` -- `requestRun()` L139-152: replace the client-built sprintf with `data.message`, fallback to a generic translated line; drop the now-unused `commandLabel` import only if nothing else in the file uses it (it does: `requestedMessage`, `giveUpMessage`, `outcomeToast` — keep the import).
- `src/post-reviews/bulk-bar.js` `outcomeLine()` L27-36 and `src/kit/use-single-run.js` -- read-only: they print `result.message` / `startError.message`, so they inherit the server text. No change.
- `tests/harness/quota-harness.php` -- Row 4 (L173-181) asserts only the 502 code; the non-2xx check (L290-293) asserts the admin text for `editor_caps()` (no `pqm_manage_settings`, so it will flip to the non-admin wording — the matrix says so, update the expectation to the non-admin text and add an admin case). Add the unreachable message for both roles. Admin caps: `editor_caps() + ['pqm_manage_settings' => true]` (bug 32 rows L296-307).
- `docs/brand.md` L32 -- the "Do" example sentence ("Could not connect to n8n. Check your Webhook URL in Settings."): append the role note from the Decisions, same bullet, one sentence.
- Not touched: `post-quality-manager/admin/class-pqm-admin.php` `ajax_test_connection()` (admin-only connection test, Decisions).
- `memory/admin-ui-rebuild.md` -- the ticket 32 bullet says "the `pqm_not_configured` copy is role-aware"; extend to all three failed-start messages and note the client shows the server's text.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- role-aware unreachable and non-2xx messages, one `$can_manage` read -- AC1, AC2
- [x] `src/kit/runs.js` -- show the server's non-2xx message, generic fallback -- AC3 one wording
- [x] `tests/harness/quota-harness.php` -- unreachable × role, non-2xx × role, response keys unchanged -- AC4
- [x] `docs/brand.md` -- role note on the n8n connection example -- Decisions
- [x] `memory/admin-ui-rebuild.md` -- durable fact -- DoD

**Acceptance Criteria:**
- Given n8n is unreachable or answers non-2xx, when a user who cannot manage settings starts a run from the detail panel, the Dashboard queue or Review selected, then the notice names the reason and tells them to ask an administrator, and never names Settings or n8n as their step.
- Given the same conditions, when a user who can manage settings starts a run, then the messages read exactly as today.
- Given a non-2xx answer, when the client shows the failure, then the text equals the server's `message`.

## Implementation Notes

## Plan Change Log

## Review Triage Log

Quick pass (1 lens), loop 0: 0 high, 0 medium, 0 low, 0 false, 0 maybe-false. The lens confirmed AC1-AC3, the unchanged response shape, the role split (`pqm_manage_settings` is admin-only and gates the n8n connection), and the translator-comment placement inside the ternaries.
- Harness hygiene (own read of the diff, not a lens finding): the new harness `echo` line carried a literal line break instead of `
`; fixed.
- Noted, not this change: the quota harness's existing reset-date info line prints a date one day early. It is pre-existing harness code that asserts only a non-empty value; not investigated further.

## Verification

**Commands:**
- `php -l post-quality-manager/includes/class-pqm-rest-api.php` -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green, quota harness carries the four new role rows
- `npm run lint:js` and `npm run build` -- expected: clean, bundle builds

**Manual checks (if no CLI):**
- Webhook URL at `https://127.0.0.1:9/webhook`. As an Author: Post Reviews → Details → Run Review shows "Could not connect to n8n (…). Ask an administrator to check the n8n connection, then try again." As an admin: today's "Check your Webhook URL in Settings."
- Webhook URL at a real n8n with the workflow inactive. As an Author: "Review could not start — n8n answered HTTP 404. Ask an administrator to check the workflow in n8n, then try again." Same text in a Review selected report line. As an admin: today's "Check that the workflow is active in n8n".
- Restore the URL and the workflow: a normal run is unchanged.
