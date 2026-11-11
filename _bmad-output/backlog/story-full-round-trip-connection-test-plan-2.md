---
title: 'The first review gives up on time and says why nothing came back'
type: 'feature'
build_of: '2'
created: '2026-10-22'
status: 'done'
baseline_revision: 'd914c0dff6cecb67a39120ce3b7cc88a076582fb'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On a site n8n cannot call back, the wizard's first review never lands: the `processing` row stays forever, every reopen of the dialog resumes the wait for a fresh three minutes, and the give-up line ("still running in n8n — results will appear when it finishes") promises a result that will not come, without naming where the result goes or why it could be lost (retrospective A8, story 2's goal b).

**Approach:** Measure the wait from the run's start (the `processing` row's `reviewed_at`), not from the dialog's mount, and replace the step's give-up line with one sentence naming the webhook URL the review went to, the site address n8n must reach to send the result back, and the two places to look: n8n's Executions and Test again on the n8n Workflow page, which since story 2 proves the way back.

**Decisions:** D1 (owner, 2026-10-21) this is story 2's goal (b), a build of its own, no template bump. D2 (owner, 2026-10-22) branch `feat/first-review-wait-cause`; implementer and review lenses on Opus 5.5 (session rule). D3 (agent) the limit stays the kit's `GIVE_UP_AFTER` (3 min); no server-side expiry of `processing` rows.

## Boundaries & Constraints

**Always:** copy per `docs/brand.md` (gentle, actionable, says what is known); every user string through `__()` with a translators comment for placeholders; `GET /setup` stays behind `pqm_manage_settings`; Jest proves every matrix row (jsdom, fake timers); `php -l` and the setup harness for the new field.

**Never:** no change to `usePolling`, Post Reviews, `giveUpMessage()` (other callers keep it), the template, `PQM_DB`, the trigger route or the ping; no new REST route; no automatic ping from the dialog; no cleanup of stale `processing` rows (ranked separately if ever needed).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Result lands in time | Run Review, a scored row within 3 min | unchanged: score line, `onComplete` | — |
| Run fails | an error row | unchanged failed notice | — |
| Nothing lands | Run Review, rows stay `processing` past 3 min | warning names the webhook URL, `rest_url`, Executions, Test again; Run Review enabled again | — |
| Resume, recent run | dialog opens on a post whose `processing` row is 1 min old | waits about 2 more minutes, then the same warning | — |
| Resume, stale run | `processing` row older than 3 min | the warning at the first poll, no fresh 3-minute wait | — |
| Unparseable `reviewed_at` | blank / zero date | falls back to the mount time (today's behaviour) | — |
| Missed poll | GET /reviews rejects | retried next tick; the limit still holds | — |
| No URL in state | `webhook_url` empty (should not happen: step needs `connected`) | sentence without the URL clause never renders a blank quote | — |

</frozen-after-approval>

## Code Map

- `src/setup/first-review.js` -- the step. L104–147 polling effect: `startedAt = Date.now()`; take the start from the first fetched row of command `review` while it is `processing` (`reviewed_at` is GMT `Y-m-d H:i:s`, per `class-pqm-rest-api.php` L588: parse as `${ s.replace( ' ', 'T' ) }Z`, `NaN` → keep mount time); check the limit after each fetch too, so a stale row gives up at the first poll. L263–271 give-up Notice: replace `giveUpMessage( 'review' )` + the Executions line with the new sentence (drop the `giveUpMessage` import if unused). New props `webhookUrl`, `restUrl`; update the header comment.
- `src/setup/index.js` L426 -- pass `webhookUrl={ state.webhook_url }`, `restUrl={ state.rest_url }`.
- `post-quality-manager/includes/class-pqm-rest-api.php` L1186–1191 `setup_state()` -- add `'rest_url' => rest_url()` beside `webhook_url`, one comment line (where n8n sends results, for the first review's give-up line).
- `tests/harness/setup-harness.php` L160–161 -- one row: `rest_url` present, equals the stubbed `rest_url()` (check `tests/harness/lib/wp-stubs.php` has the stub; story 2 relied on it).
- `tests/js/setup-first-review.test.js` -- new; mirror the mocks of `tests/js/setup-provision.test.js` (api-fetch, components, url) but render `FirstReview` alone with `jest.useFakeTimers()`; one test per matrix row.
- Reuse: `reviewsPath`, `requestRun`, `isFailedRow`, `inFlight` from `src/kit/runs.js`; `POLL_INTERVAL`, `GIVE_UP_AFTER` from `src/kit/use-polling.js`.
- Docs: `docs/user-paths.md` A4 UV bullet for story 2 -- name goal (b) built; `memory/admin-ui-rebuild.md` L15 `first-review.js` clause -- the wait counts from the run's start, the give-up line names the URLs. No `unreleased.md` line (no change sites must be told about).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` + `tests/harness/setup-harness.php` -- `rest_url` in GET /setup, one harness row -- the address the sentence names.
- [x] `src/setup/first-review.js` + `src/setup/index.js` -- wait from the run's start, the new give-up sentence, props -- the bounded wait and its cause.
- [x] `tests/js/setup-first-review.test.js` -- every matrix row -- proof.
- [x] `docs/user-paths.md`, `memory/admin-ui-rebuild.md` -- records.

**Acceptance Criteria:**
- Given a site n8n cannot call back, when the owner runs the first review and waits, then within 3 minutes of the run the step says the result did not come back, names both addresses and the two places to look, and Run Review is enabled.
- Given that run is still `processing`, when the dialog is reopened later, then the warning shows at once instead of a new wait.
- Given the sweep, when run, then every harness passes, Jest passes with the new file, `npm run lint:js` is clean.

## Implementation Notes

- Implementer: subagent on Opus 5.5 (session rule, D2), branch `feat/first-review-wait-cause`.
- Rendered by hand from the workspace root (BMad skills unregistered there, owner rule 2026-10-16); the step-03 implementer and the quick lens ran as subagents, the review patch went back to the same implementer. No commit by the agent (house rule; no blanket authorization given this session).
- `first-review.js`: `runStart()` parses `reviewed_at` as `${ at.replace( ' ', 'T' ) }Z`; the start is read once, from the first poll that sees the review row `processing`; the limit is checked before each fetch (missed polls) and after each `processing` fetch (stale row gives up at the first poll). The skew ceiling is the comment on `runStart()`. `gaveUpText()` holds the copy draft verbatim, with a second string (no `%1$s` clause) for an empty `webhookUrl`; "3 minutes" is literal in the copy, as drafted, so a change of `GIVE_UP_AFTER` must touch it too. `giveUpMessage` import dropped; `runs.js` untouched.
- `rest_url` = `rest_url()` (the REST root, e.g. `https://example.com/wp-json/`) in `setup_state()`; harness row added (setup 151 → 152).
- Tests: `tests/js/setup-first-review.test.js`, 9 tests (the unparseable row runs blank and zero dates); suite 149 → 158.
- Incident, fixed: a `wp-scripts lint-js --fix <file>` run linted beyond the file and rewrote `bin/zip.js` and `admin/js/pqm-admin.js` (both clean at baseline); both restored with `git checkout --`, untouched by this change.

- 2026-10-22, agentic walk on the owner's Local sandbox (the owner asked the agent to run the manual checks): the branch's plugin folder with a fresh `npm run build` copied over the installed 3.9.0 after a backup; the webhook URL set by WP-CLI to a stub on 127.0.0.1 that answers 200 and never calls back (n8n accepting the review, the callback lost); the dialog driven in headless Edge (Playwright) as user 1 through a one-hour auth cookie. The sandbox holds earlier results, so the page renders no "Run your first review" button; the walk clicked an injected button of the same class and `data-step="review"`, which the dialog's delegated listener opens like the page's. Results: check 1 passed (Run Review → the warning 181 s later, both addresses, Run Review enabled; the stub got one `review` POST); check 2 passed (reopened, the warning at the first poll, 4 s, no second POST); bonus matrix row "resume, recent run" passed (the row's `reviewed_at` set 60 s back → warning 114 s after the dialog opened). Cleanup: the walk's `processing` row deleted by id, the URL restored to empty, both login sessions destroyed, the plugin folder restored from the backup (diff identical), `pqm_setup_progress` and the usage meta unchanged. Seen, not from this change: on a site that already holds results the step shows "Done — the first result is in." under the warning (derived completion, story 36).

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, Opus 5.5, 2026-10-22): 2 findings — high 0, medium 0, low 2, false 0, maybe-false 0; 1 → patch, 1 rejected.**

1. low → patch — `_bmad-output/initiative-post-quality-manager/deferred-work.md`, goal (b)'s entry still reads as open; the story 78 entry above it was closed by its own build ("Resolved by …"). Verified by reading the entry. Patch: the same "Resolved by" clause naming this plan.
2. low → rejected — `src/setup/first-review.js` `runStart()`: a run started in this dialog also takes its start from the row's `reviewed_at`, so a server clock more than 3 min behind the browser gives up at the first poll (and one ahead lengthens the wait). Verified: the row is read on every waiting phase, fresh runs included. Rejected by the triage rule: unlikely in everyday use (a minutes-long skew between a WordPress host and the admin's browser), the plan's Design Notes accept the ceiling, and the fix adds a branch (read the row only on the resume path). Revisit with the upgrade path named in the code comment if a site reports it.

## Design Notes

Copy draft (the implementer may tighten, not widen):
"No result came back within 3 minutes. The review was sent to %1$s; n8n sends the result back to %2$s, which it must be able to reach — a local, private or password-protected site cannot be called back. Open Executions in n8n to see where the review stopped, or use Test again on the n8n Workflow page: it checks the way back in a few seconds."

Ceiling, one comment in code: the start comes from the server's clock, the limit from the browser's; a skew of minutes shortens or lengthens the wait by as much. Upgrade path: a server-computed age on the row, if a site reports it.

Skipped: expiring stale `processing` rows server-side — add when Post Reviews shows a never-ending spinner in the wild.

## Verification

**Commands:**
- `php -l post-quality-manager/includes/class-pqm-rest-api.php` -- expected: clean.
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 0 failed everywhere; setup +1 row.
- `npm run test:unit` -- expected: 149 + the new tests, all passed. `npm run lint:js` -- clean.

**Manual checks (Local sandbox, which n8n cannot call back — the very case):**
1. Open the setup dialog's first-review step, Run Review, wait 3 minutes -- expected: the warning with both addresses; Run Review enabled.
2. Close and reopen the dialog on the review step -- expected: the warning at once (the row is older than 3 min). Clean up: none needed beyond the `processing` row the run wrote (leave it or delete it with WP-CLI, owner's call).
