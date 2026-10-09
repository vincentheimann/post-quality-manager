# 0003. Freemium plan and role-based access share one feature gate

- Status: accepted; "one zip, one build" and "one n8n template" superseded by [0014](0014-two-builds-from-one-source.md), the rest stands
- Date: 2026-09-22

## Context

Two owner requests arrived together (backlog, 2026-09-22): (1) plugin features must follow WordPress roles — Administrator full rights, Editor adds per-agent overrides and Automated Reviews (cron), Author gets Review and Key Takeaways only with a per-user action quota for cost control, Contributor nothing; (2) a free plugin limited to Review and Key Takeaways, with a paid plan for everything else. Today every check is binary (`edit_posts` vs `manage_options`), so Contributors can trigger any command and there is no notion of plan. Alternatives weighed: separate free and pro builds vs one build unlocked by a license key; home-grown license endpoint vs a hosted licensing service (Freemius, EDD Software Licensing, Lemon Squeezy); WordPress.org-first vs GitHub Releases-first for the free tier; one n8n template vs a trimmed free template. The owner asked for best practice under KISS.

## Decision

Build **one feature gate** (`PQM_Features::can( $feature )`) that combines plan state and user capability, and route both requests through it.

- **One zip, one build.** Paid = a license key flipping the gate. No separate pro package.
- **Hosted licensing, Freemius by default.** The gate reads a single `is_pro()` source so the provider stays swappable.
- **Free tier via GitHub Releases first**, WordPress.org afterwards, reusing the same gate. Review + Key Takeaways is a functional free version under WordPress.org's freemium rule.
- **One n8n template.** The plugin refuses gated commands before calling the webhook; template and version handshake unchanged.
- **Roles map to plugin-specific capabilities** (registered on activation, remappable by role editors) enforced in REST permission callbacks and AJAX handlers, not only menus. Author quota is **per user**; the paid plan is licensed **per site**, and any plan cap is per site. Cron runs are site-level and outside the per-user quota. Contributors get no access.
- Paid features still require the user's own Claude API key and n8n instance; the price covers the plugin, not inference.

## Consequences

- Every command trigger and settings section gets a gate call; new agents must declare their tier and capability up front.
- Freemius brings a revenue share and a sizeable SDK into the plugin; the provider-agnostic gate is what keeps that reversible.
- A usage table or per-month user meta is needed for the Author quota; the DB schema grows.
- The free tier's limits are enforced in PHP, not by withholding workflow nodes, so the shipped template exposes paid agents to anyone who reads it — accepted (the gate, not obscurity, is the control).
- Neither item is ranked yet: both go through `/prioritize` against the UV table before implementation.
