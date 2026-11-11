---
title: 'A blogger who chose to set up n8n by hand can go back to the guided setup'
type: 'feature'
ticket: 106
created: '2026-11-05'
status: 'done'
baseline_revision: '4429655691086f3b84e8dc2137c32da516d0a29a'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** "Set up by hand instead" stores `manual: true` in the setup progress and nothing clears it: POST /setup refuses `manual: false`, and the page's state 3 ("You chose to set up n8n by hand.") offers no control that opens the setup dialog. A blogger who changes their mind stays on the manual path.

**Approach:** POST /setup `{manual: false}` removes the stored choice. Where the choice is the reason shown (the page's state 3 and the dialog's Set up by hand step, no webhook URL saved), a "Use the guided setup instead" control follows "You chose to set up n8n by hand.". It clears the choice inside the setup dialog, which then shows the guided setup's first open step, and the page re-renders behind it without a reload.

## Boundaries & Constraints

**Always:** owner decisions 2026-11-05 (plan checkpoint): (1) on the page, the control is a link-styled button (`button-link`) on its own line right after the reason sentence, like "Set up by hand instead" on states 1–2; (2) in the dialog's Set up by hand step, when the reason is the choice, the link replaces "Connect by key instead"; that disclosure stays after a failed check; (3) a site whose key passes while the choice is stored shows the page control, which leads to Set up the workflow; (4) the plan is kept whole. The owner's wording "Use the guided setup instead" (2026-11-03). Shown only when the reason is the stored choice (`kind: 'choice'`) and no webhook URL is saved (ticket AC4). The page control opens the dialog through the existing `.pqm-open-setup` delegated listener and the dialog does the POST, so a failed write shows in the dialog (the save error lives in the Modal). No control without the built app (`app_built()`, like the Set up n8n button). Exactly one `.button-primary` per state stays true. `manual: true` keeps its behaviour; the callback refuses a non-boolean with today's `pqm_setup_bad_manual` 400.

**Never:** no new route, argument, option or schema change; nothing on n8n; no change to the key path's steps, provisioning, adoption, or the stored n8n connection; no way back from a failed connection check (out of scope); no Pro marker (both builds).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Way back, no key | choice stored, no URL, no key | page state 3 + the control; click → dialog posts `{manual: false}`, shows Connect your n8n; page reads "Not connected to n8n yet." | — |
| Way back, key passes | choice stored, no URL, passing key | page state 3 + the control; click → Set up the workflow; page "Connected to n8n at …" | — |
| Choice again | after the way back | "Set up by hand instead" stores `manual: true`; state 3 as before | — |
| Dialog, the choice | Set up by hand step, `kind: 'choice'`, no URL | the link after the Notice; no "Connect by key instead" | — |
| Failed check | probe failure (with or without the choice) | no control, page or dialog; the dialog keeps "Connect by key instead" | — |
| Hand-made workflow | URL saved, no setup map | no control | — |
| No built app | `admin/build/` missing | no page control | — |
| Clear refused | POST /setup answers an error | dialog stays on Set up by hand with the error | message from the reply, else "Could not save your progress. Try again." |
| API values | `manual` `true` / `false` / other | stores / removes the key (nothing else written) / 400 `pqm_setup_bad_manual` | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` -- `update_setup_callback()` L1337–1363 (`manual` branch L1342–1350: refuses anything but `true`); docblocks L17, L1192–1194, L1332–1336 say "only `true`, nothing clears it"; `setup_progress()` L1196 keeps the key only when stored; `setup_state()` L1277 (`manual`, `manual_reason`).
- `post-quality-manager/includes/pqm-functions.php` -- `postquality_manual_reason()` L608: a failed check outranks the choice, a passing key hides both (read only).
- `post-quality-manager/admin/class-pqm-admin.php` -- `workflow_page_state()` L1488–1494 (the page falls back to the stored choice behind a passing key); `render_workflow_body()` L1543–1553 (state 3 sentence and reason in `$details`, each escaped as its own `<p>`), `$setup_button` L1622 (the `app_built()` guard and `pqm-open-setup` markup to reuse), status block L1682–1696.
- `src/setup/index.js` -- `.pqm-open-setup` listener L148–161 (maps `data-step` to `pending`), pending effect L163–169, `save()` L187 (answers fresh state into `setState`), `reread()` L234 (the step-landing rule to reuse: keep the step when the new path has it, else `resumeAt`), `onByHand` L310, `requestWorkflowRefresh(WORKFLOW_REFRESH_EVENT)` L303, save error in the Modal L467.
- `src/setup/steps.js` -- `Manual` L422–482 (reason Notice L435–450; "Connect by key instead" L469–479); `ConnectN8n` L169–183 ("Set up by hand instead", a `Button variant="link"` in a `<p>`: the pattern to reuse).
- `tests/harness/setup-harness.php` L220–239 (the `manual` rows; L238–239 asserts `false` clears nothing: rewrite), `tests/harness/render-matrix-harness.php` L697–722 (state 3 rows; L706/L712 match the reason `<p>` exactly: keep the control out of that `<p>`), `tests/js/setup-provision.test.js` L567–690 (Set up by hand and Manual rows, `closeRequests()`).
- `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md` L59 (state 3 row), L79, L148–149 (Flow 2); `memory/n8n-provisioning.md` L8 and L12 ("nothing clears it", "only `true`").

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- `manual: false` unsets the key; `! is_bool()` keeps the 400; the three docblocks -- AC2, AC5
- [x] `src/setup/index.js` -- one `onGuided` (POST `{manual: false}`, land like `reread()`, ask the page to refresh); `data-step="guided"` opens the dialog and runs it -- AC2, the error in the dialog
- [x] `src/setup/steps.js` -- `Manual`: with `kind: 'choice'` and no URL saved, the link in a `<p>` after the Notice (the `ConnectN8n` pattern) in place of "Connect by key instead" (decision 2) -- AC1, AC4
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- state 3 with `kind: 'choice'` and the built app: `<p><button type="button" class="button-link pqm-open-setup" data-step="guided">` right after the reason line, outside its `<p>` (decision 1) -- AC1, AC4
- [x] `tests/harness/setup-harness.php`, `tests/harness/render-matrix-harness.php`, `tests/js/setup-provision.test.js` -- one row per matrix line; the rewritten `false` row -- AC1–AC5
- [x] `EXPERIENCE.md` (state 3 row, Flow 2), `memory/n8n-provisioning.md` L8, L12 -- the record follows the code

**Acceptance Criteria:**
- Given every touched PHP and JS file, when `php -l`, `node --check`, every `tests/harness/*.php`, `npm run test:unit` and `npm run lint:js` run, then all pass.
- Given the showcase on a build of this branch with the choice stored, when the owner clicks the control, then the dialog opens on Connect your n8n and the page reads "Not connected to n8n yet." without a reload; choosing by hand again returns to state 3.

## Implementation Notes

- Implementer (2026-11-05), the six tasks; the showcase walk (second AC) is still open. The callback: `! is_bool()` keeps today's 400, then `true` stores and `false` unsets `manual` (only `postquality_setup_progress` is written). The page: `$guided_link` in `render_workflow_body()`, state `manual` + `reason.kind === 'choice'` + `app_built()`, echoed right after the `$details` loop, so it is its own `<p>` after the reason line, inside the status block, before the version line.
- The dialog: the step-landing rule of `reread()` is now a module function `landing( fresh )` (the updater for `setCurrent`), shared by `reread()` and `onGuided`. `save()` now resolves to the fresh state instead of `true` (still `false` on failure; every existing caller only tests truthiness), so `onGuided` can land on it. The page's `data-step="guided"` maps to `pending: 'guided'`; the pending effect opens the dialog at `resumeAt( state )` as for `resume`, then runs `onGuided` through a ref (`guided.current`, assigned after its definition each render — the `completed` ref pattern of `first-review.js`), which keeps `react-hooks/exhaustive-deps` clean without a disable comment. A refused write re-reads GET /setup (review fix: the page's own "Set up by hand instead" stores the choice without telling the dialog, which may then have opened on Connect your n8n), so the dialog lands on the stored state's step (Set up by hand without a key) with the reply's message kept, else "Could not save your progress. Try again.", and asks for no refresh.
- `Manual` in `steps.js` takes `onGuided` and `saving`; `chosen` = `kind: 'choice'` and no URL saved (`! state.connected`) shows the link after the Notice and hides "Connect by key instead"; a failed check keeps the disclosure. The key-passes-from-the-manual-step JS row now starts from a failed check (the island is no longer there after the choice).
- Tests: `setup-harness.php` +9 checks (the non-boolean loop now runs on a site with the choice stored and asserts it is kept; `false` removes, again with nothing stored, choosing again, a passing key, a failed check plus the choice: the probe stays the reason); `render-matrix-harness.php` per-row control assertions for the four state 3 rows, the key-passing row, two URL-saved rows, the round trip after `{manual: false}` (state 2, then state 1), and the source check's `self::app_built()` count 3 → 4; `setup-provision.test.js` +6 tests, 3 reworked, `openDialog()` takes an optional `data-step`, new helpers `choiceSite()`, `probeSite()`, `refreshRequests()`.
- Verified 2026-11-05: `php -l` on both PHP files; every `tests/harness/*.php` 0 failed, with `admin/build/` present and again with it moved aside (CI's unbuilt branch; restored after); `npm run test:unit` 179/179; `npm run lint:js` clean; `npm run build` compiles (the local `admin/build/` is now this branch's). `node --check` exits 0 on the three touched JS files, but Node 22 does not parse their JSX (a scratch probe with JSX fails), so the parse is really proven by lint, Jest and webpack.
- Records: besides the state 3 row, L79 and Flow 2, the EXPERIENCE.md Wizard row (Component Patterns) now names state 3's control as an opener, and its `updated:` is 2026-11-05. `memory/n8n-provisioning.md` L8 and L12 rewritten; L12 also names the dialog link replacing "Connect by key instead" after the choice.
- Showcase walk (2026-11-05, second AC; a Free build of this branch on the showcase, backed up first; the choice stored through `rest_do_request()` POST /setup `{manual: true}`, the page rendering state 3 with one `data-step="guided"` control; the owner's Claude in Chrome clicked from a prompt the orchestrator wrote). Run 1: the page control opened the dialog and the page followed to "Not connected to n8n yet." without a reload (only fetch calls), but the dialog first showed "Step 1 of 4: Set up by hand" until the write answered, then "Step 1 of 5: Connect your n8n": the island's GET /setup from the mount still held `manual: true`, so the pending effect opened at `resumeAt( state )` on the manual path. Its step 3 "fail" (the dialog closed after "Set up by hand instead") was the prompt's error: the wizard's "Set up by hand instead" closes it on the page's Set up by hand disclosure (EXPERIENCE.md L79, story 67), and the page was back in state 3. Fix (orchestrator, walk triage W1): for `pending: 'guided'` the effect sets the island's state to a copy with `manual: false` and `manual_reason: null` and opens at that copy's `resumeAt()`; the write's answer (`landing( fresh )`) or the refused write's re-read replaces the guess, so the refused path still lands on Set up by hand with the cause. One JS test holds POST /setup unanswered and checks the heading before and after (red before the fix: "Step 1 of 5: Set up by hand", the Pro build's six steps in Jest). Run 2, on a fresh build, the extension recording the dialog heading and the page status on every frame: the dialog's first frame was "Step 1 of 5: Connect your n8n", Set up by hand never showed; "Set up by hand instead" closed it with the page in state 3 and the disclosure open and focused; `&postquality_setup=1` opened "Step 1 of 4: Set up by hand" with "You chose to set up n8n by hand." and the link, no "Connect by key instead"; its link moved to Connect your n8n, the page to state 1, and the X kept it there. No console errors in either run (WordPress's two JQMIGRATE lines only); `debug.log` 106 lines, all WP-CLI's own deprecations, none from the plugin. Restore: plugin files, database, `wp-config.php`, `debug.log` deleted; plugin list, tables/reviews/users/log, plugin files + `wp-config.php` all "same". After the fix: `npm run test:unit` 181/181, `npm run lint:js` clean, `npm run build` compiles; no PHP touched, so the harness run above stands.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-05, Quick lens on Opus 5.5; diff since 4429655):** high 0 · medium 1 · low 3 · false 1 · maybe-false 0.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | A refused clear from the page leaves the dialog on Connect your n8n, not on Set up by hand (matrix row "Clear refused") | low | patch | Real: `src/index.js` mounts the setup island once and it reads GET /setup only at mount; the page's own "Set up by hand instead" (`pqm-admin.js` `data-pqm-post="setup"`) stores the choice without telling it, so `resumeAt( state )` answers the key path. A refused POST /setup is rare (nonce, network); the fix is one branch. Patched: `onGuided` re-reads the state on a refused write (the save error kept); new JS row (GET /setup stale, then the choice; POST refused → Set up by hand, the error, no page refresh). |
| 2 | After the page control's success, closing the dialog leaves focus on the document body (EXPERIENCE.md: focus returns to the primary action) | medium | defer | Traced, not run in a browser: `onGuided` asks for the body refresh while the Modal is open, the swap detaches the button that opened it, and `swapWorkflowStatus()` refocuses only when focus was inside the body. Pre-existing: the ping and the provisioning (`steps.js` `swapStatusBlock` L77, L284) and a key change (`onN8nChange`) already swap the body under the open dialog; the intent asks for the page to follow behind the dialog. Entry in `deferred-work.md`. |
| 3 | The plan's second AC (the showcase walk) is not met | false | reject | The walk runs after the review by design, with the owner clicking in a browser; the plan moves to `built`, not `done`, until it passes. |
| 4 | `README.md` L68: the `/setup` row says POST stores `{manual: true}` only | low | patch | Direct correction: the row now names `{manual: false}` removing the choice. |
| 5 | EXPERIENCE.md L79 reads as if the wizard's link also shows after a failed check; L153 names only "Not connected to n8n yet." | low | patch | Direct correction: L79 says "by choice"; L153 names both sentences (a passing key: "Connected to n8n at {host}…"). |

Patch round (2026-11-05, the same Opus implementer): rows 1, 4, 5 fixed. Re-run by the orchestrator: `php -l` clean on both PHP files, every `tests/harness/*.php` 0 failed (23 files, `admin/build/` present), `npm run test:unit` 180/180, `npm run lint:js` clean, LF only.

**Walk (2026-11-05, the showcase, two runs; the extension's notes triaged by the orchestrator):** medium 1 · low 1 · by design 4.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| W1 | The page control opens the dialog on "Step 1 of 4: Set up by hand" until the write answers, then jumps to "Step 1 of 5: Connect your n8n" | low | patch | Seen in run 1. The island's mount state still says `manual: true`; fixed in the pending effect (Implementation Notes, walk line), a JS test red before the fix, run 2 recorded frame by frame: never shown. |
| W2 | Focus falls to the document body when the dialog's own "Use the guided setup instead" moves it to Connect your n8n (the clicked link unmounts), and stays there after the X | medium | defer | Seen in run 2. No step change in the dialog moves focus, so a control that unmounts itself drops it; the close half is pass 1's row 2, now seen in a browser. The `deferred-work.md` entry is widened to both halves; one fix (focus the new step's heading, and the primary after a close from the body) serves every flow. |
| W3 | Connect your n8n opens small and grows about 0.6 s later, when the connection island's GET /n8n/connection answers | low | defer | Pre-existing on every open of that step; entry in `deferred-work.md`. |
| — | The page's status follows 0.3–0.6 s after the dialog; no focus ring on a mouse-focused disclosure; the address loses `postquality_setup=1`; the self-opening dialog waits for GET /setup | by design | — | The page re-reads its body from the server after the write; `:focus-visible`; `replaceState` on purpose; story 36's pending open. |

## Design Notes

Skipped: a release-notes line — nothing changes for a site that does not click the new control.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failed
- `npm run test:unit` and `npm run lint:js` -- expected: green
- `php -l` on each touched PHP file, `node --check` on each touched JS file -- expected: clean

**Manual checks (if no CLI):**
- The showcase, a build of this branch (`docs/local-verification.md` walk: backup, install, restore): store the choice, then the page control → dialog on Connect your n8n, page state 1; "Set up by hand instead" → state 3 again; restore "same".
