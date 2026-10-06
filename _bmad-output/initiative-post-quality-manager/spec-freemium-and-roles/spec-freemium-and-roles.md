---
id: SPEC-freemium-and-roles
companions: [access-matrix.md]
sources: [../../../docs/adr/0003-freemium-plan-and-role-based-access.md, ../../../docs/adr/0006-license-provider-ships-as-stub.md, ../../../memory/freemium-and-roles.md]
---

> **Canonical contract.** This SPEC and the files in `companions:` are the complete, preservation-validated contract for what to build, test, and validate. Source documents listed in frontmatter are for traceability — consult them only if you need narrative rationale or prose color this contract intentionally omits.

# Freemium plan and role-based access through one feature gate

## Why

Owner mandate (ADR 0003, 2026-09-22) combining two requests that share one mechanism: monetize the plugin with a free/paid split, and align features with WordPress roles. Before this work every check was binary (`edit_posts` vs `manage_options`): Contributors could trigger any command, there was no notion of plan, and multi-author sites had no cost control over API-spending actions. A functional free tier (Review + Key Takeaways) is also the gate WordPress.org distribution (UV7) waits on.

## Capabilities

- **CAP-1**
  - **intent:** Every agent-command decision goes through a single gate, `PQM_Features::can( $command )`, combining plan state and user capability; the command registry is the one command whitelist.
  - **success:** A gated or unknown command is refused in PHP — REST 403 `pqm_feature_gated`, or 400 for a command outside the registry — before any webhook call, for an unlicensed site and an under-privileged user alike; no trigger path bypasses the gate. Cron applies the plan half only and skips a plan-gated batch silently; the connection-test `ping` runs no agent and sits outside the gate (access matrix).
- **CAP-2**
  - **intent:** Plan state comes from one swappable license provider read only through `is_pro()`; Freemius is the chosen provider and ships as a stub until it is set up (ADR 0006).
  - **success:** Entering a key on a free install unlocks paid features without a different build; clearing it reverts to free. Today any non-empty key is pro; real Freemius validation replaces the provider's internals only, with no gate caller touched. A `pqm_is_pro` filter override (dev) always wins.
- **CAP-3**
  - **intent:** WordPress roles map to five plugin capabilities (Administrator full; Editor = Author set + the four paid agents + per-agent overrides + Automated Reviews; Author = view + Review + Key Takeaways; Contributor nothing), registered on activation, re-registered on upgrade, and remappable by role editors.
  - **success:** Each role reaches only its set per the access matrix, enforced in REST `permission_callback`s and server-side section gates — a direct REST call from a Contributor fails even though the menu never showed the feature, and a role remapped to one capability gets exactly that capability's pages and sections.
- **CAP-4**
  - **intent:** Users who cannot run paid agents work under a visible per-user monthly action quota, Admin-configured, shipped default 20, `0` = unlimited.
  - **success:** A quota subject sees their remaining actions, is refused (429) with a used/limit count and reset date once spent, and spends one action only when n8n accepts a manual trigger; refusals, failed webhooks, Insert into Post and cron never consume it.
- **CAP-5**
  - **intent:** Commands declare tier, capability and display label up front in the registry the gate reads.
  - **success:** Adding a command takes a registry entry plus its n8n template branch and nothing else for the gate, the REST enums and the UI labels to follow; the harnesses fail when registry, REST enums and template drift apart.

## Constraints

- One zip, one build: paid is a license key flipping the gate, never a separate pro package.
- One n8n template: enforcement lives in PHP before the webhook, never in withheld workflow nodes — the shipped template exposing paid agents is accepted; the gate, not obscurity, is the control.
- License and plan caps are per site; the quota is per user; cron runs are site-level.
- Single-site only: the quota counter is network-wide user meta while the quota setting is per site.
- Paid features still require the user's own Claude API key and n8n instance; the price covers the plugin, not inference.
- The free tier must remain a functional standalone product (WordPress.org freemium rule).
- The Freemius SDK and its revenue share are accepted, but confined behind the provider-agnostic `is_pro()` source.
- Gated-feature presentation: role refusal is checked first and hides the feature; plan refusal renders it visible but locked with upgrade copy.
- A Settings save never wipes what the saver could not post: role-hidden, plan-locked or absent sections carry their stored values, never defaults.

## Non-goals

- WordPress.org submission (readme.txt, screenshots, i18n, GPL review) — UV7, stays parked behind this gate.
- Real license validation before the Freemius product exists (ADR 0006).
- Multisite support.
- A second build or a trimmed free n8n template.
- An in-plugin role editor — capabilities are remappable with existing role-editor tools.
- New agents, template changes, or version-handshake changes.
- Covering inference costs or reselling API access.

## Success signal

On one zip: an unlicensed site gives every Author working Review + Key Takeaways under a visible quota and refuses every other agent in PHP before any webhook call; entering a license key on the same install unlocks the paid set per role (initiative Done-when #3).

## Open Questions

- Price point (Freemius product setup is a hitl step on the license-provider work; the price itself is an owner decision that does not block the build).
