---
title: "The n8n Workflow page shows one next step for the site's state"
type: 'feature'
ticket: '67'
created: '2026-10-19'
status: 'built'
baseline_revision: '0cf936b6c0f89904039f9853d8d9e005bd96a1f6'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The n8n Workflow page stacks every section in every state (status line, n8n Instance island, Workflow File's four steps, the per-agent table, Connection Test), the webhook URL is edited on Settings, and the page does not follow the wizard: after a connect, provisioning or adoption inside the dialog the page island still shows the old state. A blogger at the setup wall (A4, UV4) has to work out alone which part applies to them.

**Approach:** The page is rebuilt to the approved experience spine: one PHP renderer resolves the site's state (needs attention > set up > manual path > connected, not set up > not set up) and renders the whole body under the H1: a status block (state sentence, version and last check, **Test again**), exactly one primary action, the state's body, then the three collapsed disclosures (Set up by hand, n8n connection, Webhook) the state allows. Every reply that carries `status_html` carries that whole body, so one swap re-renders the page and remounts the n8n connection island fresh; the setup dialog's close triggers the same refresh. The webhook URL moves here (step 4 field in the manual path, the Webhook disclosure otherwise, saved through the existing `POST /setup/connect`), **Rotate token** and **Update workflow** become two buttons of one REST-action handler, the per-agent table moves to Settings → AI, Settings → Connection shows the URL read-only and links for the token, and the retrospective's A7 copy pass lands.

## Boundaries & Constraints

**Always:** Native `<details>` disclosures, collapsed, no remembered state; one `.button-primary` on the page in every state (the status block's), every island button secondary on the page. The status block is one `aria-live="polite"` region whose content is replaced, never the region itself. All page-local writes go through the existing routes: `POST /setup/connect` (URL), `POST /n8n/token`, `POST /n8n/workflow`, AJAX `pqm_test_connection` and `pqm_workflow_status`; REST writes sanitize in place. `sanitize_settings()` keeps the stored `n8n_webhook_url` and `webhook_token` whatever the Settings form posts (the disabled-controls pitfall). Free and Pro render every state without a locked or empty section; Pro code stays under `is__premium_only()` markers. Copy per `docs/brand.md`; the spine's draft sentences are the starting copy. Styles in `admin/css/pqm-admin.css`, no inline styles.

**Never:** change a REST route's contract, `PQM_WORKFLOW_VERSION`, the template, the version handshake, what any capability does in n8n, or the wizard's steps and their order. No second primary button, no new colour, icon set or card grid. No new npm or Composer dependency. Not this story: the round-trip test and the bounded first-review wait (story 2), a confirmation before Update on an adopted workflow (76), Start over (71), the Dashboard and Post Reviews entry points (they keep linking here), the welcome notice's own rule (setup complete).

## I/O & Edge-Case Matrix

State inputs: `pqm_get_settings()['n8n_webhook_url']`, `pqm_workflow_report()` (`version`, `at`, `via`, `variant`, `miswired`, new `failed`), `PQM_N8n::connection()` (`api_key`, `probe`, `failed`), `pqm_manual_reason()`, the setup map, leftovers, `PQM_DB::has_result()`, `PQM_N8n::managed_blocked()`.

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| 1 Not set up | No URL, no key, no manual choice, no manual-path probe failure | "Not connected to n8n yet." · primary **Set up n8n** (opens the wizard at its first open step) · one paragraph naming the three values · link "Set up by hand instead" · no disclosure | — |
| 2 Connected, not set up | Key with a passing probe, no map, no URL | "Connected to n8n at {host}. The workflow is not set up yet." · primary **Set up n8n** · leftovers → info notice "An earlier setup left a workflow or credentials in n8n. Setting up removes them first." · disclosures: n8n connection; "Set up by hand instead" link | — |
| 3 Manual path | No URL and (manual choice stored, or last probe failed with a manual-path cause) | "Setting up by hand." + the reason sentence (choice / "On {date}, the n8n connection check did not pass: {cause}.") · Set up by hand open, its four steps; step 4 holds the URL field and primary **Save and test** · disclosure: n8n connection (Connect, secondary) | Empty or test URL: `save_webhook_url()`'s refusal in the block's result line, nothing saved |
| 4 Set up | URL saved, report present, no failure, version current, variant ok, not mis-wired | "Your workflow answers. Version {n} · checked {when} by a test / by a command." · primary: no stored result → **Run your first review** (wizard, review step); results → **Open Post Reviews** · disclosures: n8n connection (managed line; adoption offer when `adoptable`), Webhook (URL + Change, token row, **Rotate token** when `rotatable`), Set up by hand (hand-made setup only: no map) | — |
| 4a Saved, not checked | URL saved, no report | "Saved, not checked yet." · primary **Test the connection** · same disclosures | — |
| 4b Pro, no Application Passwords | State 4 on Pro, `wp_is_application_passwords_available_for_user()` false for the credential's user (the check `render_wordpress_credential__premium_only()` already makes) | A warning line under the status block: Rewrite stays off until a WordPress credential is added | — |
| 5 Ping fails | URL saved, `report.failed` set (a ping of the saved URL did not pass) | "The last test on {when} did not reach your workflow: {message}." · primary **Test again** · disclosures: n8n connection, Webhook, Set up by hand (hand-made only) | — |
| 5 Mis-wired | `report.miswired` set | The diagnosis sentence · primary **Test again** · the fix button secondary | — |
| 5 Outdated / Free variant on Pro | `report.version < PQM_WORKFLOW_VERSION`, or `variant = free` on Pro | "Your workflow is version {v}; this plugin needs {n}." / the Free-variant sentence · managed → primary **Update workflow**; hand-made → primary **Re-import the workflow**, which opens Set up by hand at step 1 (unpublish-first note kept) | Update's refusal (409, 502) in the block's result line |
| Any action reply | AJAX ping, `pqm_workflow_status`, `/setup/connect`, `/n8n/provision`, `/n8n/token`, `/n8n/workflow` | `status_html` = the whole page body rendered from the fresh state, with the action's outcome sentence as its first line; the client swaps the body's content and dispatches `pqm:workflow-status`; the island remounts and re-reads `GET /n8n/connection` | A network error: the button re-enabled, the outcome line says the request did not go through |
| Failure record | A ping of the saved URL fails (AJAX, wizard connect, update, rotation) | `report.failed = {message, at}` stored; a passing ping clears it; a URL change or a download deletes the report as today | — |
| Dialog closes | Any close (button, Escape, click outside) after connect, provision, adopt or by-hand | The page refreshes through `pqm_workflow_status` (same path as the window-focus refresh): the body shows the new state, no reload; by-hand hands over to state 3 with Set up by hand open and focused | — |
| Settings save | Settings form posted by an Administrator, URL and token fields absent | Stored URL and token kept; the Connection tab shows the URL read-only with "Change it on the n8n Workflow page" and the token row as a link there; no Send test ping; Notification URL unchanged | — |
| Free build | Every state above | Same layout; the manual steps' Free variants; no Pro-only row (no WordPress credential, no 4b, no Free-variant notice) | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- `render_workflow()` L1362–L1432 (becomes H1 + setup root + `<div id="pqm-workflow-body" aria-live="polite">` + the body renderer); `render_workflow_status()` L1442–L1547 (becomes `render_workflow_body(string $outcome = ''): string`, the one renderer behind every `status_html`: resolver → status block → primary action → body → disclosures; keep the mis-wired, outdated and Free-variant notice logic and their classes `.pqm-test-connection`, `.pqm-update-workflow`, `.pqm-reimport`); new private `workflow_page_state(): array` = `{state, reason, fix, managed, has_result, …}` in the resolution order of the matrix; `render_workflow_file()` L1561–L1685 → rendered inside the Set up by hand disclosure, step 4 gains the URL field + **Save and test** (`.pqm-save-webhook`) and loses the Settings link L1675–L1680; intro L1595–L1597 and reason notice L1598–L1609 move to the status block (state 3's sentence); the per-agent table L1386–L1419 → a private `render_agent_table()` called from the AI panel after L938; Connection tab L780–L821: URL row → read-only text + link, Send test ping gone, token row → link, Notification URL kept; `sanitize_settings()` L369–L375: both keys from `$existing`, the test-URL warning L371–L374 dropped (`save_webhook_url()` refuses test URLs); the "New here?" notice L716–L723 links to the Workflow page only; `ajax_test_connection()` L1951 and `ajax_workflow_status()` L1989: `status_html` from the body renderer, with the outcome; `ping_webhook()` L1859: record `failed` on a failed ping of the saved URL, clear it on a pass (where the report is stored today).
- `post-quality-manager/includes/pqm-functions.php` -- `pqm_workflow_report()` L358 (option `pqm_workflow_version_seen`): the `failed` key read; `pqm_record_workflow_version()` L384 clears it on a pass; a new `pqm_record_workflow_failure(string $message)` beside it sets it; `pqm_manual_reason()` L536 reused.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `setup_connect_callback()` L1255, `provision_n8n_callback()` L1429, `rotate_n8n_token_callback()` L1514, `update_n8n_workflow_callback()` L1549: `status_html` from the body renderer, the outcome sentence passed; contracts unchanged.
- `post-quality-manager/includes/class-pqm-n8n.php` -- `miswiring()` (the two shapes): a Claude node or trigger carrying a credential of another type gets its own sentence (E7); `public_state()` L98 unchanged.
- `post-quality-manager/admin/js/pqm-admin.js` -- `initTestConnection()` L283 (no Settings field any more: send empty, server pings the saved URL; busy label on the button; result = the swapped body, `showTestResult()` L337 deleted); `swapWorkflowStatus()` L350 → swaps `#pqm-workflow-body`'s innerHTML, dispatches `pqm:workflow-status`; `initWorkflowStatusRefresh()` L372 also runs on `pqm:setup-closed`; `initUpdateWorkflow()` L420 → one `initRestActions()` for `[data-pqm-post]` buttons (`n8n/workflow`, `n8n/token`); `initReimportLink()` L398 opens the Set up by hand `<details>` and focuses its heading; new `initSaveWebhook()` (POST `setup/connect` with the field's value); `initCopyRows()` L480 re-run after a swap (delegate or re-init on the event).
- `src/kit/swap-status-block.js` -- the one swap for React callers: same target, same event; `src/kit/use-reimport-shown.js` -- delete (no island button needs it; the event constant moves to `swap-status-block.js`).
- `src/index.js` L18–L29 -- mount the n8n connection island on `#pqm-n8n-connection-root` at load and again on every `pqm:workflow-status` (new root node after a swap: unmount the old root, create a new one).
- `src/setup/index.js` -- the island renders the Modal only (button L314–L323 removed); a delegated click on `.pqm-open-setup` opens at `resumeAt(state)` (a `data-step` of `review` for **Run your first review**); `onRequestClose` L326–L331 dispatches `pqm:setup-closed`; `onByHand` L264–L271 closes and dispatches instead of reloading; `onN8nChange` → same event. `src/setup/steps.js` -- `Connect` L75 and `Provision` L282 keep `swapStatusBlock`; the Anthropic key help L338 gains the Console link used in PHP L1646; the n8n API key hint kept in one place (the island's), the step's copy points at it; `primary` prop handed to the inner island stays.
- `src/n8n-connection/index.js` -- `rotate` L302–L319 and its button L500–L512 removed (Rotate is PHP now); `primary` L354 → a `variant` prop (`secondary` on the page, `primary` in the dialog); the managed line L357–L376: adds what managing means and, for `wizard_created: false`, "Nothing in n8n changed."; the n8n API key hint is this island's one copy; adoption L275–L299 unchanged, its confirm also calls the kit swap after `onChange` (reply has no `status_html`: dispatch the refresh instead).
- `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-workflow-status` accent per state (`data-state` on the block: line / info / success / warning / error per DESIGN.md), `details.pqm-disclosure > summary` with the H2 look, the outcome line classes replacing `.pqm-test-result` L67–L70.
- `tests/harness/render-matrix-harness.php` -- L419–L578 (Workflow File), L580–L642, L644–L733, L797–L830, L834–L950, L971–L980, L1095–L1170 (tab matrix), L736–L739 (token description): rewrite for the new markup; add one section per matrix row asserting the resolver's answer, the sentence, the one primary, the disclosures present; AI panel: the per-agent table. `tests/harness/sanitize-matrix-harness.php` -- Case 8 L349–L358 gains "URL and token kept when absent"; `form_post()` L539–L560 and `$TABS['connection']` L562 drop the two keys. `tests/harness/setup-harness.php` L415 and `tests/harness/n8n-api-harness.php` L610, L1442–L1625 -- the `status_html` expectations and `UPDATE_BUTTON`.
- `tests/js/workflow-status.test.js` -- fixture = the new body; the swap, focus and REST-action rows; `tests/js/setup-provision.test.js` L50 (Modal mock: honour `onRequestClose`), `setup-connect.test.js`, `n8n-connection.test.js` (`variant`, managed line copy, no rotate), `settings-tabs.test.js` (`#pqm_n8n_webhook_url` gone).
- `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md`, `docs/user-paths.md` A4 (the "shows everything at once" UV line) -- one line each; `_bmad-output/backlog/story-n8n-workflow-page-lean-ux-rework.md` untouched.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/admin/class-pqm-admin.php` -- the resolver, the body renderer and its five states, the disclosures, the Save and test field, the AI-tab table, the Connection tab, the sanitizer, the two AJAX replies, the failure record -- the page is one state machine rendered in one place.
- [ ] `post-quality-manager/includes/pqm-functions.php`, `class-pqm-rest-api.php`, `class-pqm-n8n.php` -- the `failed` key, the four `status_html` replies, the other-type credential sentence.
- [ ] `post-quality-manager/admin/js/pqm-admin.js`, `admin/css/pqm-admin.css` -- the body swap, the REST-action handler, Save and test, the refresh on dialog close, copy rows after a swap, the state accents.
- [ ] `src/index.js`, `src/kit/`, `src/setup/`, `src/n8n-connection/` -- remount on swap, the dialog opened from the block, the close event, Rotate removed from the island, the managed line copy, the Console link.
- [ ] `tests/harness/*.php`, `tests/js/*.test.js` -- one check per matrix row; the gaps named in the Code Map.
- [ ] `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md`, `docs/user-paths.md` -- the lines named in the Code Map.

**Acceptance Criteria:**
- Given the owner's walk through the five states on the environment they name (AC6, hitl), when they open the page in each state, then they name the next step without reading the other sections, and after the wizard connects, provisions, adopts or hands over, the page behind it shows the new state without a reload (AC7, AC8).
- Given `php tests/harness/render-matrix-harness.php`, then every matrix row has a passing check and no state renders more than one `.button-primary`.
- Given the Free build (`php bin/build-free.php` + `tests/harness/free-build-harness.php`), then every state renders without a locked or empty section.

## Design Notes

- **One body, one swap.** Every `status_html` today replaces the status block only, and the islands outside it drift (E9). Rendering the whole body under the H1 from one resolver, and remounting the island on each swap, makes every reply and the dialog's close one mechanism; the island's mount-time read is then the re-read AC7 asks for. The cost, a remount on each action, is paid only on user actions.
- **Rotate and Update as PHP buttons.** Both post to a REST route and want the fresh body back; one `[data-pqm-post]` handler in `pqm-admin.js` is A7's one renderer (duplication 8). The island keeps what needs its form state (connect, forget, adoption).
- **The failure record.** Without it, "URL saved and the last check failed" cannot be told from "set up" on page load (the previous passing report stays). One optional key on the existing report option; no schema change.
- **Skipped:** a `start step` argument for the wizard (`resumeAt` already lands on the state's step; `data-step="review"` only forces the review step for a site with results); remembering open disclosures; a per-state body for the Free variant beyond the spine's rows.

## Verification

**Commands:**
- `php -l` on every touched PHP file; `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness "0 failed" (render-matrix 378, sanitize-matrix 224, setup 138, n8n-api 450 today; counts rise)
- `npm run lint:js && npm run test:unit && npm run build` -- expected: clean, Jest green (132 today), `admin/build/` written
- `php bin/build-free.php` then `php tests/harness/free-build-harness.php` -- expected: 0 failed

**Manual checks (if no CLI):**
- On the owner's Local sandbox: states 1 → 2 → 4 through the wizard; state 3 by "Set up by hand instead" then Save and test; state 5 by planting an older version with WP-CLI (as in the 6.12 walk) and by a URL that does not answer; Rotate token and Update workflow from the Webhook disclosure and the status block; Settings → Connection shows the URL read-only and saving Settings changes neither URL nor token.
