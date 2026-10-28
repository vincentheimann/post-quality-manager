---
id: 67
type: story
title: "The n8n Workflow page shows one next step for the site's state, not everything at once"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: true
hitl: true
risk: medium
---

# The n8n Workflow page shows one next step for the site's state, not everything at once

## Description

The n8n Workflow page is rebuilt to the target layout the owner approved on 2026-10-14: a status block that is the one place for the site's state, the workflow version and the test; exactly one primary action per state; everything the state does not need behind collapsed disclosures. The five states and their actions are the experience spine's. The page is rebuilt as it ships after the n8n provisioning epic (entries 6.1–6.12), whose controls each landed where the spine's "Where each epic entry lands" table placed them. The webhook URL moves from Settings to this page, the per-agent table moves to Settings → AI, and the page-state findings of the epic's retrospective (item A7) land here. The owner walks the five states at the end (AC6, the hitl step).

## Acceptance Criteria

1. **An audit before the build**
   **Given** the page as it shipped on 2026-10-14
   **When** the story is refined
   **Then** a `bmad-ux` audit lists every element on the page (section, control, line of copy), the job it does and the site state it serves, and the owner approves a target layout per state before any code changes. *(Met on 2026-10-14; see Notes.)*
2. **One primary action per state**
   **Given** a site in each of the spine's five states: not set up; connected, not set up; manual path; set up; needs attention
   **When** an Administrator opens the page
   **Then** the status block says the state in one sentence, exactly one primary action is shown, and the sections that state does not need are collapsed behind a disclosure, not removed
   **And** the state is resolved in the spine's order, needs attention first, so a failing or outdated workflow is never shown as set up
3. **Each thing in one place**
   **Given** the webhook URL, the connection test, the workflow version and the webhook token
   **When** this page and the Settings page are compared
   **Then** each is shown and edited in one place only, as the spine's "One place for each thing" table says: the URL is edited on this page and shown read-only on Settings with a link here; the test and the version live in the status block; the token is shown on this page and Settings links to it instead of a field
4. **Reference material out of the main path**
   **Given** the per-agent parameters table
   **When** the page opens in any state
   **Then** the table is not on this page; it sits on the Settings page's AI tab beside the values it repeats
5. **Nothing lost**
   **Given** the epic's capabilities (CAP-1 to CAP-8), the manual path included
   **When** the rework is done
   **Then** every capability is still reachable from the page, and the Free and Pro builds both render every state without a locked or empty section
6. **The owner's pass**
   **Given** the reworked page on a site the owner walks
   **When** the owner walks each of the five states
   **Then** they name the next step in each state without reading the other sections
7. **The page follows the wizard**
   **Given** the page open behind the setup wizard
   **When** the wizard connects, provisions, adopts, or hands over to the manual path, and closes
   **Then** the status block, the primary action and the disclosures show the new state without a reload, and the n8n connection disclosure offers nothing the wizard already did
8. **A setup's outcome survives the dialog**
   **Given** a provisioning that finished, or that stopped at a named step
   **When** the dialog closes, by its own button or by a click outside it
   **Then** the status block says the outcome and the state's next step
9. **Settings never restores a stale token or URL**
   **Given** the Settings page opened before a rotation or a provisioning changed the webhook token or URL
   **When** that Settings form is saved afterwards
   **Then** the token and the URL stay as the rotation or provisioning left them
10. **A managed setup says what managing means**
    **Given** a provisioned or adopted setup
    **When** the page opens in the set up state
    **Then** the n8n connection disclosure says since when the plugin manages the workflow, that it can update the workflow and rotate the token from this page, and, for an adopted setup, that adoption changed nothing in n8n
11. **One wording per fact**
    **Given** every sentence and button the page and the wizard's n8n steps show
    **When** they are read against `docs/brand.md`
    **Then** each re-check button carries one name per job (the ping, the connection check, the URL save), message endings follow one pattern, the n8n API key hint appears once, every place that asks for the Anthropic key links to the same page of the Anthropic Console, and a Claude node carrying a credential of another type is named as such, never as "no credential is set"
12. **The status block announces its changes**
    **Given** a screen reader on the page
    **When** a test, a check, an update or a rotation replaces the status block
    **Then** the new state sentence is announced once, focus stays on the block, and opening a disclosure from a link moves focus to its heading

## Boundaries

- Must not change:
  - the REST routes and their contracts;
  - the workflow template and `PQM_WORKFLOW_VERSION`;
  - the version handshake;
  - what each capability does: this story changes presentation, state handling and copy, not what the plugin does in n8n;
  - the wizard's steps and their order (story 55); only its copy (AC11) and the page's reaction to it (AC7, AC8) change.
- Out of scope:
  - the Settings page's other sections, beyond the Connection tab's URL and token (AC3) and the AI tab's new table (AC4);
  - the Dashboard and Post Reviews pages; their "Set up the workflow" entry points keep linking here;
  - the round-trip test and the bounded first-review wait (story 2, retrospective A8);
  - a confirmation before Update workflow on an adopted workflow (story 76), unless the owner folds it in while this story is open;
  - starting the setup over (story 71).

## References

- user path — `docs/user-paths.md`, A4 (setup friction, UV4)
- design — `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md` (the five states, one place for each thing, build notes), `DESIGN.md`, `audit.md` (AC1), `mockups/key-workflow-page.html` (every state; the spines win on conflict)
- epic — `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning.md` (entries 6.6–6.10 added their controls to this page)
- retrospective — `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md`, item A7 and its sources (adversarial 6, duplication 8 and 10, pattern 4 and 5, edge cases E7 and E9)
- spec — `_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md` (CAP-1 to CAP-8, for AC5) and `failure-modes.md` (the causes that open the manual path)
- copy — `docs/brand.md`, Copy Rules
- precedent — backlog story 1 (the 2026-07-24 lean UX pass), story 28 (Settings tabs UX review)

## Notes

- Filed 2026-10-14 at the owner's request, during the entry 6.4 build. Unrefined; ranking is `/prioritize`'s job.
- Open question for the owner (at refinement): sequencing against the provisioning epic.
  - **(a) Before entry 6.6:** the epic's new controls land in the new layout. The audit then works from a page that is still changing.
  - **(b) After entry 6.12:** the rework audits the final page.
  - Recommended: (a), as a short design-only pass (the audit plus the approved target layout) before 6.6, with the build after 6.12. Entries 6.6–6.10 then follow the approved layout instead of adding sections.
- Lean UX practices to apply at refinement:
  - state the outcome as a hypothesis with a success signal (for example, time from opening the page to the first ping that passes, on the owner's walk);
  - progressive disclosure;
  - one primary action per screen state;
  - remove before adding;
  - validate with the owner's walk before polishing.
- Decision (owner, 2026-10-14): sequencing (a). The design-only pass ran before entry 6.6; the build lands after entry 6.12.
- Done (2026-10-14): AC1. The audit and the target layout per state are in `_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/` (`audit.md`, `EXPERIENCE.md`, `DESIGN.md`, `mockups/key-workflow-page.html`), approved by the owner on 2026-10-14. Layout: a status block (the one place for the state, the version and the test), one primary action per state, the rest in collapsed `<details>`. Five states, resolved in order: needs attention > set up > manual path > connected, not set up > not set up. The per-agent table moves to Settings → AI; the webhook URL is edited on this page only.
- Refinement input (owner, 2026-10-16, after the entry 6.12 walk on their Local sandbox): "the behaviour of this process must be much clearer for the user; lean UX and UX best practices apply here". Observations from that walk, for the build after 6.12:
  - The test lives at the bottom under "Connection Test" while the line under the title says "not checked yet": the owner asked "do you mean Send test ping?". The status block (EXPERIENCE.md) is the one place for the state and the test.
  - After adoption the only feedback is "The plugin has managed this workflow since …" plus a Rotate button: say what managing means (the plugin can update the workflow and rotate the token from here) and what changed (nothing in n8n).
  - (Checked, not a gap: a passing ping swaps in the status block, so a genuinely outdated workflow shows Update workflow at once; the reload in the walk was a test artifact of a version planted by WP-CLI behind the open page.)
  - The wizard's Set up the workflow step gives no link to where the Anthropic key is made; the Workflow File section does (the API keys page of the Anthropic Console). Same link in both.
  - The dialog's provisioning result vanished on a misclick; the state must survive outside the dialog (the status block).
  - The first-review step waits without end when the callback cannot land (the template's callback node continues on error, so n8n reports success): a bounded wait with a sentence pointing at the webhook URL and the site's reachability (backlog story 2 owns the round-trip test; the page owns the sentence).
  - The sequence the owner walked (ping, offer, proposal, managed line, update, rotate) needs the page to say the next step for the state, not the owner to know it: AC2 and AC6 stand; the audit's five states are confirmed by this walk.
- Refinement input (epic retrospective, 2026-10-16, item A7) — findings the page rebuild absorbs, each with its source in `epic-n8n-provisioning-retrospective.md`:
  - The page's n8n Instance island reads its state once on mount and the wizard uses its own copy of the island, so after the wizard connects, provisions or adopts, the page still shows the old state and its adoption offer answers "already provisioned" (edge-case E9). The rebuilt page re-reads its state when the dialog changes it.
  - The Settings form re-saves the webhook token and URL it rendered, so a Settings tab opened before a rotation or a provisioning and saved after restores the old values locally (adversarial 6). With the URL moved to this page (AC3), the token field stops being posted too.
  - Update workflow's result lands in a vanilla result line and Rotate's in a React notice, from two status-block swap helpers (duplication 8). One renderer for both.
  - Copy pass against `docs/brand.md`: the message endings ("then try again" / "then test again" / "Try again in a moment"), the re-check button named Check again, Test again and Save and check / Save and test, the n8n API hint in three copies, and the "no credential is set" sentence when a Claude node carries a credential of another type (pattern divergence 4, 5; duplication 10; edge-case E7).
- Refined 2026-10-19 and approved by the owner the same day (bmad-ticket, rendered by hand from the workspace root per the owner's 2026-10-16 rule): criteria 7–12 written from the Notes above and the retrospective's A7; AC2–AC4 now name the spine's five states and its "One place for each thing" table; the Boundaries name the sibling stories that own the neighbouring work (2, 71, 76). The 2026-10-14 audit, the spine, the design spine and the mock stand unchanged.
- Decision (owner, 2026-10-16, `/prioritize`): this story is built first, before backlog 71–78; story 76 folds in only if it comes up while this story is still open.
- Decision (spine, 2026-10-14): disclosures are native `<details>` with no remembered state; the status block is an `aria-live="polite"` region (AC12); the page re-reads its state when the wizard closes.
- Decision (retrospective A7, 2026-10-16): one renderer serves the status block for the page load, the ping, Update workflow and Rotate (duplication 8).
- Decision (owner, 2026-10-19, confirmed at refinement; drafted as an assumption): AC1 is met by the 2026-10-14 audit and the approved spine; no second audit. Entries 6.6–6.12 placed their controls per the spine's "Where each epic entry lands" table, so the build's plan opens with the delta between the shipped page and the spine, element by element, instead of a new inventory.
- Decision (owner, 2026-10-19, confirmed at refinement; drafted as an assumption): the per-agent table lands on Settings → AI as a read-only "What each agent sends" block under the fields it repeats (AC4; the spine names the tab, not the placement).
- Decision (owner, 2026-10-19, confirmed at refinement; drafted as an assumption): on Settings → Connection the Notification URL field stays as it is; the webhook URL becomes a read-only line with "Change it on the n8n Workflow page" and the Webhook Token row becomes one link to this page (AC3, AC9). The sanitizer keeps the stored URL and token when their fields are absent from the post (AGENTS.md "Disabled form controls don't POST").
- Decision (owner, 2026-10-19, confirmed at refinement; drafted as an assumption): the wizard opens at the state's step without a new start-step argument, because the step it resumes at (the first open one) is derived from the same stored setup progress as the page's state (spine build note); the plan verifies it before building on it.
- Decision (owner, 2026-10-19, confirmed at refinement; drafted as an assumption): the walk for AC6 runs on the owner's Local sandbox, the standard walk environment since entry 6.12; the needs-attention state is produced by planting an older version with WP-CLI as in that walk; the owner names the environment they used before it is recorded.
- Risk medium: the Settings sanitizer and the page's shared status-block code are touched; a mistake there resets a site's webhook URL or token silently. The sanitize matrix harness and the render matrix harness cover both paths; the owner's walk (AC6) is the check outside the criteria.
- Release note: the webhook URL moves from Settings to this page and Settings' Send test ping goes; one line in `docs/release-notes/unreleased.md` in the same PR (AGENTS.md Definition of Done).
- Paths for the builder (recorded at the owner's convention, not a reference): `post-quality-manager/admin/class-pqm-admin.php` (`render_workflow()`, `render_workflow_status()`, `render_workflow_file()`, the Connection tab in `render_settings()`, `sanitize_settings()`), `post-quality-manager/admin/js/pqm-admin.js` (`initTestConnection()`, `swapWorkflowStatus()`), `src/n8n-connection/`, `src/setup/`, `src/kit/use-reimport-shown.js`, `src/kit/swap-status-block.js`; tests `tests/js/workflow-status.test.js`, `tests/js/n8n-connection.test.js`, `tests/harness/render-matrix-harness.php`, `tests/harness/sanitize-matrix-harness.php`.
- Decision (owner, 2026-10-28): the walk for AC6–AC10 ran as an agent walk on the showcase site against the owner's own n8n instance, with temporary keys the owner created and revoked after (the environment the owner named, replacing the Local sandbox of the 2026-10-19 decision). AC6 met by the owner from the six state screenshots; AC12 not walked. Evidence in the plan's Implementation Notes. Story done.
