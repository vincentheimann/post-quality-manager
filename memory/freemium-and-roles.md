---
name: freemium-and-roles
description: Product direction locked 2026-09-22 — free tier (Review + Key Takeaways) vs paid plan, WordPress role mapping, one shared feature gate; not yet built or prioritized
metadata:
  type: project
---

Decided 2026-09-22 (ADR [0003](../docs/adr/0003-freemium-plan-and-role-based-access.md)), **not yet implemented and not yet ranked** — run `/prioritize` before picking it up:

- **Tiers:** free = Review + Key Takeaways; paid = all agents, per-agent overrides, cron, role management. One zip, license key flips the gate. Licensing via Freemius (provider-agnostic `is_pro()` source). Paid plan licensed per site.
- **Roles:** Administrator full · Editor = Author + per-agent overrides + cron · Author = Review + Key Takeaways with a per-user quota · Contributor nothing. Implemented as plugin capabilities registered on activation, enforced in REST + AJAX.
- **Gate:** single `PQM_Features::can()` combining plan + capability. One n8n template; the plugin refuses gated commands before the webhook.
- **Distribution:** GitHub Releases first, WordPress.org later with the same gate (updates the "deferred" stance in [[pivot-agnostic]]).

Backlog entries: `tasks/enhancements.md` → Product → "Role-based access to plugin features" and "Free vs. paid plan (freemium)". Related: [[pivot-agnostic]], [[user-paths]].
