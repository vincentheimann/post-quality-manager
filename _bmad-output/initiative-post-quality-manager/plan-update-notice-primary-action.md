---
title: 'The workflow update notice carries the one primary action: Re-import the workflow'
type: 'feature'
ticket: ''
created: '2026-10-15'
status: 'built'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
baseline_revision: 'f9dcd82961a457b14256e6d51e7ca9fb21a3d0a8'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** When the n8n Workflow page says "Workflow update available" (or, on Pro, that the workflow is the Free variant), the notice names no action. It says "Download or re-import it below". The page's only buttons are the setup guide's ("Setup guide", secondary once setup is complete; "Set up the workflow", primary before), and the guide has no re-import step since entry 6.6. The owner asked on 2026-10-15 for the next step to be more prominent there.

**Approach:** Give the fix to the notice that states the problem, following the approved layout (EXPERIENCE.md, state 5: the fix is the one primary action). Each warning gets a **Re-import the workflow** button that moves to the Workflow File section and focuses its heading. While an update is needed, the setup guide's button is never primary. Entry 6.9 later replaces this action with **Update workflow** on sites the plugin set up.

## Boundaries & Constraints

**Always:**
- Exactly one primary button on the page while an update is needed: the notice's.
- The button moves focus to the Workflow File heading (`#pqm-workflow-file`, `tabindex="-1"` since entry 6.6), and it keeps working after the status block is re-rendered (a test, the wizard, window focus).
- The notice copy follows `docs/brand.md`: the problem in one sentence, then the action.

**Never:**
- The rest of story 67's layout (the status block redesign, the disclosures).
- One-click update (6.9) or anything that changes n8n.
- A change to the version handshake or to when the notices show.

**Decision (owner, 2026-10-15):** choose the option with the least cognitive load. The fix sits in the notice that states the problem; the setup guide stays secondary.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Older workflow answered | report version < `PQM_WORKFLOW_VERSION` | The warning notice ends with a primary **Re-import the workflow** button; the setup guide's button is secondary | — |
| Free variant on Pro | Pro build, report variant `free` | The same button in that notice | — |
| Both at once | older and Free | One button only, in the first notice | — |
| Up to date | report current | No notice, no button; the setup guide's button as today | — |
| Not checked yet | no report | As today | — |
| Button activated | click or Enter | The page moves to the Workflow File section, and focus lands on its heading | — |
| Status block re-rendered | after a test or the wizard's ping | The new notice's button works the same | — |
| Setup not complete and update needed | `complete` false, older report | The notice's button is the one primary; "Set up the workflow" is secondary | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- `render_workflow_status()` (around L1478–1500): the update notice (version < `PQM_WORKFLOW_VERSION`) and the Pro-only Free-variant notice (inside `is__premium_only()`). Each gets `<a class="button button-primary pqm-reimport" href="#pqm-workflow-file">Re-import the workflow</a>`; when both show, only the first carries it. Copy: the problem in one sentence, then the button (see Plan Change Log, KEEP). The Workflow File H2 (`id="pqm-workflow-file" tabindex="-1"`) already exists.
- **The status block is the one source of truth for "an update is needed"**: a rendered `.pqm-workflow-status .pqm-reimport`. Every swap of the block announces itself with one DOM event, `document.dispatchEvent( new CustomEvent( 'pqm:workflow-status' ) )`:
  - `post-quality-manager/admin/js/pqm-admin.js` -- `swapWorkflowStatus()` L339, called by the test ping L314 and the window-focus refresh L365, dispatches it after replacing the block. The delegated `click` listener for `.pqm-reimport` lives here too.
  - `src/setup/steps.js` -- `swapStatusBlock()` L46 (wizard connect L87, provisioning L294) dispatches it as well.
- `src/kit/use-reimport-shown.js` (new, beside `use-polling.js` and `use-single-run.js`) -- one hook, `useReimportShown()`. It reads `!! document.querySelector( '.pqm-workflow-status .pqm-reimport' )` at first render (the PHP block is in the page before the islands mount, so there is no flash) and again on each `pqm:workflow-status` event. Both islands import it from the kit (the kit imports no page module).
- `src/setup/index.js` ~L312 -- the guide's button: `variant={ complete || reimportShown ? 'secondary' : 'primary' }`. While `state` is null and the block shows the button, it is already secondary.
- `src/n8n-connection/index.js` L182–189 -- the **Connect** button is `primary` unless the page shows the re-import button. Inside the setup guide (where the island is rendered with `onChange`), it stays primary: the Modal is its own surface.
- No REST change: `setup_state()` gets no `update_needed`.
- Tests:
  - `tests/harness/render-matrix-harness.php` L603–606: the button, its class and href, and one button when both notices show (PHP markup).
  - `tests/js/workflow-status.test.js`: the delegated click focuses the heading, also after a swap; each swap dispatches `pqm:workflow-status`.
  - `tests/js/setup-provision.test.js` (or a new small test): the guide's button follows the block, so it is secondary with `.pqm-reimport` present at mount, flips after an event that removes it, and flips back.
  - `tests/js/n8n-connection.test.js`: Connect is secondary on the page with the re-import button, and primary in the guide.
- `memory/llm-options-architecture.md` (the handshake line): the notice carries the re-import action until 6.9; the status block signals it to the islands.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- the button in both notices, one at most, and the copy.
- [x] `post-quality-manager/admin/js/pqm-admin.js` -- the delegated listener and the event after each swap.
- [x] `src/setup/steps.js` -- the event after `swapStatusBlock()`.
- [x] `src/kit/use-reimport-shown.js`; `src/setup/index.js` and `src/n8n-connection/index.js` use it.
- [x] The tests above, and the memory line.

**Acceptance Criteria:**
- Given a site whose workflow is older, when the n8n Workflow page loads, then it shows exactly one primary button, labelled **Re-import the workflow**, inside the update notice.
- Given that button, when it is activated by mouse or keyboard, then focus is on the Workflow File heading.

Skipped: a different action for sites the plugin set up — entry 6.9 replaces the label with **Update workflow** there.

## Plan Change Log

- **2026-10-15, review pass 1 → bad_plan.**
  - **Trigger:** the "exactly one primary button" rule spans the page's two React islands, which the first Code Map left out.
    - The n8n Instance island's **Connect** stayed primary.
    - The guide's button read a GET /setup field (`update_needed`) that went stale whenever the status block was swapped (page test ping, window focus, provisioning), and showed as primary while GET /setup loaded.
  - **Amended:** the status block's rendered re-import button becomes the one source of truth, with a `pqm:workflow-status` event after every swap. Both islands read it; no REST field.
  - **Avoids:** two primary buttons on an outdated page, and a stale variant after a swap.
  - **KEEP from pass 1** (its diff is kept in the session scratchpad, `plan-c-pass1-keep.diff`):
    - the PHP button markup and the one-button-at-most rule across the two notices;
    - the delegated `.pqm-reimport` listener (scroll, then focus `#pqm-workflow-file`, `preventDefault`), with the no-JS fallback of a plain link;
    - the render-matrix rows for older, Free-on-Pro, both, current and not-checked states;
    - the jsdom tests for focus after a ping swap and after a window-focus swap, with document listeners removed after each test;
    - the `esc_html__` stub in `n8n-api-harness.php`;
    - the Free-build harness rows (no button for the Free variant on the Free build, one button for an older Free template);
    - the memory line.
  - **Copy:** "Workflow update available." then one sentence — "Your n8n workflow is older than this plugin needs; a re-import gives it a new production URL." — then the button. The paste step stays in Workflow File step 4.

## Review Triage Log

Pass 1 (2026-10-15, lens `quick`): 0 high, 2 medium, 1 low, 0 false, 0 maybe-false. Findings 1 and 2 share one root cause (the plan treated the page's primary action as PHP-only), routed bad_plan; code reverted, plan amended (Plan Change Log), re-derived. Finding 3 folded into the KEEP copy.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | The n8n Instance island's **Connect** is always primary, so an outdated page shows two primary buttons (AC1) | medium | bad_plan | `src/n8n-connection/index.js` L182–189; the render-matrix "only primary" check counts PHP markup only (`render_app_root()` emits an empty div). |
| 2 | The guide's button variant read `update_needed` from GET /setup, stale after any status-block swap outside the wizard and primary while GET /setup loads | medium | bad_plan | `swapWorkflowStatus()` (test ping, window focus) and `swapStatusBlock()` (provisioning) never tell the island. |
| 3 | The notice puts two sentences before the action and drops the paste consequence | low | folded | brand.md: the problem in one sentence, then the action; the paste step lives in Workflow File step 4. |

Pass 2 (2026-10-15, lens `quick`, the re-derivation): 0 high, 1 medium, 0 low, 0 false, 0 maybe-false. Patched.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | The notice says unconditionally that a re-import gives a new production URL; from version 6 the path is per site, so at the next template version it would contradict Workflow File step 4 | medium | patch | `pqm_webhook_path()`; the old copy and the ping message limit the claim to versions below 6. The Change Log's KEEP copy was wrong; the clause now shows only for a reported version below 6. |

## Verification

**Commands:**
- `php -l` on each touched PHP file; `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean
- `for h in tests/harness/*-harness.php; do php "$h" || echo "FAIL $h"; done` -- expected: no FAIL
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- expected: green
