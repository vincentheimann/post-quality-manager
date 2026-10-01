---
id: SPEC-freemium-and-roles
companions: [access-matrix.md]
sources: [../../../docs/adr/0003-freemium-plan-and-role-based-access.md, ../../../memory/freemium-and-roles.md]
---

> **Canonical contract.** This SPEC and the files in `companions:` are the complete, preservation-validated contract for what to build, test, and validate. Source documents listed in frontmatter are for traceability — consult them only if you need narrative rationale or prose color this contract intentionally omits.

# Freemium plan and role-based access through one feature gate

## Why

Owner mandate (ADR 0003, 2026-09-22) combining two requests that share one mechanism: monetize the plugin with a free/paid split, and align features with WordPress roles. Today every check is binary (`edit_posts` vs `manage_options`): Contributors can trigger any command, there is no notion of plan, and multi-author sites have no cost control over API-spending actions. A functional free tier (Review + Key Takeaways) is also the gate WordPress.org distribution (UV7) waits on.

## Capabilities

- **CAP-1**
  - **intent:** Every plugin feature decision goes through a single gate, `PQM_Features::can( $feature )`, combining plan state and user capability.
  - **success:** A gated command is refused in PHP — REST and AJAX return a proper error — before any webhook call, for an unlicensed site and for an under-privileged user alike; no trigger or gated settings section bypasses the gate.
- **CAP-2**
  - **intent:** Plan state comes from hosted licensing: a license key flips `is_pro()`, Freemius by default.
  - **success:** Entering a valid key on a free install unlocks paid features without a different build; removing it reverts to free. The gate reads only `is_pro()`, so the provider is swappable without touching gate callers.
- **CAP-3**
  - **intent:** WordPress roles map to plugin-specific capabilities (Administrator full; Editor = Author set + the four paid agents + per-agent overrides + Automated Reviews, decision 2026-10-01; Author = Review + Key Takeaways; Contributor nothing), registered on activation and remappable by role editors.
  - **success:** Each role can trigger only its set, enforced in REST `permission_callback`s and AJAX handlers — a direct REST call from a Contributor fails even though the menu never showed the feature.
- **CAP-4**
  - **intent:** Authors work under a visible per-user monthly action quota, Admin-configured, shipped default 20 actions per month (decision 2026-10-01).
  - **success:** An Author sees their remaining quota, is refused with a clear count once it is spent, and cron runs never consume it (site-level, outside per-user quota).
- **CAP-5**
  - **intent:** Agents declare their tier and capability up front in a registry the gate reads.
  - **success:** Adding a new agent requires a tier + capability declaration and nothing else for the gate to enforce it.

## Constraints

- One zip, one build: paid is a license key flipping the gate, never a separate pro package.
- One n8n template: enforcement lives in PHP before the webhook, never in withheld workflow nodes — the shipped template exposing paid agents is accepted; the gate, not obscurity, is the control.
- License and plan caps are per site; the Author quota is per user; cron runs are site-level.
- Paid features still require the user's own Claude API key and n8n instance; the price covers the plugin, not inference.
- The free tier must remain a functional standalone product (WordPress.org freemium rule).
- Quota storage grows the DB schema — house policy: owner approval before any schema change.
- The Freemius SDK and its revenue share are accepted, but confined behind the provider-agnostic `is_pro()` source.
- Gated-feature presentation (decision 2026-10-01): plan-gated features render visible but locked with upgrade copy; role-gated features stay hidden.

## Non-goals

- WordPress.org submission (readme.txt, screenshots, i18n, GPL review) — UV7, stays parked behind this gate.
- A second build or a trimmed free n8n template.
- An in-plugin role editor — capabilities are remappable with existing role-editor tools.
- New agents, template changes, or version-handshake changes.
- Covering inference costs or reselling API access.

## Success signal

On one zip: an unlicensed site gives every Author working Review + Key Takeaways under a visible quota and refuses every other agent in PHP before any webhook call; entering a license key on the same install unlocks the paid set per role (initiative Done-when #3).

## Assumptions

- Quota period is monthly (ADR 0003's consequences name "per-month user meta").
- The Author quota applies regardless of plan state — it is role-level cost control, not a plan cap.
- The quota counts successful manual command triggers only.

## Open Questions

- Price point (Freemius product setup is a hitl step on the license-provider story; the price itself is an owner decision that does not block the build).
