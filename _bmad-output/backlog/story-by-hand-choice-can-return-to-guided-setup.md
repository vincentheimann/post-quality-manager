---
id: 106
type: story
title: "A blogger who chose to set up n8n by hand can go back to the guided setup"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# A blogger who chose to set up n8n by hand can go back to the guided setup

## Description

"Set up by hand instead" stores the blogger's choice, and nothing clears it: the n8n Workflow page then reads "You chose to set up n8n by hand." with the manual steps open, and offers no way back to the setup dialog's key path. A blogger who changes their mind (they got an n8n API key after all, or the manual steps look longer than expected) is stuck on the manual path. This story adds a link right after that sentence that clears the choice and opens the guided setup at its first open step.

## Acceptance Criteria

1. **The way back is offered where the choice is shown**
   **Given** a site whose owner chose to set up n8n by hand, with no webhook URL saved yet
   **When** the n8n Workflow page or the setup dialog's Set up by hand step shows "You chose to set up n8n by hand."
   **Then** a link "Let the plugin set up n8n instead" follows that sentence
2. **The link clears the choice and opens the guided setup**
   **Given** that link
   **When** it is clicked
   **Then** the stored choice is removed, the setup dialog shows Connect your n8n (or Set up the workflow when a key already passes the check), and the page behind it reads "Not connected to n8n yet." or "Connected to n8n at …", without a reload
3. **The choice can be made again**
   **Given** the guided setup after the way back
   **When** the blogger picks "Set up by hand instead" again
   **Then** the choice is stored and the page reads "You chose to set up n8n by hand." as before
4. **Only the blogger's own choice gets the link**
   **Given** the manual state reached by a failed connection check (the "the n8n connection check did not pass" line) or by a workflow made by hand (a saved webhook URL)
   **When** the page or the dialog renders
   **Then** the link is not shown
5. **The API refuses anything but the two choices**
   **Given** POST `/setup` with `manual`
   **When** the value is `true` or `false`
   **Then** it stores or clears the choice; any other value answers 400 with the existing message

## Boundaries

- Must not change: the key path's steps, provisioning, adoption of a hand-made workflow (entry 6.8), the stored n8n connection, anything on n8n.
- Out of scope: a way back from a failed connection check (its fix is the key or the instance, not the choice); the "Connect by key instead" disclosure inside the dialog's Set up by hand step (refinement decides whether the new link replaces it).

## References

- decision — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning.md L56 (the manual path open by choice)
- source — `admin/class-pqm-admin.php` (the `manual` state's sentence and actions, the `pqm-by-hand-link`), `src/setup/steps.js` (`Manual`), `src/setup/index.js` (`onByHand`), `includes/class-pqm-rest-api.php` (`update_setup_callback`: only `manual: true`, "nothing clears it")
- spec — _bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md (Flow 2, the microcopy table)
- tests — `tests/harness/render-matrix-harness.php` (the "by choice" rows), `tests/harness/free-build-harness.php`, `tests/js/setup-provision.test.js`

## Notes

- Decision: owner, 2026-11-03 — asked for a way back to the guided setup after "You chose to set up n8n by hand.", with wording adapted from their draft "I change my mind and I would like to use the wizard."
- Proposal (agent, 2026-11-03), for the owner to confirm at refinement: **"Let the plugin set up n8n instead"**. It mirrors "Set up by hand instead" (the "… instead" pair the dialog already uses with "Connect by key instead"), says who does the work, and avoids "wizard", which no interface string uses (brand.md: no jargon, concise). Alternatives: "Use the guided setup instead"; "Changed your mind? Let the plugin set up n8n."
- Fact (2026-11-03): in the page's `manual` state the primary action is empty (Save and test sits under step 4), so the page has no control that opens the dialog; inside the dialog, "Connect by key instead" opens the key form only while no webhook URL is saved, and a passing key then outranks the stored choice.
- Open questions for refinement: whether the link is a link or a secondary button on the page; whether it also replaces "Connect by key instead" in the dialog; whether a site in the `manual` state with a passing key (the page keeps the choice there) shows the link too.
