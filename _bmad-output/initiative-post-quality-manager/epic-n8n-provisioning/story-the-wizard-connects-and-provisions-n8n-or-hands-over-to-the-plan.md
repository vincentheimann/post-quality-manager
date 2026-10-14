---
title: 'The wizard connects and provisions n8n, or hands over to the manual path'
type: 'feature'
ticket: '6'
created: '2026-10-14'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '314bdc4e706065b4cc46a941d5a87d7eceba3f3c'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md', '{project-root}/_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The setup wizard still walks a blogger through five manual n8n steps (get an instance, import, credentials, link, publish) before the connect step. The server-side provisioning (`POST /n8n/provision`, entries 4–5) has no UI. The policies say the plugin never sees the provider key and creates nothing on n8n, which has been false since entry 4.

**Approach:** Replace the wizard's n8n steps with two steps:
- **Connect your n8n:** the n8n Instance island, reused, with "Set up by hand instead" as a secondary link.
- **Set up the workflow:** the Anthropic key, typed once, sent to `POST /n8n/provision`.

The existing first review and configuration steps follow unchanged. The owner's manual choice is stored, and the manual path in the wizard becomes one step: the Workflow File section on the page, plus the existing paste-URL connect body. Update the policy texts to match.

## Boundaries & Constraints

**Always:**
- The Anthropic key goes from the field to `POST /n8n/provision` only. It is never kept in state after the request, never logged, and the field is cleared after the request.
- The wizard follows `ux-n8n-workflow-page/EXPERIENCE.md`: it opens at the step the site's state names. "Set up by hand instead" stores the choice and leads to the Workflow File section.
- A re-walk moves nothing. Steps already done stay done; a completed site opens on its first open configuration step, as today.
- Free and Pro run the same n8n steps. Provisioning is free (ADR 0003).
- Copy follows `docs/brand.md`. n8n UI words are verified against `n8n@2.42.3`, as in entry 2.

**Never:**
- The page restructure (story 67, after 6.12), adoption (6.8), update (6.9) or rotation (6.10).
- Changes to `PQM_N8n::provision()`, the template or `PQM_WORKFLOW_VERSION`.
- A second wizard or a second copy of the manual steps: the five manual dialog steps are removed, not reworded.
- Pushing to the `postquality.com` repository without the owner's word (see Tasks).

**Decisions (owner, 2026-10-14, plan approved as presented):**
- **D1:** plan length kept (about 2,300 tokens): one goal, and the policy wording ships with the story that makes it true.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Fresh site | no key, no URL, no choice | Opens on **Connect your n8n** | — |
| Connect passes | island POST, probe ok | The island tells the wizard; **Connect your n8n** is done; next is **Set up the workflow** | — |
| Connect fails, manual-path cause | probe `reason` set | The island shows its message; the step offers "Set up by hand instead" | — |
| Provision passes, ping ok | valid Anthropic key | Reply `provisioned`, `ping_ok`; local state `connected`; next is the first review; the status block re-reads | — |
| Provision passes, ping fails | `ping_ok:false` | The reply message is shown, plus the existing paste-URL connect body as the fallback | — |
| Pro without Application Passwords | `rewrite_ready:false` | Proceeds; the reply's Rewrite message is shown | — |
| Provision refused | `pqm_provider_key_invalid`, `pqm_provider_unreachable`, `pqm_provision_failed` (any step incl. `cleanup`), `pqm_n8n_not_connected` | The REST message is shown at the field; the key field is cleared; the step stays open | Error notice; nothing marked done |
| Provisioned, URL missing | setup map exists, no webhook URL | The step shows the paste-URL connect body instead of the key field (no 409 round trip) | — |
| Leftovers waiting | `pqm_n8n_leftovers` exists | An info notice above the key field: an earlier setup left objects in n8n; setting up removes them first | — |
| By hand, by choice | "Set up by hand instead" | `POST /setup {manual:true}` stored; the dialog closes; the page reloads at the Workflow File section (its choice line shows) | Write error shown in the dialog |
| Re-walk, manual | `manual` or a probe failure (`pqm_manual_reason()` not null), no passing key, no URL | Opens on **Set up by hand**: the reason, a link to the Workflow File section, the paste-URL connect body, and a collapsed "Connect by key instead" holding the island | — |
| Key path from the manual step | the island's probe passes there | `manual` turns false (a passing key outranks it); the wizard moves to **Set up the workflow** | — |
| Key path wins | key stored with a passing probe, `manual` set | Key path steps; the stored choice is ignored (as `pqm_manual_reason()`) | — |
| Already connected | webhook URL saved (any path) | Both n8n steps done; opens on the first review or the first open config step | — |
| Completed site | `complete` | As today: n8n steps done, first open config step or the finish panel | — |

</frozen-after-approval>

## Code Map

- `src/setup/steps.js` -- `STEPS` L538–585: remove `instance`, `import`, `credentials`, `link`, `publish` (and their bodies and helpers that only they use). Add `n8n` (Connect your n8n), `provision` (Set up the workflow) and `manual` (Set up by hand). Keep the `Connect` body (L438–531) as a component the `provision` fallback and `manual` reuse, and keep `review`. `stepsFor()` L594–609 chooses the key path (`n8n`, `provision`, `review`, config) or the manual path (`manual`, `review`, config) from `state.manual`. The header comment L1–8 (stale `render_credentials_list()`, "seven n8n steps") is rewritten: this resolves the deferred items on `steps.js:7` and on the dialog's outdated n8n labels, since the labelled steps are gone.
- `src/setup/index.js` -- `stepDone` L50–52, `resumeAt()` L61–66: `n8n` is done when `state.n8n.key_set && state.n8n.probe`, or when `connected`; `provision` and `manual` are done when `connected`. `onConnected` L158–165 patches local state; provisioning reuses it with the reply. The by-hand link POSTs `/setup {manual:true}`, then `window.location` goes to the page with `#pqm-workflow-file` and reloads. The intro line and button labels L197–214 stay (story 67).
- `src/n8n-connection/index.js` -- add an optional `onChange(publicState)` prop, called after every GET/POST/DELETE that changes the state. Nothing else changes; the page mount passes none.
- `post-quality-manager/includes/class-pqm-rest-api.php`:
  - `SETUP_STEPS` L984 and `SETUP_TICKABLE` L987: the new ids (`n8n`, `provision`, `manual` are derived, not tickable).
  - `setup_state()` L1021: add `n8n` (`PQM_N8n::public_state()`), `manual` (`null !== pqm_manual_reason()`), `provisioned` (the setup map exists) and `leftovers` (the leftovers option exists). The derived steps are computed here like `review`.
  - `update_setup_callback()` L1117: accept `manual: true` (writes `pqm_setup_progress['manual']`; only `true` is accepted) and add the arg to the route's schema.
  - Old step keys already stored in the option are ignored (not in `SETUP_STEPS`).
- `post-quality-manager/admin/class-pqm-admin.php` -- `render_workflow()` L1379: the Workflow File H2 gets `id="pqm-workflow-file"`.
- `docs/policies/privacy.md` L11, L14, L15 and `docs/policies/readme-external-services.md` L12, L14, L26: the provider key is forwarded once during setup (one request to Anthropic to check it, then into the n8n credential) and never stored. WordPress itself calls `api.anthropic.com` once during setup and the n8n API to create, activate and, on a failed setup, remove the credentials and workflow it made.
- `docs/release-notes/unreleased.md` -- one line: the setup guide now connects n8n by API key and sets the workflow up itself; the step-by-step manual setup lives in the Workflow File section of the n8n Workflow page.
- Tests:
  - `tests/js/setup-config.test.js` uses the `apiFetch` dispatcher L142–155, so its state fixtures need the new fields.
  - `tests/js/setup-connect.test.js` covers the `Connect` body.
  - `tests/js/n8n-connection.test.js` covers the island.
  - `tests/harness/setup-harness.php` calls the callbacks directly (L151–165; the `manual` rows are at L259).
  - `tests/harness/free-build-harness.php` covers the Free GET /setup.

## Tasks & Acceptance

**Execution:**
- [x] `src/n8n-connection/index.js`, `tests/js/n8n-connection.test.js` -- the `onChange` prop, plus a test that it fires with the public state.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php`, `post-quality-manager/admin/class-pqm-admin.php` -- the step lists, the new state fields, the `manual` write, and the section id.
- [x] `src/setup/steps.js`, `src/setup/index.js` -- the three steps, `stepsFor`, `stepDone`, the by-hand handoff, the provisioning body (password field, busy state, error notice, ping-fail fallback, leftovers notice, the Rewrite message on Pro).
- [x] `tests/js/setup-provision.test.js` (new) -- one test per matrix row that only a browser shows: the step chosen on open, provision success / ping fail / error (key cleared), by hand, re-walk. Update `setup-config.test.js` and `setup-connect.test.js` fixtures.
- [x] `tests/harness/setup-harness.php`, `tests/harness/free-build-harness.php` -- GET /setup fields and derived steps per state, `POST /setup {manual:true}` (and `false` or other values refused), old stored step keys ignored, the Free build's same steps.
- [x] `docs/policies/privacy.md`, `docs/policies/readme-external-services.md`, `docs/release-notes/unreleased.md` -- the texts above.
- [x] `memory/n8n-provisioning.md` -- the wizard line; `deferred-work.md` -- mark the two `steps.js` items resolved.

**Acceptance Criteria:**
- Given a fresh site with a reachable n8n and valid keys, when the owner walks the wizard, then they type only the n8n address, the n8n API key and the Anthropic key before the first review step.
- Given any GET /setup or wizard state, when the replies and the rendered dialog are read, then the Anthropic key appears in none of them after the request.
- Given the policy texts, when read, then they say the provider key is forwarded once during setup and never stored, and they name WordPress's own calls to Anthropic and to the n8n API.

Skipped: page restructure and button labels (story 67). Skipped: clearing the stored manual choice itself; a passing key outranks it (`pqm_manual_reason()`), so the manual step's "Connect by key instead" is the way back.

After merge (owner's word needed): sync the `postquality.com` privacy page with both policy changes (entries 1 and 6) in one site PR, before any release that ships this entry (entry 1 plan D3).

## Review Triage Log

Pass 1 (2026-10-14, lens `quick`): 0 high, 5 medium, 9 low, 0 false, 0 maybe-false. Routes: 9 patched, 1 rejected (low), 2 deferred, 1 held for the owner (a file deletion); finding 2's probe-over-choice part is entry 2's D2, not a defect. All harnesses, Jest 68/68 and lint were green before the pass.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | The Workflow File intro still says the wizard "walks you through each step", looping a reader sent there by "Set up by hand instead" | medium | patch | `class-pqm-admin.php` ~L1550, shown while setup is incomplete, which is exactly the by-hand reader. |
| 2 | "Set up by hand instead" stays offered after the key check passed, where the stored choice has no effect | low | patch | `pqm_manual_reason()` lets a passing key outrank the choice; the link renders unconditionally in `ConnectN8n`. Fix: hide it once the step is done. |
| 3 | `src/setup/copy-value.js` and its `.pqm-copy-value` rules are dead | low | owner | Grep: no importer left; the PHP rows use `.pqm-copy-row`. Deleting a file needs the owner's word (AGENTS.md). |
| 4 | GET /setup still returns `token`, `import_url`, `download_url`, `app_passwords`, `user_login`, `site_host`; nothing reads them | medium | patch | Grep: only the harness reads them; the webhook token travels in every reply for nothing. Removal is a deletion. |
| 5 | README.md L46/L62 describe the removed manual steps and the old `/setup` row | low | patch | Docs must match the change (DoD). |
| 6 | `memory/admin-ui-rebuild.md` L15 still states the seven-step dialog and the connect tick | low | patch | Memory is updated in the change that makes a fact true. |
| 7 | `docs/user-paths.md` A4 CV still describes the removed dialog | low | defer | The epic gives user paths to entry 12; logged in deferred-work.md. |
| 8 | `class-pqm-n8n.php:346` comment names the setup dialog as a carrier of the deterministic names | low | defer | Inside `provision()`, which the frozen Never excludes; comment only; logged for entry 11's sweep. |
| 9 | After "provision passes, ping fails", `provision`/`manual` read done because a URL is saved; reopening loses the paste-URL fallback | medium | patch | The diff removed entry 4's `steps.connect = ping ok` writes; failure-modes.md requires "setup marked incomplete". Fix restores the writes and derives done = connected and no recorded failed ping, which keeps the "Already connected" row (no record counts as done). This patch amends the Code Map's derivation rule. |
| 10 | The leftovers notice appears only after a reload, not on the retry right after the failure | low | reject | The error message already says "Some of what it created is still in n8n: the next try removes it first."; the fix would add a re-fetch. |
| 11 | Harness rows "manual: 1 / "true" refused" claim endpoint behaviour real WordPress never reaches (the boolean schema coerces) | low | patch | Relabel as callback-level guards. |
| 12 | `privacy.md` L14 names only the page for the n8n API check; the wizard runs it too | low | patch | Direct correction. |
| 13 | "Show the Workflow File section" scrolls without moving focus | medium | patch | EXPERIENCE.md Accessibility Floor: focus moves to the heading. Fix: `tabindex="-1"` + focus. |
| 14 | `src/n8n-connection/index.js:3` says "reused unchanged" while the diff adds `onChange` | low | patch | Comment only. |

## Design Notes

- The island is reused, not re-implemented: entry 1 built it as a React island for this reason. The wizard's "done" for **Connect your n8n** is the island's public state, so a key stored earlier on the page counts.
- The manual path in the wizard does not repeat the steps: EXPERIENCE.md's one-place rule puts them on the page. The wizard step links there and keeps the paste-URL body so the wizard can still finish and run the first review.
- Labels from EXPERIENCE.md: "Connect your n8n", "Set up the workflow", "Set up by hand instead". Final copy is written at build.

## Verification

**Commands:**
- `php -l` on each touched PHP file; `node --check` on each touched JS file -- expected: clean
- `for h in tests/harness/*-harness.php; do php "$h" || echo "FAIL $h"; done` -- expected: no FAIL
- `npm run test:unit` and `npm run lint:js` -- expected: green
- `npm run build` -- expected: builds

**Manual checks:**
- The owner's walk of the wizard on the environment they name, with a dedicated expiring n8n key: fresh key path to the first review, and by hand by choice. This doubles as entry 5's real-instance forced failure if the owner wants one (a wrong Anthropic key leaves nothing; a refused step is shown).
