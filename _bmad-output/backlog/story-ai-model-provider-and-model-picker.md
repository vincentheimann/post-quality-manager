---
id: 25
type: story
title: "The AI Model section picks a provider, then a model, across Anthropic, OpenAI and Google"
parent: none
covers: [O5]
after: [6, 22, 27, 31]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The AI Model section picks a provider, then a model, across Anthropic, OpenAI and Google

## Description

Owner request (2026-10-06). The model registry offers four Claude models only, the default is `claude-sonnet-4-6`, and every agent node in the n8n template calls the Anthropic API. This story redesigns the AI Model block as a provider choice followed by a model choice, with the Custom model ID entry kept as it works today, and fills it with six current models from three providers (the table in Notes). The per-agent Model overrides use the same provider-then-model choice. The template gains one request path for the non-Anthropic providers beside the existing Anthropic one. The person step: the owner creates an API key for each provider they use and stores it in n8n.

## Acceptance Criteria

Verify: the AI Model block shows a provider choice, then only that provider's models, plus the Custom model ID entry; every agent's override offers the same choice. A command sent with each of the six models completes end to end with no HTTP 400 for an unsupported parameter, and its result renders like today's Claude results. Each model's label and pricing on the page match its provider's docs. A site whose stored default or agent override names a model no longer listed keeps working, and the owner can see which model it now uses. Stored settings on a site that keeps a Claude model produce the same requests as before.

## References

- decision — docs/adr/0009-multi-provider-models-and-token-limit.md
- code — post-quality-manager/post-quality-manager.php, `pqm_default_settings()`, `pqm_model_registry()`, `pqm_model_caps()`, `pqm_effective_model()`, `pqm_llm_params()`
- code — post-quality-manager/admin/class-pqm-admin.php, AI Model section and the Custom model ID field
- code — post-quality-manager/templates/n8n-workflow.json (six Anthropic HTTP Request nodes with header-auth credentials; fallback `model` in the agent Code nodes)
- memory — memory/llm-options-architecture.md (per-agent model, inherit, model-caps decisions)
- Claude models — claude-api skill, Current Models table (cached 2026-09-25) and the Thinking & Effort table
- OpenAI models — https://developers.openai.com/api/docs/models (read 2026-10-06)
- Gemini models — https://ai.google.dev/gemini-api/docs/models and https://ai.google.dev/gemini-api/docs/pricing (read 2026-10-06)

## Notes

- Decision (owner, 2026-10-06): the picker is provider, then model, like the existing Custom model ID choice. Available on both the free and the pro plan. The per-agent overrides stay Pro, as the access matrix says; the new choice inside them follows that gate.
- Decision (2026-10-06): providers are Anthropic, OpenAI and Google; OpenRouter is the fourth, added by story 30. Models, chosen for being stable (not preview), mid or low priced and reliable at returning JSON — popularity itself is not measurable:

  | Provider | Model ID | Input / output per 1M tokens |
  |---|---|---|
  | Anthropic | `claude-sonnet-5-5` — **default** | $2 / $10 |
  | Anthropic | `claude-opus-5-5` | $4 / $20 |
  | Anthropic | `claude-haiku-4-5` | $1 / $5 |
  | OpenAI | `gpt-6.1-sol` | $2 / $10 |
  | OpenAI | `gpt-6-luna` | $0.10 / $0.50 |
  | Google | `gemini-3.8-flash` (has a free tier) | $0.75 / $3.75 |

  Flagship-priced models (Claude Fable 5.1, GPT-6 Astra, Gemini 3.1 Pro preview) stay reachable through the Custom model ID.
- Decision (2026-10-06): the default is `claude-sonnet-5-5`: the agent prompts were tuned on Claude, and it is cheaper than the current default. It needs registry changes: no temperature, effort `low`–`high` with default `high`, and thinking turned off with `between_tools` — the `disabled` value the registry uses for Sonnet 5 returns a 400 on 5.5, and `between_tools` itself returns a 400 at `xhigh` or `max`, so those two levels are not offered for this model. `claude-opus-5-5` cannot turn thinking off and defaults to effort `medium`, so the registry sets effort explicitly. `claude-haiku-4-5` keeps temperature and has no effort.
- Decision (2026-10-06): the non-Anthropic path uses the OpenAI Chat Completions request format. OpenAI supports it, Gemini exposes an OpenAI-compatible endpoint, and OpenRouter (story 30) uses it, so the template has two request shapes, not four. OpenAI recommends its newer Responses API; Chat Completions stays supported and the single shape is worth more here.
- Decision (2026-10-06): API keys stay in n8n as one header-auth credential per provider; the plugin never stores a provider key. The n8n Workflow page tells the owner which credentials to create.
- Decision (owner, 2026-10-06): each provider's schema-constrained JSON output is used instead of asking for "ONLY valid JSON" in the prompt — all three offer it, and it removes most of the JSON-parse failure path.
- Decision (owner, 2026-10-06, replaces the earlier "move to Custom model ID" rule, which validation showed would drop temperature, effort and thinking for those sites): models removed from the list keep their registry entry as hidden legacy entries. A site whose stored default or agent override names one sends byte-identical requests, and Settings labels that model "(legacy)" until the owner picks another. Sites that never saved Settings get the new default; the release notes say so.
- The architecture change is recorded in ADR 0009. The template change needs a version bump and an entry in the release notes (story 15).
- `after: [6, 22, 27, 31]` (2026-10-06): the Settings stories build in the order 26 → 27 → 25 (owner), so this story adds its picker to the AI tab story 27 creates; story 6 must run on the current model list first; story 22 reviews the generator and template before this story reworks them, so the build starts from a verified contract list; bug 31 gives the template a real failure path before a second provider adds new ways to fail.
- Open question for the plan checkpoint: the template has six copies of the model HTTP Request node, one per agent. With two request shapes that becomes twelve plus routing. Recommended: one shared call path switched on the provider the plugin names in the `llm` fragment, since the plugin already builds every request parameter. The n8n diagram follows.
- The generator (`pqm_workflow_json()`) only substitutes the site host; it needs no change unless the template starts carrying per-provider placeholders. The n8n Workflow page gains the list of credentials to create.
