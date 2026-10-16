---
id: 67
type: story
title: "The n8n Workflow page shows one next step for the site's state, not everything at once"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The n8n Workflow page shows one next step for the site's state, not everything at once

## Description

Owner request (2026-10-14): the n8n Workflow page needs a large UX rework to cut cognitive overload, applying lean UX practices. Today the page stacks every section whatever the site's state:
- the setup dialog's button;
- the version status line and update notice;
- the n8n Instance island (connect by API key);
- the Workflow File section (four numbered manual steps with copy rows);
- a five-column table of what each agent sends to Claude;
- the Connection Test.

The webhook URL it depends on is edited on the Settings page instead.

A blogger evaluating the plugin meets all of this at the funnel's friction wall (user path A4, UV4) and has to work out alone which part applies to them. The provisioning epic will add more to the same page: the wizard path (entry 6.6), the mis-wiring diagnosis (6.7), adoption (6.8), update (6.9) and token rotation (6.10).

This story reshapes the page around the site's state: one primary action per state, and the rest disclosed only when asked for. Each piece of information sits in one place, and the page's copy follows `docs/brand.md`.

## Acceptance Criteria

1. **An audit before the build**
   **Given** the page as it ships at refinement time (epic entries included)
   **When** the story is refined
   **Then** a `bmad-ux` audit lists every element on the page (section, control, line of copy), the job it does and the site state it serves. The owner approves a target layout per state before any code changes.
2. **One primary action per state**
   **Given** a site in each state the audit names (at least: not connected, connected but not set up, set up and answering, set up but outdated or failing, manual path chosen)
   **When** an Administrator opens the page
   **Then** exactly one primary action is shown. The sections that state does not need are hidden or collapsed behind a disclosure, not removed.
3. **Each thing in one place**
   **Given** the webhook URL, the connection test and the workflow version
   **When** the page and the Settings page are compared
   **Then** each is shown and edited in one place only. The other place links to it instead of repeating it.
4. **Reference material out of the main path**
   **Given** the per-agent parameters table
   **When** the page opens in any state
   **Then** the table is not part of the setup path. It sits behind a disclosure or on the Settings page's AI tab, as the audit decides.
5. **Nothing lost**
   **Given** the epic's capabilities (CAP-1 to CAP-8) and the manual path (CAP-8)
   **When** the rework is done
   **Then** every capability is still reachable from the page, and the Free and Pro builds both render without a locked or empty section.
6. **The owner's pass**
   **Given** the reworked page on the owner's site
   **When** the owner walks each state from the audit
   **Then** they confirm the page answers "what do I do next" in each state without reading the other sections.

## Boundaries

- Must not change:
  - the REST routes and their contracts;
  - the workflow template and `PQM_WORKFLOW_VERSION`;
  - the version handshake;
  - what each capability does. This story changes presentation and copy, not behaviour.
- Out of scope:
  - the Settings page's other sections;
  - the Dashboard and Post Reviews pages;
  - the setup dialog's non-n8n steps (story 55).

## References

- user path — `docs/user-paths.md` A4 (setup friction, UV4)
- epic — `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning.md` (entries 6.6–6.10 add to this page)
- copy — `docs/brand.md` (Copy Rules)
- code — `post-quality-manager/admin/class-pqm-admin.php` `render_workflow()`, `render_workflow_status()`, `render_workflow_file()`; `src/n8n-connection/`; `src/setup/`
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
