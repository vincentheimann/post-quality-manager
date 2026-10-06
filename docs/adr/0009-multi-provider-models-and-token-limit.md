# 0009. Agents run on several model providers through two request shapes, under one site-wide token limit

- Status: accepted
- Date: 2026-10-06

## Context

Every agent calls Claude: the plugin builds the request from its model registry, and the six model nodes in the n8n template call `api.anthropic.com` (memory/llm-options-architecture.md). The owner asked on 2026-10-06 for a provider-then-model choice, free models, and a way to cap what model calls consume (initiative requirement O5). Real alternatives for the request path: stay Claude-only with the Custom model ID as the escape hatch; one request shape per provider in the template (four); route every call through OpenRouter (one shape and one key, but a middleman and a markup on every call, including Claude's); or keep Anthropic's own shape and add one OpenAI-compatible shape, which OpenAI, Google's Gemini endpoint and OpenRouter all accept. For the limit: tokens, estimated dollars, or number of calls.

## Decision

Anthropic's Messages API plus one OpenAI Chat Completions request shape, with the plugin still building every request. The limit is one site-wide monthly token count, set by the owner.

- Providers: Anthropic, OpenAI, Google (Gemini), and OpenRouter for free models. The registry gives each model its provider; the default model is `claude-sonnet-5-5`.
- The workflow still spreads what the plugin sends and never decides. It picks the request shape from the provider the plugin names.
- Provider API keys live in n8n credentials, one per provider. The plugin never stores one.
- Agents ask each provider for schema-constrained JSON instead of relying on the prompt alone.
- The workflow forwards each response's reported token usage to the plugin. Input and output tokens count the same, whatever the model. A limit of 0 means unlimited. A reached limit refuses new commands before the webhook call, like the Monthly Quota.
- Model choice and the limit are on both plans. ADR 0003's command gate is unchanged.

## Consequences

- Easier: an owner can run the product on cheaper or free models and cap its spend; a new model on a supported provider is still one registry entry.
- Harder: the template is reworked (version bump and re-import), and every provider the owner uses adds an n8n credential to the setup — A4 is already the friction wall, so Claude stays the default and other providers are opt-in. The registry now tracks three vendors' model releases. Prompts were tuned on Claude, so other models may score differently.
- Committed: usage shown is an estimate from reported tokens, not an invoice. Counting all tokens the same means a cheap model draws from the pool as fast as an expensive one; accepted for simplicity.
- Built by backlog stories 25 (picker and request path), 29 (usage and limit) and 30 (OpenRouter). Bug 31 (template failure path) comes first.
