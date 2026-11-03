---
id: 101
type: story
title: "The AI Model section offers the provider's current models, and the registry follows their life cycle"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The AI Model section offers the provider's current models, and the registry follows their life cycle

## Description

The AI Model section offers four Anthropic models: Claude Opus 4.8, Sonnet 5, Sonnet 4.6 and Haiku 4.5. The provider has released a newer generation since, which the plugin can reach only through the Custom model ID. This story brings the registry to the provider's current list and reviews the default model, so a site can choose the best model for its cost. It is the first ticket from the life cycle check (ADR 0017).

## Acceptance Criteria

1. **The current models can be chosen**
   **Given** the Settings → AI tab
   **When** an Administrator opens the model choice (default and per agent)
   **Then** it offers the provider's current generally available models the owner selects at refinement, each with its label and price, and still handles every model a site may have saved, as the refinement decides
2. **Every request a listed model receives is valid for it**
   **Given** each listed model
   **When** a command runs with it
   **Then** the request carries only the parameters that model accepts (sampling, effort levels, thinking), so no listed model answers 400 for the request's shape
3. **The default model is a decision**
   **Given** the registry's default
   **When** the story is done
   **Then** the default is the model the owner chose at refinement, and sites that never chose a model see the change in the release notes
4. **The labels state the provider's prices**
   **Given** each listed model's label
   **When** it is read
   **Then** its price matches the provider's price list on the day of the change
5. **Tests cover the registry**
   **Given** the harness sweep
   **When** it runs
   **Then** a harness row checks each listed model's request shape against the parameters it accepts

## Boundaries

- Must not change: the Custom model ID path; the request shape for a model the registry already lists, unless criterion 2 requires it.
- Out of scope: other providers (ADR 0009, story 25); moving to a newer Anthropic API version (see Notes).

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the models rule)
- decision — docs/adr/0009-multi-provider-models-and-token-limit.md
- source — memory/llm-options-architecture.md (the registry, payload-driven parameters)
- record — memory/lifecycle.md

## Notes

- Decision: owner, 2026-11-03 — the choice and the evolution of the available models is a PBI; life cycle management is defined by ADR 0017.
- Fact (`/lcm`, 2026-11-03, the provider's model reference cached 2026-10-06): every registry model is still served, and none is deprecated. Not listed: Claude Opus 5.5 (the provider's default), Sonnet 5.5, Haiku 5.5, Opus 5, Fable 5.1. The registry labels Sonnet 5 "$3 / $15"; its price is $2 / $10.
- Fact (2026-11-03): the workflow and the plugin send `anthropic-version: 2023-06-01`. On API version `2026-10-21` a request carrying `temperature` answers 400 on every model, and the registry still marks Sonnet 4.6 and Haiku 4.5 as taking temperature. Moving the API version is a separate decision; this story keeps 2023-06-01 unless the refinement says otherwise.
- Open question: which models to list (a short list per tier, or every current model), the default, and what happens to a saved model the new list drops.
- Open question: whether the template's model IDs change with the registry (a template version bump).
