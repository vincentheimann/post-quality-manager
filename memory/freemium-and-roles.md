---
name: freemium-and-roles
description: Product direction locked 2026-09-22 — free tier (Review + Key Takeaways) vs paid plan, WordPress role mapping, one shared feature gate; ranked first and incepted into 8 stories 2026-10-01
metadata:
  type: project
---

Decided 2026-09-22 (ADR [0003](../docs/adr/0003-freemium-plan-and-role-based-access.md)), **ranked first by `/prioritize` and incepted 2026-10-01** — requirement ids CAP-1…CAP-5 live in `_bmad-output/initiative-post-quality-manager/spec-freemium-and-roles/` (kernel + access-matrix.md companion):

- **Tiers:** free = Review + Key Takeaways; paid = all agents, per-agent overrides, cron, role management. One zip, license key flips the gate. Licensing via Freemius (provider-agnostic `is_pro()` source). Paid plan licensed per site.
- **Roles:** Administrator full · Editor = Author + the four paid agents + per-agent overrides + cron (decided 2026-10-01) · Author = Review + Key Takeaways with a per-user monthly quota (Admin-configured, default 20; decided 2026-10-01) · Contributor nothing. Implemented as plugin capabilities registered on activation, enforced in REST + AJAX.
- **Presentation** (decided 2026-10-01): plan-gated features visible but locked with upgrade copy; role-gated features hidden.
- **Gate:** single `PQM_Features::can()` combining plan + capability. One n8n template; the plugin refuses gated commands before the webhook. **Built (story 1.1, 2026-10-01):** `includes/class-pqm-features.php` — `registry()` (six agents, tiers per the access matrix, capabilities still `edit_posts` until story 2), stub `is_pro()` behind the public `pqm_is_pro` filter (mu-plugin `__return_true` demos the paid path until story 3), one gate call in `PQM_REST_API::trigger_review_callback()` covering REST + AJAX + GET fallback; paid buttons render visible-but-locked via `pqmAdmin.gatedCommands`. `score`/`apply*`/`ping` and cron are outside the gate by design.
- **Distribution:** GitHub Releases first, WordPress.org later with the same gate (updates the "deferred" stance in [[pivot-agnostic]]).

Stories: `_bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/tickets.toml`, 8 entries in build order; tracer = 1.1 feature gate tracer; story 4 carries the schema unknown (usage table vs user meta) behind a plan checkpoint; one `bmad-build` per story. Related: [[pivot-agnostic]], [[user-paths]], [[bmad-setup]].
