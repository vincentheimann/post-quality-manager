---
id: 115
type: bug
title: "In the setup dialog, the Set up the workflow button sits lower than the Anthropic key field"
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

# In the setup dialog, the Set up the workflow button sits lower than the Anthropic key field

## Description

On step 2 of 5 of the setup dialog ("Set up the workflow"), the **Set up the workflow** button stands beside the Anthropic API key field but about 30 px lower: its bottom edge lines up with the help line under the field ("Create one on the API keys page of the Anthropic Console."), not with the field. The button looks detached from the field it submits. The owner reported it during the sprint walk.

## Reproduction

1. The showcase, 2026-11-04 (the sprint walk), Free build from `fix/112-114-workflow-left-in-n8n` (3.11.1 plus bugs 112 and 114); any build with the setup dialog.
2. n8n Workflow page → Setup guide, connect n8n (step 1), at desktop width.
3. Actual: on step 2, the button's top is below the field's top, its bottom on the help line's bottom.
4. Expected: the button lines up with the field, and the help line sits under the field alone.

## Cause Hypothesis

`.pqm-setup-connect` (`src/setup/style.css` L53–60) is a wrapping flex row with `align-items: flex-end`, so every child meets the row's bottom. Step 1's field has no help line, so its button meets the input's bottom edge; step 2's `TextControl` carries `help` (`src/setup/steps.js` L350–374), which makes the control's bottom the help line's. On the Pro build, the Application Password sentence (`steps.js` L375–387, a `<p>` inside the same form) becomes a third item of the row: check that state too. `.pqm-setup-run` (`src/setup/first-review.js` L280) shares the rule; its `SelectControl` has no help line.

## Acceptance Criteria

1. **The button lines up with the key field**
   **Given** step 2 of the setup dialog, on the Free build and on the Pro build with the Application Password sentence
   **When** it is shown at 1280 px and at 400 px
   **Then** the button lines up with the key field (beside it, or under it when the row wraps), and the help line and the Pro sentence stay under the field
2. **The other rows keep their layout**
   **Given** step 1 (Connect your n8n) and the first review's row
   **When** they are shown at both widths
   **Then** they look as before the fix
3. **The check is recorded**
   **Given** the fix
   **When** it is checked on the showcase
   **Then** the plan's notes name each state and width seen (no unit test reads a layout in jsdom)

## References

- found by — _bmad-output/backlog/bug-update-workflow-fails-after-free-to-pro-plan.md (the sprint walk, 2026-11-04)
- source — `src/setup/style.css` L53–66, `src/setup/steps.js` L104 (step 1's form) and L349–396 (step 2's), `src/setup/first-review.js` L280

## Notes

- Fact (owner, 2026-11-04, the sprint walk): "The "Set up the workflow" button have a bad alignment.", with a screenshot of step 2 of 5 on the Free build at desktop width. The screenshot is not in the repository.
- Filed 2026-11-05 in the sprint's records PR, unrefined.
