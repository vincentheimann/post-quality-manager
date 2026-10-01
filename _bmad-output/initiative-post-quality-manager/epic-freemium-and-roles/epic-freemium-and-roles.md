---
type: epic
title: "Features follow plan and role through one gate"
parent: initiative-post-quality-manager
covers: [O1, O2]
after: []
assignee: ""
risk: high
---

# Features follow plan and role through one gate

<!-- Envelope. Requirements and the stories in tickets.toml are completed at inception, after bmad-spec distils ADR 0003 + the backlog notes into a spec. -->

## Description

One feature gate, `PQM_Features::can( $feature )`, combines plan state (free vs paid, per-site license, hosted licensing with Freemius as the default provider behind a swappable `is_pro()`) and user capability (plugin-specific capabilities registered on activation and mapped to Administrator / Editor / Author; Contributors get nothing). Every command trigger — REST `trigger-review`, `ajax_trigger_command`, cron — and every gated settings section calls it. Authors get Review + Key Takeaways under a per-user monthly quota the Admin configures, with the counter shown next to the buttons. The decisions are final in ADR 0003; what remains is the spec and the slicing.

## Outcome

Site owners can hand the plugin to a whole editorial team without cost surprises, and the free tier becomes a functional WordPress.org-compliant product; the signal is one zip serving both plans with gated commands refused in PHP before any webhook call.

## Done when

1. A fresh install with no license runs Review and Key Takeaways and refuses the four other commands before the webhook call, in UI, REST and AJAX alike.
2. The same zip with a valid license key runs all six commands; the n8n template and the version handshake are unchanged.
3. An Author sees and consumes a quota; an Editor edits per-agent overrides and cron but not webhook, token or API-key settings; a Contributor has no menu, no REST, no AJAX access.
4. Capabilities are remappable with any WordPress role editor without code changes.

## Boundaries

The plugin's PHP permission layer, settings UI and activation. Not the n8n template (one template, ADR 0003), not WordPress.org submission (parked, UV7), not the price itself.

## References

- decision — docs/adr/0003-freemium-plan-and-role-based-access.md
- parent — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Requirements O1–O2
- code — post-quality-manager/includes/ (REST permission callbacks) and post-quality-manager/admin/ (AJAX handlers, menus), the enforcement points
- facts — memory/freemium-and-roles.md

## Notes

- Decision: Author quota per user; paid license per site; cron runs are site-level and outside the per-user quota (user, 2026-09-22).
- Open question: Freemius SDK size and revenue share vs alternatives — ADR 0003 accepts Freemius as default; re-open only if the spec finds a blocker.
- Unknown: usage table vs per-month user meta for the quota; the spec decides, the DB schema grows either way.
- Waits on `/prioritize` ranking against UV1–UV7 before inception (ADR 0003 consequence).
