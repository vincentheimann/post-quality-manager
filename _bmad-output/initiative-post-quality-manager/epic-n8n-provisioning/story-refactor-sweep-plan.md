---
title: 'Refactor sweep'
type: 'refactor'
ticket: '11'
created: '2026-10-16'
status: done
baseline_revision: '431bfac6d4b347f46abd51533f4a77e1347f5b13'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Ten entries built the n8n client (`PQM_N8n`, 1,172 lines, 45 methods) one feature at a time. Three things drifted: every refused n8n call reads "The n8n setup stopped while …", also for a read, a token rotation and a template update where no setup runs (rejected as out of scope by the 6.10 and 6.9 reviews); the same two checks (a key with a passing probe; a JSON 404 meaning "gone") are written three times each; and the records lag the code (the policies never say a test ping reads the workflow, `AGENTS.md` lists one React island where there are two). The ticket: cleanup from the build records and the deferred findings, behaviour unchanged.

**Approach:** Scope set at the start (owner, 2026-10-16), cleanup only: one error sentence per kind of action, one helper per repeated check, the two record fixes, the deferred-work entries closed. Every harness assertion that pins a sentence moves with it; no assertion is weakened, no route, reply shape, option or n8n call changes.

## Boundaries & Constraints

**Always:** behaviour unchanged: the same routes, replies, error codes and HTTP statuses, the same n8n calls in the same order, the same options written; the harness sections of entries 1–10 keep every row (a pinned sentence is updated to the new wording, never dropped); the source-regex rows of `n8n-api-harness.php` (`propose_adoption`, `rotate_token` … `managed_blocked`, `update_workflow`) keep matching, so those method names stay and no new helper between `rotate_token()` and `managed_blocked()` calls `request()`. Copy per `docs/brand.md`: precise, the action named, the fix named. Every deferred-work entry this sweep settles gets a `resolved:` line.

**Never:** a new feature, a new route, a new option or REST field; a change to the templates, `PQM_WORKFLOW_VERSION`, the status block's structure, the islands' behaviour, the wizard, the Settings page; a change to `docs/user-paths.md` or to entry 5's ticket note (both entry 12's); a dependency; a rename that a harness source regex pins.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Provisioning refused | A create or activate call fails during `provision()` | "The n8n setup stopped while creating the workflow (n8n answered HTTP 400)." + the rollback ending, as today | — |
| Adoption read refused | `propose_adoption()`'s list read fails | "The plugin could not finish reading your workflows (n8n answered HTTP 500). Try again in a moment." (502 `pqm_adoption_failed`, as today) | — |
| Rotation refused | The PATCH fails | "The plugin could not finish updating the webhook credential (…). Try again in a moment." (502 `pqm_rotation_failed`) | — |
| Update refused | The GET or the PUT fails | "The plugin could not finish reading your workflows (…)" / "… updating the workflow (…). Try again in a moment." (502 `pqm_update_failed`) | — |
| 2xx without JSON | Any of the above | The per-step answer as today ("n8n did not answer with your workflows", "n8n did not confirm the change", "n8n did not confirm the update", "n8n did not return what it created") | — |
| Gone | A JSON 404 on a credential PATCH or a workflow GET | The same two "no longer in n8n" sentences as today, now through one helper | — |
| Gates | Adoption and managed gates | The same codes, statuses and sentences; the passing-key check through one helper | — |
| Policies | A reader of `docs/policies/*.md` | One clause says that, with an n8n API key stored, a test ping of the saved webhook URL also reads the workflow through the n8n API, changing nothing | — |
| Agent instructions | `AGENTS.md` managed block | Names both React islands on the n8n Workflow page (the setup dialog `src/setup/`, the n8n Instance section `src/n8n-connection/`) | — |
| Gates green | The whole suite | Every harness, Jest, lint and build pass with the same counts plus the rows this sweep renames | — |

## Decisions (owner, 2026-10-16)

Approved as proposed at the plan checkpoint. Implementer: Fable 5.1 (the owner's standing choice since entry 6.9: "I'll explicitly tell if I need Opus").

- **D1 — `AGENTS.md`:** the two sentences inside the managed block are hand-edited in this sweep and the plan says so; the project-context skill's next audit or refresh re-verifies the block.

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` --
  - `stopped()` L1010–L1045: keep it for the provisioning steps (`credential_*`, `workflow`, `activate`; `fail()` appends its own endings). Add private `could_not( string $step, $response ): string` = "The plugin could not finish %1$s (%2$s). Try again in a moment." with the same `$labels` and `$answer` logic (extract both into one private `answer( $step, $response )` and a `$labels` constant or static so the two templates share them); used by the four callers that today append `' ' . __( 'Try again in a moment.' )` to `stopped()`: `workflow_at()` L476 (`read`), `rotate_token()` L1074 (`credential`), `update_workflow()` L1141 (`read`) and L1167 (`update`).
  - `gone( $response ): bool` = `! is_wp_error( $response ) && 404 === $response['code'] && null !== $response['json']` (the subexpression `removed()` L980 also carries): used at L1072, L1139 and inside `removed()`.
  - `connected_key(): bool` = `'' !== $connection['api_key'] && ! empty( $connection['probe']['ok'] )` from `connection()`: used by `adoption_blocked()` L642 and `managed_blocked()` L1089 (both then `return self::not_connected()` as today). Place the new helpers after `update_workflow()`, outside the `rotate_token … managed_blocked` regex span.
  - Docblocks that say "setup stopped" for the later actions: reword with the sentence.
- `tests/harness/n8n-api-harness.php` -- the pins: L1148 and L1585 (`"The n8n setup stopped while reading your workflows ($says). Try again in a moment."` → the new sentence), `ROTATION_STOPPED` L1261, `UPDATE_STOPPED` L1441; the provisioning rows keep "The n8n setup stopped while …". The source regexes L1249, L1429, L1640 unchanged.
- `tests/harness/free-build-harness.php` -- grep for "setup stopped" (none expected; confirm).
- `docs/policies/privacy.md` L14 and `docs/policies/readme-external-services.md` L12, L26 -- after the Connect / Check again clause: "When you send a test ping with an n8n API key stored, the plugin also reads the workflow at your webhook address through n8n's API, to check which credentials its nodes use; nothing on n8n changes." (6.8 deferred entry; the site copy follows through story 68).
- `AGENTS.md` L8 (inside the managed `bmad:context` block) -- "the n8n Workflow page also mounts one React island, the setup dialog (`src/setup/`)" → "mounts two React islands, the setup dialog (`src/setup/`) and the n8n Instance section (`src/n8n-connection/`)"; L22's React sources list gains `n8n-connection/` (6.1 deferred entry; D1: a hand edit inside the managed block, noted here).
- `_bmad-output/initiative-post-quality-manager/deferred-work.md` -- `resolved:` lines on: the 6.6 entry on the `provision()` comment (already gone since entry 9's `credential_names()`, resolved by reference), the 6.8 policies entry, the 6.1 `AGENTS.md` entry (D1).
- `memory/n8n-provisioning.md` -- an Entry 11 line (the helpers, the two sentence templates, the records closed).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `could_not()`, `answer()`, `gone()`, `connected_key()`; the four callers and the three duplicated checks rerouted; docblocks -- one sentence per kind of action, one helper per repeated check.
- [x] `tests/harness/n8n-api-harness.php` -- the four pinned sentences moved to the new wording; nothing else -- the rows prove behaviour unchanged.
- [x] `docs/policies/privacy.md`, `docs/policies/readme-external-services.md` -- the test-ping clause.
- [x] `AGENTS.md` -- the two-island sentence and the sources list (D1: a hand edit, noted).
- [x] `_bmad-output/initiative-post-quality-manager/deferred-work.md`, `memory/n8n-provisioning.md` -- the `resolved:` lines, the Entry 11 line.

**Acceptance Criteria:**
- Given the whole suite after the sweep, when every harness, `npm run test:unit`, `npm run lint:js` and `npm run build` run, then all pass and the harness counts equal the counts before the sweep (2,149; n8n-api 450).
- Given the diff, when read, then no route, reply key, error code, HTTP status, option key, n8n call or template changed; only sentences, helpers and records.

## Implementation Notes

- Built 2026-10-16 by a Fable 5.1 implementer subagent in a scratchpad worktree, from the plan. The plan's claim that entry 9 had removed the 6.6 comment drift was false: the drift had moved into `credential_names()`'s docblock; the implementer fixed it there and the `resolved:` line says so. Two row labels in `n8n-api-harness.php` that named `stopped()` now name `could_not()` (label text only). `label()` is a static method rather than a constant: the labels need `__()` at runtime. The readme's suggested privacy text (L26) also gained the test in its action list, which the Code Map had not named.
- Harness counts equal before and after: 2,149 (n8n-api 450, render-matrix 378, free-build 77); Jest 132; lint and build clean; `php -l` clean. Re-run by the session with the same counts.
- Review patches (pass 1, rows 1–2) applied by the same implementer: the Jest fixture and the memory Entry 8–10 lines moved to the `could_not()` wording. Gates re-run by the session after the patches: harness 2,149 (equal), Jest 132, lint and build clean. No commit (house rule).

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-16): 3 findings: 0 high, 0 medium, 3 low, 0 false, 0 maybe-false. 2 patches, 0 deferred, 1 rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | The Jest fixture for a refused update still quotes "The n8n setup stopped while updating the workflow (…)" as the server's message; the test passes because it echoes its own mock | low | patch | Verified at `workflow-status.test.js` L533/L537. The plan's Always clause moves every pinned sentence; the Code Map missed the Jest file. Both strings changed to the `could_not()` wording. |
| 2 | The memory Entry 8, 9 and 10 lines still name `stopped('read'|'update'|'credential')` for the paths that now go through `could_not()`, contradicting the Entry 11 line | low | patch | Verified at L14–L16. Four mentions replaced; memory is "what is true now". |
| 3 | `privacy.md` L14 also rewords "When you set the workflow up from the setup guide" to "When you let the setup guide set the workflow up", outside the Code Map | low | reject | Verified: same meaning, aligns with the readme's wording, a cleanup inside a cleanup sweep whose intent includes the records; no harm named. Noted here instead of reverting. |

## Design Notes

Why two templates and not one neutral sentence: "The n8n setup stopped while creating the workflow" is right during provisioning (a setup is running, and `fail()` goes on to say what was rolled back); the later actions are single calls on a site already set up, so "The plugin could not finish updating the workflow (n8n answered HTTP 500). Try again in a moment." names the action and the fix in one sentence (brand: precise, the action named).

Skipped: folding `adoption_blocked()` and `managed_blocked()` into one gate (they differ on the map: one refuses with it, the other without it; a shared helper would need a flag). Skipped: moving the n8n client's read helpers (`workflow_at`, `published_nodes`, `trigger`, …) into a second class — the file is long but each entry's section is contiguous and the harness regexes read it as one file. Skipped: the `pqm_webhook_url_changed` wording (made action-neutral in 6.9). Skipped: user-paths A4 and entry 5's ticket note (entry 12).

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: every harness passes, the same counts as before the sweep.
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- expected: green.
- `php -l` on the touched PHP file -- expected: no errors.
- `git diff --stat` -- expected: only the files in the Code Map.

**Manual checks (if no CLI):**
- None: a refactor with every behaviour pinned by the harnesses. The sentences are read once in the diff against `docs/brand.md`.
