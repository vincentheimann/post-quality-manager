---
title: 'The n8n Instance section shows one state at a time: connected, changing, or connecting; the adoption offer stays put'
type: 'bugfix'
ticket: ''
created: '2026-10-15'
status: 'done'
baseline_revision: '4d9c828859e482ed6a0541752771cb018f2cbfb2'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On the owner's site (2026-10-15, after entry 6.8) the n8n Instance section reads two ways at once. Connected, it still shows the address and key fields with a primary **Connect** that re-runs the check, beside **Check again**, which does the same. And the adoption offer shares one slot with the form: pressing **Let the plugin manage this workflow** replaces the whole form with the proposal, and the offer comes and goes as other buttons are pressed, so the owner cannot tell what a click will do.

**Approach:** The section becomes two blocks that never swap. The connection block shows one of three states: connected (a summary of address and key, with **Check again**, **Change** and **Forget key**), changing (the fields with **Save and check** and **Cancel**), or connecting (the fields with **Connect**, as today for a site without a passing key). The adoption block below it shows one of three contents in place: the offer, the proposal (opened by the offer's button, closed by **Cancel**), or the managed line. Nothing moves elsewhere.

## Boundaries & Constraints

**Always:** the same REST calls and replies as today (`GET|POST|DELETE /n8n/connection`, `GET|POST /n8n/adoption`); the server is untouched. One primary at most in the section, by today's rule (the status block's primary wins on the page; the wizard's Modal is its own surface): **Connect** or **Save and check** while a form is open, else **Manage this workflow** while the proposal is open, else none. The key field stays write-only, empty, with the hint placeholder. Every state change moves keyboard focus to what replaced the pressed control. Copy per `docs/brand.md`: a state sentence, then its action.

**Never:** a change to `GET /setup`, the wizard's steps, the status block, PHP or the harnesses; a `<details>` (story 67 rebuilds the page); a confirmation dialog on Forget key (unchanged).

## I/O & Edge-Case Matrix

State = `GET /n8n/connection` (`key_set`, `probe`, `setup`, `adoptable`); connected = `key_set && probe.ok`.

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Connected | Connected, page or wizard | The success Notice (as today), one line "n8n at {host} · API key ending in ab12" (or "set"), buttons **Check again**, **Change**, **Forget key**, all secondary; no fields, no Connect | — |
| Connecting | No key, or a key whose last check failed | The fields and **Connect** as today (Check again and Forget key beside it while a key is stored) | A failed check: the cause in the failure Notice, the form stays |
| Change | **Change** pressed | The fields (address prefilled, key empty with the hint placeholder), **Save and check** (enabled by today's `canSubmit` rule) and **Cancel**; focus on the address field | — |
| Save and check | Submitted, check passes | Back to Connected with the new state; focus on **Change** | Fails: the cause in the Notice, the form stays, nothing stored (server rule) |
| Cancel the change | **Cancel** pressed | Back to Connected, the typed values dropped; focus on **Change** | — |
| Forget key | Pressed in Connected | Connecting (address kept, "API key: not set"); the adoption block empties (`adoptable` false), a proposal open closes | — |
| Offer | `adoptable`, no proposal | The adoption block: the offer paragraph and **Let the plugin manage this workflow**; the connection block unchanged above it | — |
| Proposal | The offer's button pressed | The proposal replaces the offer in place; the connection block stays visible and usable; focus on the proposal | A refused GET: the cause in the Notice, the offer stays |
| Confirm | **Manage this workflow** | The managed line replaces the proposal in place; focus on it | Refused (changed since): the cause, the offer back |
| Managed | `setup` set | The managed line in the adoption block ("set this workflow up on" / "has managed since"), no offer | — |
| Wizard | `onChange` passed, not connected | Connecting, as today; once connected, Connected (the step ticks through `onChange`) | — |

## Decisions (owner, 2026-10-15)

Approved as proposed at the plan checkpoint.

- **D1 — the submit's label when changing a connected site:** **Save and check** (it stores only when the check passes, as today), so **Connect** appears only while nothing is connected.
- **D2 — Forget key with a proposal open:** the proposal closes with the offer, since nothing can be adopted without a key; no confirmation.

</frozen-after-approval>

## Code Map

- `src/n8n-connection/index.js` -- the one file to change. State: `state`, `url`, `key`, `busy`, `failure`, `proposal`, `focusTo` + three refs (L60–L110). `call()` / `stored()` / `send()` (L112–L160): keep as the REST layer. `connect` (L162) → the submit for both Connecting and Change. The render (L262–L470): split into the connection block (`.pqm-n8n-connection-block`: Notice, key-state line, then summary | form) and the adoption block (`.pqm-n8n-adoption`: managed | proposal | offer). New state `changing` (bool) opened by **Change**, closed by Cancel and by a passing `stored()`. `connected` (L198) is the Connected test. The focus effect (L84–L97): extend `focusTo` with `'change'` (the address field) and `'summary'` (the Change button); the existing `'proposal'` / `'closed'` stay.
- `src/n8n-connection/style.css` -- `.pqm-n8n-connection-actions` (flex, 8px gap): reuse for the summary's and the proposal's buttons; one rule for the summary line if needed. No inline styles (AGENTS.md).
- `src/setup/steps.js` L183, L453 -- mounts the island with `onChange`; unchanged. `tests/js/setup-provision.test.js` L537 checks only that `.pqm-n8n-connection` exists.
- `tests/js/n8n-connection.test.js` -- `mount()`, `CONNECTED` / `NOT_SET` fixtures, `field()`, `typeInto()`, `submit()`; the Button mock is a `forwardRef`. Existing tests to adjust: L144 (connected reload: now the summary, no fields), L242 (Check again / Forget from the summary), L312–L360 (primary rule: now applies to Save and check / Connect / Manage this workflow), L411 (proposal "in place of the form" → in place of the offer, the form or summary still present).
- `docs/release-notes/unreleased.md` L21 -- the adoption line stays true; no new line (a layout change of one section, no new default or removed screen).

## Tasks & Acceptance

**Execution:**
- [x] `src/n8n-connection/index.js` -- the two blocks and three states each, per the matrix; `changing` state; **Save and check** / **Cancel**; the proposal in place of the offer; focus rules; copy: summary line, Change, Save and check.
- [x] `src/n8n-connection/style.css` -- the summary line's spacing, if the actions rule does not cover it. (Not needed: the summary is a `<p>` and a `.pqm-n8n-connection-actions` row; no CSS change.)
- [x] `tests/js/n8n-connection.test.js` -- one test per matrix row (adjusting the ones named in the Code Map), each asserting the visible controls, the call made and `document.activeElement` where a row names focus.

**Acceptance Criteria:**
- Given a connected site on the n8n Workflow page, when the section renders, then no field and no **Connect** show, and pressing **Change** then **Cancel** returns to the same summary without a REST call.
- Given a connected, hand-made site with a URL saved, when **Let the plugin manage this workflow** is pressed, then the connection summary stays on screen above the proposal, and **Cancel** restores the offer under it (hitl, on the environment the owner names).
- Given any state, then the section holds at most one `button-primary`, and none while the status block shows one.

## Implementation Notes

- **Code Map claim corrected:** `tests/js/setup-provision.test.js` did not only check that `.pqm-n8n-connection` exists; its "the island passes" test read the island's key field after a passing connect (now the summary, no field). Adjusted: that assertion now reads the summary line and expects no key field, and its Button mock became a `forwardRef` (as in `n8n-connection.test.js`), since the summary's Change button carries a focus ref. No other wizard test changed.
- **Failure Notice per block:** `failure` is `{ block, message }`; a connection call's cause shows inside `.pqm-n8n-connection-block`, an adoption call's inside `.pqm-n8n-adoption`, so a failed Save and check stays by its form and a refused proposal by its offer. Cancel (either) drops only its own block's cause.
- **Summary line:** the existing `.pqm-n8n-key-state` line reads "n8n at {host} · API key: set, ending in ab12" while connected (host = the stored origin without its scheme), and the key state alone while a form shows.
- **Focus:** `'change'` focuses the form's first input (via a form ref, so it does not rely on TextControl forwarding refs) and is used by Change and by Forget key (the fields replace the pressed button); `'summary'` focuses Change after Cancel and after any passing Connect / Save and check / Check again that replaced a form.
- **Open proposal:** `stored()` closes it whenever the reply is no longer `adoptable` (D2: Forget key); Change and Cancel in the connection block leave it open.
- **Not covered by the matrix, kept as today:** a failed Check again from the summary leaves the summary and the success Notice (the server keeps the last passing probe) and shows the cause under the buttons.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, 2026-10-15): 6 findings: 0 high, 0 medium, 5 low, 1 false, 0 maybe-false. 5 patches, 0 deferred, 1 rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `memory/n8n-provisioning.md` Entry 8 line still says the proposal opens "in place of the form" | low | patch | Verified at L14; the Definition of Done asks memory in the same change. Line rewritten for the two blocks. |
| 2 | A passing Manage this workflow while Change is open resets the typed address and key | low | patch | Verified: `adopt` → `stored()` runs `setUrl( data.base_url )` and `setKey( '' )`, `changing` stays true. Breaks "two blocks that never swap"; fix: the confirm writes `state` only. |
| 3 | `call()` clears both blocks' failure Notices before every call | low | patch | Verified: `setFailure( null )` at the top of `call()`; the per-block Notices the build chose then vanish when the other block acts. Fix: clear the calling block only. |
| 4 | A passing Save and check to another instance keeps the old instance's proposal open | low | patch | Verified: `stored()` closes the proposal only on `! data.adoptable`, which a new key keeps true. The server refuses a stale id, but the shown proposal is wrong. Fix: close it after a passing Change. |
| 5 | The intro paragraph "Connect your n8n instance…" shows in the Connected state | low | patch | Verified: rendered unconditionally above the Notice. The plan's own problem statement is a section that reads two ways when connected; fix: render it only while a form is open. |
| 6 | AC2 not verified (HITL) | false | reject | Not a code defect: the owner's walk on their site, after the build; the jsdom rows cover the DOM order. Carried to step 05 as open. |

## Design Notes

Why two blocks: the connection and the adoption are two facts about the site (is a key stored and passing; does the plugin manage the workflow), each with its own actions; one slot for both made every button change the other's visibility. Why a summary when connected: the fields are inputs for a change, not a display of the state; showing them invites a re-type, and **Connect** on a connected site is **Check again** with a different name.

Draft copy (the build writes the final): summary line *"n8n at {host} · API key: set, ending in ab12"*; **Change**; **Save and check**; the offer and proposal texts as merged in 6.8.

Skipped: a disclosure around the section — story 67. Skipped: a "Test the connection" row in the summary — the status block's Test again is the one place (EXPERIENCE.md AC3).

## Verification

**Commands:**
- `npm run test:unit` and `npm run lint:js` -- expected: green.
- `npm run build` -- expected: compiles.
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` -- expected: unchanged (no PHP touched).

**Manual checks (if no CLI):**
- HITL on the environment the owner names, a zip of the branch, led by the button the owner pressed when the offer disappeared on the 6.8 walk (the offer's own, Forget key, or Manage this workflow; not answered at the checkpoint, the build covers all three): the connected summary with its three buttons; Change → Cancel; Change → Save and check with the stored address and no key (a re-check); the offer under the summary; the proposal in place, the summary still above it; Cancel; Forget key with the proposal open. If the site already stores the map (the 6.8 walk ended with a confirm), `wp option delete pqm_n8n_setup` restores the offer; nothing on n8n changes.
- **Section check: passed 2026-10-16.** Run by the owner in the browser on their production site (a zip of main after PR #138), every item: the connected summary with Check again, Change and Forget key; Change → Cancel; Change → Save and check with the stored address and no key; the offer under the summary and the proposal opening in place with the summary still above it; Cancel restoring the offer; Forget key with the proposal open closing it with the offer (D2). The button that made the offer disappear on the first look after 6.8 was not named; the build covers all three. Recorded in the entry 6.11 done chore.
