---
id: 123
type: story
title: "Claude Fable 5.2 gets a usable result from every agent, listed or through the Custom model ID"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# Claude Fable 5.2 gets a usable result from every agent, listed or through the Custom model ID

## Description

The provider released Claude Fable 5.2 as a current, generally available model. The AI Model section does not list it, so a site reaches it only through the Custom model ID, and that path sends a request shape that may not fit a model that always thinks. This story decides whether the registry lists Fable 5.2 and makes sure a site that picks it, either way, gets each agent's result. The person's part: the owner chooses at refinement and approves the paid live run that proves it.

## Acceptance Criteria

1. **Every agent returns its result on Fable 5.2**
   **Given** a site whose model is Claude Fable 5.2, chosen the way the refinement decides (listed, or as a Custom ID)
   **When** each command runs (Review and Takeaways on the Free build; Sources, SEO, Audit and Rewrite on the Pro build)
   **Then** each stores the agent's result, never "returned no usable result", and no request answers 400
2. **A listed Fable 5.2 states its price and gets its own request rules**
   **Given** the refinement decides to list it
   **When** an Administrator opens the model choice (default and per agent)
   **Then** Claude Fable 5.2 appears with the provider's list price on the day of the change, and its requests carry the effort levels and the thinking rule the provider documents for it
3. **A Custom ID of a model that thinks by default gets a usable result**
   **Given** a Custom model ID the registry does not list, of a model that thinks when the request omits the thinking key
   **When** a command runs with it
   **Then** the reply carries the agent's result, or a live run recorded in the plan shows the plain output budget already suffices
4. **The default model stays**
   **Given** a site that never chose a model
   **When** a command runs
   **Then** it uses Claude Sonnet 5.5, as before
5. **Tests cover the request shape**
   **Given** the harness sweep
   **When** it runs
   **Then** a harness row checks the request a Fable 5.2 site sends (listed or Custom) against the parameters the provider documents for it

## Boundaries

- Must not change: the default model; the request shape of the seven models the registry lists today; the `anthropic-version` the plugin and the workflow send (`2023-06-01`).
- Out of scope: other providers (ADR 0009); moving to API version `2026-10-21` (story 101's Notes).

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the models rule)
- source — memory/llm-options-architecture.md (the registry, payload-driven parameters, the thinking rule)
- record — memory/lifecycle.md (AI models row, `/lcm` 2026-11-11)
- related — _bmad-output/backlog/story-model-registry-follows-the-provider.md (story 101: why Fable 5.1 is not listed)
- source — https://platform.claude.com/docs/en/models/overview
- source — https://platform.claude.com/docs/en/about-claude/model-deprecations

## Notes

- Decision: owner, 2026-11-11 — file it from `/lcm`; not on the launch path, it does not block 4.0.0.
- Fact (`/lcm`, 2026-11-11, the provider's models overview and deprecations page read that day): Claude Fable 5.2 (`claude-fable-5-2`) is current and generally available: $8 / $40 per million tokens, adaptive thinking always on, default effort `high`, 1M context, 128K output, retirement not sooner than 2027-11-09. Fable 5.1 is now legacy. The seven registry models are all active, none deprecated.
- Fact: the Custom profile sends no effort, no thinking key and no thinking reserve (story 101), and thinking counts against `max_tokens`. The agent options travel in the payload, so a registry change needs no workflow re-import.
- Assumption: an always-thinking model may spend a small budget (Review: 2,500 tokens) on thinking and return no text. Unverified: settling it takes a paid call.
- Open question: list Fable 5.2 (and Fable 5.1, now legacy), or keep both on Custom, as story 101 chose for Fable 5.1.
- Open question: whether criterion 3's answer also covers the other models that think by default and are reached only through Custom (Opus 5, Fable 5.1).
- Open question: the live run's key and license follow bug 121's walk (the key from `.secrets/`, never printed; the owner activates and deactivates the license); the owner approves the spend first.
