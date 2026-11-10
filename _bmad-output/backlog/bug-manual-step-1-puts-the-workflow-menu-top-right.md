---
id: 120
type: bug
title: "The manual setup's step 1 sends the user to the top right for the workflow menu, which n8n shows beside the workflow's name"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# The manual setup's step 1 sends the user to the top right for the workflow menu, which n8n shows beside the workflow's name

## Description

Story 111's walk (2026-11-10) followed the n8n Workflow page's Set up by hand steps on a disposable n8n 2.46.7. Step 1 says to "open the workflow menu (the three dots at the top right)". In n8n 2.46.7's editor the three dots sit beside the workflow's name at the top left; the top right holds **Publish** and its chevron. A blogger who looks where the step says finds Publish, not the menu. The import itself works once the menu is found (story 111's walk: "Import" → "From file" lands the workflow). The words came with the Workflow File section (provisioning entry 2, 21de5b3, 2026-10-14) and were never checked against a running editor; `memory/n8n-provisioning.md` verifies labels against `en.json`, and where they sit against the `editor-ui` components.

## Reproduction

1. The showcase, the Free build of main 5288cb3 (3.13.1); a disposable n8n 2.46.7 (`docs/local-verification.md`, "A disposable n8n").
2. n8n Workflow page → **Set up by hand instead** → step 1, "Import the workflow".
3. In n8n, open a new workflow and look at the top right.
4. Actual: Publish and its chevron. The three dots are at the top left, right after the workflow's name ("My workflow").
5. Expected: the step names where the menu is.

## Acceptance Criteria

1. **Step 1 places the menu where n8n shows it**
   **Given** the n8n Workflow page's manual path, on the Free and the Pro build
   **When** a blogger reads step 1
   **Then** it places the workflow menu where n8n 2.36+ and n8n 3 show it (for example "the three dots beside the workflow's name"), and `render-matrix-harness.php` / `free-build-harness.php` check the new words

## Notes

- Fact (story 111's walk, 2026-11-10, n8n 2.46.7, screenshots in that session's scratchpad, deleted with it): the header reads "Personal / <workflow name> ⋯" at the left, Editor / Executions / Evaluations in the middle, Publish with its chevron at the right.
- Open question (refinement): where n8n 3 puts the menu; read the header component at the n8n 3 tag story 119 uses, or at `n8n@3.1.0`, before writing the words.
- Open question (owner): severity; P3 proposed (the step still names the entries, and the menu is one glance away).
