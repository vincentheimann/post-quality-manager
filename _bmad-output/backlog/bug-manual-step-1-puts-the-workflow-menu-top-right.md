---
id: 120
type: bug
title: "The manual setup's step 1 sends the user to the top right for the workflow menu, which n8n shows beside the workflow's name"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: true
hitl: false
risk: low
severity: P3
estimate: ""
---

# The manual setup's step 1 sends the user to the top right for the workflow menu, which n8n shows beside the workflow's name

## Description

Story 111's walk (2026-11-10) followed the n8n Workflow page's Set up by hand steps on a disposable n8n 2.46.7. Step 1 says to "open the workflow menu (the three dots at the top right)". In n8n 2.46.7's editor the three dots sit beside the workflow's name at the top left; the top right holds **Publish** and its chevron. A blogger who looks where the step says finds Publish, not the menu. The import itself works once the menu is found (story 111's walk: "Import" → "From file" lands the workflow).

Cause (hypothesis): the words came with the Workflow File section (provisioning entry 2, 21de5b3, 2026-10-14). Its label check says it read the `editor-ui` components "for where they sit" at `n8n@2.42.3`, but `MainHeader/WorkflowDetails.vue` at that tag already puts the ⋯ right after the workflow's name, as at 2.46.7: the check confirmed the labels and missed the position.

## Reproduction

1. The showcase, the Free build of main 5288cb3 (3.13.1); a disposable n8n 2.46.7 (`docs/local-verification.md`, "A disposable n8n").
2. n8n Workflow page → **Set up by hand instead** → step 1, "Import the workflow".
3. In n8n, open a new workflow and look at the top right.
4. Actual: Publish and its chevron. The three dots are at the top left, right after the workflow's name ("My workflow").
5. Expected: the step names where the menu is.

## Acceptance Criteria

1. **Step 1 places the menu where n8n shows it**
   **Given** the n8n Workflow page's manual path, on the Free and the Pro build, with or without a known n8n address
   **When** a blogger reads step 1
   **Then** it sends them to the three dots right after the workflow's name, at the top left of n8n's editor
2. **The checks hold the new words and refuse the old**
   **Given** the harnesses that read step 1 on both builds
   **When** they run
   **Then** they pass on the new sentence and fail if "at the top right" comes back anywhere on the page

## Boundaries

- Must not change: the rest of step 1 (the empty workflow, "Import" → "From file", n8n 3's "Import JSON", the Download button), the other setup steps, the workflow template, the guided setup dialog.

## References

- found by — _bmad-output/backlog/story-n8n-3-readiness-plan.md (story 111's walk, 2026-11-10)
- n8n's words — memory/n8n-provisioning.md (the menu labels line, which still names this bug and is updated by the build; the n8n 3.1.0 rules)
- source — https://github.com/n8n-io/n8n/blob/n8n@2.46.7/packages/frontend/editor-ui/src/app/components/MainHeader/WorkflowDetails.vue (and the same path at `n8n@3.1.0` and `n8n@3.2.0`)

## Notes

- Fact (story 111's walk, 2026-11-10, n8n 2.46.7, screenshots in that session's scratchpad, deleted with it): the header reads "Personal / <workflow name> ⋯" at the left, Editor / Executions / Evaluations in the middle, Publish with its chevron at the right.
- Fact (n8n source read 2026-11-10 at `n8n@2.46.7`, `n8n@3.1.0` and `n8n@3.2.0`, with 2.36.0 and 2.40.0 spot-checked): `MainHeader/WorkflowDetails.vue` lays one row: the breadcrumbs ("Personal" / the workflow's name), then the ⋯ menu (`ActionsDropdownMenu.vue`, an icon button labelled "More actions" for screen readers, no visible text), then a spacer, then Publish at the right. The tabs are centred on the header's lower edge, outside that row. `MainHeader.vue`, `WorkflowDetails.vue` and `TabBar.vue` are byte-identical at the three tags (`WorkflowDetails.vue` also at 2.42.3 and 2.47.3); `ActionsDropdownMenu.vue` differs only in the import entry ("Import" → "From file" in 2.36+, "Import JSON" in 3.x). So n8n 2.36 and later and n8n 3 put the menu in the same place. n8n 3.2.0 is a pre-release of 2026-11-10; 3.x has no stable release yet.
- Handoff: story 119 (the template on n8n 3's first stable release) also checks the menu's position this step names, since this ticket's n8n 3 reading comes from pre-release source.
- Decision (owner, 2026-11-10, the first `/prioritize` run that day): severity P3 accepted.
- Decision (owner, 2026-11-10, the second `/prioritize` run): bug 120 is built before chore 87 → entry 4.8, so 4.0.0 does not ship the wrong position.
- Decision (owner, 2026-11-10, approving the refinement after the `validate.md` check: 3 fixes and 2 suggestions applied, no ask): the new words are "open the workflow menu (the three dots right after the workflow's name, at the top left)". They keep the menu's name and the three dots the editor shows, and add the name as the landmark. "More actions" is left out because it is screen-reader text only.
