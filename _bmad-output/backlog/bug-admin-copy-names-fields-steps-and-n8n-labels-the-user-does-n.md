---
id: 147
type: bug
title: "Admin copy names fields, steps and n8n labels the user does not see"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# Admin copy names fields, steps and n8n labels the user does not see

## Description

Several admin sentences name a field, a step, a capability or an n8n label that the user cannot find where the sentence points: an editable Webhook URL field that Settings shows read-only, "activated" where n8n says "Publish", a pattern setting Free does not have, "Get Pro" where every other place says "Renew Pro". Each sentence should name what the user sees.

## Reproduction

1. Pro 4.0.5 on the Free-variant workflow (story 38's N4, 2026-11-19): the ping's Free-variant sentence (and the older-version one) says to paste the production URL "as the n8n Webhook URL in Settings"; Settings shows that URL read-only since story 67, and a managed site's fix is Update workflow.
2. Free 4.0.4 against n8n 2.48.5 (N1, 2026-11-18): the 404 test sentence and the trigger refusal say "activated" / "active", where n8n 2.48.5 and the plugin's own step 4 say "Publish". The manual import step names neither n8n 2.48.5's "Importing this from an AI tool?" dialog nor its autosave (the editor has no Save button).
3. Free 4.0.5 (E looks, sitting D): the plugin says both "Set up n8n" and "Set up the workflow" for the same step.
4. Pro 4.0.7 (sitting F, 2026-11-23): Settings → Usage & Limits help says "paid-agents capability"; the Roles tab names it "Run paid agents". The Agent-Specific Overrides intro says "Only the controls the selected model supports are shown", yet each box shows a Temperature slider marked "Not supported by the selected model" (deferred-work.md L143–145 notes the Reasoning Effort select too).
5. Free 4.0.5: the Insert into Post dialog says "using the pattern chosen in Settings"; Free has no Pattern setting and inserts a plain list.
6. Pro 4.0.2 on a lapsed license (the 2026-11-17 Pro walk): the setup guide's schedule step says "Scheduled reviews are available with Pro. Get Pro", while Settings → License and the SDK notice say "Renew Pro".
7. Expected: each sentence names the field, step, label or action the user can see and use.

## Cause Hypothesis

The sentences were written before later stories changed the screens they point to (story 67's read-only URL, n8n's rename to Publish, story 54's Free build without patterns, bug 93's lapse copy). Bug 124 covers one sibling ("Workflow File section").

## Acceptance Criteria

1. **Copy names what the user sees**
   **Given** each case in the reproduction
   **When** the sentence shows
   **Then** it names the field, step, capability or n8n label as the screen shows it, and offers the action that applies (Update workflow on a managed site, Renew Pro on a lapsed one, no pattern on Free)
2. **The overrides intro matches its controls**
   **Given** a model that does not support a control
   **When** Agent-Specific Overrides shows
   **Then** either the control is hidden, as the intro says, or the intro says unsupported controls show as unsupported
3. **Tests cover the condition found and fixed**
   **Given** the harnesses and the JS tests that hold these strings
   **When** they run
   **Then** a test that fails on the defect covers the Free-variant sentence and the Free Insert dialog
4. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected copy already shows, with the evidence recorded in Notes — this supersedes 1 to 3

## References

- parent — none
- source — story 38's plan, Implementation Notes: the 2026-11-17 Pro walk, N1 (2026-11-18), the 2026-11-19 frame N4 and E looks sitting D, the 2026-11-23 licensed sitting (P054)
- copy rules — `docs/brand.md`
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L143–145
- related — bug 124 (n8n errors name a section the page no longer has)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch as its own ticket beside bug 124; does not block the WordPress.org submission.
- Assumption: severity P3 — copy; every action still works. Risk low.
- Open question: one name for the setup step ("Set up n8n" or "Set up the workflow").
