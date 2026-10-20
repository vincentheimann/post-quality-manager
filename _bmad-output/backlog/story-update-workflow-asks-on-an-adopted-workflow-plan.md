---
title: "Update workflow asks before replacing a workflow the Administrator made by hand (story 76)"
type: 'feature'
ticket: '76'
created: '2026-10-20'
status: built
baseline_revision: '847fabefa6090e5124c5f049cf414143307d8d1f'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/n8n-provisioning.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On an adopted (hand-made) workflow, Update workflow writes the bundled template over every node, connection and setting on one click, while the notice promises only that "your credentials and production URL stay". The spec says nothing made by hand is changed without explicit confirmation.

**Approach:** the status block marks the Update button of an adopted site as needing a confirmation; `pqm-admin.js` hands that click to the n8n connection island (already on the page, owner of the managed line) instead of posting, and the island opens a `@wordpress/components` Modal that says what the update replaces and what it keeps; confirm runs the same `POST /n8n/workflow` and swaps the reply's body. A provisioned site keeps its one click. The notice copy names what stays (credentials, webhook path, name) and says the nodes are replaced.

**Decisions taken at planning (owner to confirm at the checkpoint):**
- D1: the confirmation lives in the n8n connection island (a Modal needs `@wordpress/components`; the island is mounted on the page in every state that offers Update), reached through one cancelable DOM event from `pqm-admin.js`. No new island, no second REST route.
- D2: without the built admin app (a dev checkout: no island mounts, as no setup dialog opens) the event has no listener and the click posts as today; the page already warns that the app is not built.
- D3: one notice sentence for both kinds of map: "The plugin can update it in place: the workflow's nodes are replaced; its credentials, webhook path and name stay." The Modal (owner wording, 2026-10-20, replacing the draft "says the same"): "This replaces every node, connection and setting of the workflow with this plugin's template. Its credentials, webhook path and name stay." then "n8n keeps its own version history."
- D4: the Modal's confirm is busy while the call runs; the reply (success or the refusal that carries `status_html`, story 74) swaps the page's body, which remounts the island, so the Modal needs no close of its own; a refusal without a body closes the Modal and shows in the adoption block's Notice.

## Boundaries & Constraints

**Always:** what `update_workflow()` writes, the ping after it and the version record are untouched; copy per docs/brand.md; exactly one `.button-primary` on the page (the Modal's primary is inside the Modal); the Modal only on the page variant (inside the wizard a second Modal would open over it, as Start over does).

**Never:** `window.confirm()`; a backup of the workflow; a change to Rotate token or to the provisioned path's one click; a REST or payload change; a confirmation on the re-import path (nothing is written there).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| adopted, button | map `wizard_created` false, Update offered (primary or secondary) | the button carries `data-pqm-confirm="update"`; notice sentence per D3 | — |
| provisioned, button | map `wizard_created` true | no `data-pqm-confirm`; the same notice sentence | — |
| click, adopted | the page with the island mounted | `pqm-admin.js` dispatches cancelable `pqm:confirm-action` `{action:'update'}`; the island calls `preventDefault()` and opens the Modal "Replace the workflow?"; no request yet, no busy label | — |
| click, no listener | the island not mounted (D2) | the event is not prevented; the click posts as today | — |
| cancel | Modal open; Cancel or the dialog's close | the Modal closes, no request; focus returns to the Update button | — |
| confirm, ok | Modal open; confirm | one `POST /n8n/workflow` (empty body, `apiFetch`); the reply's `status_html` swapped in (`pqm:workflow-status` dispatched once) | — |
| confirm, refused with body | the route answers a WP_Error whose data carries `status_html` | swapped in the same way (the outcome line is inside) | — |
| confirm, refused without body | a network failure | the Modal closes; the cause in the adoption block's Notice; the managed line stays | — |
| wizard | the island with `variant="primary"` (the setup dialog) | no listener, no Modal | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- `workflow_page_state()` L1409: `$base` gains `'adopted' => $has_map && empty( get_option( PQM_N8n::SETUP_OPTION )['wizard_created'] )` (read the map once into a local); `render_workflow_body()`: `$fix_button` L1601 adds ` data-pqm-confirm="update"` when `$state['adopted']`; the in-place sentence L1569 → D3's text. Nothing else.
- `post-quality-manager/admin/js/pqm-admin.js` L492–501 (the click listener of `initRestActions()`): before `post(el, body)`, `if ( el.dataset.pqmConfirm ) { var ev = new CustomEvent( 'pqm:confirm-action', { cancelable: true, detail: { action: el.dataset.pqmConfirm, element: el } } ); if ( ! document.dispatchEvent( ev ) ) return; }` (dispatchEvent returns false when prevented). Docblock line.
- `src/kit/swap-status-block.js` -- export `CONFIRM_ACTION_EVENT = 'pqm:confirm-action'` beside the other event names (the one place event names live).
- `src/n8n-connection/index.js` -- imports: `swapStatusBlock` (default) and `CONFIRM_ACTION_EVENT`; state `confirmingUpdate` (bool) + `updateButtonRef`-like plain variable for the element from `event.detail.element`; a `useEffect` (deps `[ variant ]`) that, for `variant === 'secondary'`, adds a document listener on `CONFIRM_ACTION_EVENT`: `detail.action === 'update'` → `preventDefault()`, keep `detail.element`, `setConfirmingUpdate( true )`; cleanup removes it. `call()` L213: pass `err` to `refused( err )`. Handlers: `cancelUpdate` (close, focus the kept element if still `isConnected`), `confirmUpdate` = `call( 'adoption', { path: '/post-quality/v1/n8n/workflow', method: 'POST', data: {} }, ( data ) => swapStatusBlock( data.status_html ), ( err ) => { if ( err && err.data && err.data.status_html ) { swapStatusBlock( err.data.status_html ); } else { setConfirmingUpdate( false ); setFocusTo( 'closed' ); } } )`. Render: in the `managed` block beside the Start over Modal, `variant === 'secondary' && confirmingUpdate && <Modal title="Replace the workflow?" className="pqm-n8n-update-modal" onRequestClose={cancelUpdate}>` with two `<p>` (D3 + "Every node, connection and setting you added by hand in n8n goes with it. n8n keeps its own version history.") and the actions row: `Button variant="primary" className="pqm-n8n-update-confirm" isBusy/disabled={busy}` "Update workflow", `Button variant="secondary" className="pqm-n8n-update-cancel"` "Cancel". Header comment: one line.
- `tests/js/workflow-status.test.js` -- `UPDATE` fixture + a `UPDATE_ADOPTED` with the attribute; in the REST-actions describe: a click on the adopted button dispatches `pqm:confirm-action` with `detail.action === 'update'` and `detail.element` the button, and when a test listener prevents it nothing is fetched and no busy label shows; with no listener it posts as today (D2); the provisioned button never dispatches (existing test extended with a listener spy).
- `tests/js/n8n-connection.test.js` -- describe "Update workflow asks (story 76)": the page variant with `WIZARD`-like adopted state: the dispatched event is prevented and the Modal shows with the two sentences and no request; Cancel closes it, no request; confirm POSTs `/post-quality/v1/n8n/workflow` `{ method: 'POST', data: {} }` once and the reply's `status_html` lands in `#pqm-workflow-body` with one `pqm:workflow-status`; a refusal with `data.status_html` swaps too; a refusal without closes the Modal and shows the cause in the adoption block; the wizard variant ignores the event (not prevented, no Modal). Reuse `mountOnPage`, `click`, `modal()`, `$`.
- `tests/harness/render-matrix-harness.php` L762–775 -- `$in_place` → D3's text; `$update` unchanged for the provisioned rows; one row with `wizard_created => false`: the button carries ` data-pqm-confirm="update"` (primary and, behind a mis-wiring, secondary), still one primary; one row that the provisioned button has no `data-pqm-confirm`.
- `docs/release-notes/unreleased.md` -- one paragraph; `memory/n8n-provisioning.md` -- a story 76 line (the spec's "never changed without explicit confirmation" now holds for Update on an adopted workflow; Rotate stays one click, retro decision); `_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md` L56 -- no edit (the retrospective's A14 amendment already records the reconciliation; the memory line points at it).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- `adopted` in the page state, the attribute on the button, the notice sentence -- AC1–AC3
- [x] `post-quality-manager/admin/js/pqm-admin.js`, `src/kit/swap-status-block.js` -- the cancelable event before posting -- AC1, AC2
- [x] `src/n8n-connection/index.js` -- the listener, the Modal, confirm/cancel, `call()` passing the error -- AC1
- [x] `tests/js/workflow-status.test.js`, `tests/js/n8n-connection.test.js`, `tests/harness/render-matrix-harness.php` -- one row per matrix line -- AC4
- [x] `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` -- DoD

**Acceptance Criteria:**
- Given an adopted workflow needing an update, when the Administrator presses Update workflow, then a Modal names what is replaced and what stays, and n8n receives nothing until they confirm.
- Given a provisioned workflow, when Update workflow is pressed, then the update runs at once as before.
- Given the update notice on any managed site, when it is read, then it says the nodes are replaced and that credentials, webhook path and name stay.

## Implementation Notes

- 2026-10-20, planning: `bmad-build` rendered by hand (route `full`) from the workspace root; branch `feat/n8n-update-asks-on-adopted` created from `main` at 847fabe as the stories 71 and 74 precedent (rename at the checkpoint if wanted). Story 76 is unrefined (`refined: false`): its ACs are taken as written; D1–D4 are the planning decisions, confirmed by the owner at the checkpoint 2026-10-20 (Approve and continue; the full plan kept above the token ceiling). Story 67 is built, so the ticket's "before or after it" note resolves to after: the button sits in the status block.
- 2026-10-20, build (implementer subagent, Opus 5.5, per `memory/model-split`): all five tasks built as the Code Map says. Three deviations, none touching the frozen intent: (1) the island's listener effect depends on `[ variant, hasSetup ]` (`hasSetup` = the GET reply carries `setup`), not `[ variant ]`: while the island's first GET is still in flight, or after a failed read, the managed block (the Modal's home) cannot render, so the event is left unheard and the click posts as before (D2's fallback) instead of being swallowed with no dialog; (2) `tests/harness/n8n-api-harness.php`, not in the Code Map, held the old notice sentence (`IN_PLACE`) and an adopted-map row asserting the provisioned button markup: both updated to D3's sentence and the `data-pqm-confirm="update"` button; (3) the harness `esc_html` stub is the identity, so the expected sentence carries a raw apostrophe. Rows added: `render-matrix-harness.php` +3 (provisioned without the attribute, adopted primary with it and one primary, adopted mis-wired secondary with it), `workflow-status.test.js` +2 (prevented: no fetch, no busy label, detail.action/element; unheard: posts) and the provisioned test extended with a listener spy, `n8n-connection.test.js` +6 (Modal and sentences, Cancel/close with focus back, confirm POST + swap + one event, refusal with body, refusal without body, wizard variant not prevented). `npm run lint:js -- --fix` changed only the three new test titles' quotes (pqm-admin.js and bin/zip.js are outside the lint paths, untouched). Verification: `php -l` clean, `node --check pqm-admin.js` clean; harnesses caps 284, cron-gate 35, free-build 83, insert-block 66, license 27, n8n-api 577, quota 71, release-header 31, render-matrix 409, rest-posts 165, rest-reviews 65, sanitize-matrix 226, setup 141, template-failure 55, template-guard 62, voice 23 — 0 failed; Jest 8 suites, 147 passed; lint clean; build compiled. The Local walk (Manual checks) is not run.
- 2026-10-20, review fixes (coordinator): deviation (1) above is reversed. Listening only once the map was read left a window after every body swap (and for good after a failed read) in which the adopted site's click went unheard and the template was PUT with no dialog. The island now listens from mount on the page variant (deps `[ variant ]`); an event that arrives before the state is read is prevented, and the Modal opens once the managed block renders. `pqm-admin.js`: an unheard event with `#pqm-n8n-connection-root` on the page posts nothing and shows `i18n.stateLoading` ("The page is still loading its state. Try again in a moment.", localized in `PQM_Admin`); only a page without the root (no built app) posts as before (D2). After a confirmed update, or a refusal carrying `status_html`, the island focuses the fresh body's `.button-primary`, else the `.pqm-workflow-status` (with `tabindex="-1"`), following `swapWorkflowStatus()`'s rule. Modal copy changed to the owner-approved wording: "This replaces every node, connection and setting of the workflow with this plugin's template. Its credentials, webhook path and name stay." / "n8n keeps its own version history." The notice sentence is unchanged. Tests: `workflow-status.test.js` gained a row where the island root is present but nothing listens (no fetch, outcome line); the D2 row now uses a fixture without the root. `n8n-connection.test.js`: the confirm row asserts focus on the new primary, the refusal-with-body row asserts focus on the status block, and a new row presses Update before the state arrives. The variant-prop test now unmounts its first page-variant root before mounting a second, because the leaked root's listener had been cancelling later events. Jest for both files: 80/80.

- 2026-10-20, after the review patches (gates re-run by the session agent): 17 harnesses 0 failed (render-matrix 409, n8n-api 577, setup 141), Jest 149/149, lint clean, `npm run build` compiled; `pqm-admin.js` carries this story's 13 lines only. Plan marked built; no commit (house rule). The Local walk (plan § Verification, manual checks) not run from this build.

## Plan Change Log

## Review Triage Log

- 2026-10-20, quick lens (Fable 5.1, 4 findings): 1 high, 1 medium, 2 low; 0 false. 2 → patch, 1 rejected, 1 for the owner (copy inside the frozen D3), nothing deferred.
  - **high → patch.** The listener registers only after the island's GET has returned a map (build deviation 1), and the island remounts on every body swap: between a swap and that reply, and for good when the GET fails, a click on the adopted site's Update button is unheard and PUTs the template with no dialog. D2's fallback was for an unbuilt app, which the page warns about; this window warns nothing. Fix: `pqm-admin.js` refuses an unheard `[data-pqm-confirm]` click when the built app is on the page (the island's root node exists) and shows the outcome line "Loading the page's state… try again in a moment." (error tone); the island listens from mount and opens the Modal as soon as the state is there; an unbuilt page (no root node) posts as before.
  - **medium → patch.** After a confirmed update (and a refusal carrying `status_html`) the island swaps the body through the kit's `swapStatusBlock()`, which moves no focus; the Modal unmounts with the island and its return target is detached, so focus lands on `body`. The one-click path lands on the new primary. Fix: after the swap, focus the fresh status block's `.button-primary`, else the block itself (`tabindex=-1`), the same rule `swapWorkflowStatus()` applies; Jest row asserts it.
  - **low → rejected.** The "prevented" Jest row reads the busy state after `flush()`, so a busy-then-reset regression would pass. The matrix promise holds today and the row still catches a fetch; tightening it is a test-only nicety.
  - **low → owner.** The Modal's first sentence repeats the notice sentence verbatim ("The plugin can update it in place: …"), whose "it" has no antecedent inside a dialog titled "Replace the workflow?", and the dialog never names the bundled template as the replacement. This is D3's frozen wording; the owner approved the wording 2026-10-20 (now in D3) → patch.

## Design Notes

- The DOM event is the smallest bridge between the vanilla status block and the React island: the island already remounts on every body swap and already owns a Modal for Start over, so the confirmation is one more state in the same component, not a new mount point. `dispatchEvent()` returning `false` is the standard "a listener prevented it" signal, which gives D2's fallback for free.

## Verification

**CLI:**
- `php -l post-quality-manager/admin/class-pqm-admin.php`; `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: 0 failed, render-matrix above 406
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- expected: green (`--fix` must not reformat `pqm-admin.js` beyond this story's lines)

**Manual checks (Local sandbox, agentic walk):**
- Adopt a hand-made workflow, plant an older version with WP-CLI, press Update workflow: the Modal; Cancel leaves n8n untouched; confirm updates and the page shows the new version.
