---
id: 107
type: bug
title: "On a phone, the AI tab shows the empty Custom model ID field when Custom is not selected"
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

# On a phone, the AI tab shows the empty Custom model ID field when Custom is not selected

## Description

On a screen narrower than 783 px, Settings → AI shows an empty text field with the placeholder "claude-…" under **Default Model**, although a listed model is selected, not "Custom model ID…". On a wider screen the field stays hidden until Custom is chosen. The stray field suggests a value is missing.

## Reproduction

1. The showcase, plugin 3.10.2 Pro build, 2026-11-03 (story 100's capture walk); any build with the AI tab.
2. Settings → AI, Default Model set to a listed model (Claude Sonnet 4.6), at a 400 px phone viewport.
3. Actual: the empty "claude-…" field shows under the select (story 100's phone capture `feature-ai-phone`, site branch `feat/site-features-page` commit `8480c12`; not published).
4. Expected: no field until "Custom model ID…" is selected, as on a desktop.

## Cause Hypothesis

The field carries `.pqm-hidden { display: none; }` (`admin/css/pqm-admin.css` L20). WordPress's mobile rules for `.form-table` text inputs, inside `@media screen and (max-width: 782px)`, set their `display` with a more specific selector, so they win below 783 px. Check whether the per-agent override fields that use the same class meet the same rule.

## Acceptance Criteria

1. **The field stays hidden on every width until Custom is chosen**
   **Given** the AI tab with a listed model selected
   **When** it is shown at 400 px and at 1280 px
   **Then** no Custom model ID field shows; choosing "Custom model ID…" shows it at both widths
2. **Tests cover the condition found and fixed**

## References

- found by — _bmad-output/backlog/story-site-features-page-marks-pro.md (story 100's capture walk)
- source — `admin/class-pqm-admin.php` (the `pqm_custom_model` input), `admin/css/pqm-admin.css` L20

## Notes

- Decision: owner, 2026-11-03 (story 100, review pass 1) — the site's Features page shows the AI section's desktop capture on phones; after this fix, a phone crop can be captured for it.
