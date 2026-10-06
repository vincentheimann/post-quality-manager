---
id: 30
type: story
title: "The AI Model section can use free models"
parent: none
covers: []
after: [25, 31]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The AI Model section can use free models

## Description

Owner request (2026-10-06). Every agent node in the n8n template calls `api.anthropic.com` directly. Only Claude models are offered, and every command costs Anthropic credits. This story adds OpenRouter as the fourth provider in the picker story 25 builds, with its free router and free models, so an owner can run every agent at no model cost. OpenRouter takes the same request format as OpenAI, so it reuses story 25's non-Anthropic request path. The person step: the owner creates an OpenRouter account and key and stores the key in n8n.

## Acceptance Criteria

Verify: with a free model selected as the default, and then as one agent's override, each command completes end to end and its result renders like a Claude result. The result's JSON fits the shape the plugin expects, or a failure is stored as an error and not as an empty review. Sites that keep a Claude model see no change in requests or results.

## References

- code — post-quality-manager/post-quality-manager.php, `pqm_model_registry()`, `pqm_model_caps()`, `pqm_llm_params()`
- code — post-quality-manager/templates/n8n-workflow.json (the non-Anthropic request path story 25 adds)
- OpenRouter — https://openrouter.ai/docs/quickstart and https://openrouter.ai/docs/faq (read 2026-10-06)
- memory — memory/llm-options-architecture.md
- deferred — _bmad-output/initiative-post-quality-manager/deferred-work.md (template failure paths post `status: ''`, not `error`)

## Notes

- Decision (owner, 2026-10-06): available on both the free and the pro plan.
- Decision (2026-10-06): OpenRouter is the free provider. It takes OpenAI-format requests at `https://openrouter.ai/api/v1`, one key covers every model, free models carry a `:free` suffix, and `openrouter/free` picks a free model automatically. The picker offers `openrouter/free` as the seventh model, beside story 25's six.
- Free models are limited to 50 requests per day on an account without credits and 1000 per day once $10 of credits are bought. A refused request must be stored as a failed review that says so, not as an empty review; this depends on the template failure path listed in deferred-work.
- Open question: free models are less reliable at returning strict JSON. Decide at the plan checkpoint whether a free model is offered for every agent or only for Review, Takeaways, Sources and SEO (Rewrite and Audit need the largest outputs).
- A free model's tokens still count toward story 29's token limits.
