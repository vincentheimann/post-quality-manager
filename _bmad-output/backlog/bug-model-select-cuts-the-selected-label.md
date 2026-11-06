---
id: 116
type: bug
title: "In Settings, the model selects cut the selected model's label: the default reads \"… ($2 / $10 pe\""
parent: none
covers: [UV2]
after: []
assignee: ""
refined: true
hitl: false
risk: low
severity: P2
estimate: ""
---

# In Settings, the model selects cut the selected model's label: the default reads "… ($2 / $10 pe"

## Description

Settings → AI shows the Default Model select with its label cut: "Claude Sonnet 5.5 — best quality/cost balance ($2 / $10 pe". WordPress core caps every `select` at `max-width: 25rem` (400 px), and since story 101 (3.11.0) two labels need more: Sonnet 5.5, the default every fresh site gets, about 461 px, and Haiku 5.5 ("… ($0.10 / $0.50 per MTok)"). The same cap cuts them in the six Agent-Specific Overrides model selects. The open dropdown list shows every label whole; the closed control does not. A buyer on the Features page and every new site see a price cut in half.

## Reproduction

1. The showcase, 2026-11-06, the Pro build of main 12c7a77 (3.12.0 + bug 99), licensed; a site that never saved a model.
2. Settings → AI, at window widths 790, 1024, 1280 and 1440 px.
3. Actual: the Default Model select is 400 px wide at every width (computed `max-width: 400px`); the selected label needs about 461 px and ends "pe". Selecting Haiku 5.5 cuts its label too; each `postquality_settings[agent_prompts][<agent>][model]` select does the same.
4. Expected: the selected label reads whole whenever the form's cell is wide enough for it.

## Cause

WordPress core's forms stylesheet caps `select` at 25rem; `admin/css/pqm-admin.css` sets no width for the model selects. `#pqm_default_model` carries no class; the override selects carry `pqm-agent-model` (`admin/class-pqm-admin.php` L873, L1062–1064).

## Acceptance Criteria

1. **The selected label reads whole**
   **Given** Settings → AI on a desktop window (1280 px and the 790 px capture window)
   **When** the Default Model or an agent's Model select shows Claude Sonnet 5.5 or Claude Haiku 5.5
   **Then** the closed select is wide enough for its longest label, and the label reads whole
2. **Nothing else moves**
   **Given** the other selects on Settings (effort, pattern, schedule, command) and a phone width (400 px)
   **When** the page is shown
   **Then** they look as before the fix, and on a phone the model selects stay inside the screen
3. **The check is recorded**
   **Given** the fix
   **When** it is checked on the showcase
   **Then** the plan's notes give each select's measured width against its label's at each width seen (no unit test reads a layout in jsdom)

## References

- found by — plan 101's `feature-ai` capture, 2026-11-06 (`_bmad-output/backlog/story-model-registry-follows-the-provider-plan.md`)
- source — `admin/class-pqm-admin.php` L873 (Default Model), L1062–1064 (agent Model); `admin/css/pqm-admin.css`; registry labels in `includes/pqm-functions.php`

## Notes

- Fact (agent, 2026-11-06): measured with Playwright driving Edge on the showcase; every closed model select computes `max-width: 400px` at 790, 1024, 1280 and 1440 px; label needs measured with the select's own font plus 48 px for padding and arrow.
- Owner decision (2026-11-06): fix it in this sitting, before plan 101's capture, rather than capture the cut label or shorten the labels.
