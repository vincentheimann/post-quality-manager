# 0015. The plugin provisions n8n itself through the n8n public API

- Status: accepted; amends [0009](0009-multi-provider-models-and-token-limit.md) (provider-key policy wording) and delivers the rotate action [0010](0010-one-token-authenticates-both-directions.md) deferred
- Date: 2026-10-12

## Context

Setup is the plugin's hardest step: import the workflow, create three credentials in the n8n UI, open seven flagged nodes, paste the webhook URL back. Spike 56 proved live on a self-hosted free n8n 2.42.3 (`_bmad-output/backlog/spike-n8n-provisioning-api-findings.md`) that the public API can do all of it: create credentials, create the workflow with per-node credential ids, activate, update via PUT (auto-republish), rotate the webhook token via PATCH. Real alternatives: keep the manual path only (status quo); partial provisioning of the two WordPress-known secrets, leaving the provider key manual; sending secrets in each run payload (rejected: they persist in n8n execution logs). The owner chose full provisioning (2026-10-12).

## Decision

The setup wizard provisions n8n through its public API: the owner pastes the n8n base URL and an n8n API key, then the provider key; the plugin generates a WP Application Password, creates the three credentials, creates and activates the workflow with credentials linked, computes the webhook URL, and verifies with ping.

- The n8n API key is stored in settings. The blast radius is accepted: a non-Enterprise key is unscoped, so a WordPress compromise reaches n8n. Mitigations committed to: the docs tell the owner to use a dedicated key with an expiration date, a Forget button removes it, the base URL must be HTTPS (localhost excepted), and every n8n call sends a plugin-identifying User-Agent (WAF rules ban generic agents).
- The provider key passes through once: validated by a direct provider call (n8n has no credential test for generic types), written into the n8n credential, never stored or logged. ADR 0009's decision stands; the policy copy changes from "the plugin never sees the key" to "forwarded once during setup, never stored".
- Adopting an existing manual setup lists credentials via GET (metadata only) and asks the owner to confirm the mapping; names are only trusted for setups the wizard created.
- The webhook path becomes per-site instead of the static `pqm-execute`, ending collisions on a shared n8n instance.
- The manual import path stays as the documented fallback: n8n Cloud trial has no API, self-hosted instances can disable it, older versions may lack endpoints — the wizard probes and degrades with a version hint.

This amends ADR 0009 (policy wording) and delivers the rotate action ADR 0010 deferred; neither is superseded.

## Consequences

- Easier: setup without one n8n UI visit; template updates become one click (PUT) instead of re-import, retiring the stale-import pitfall; token rotation becomes a plugin action.
- Harder: the plugin holds an unscoped n8n admin key; provisioning failures need strict rollback order (deactivate → delete workflow → delete credentials — n8n deletes credentials under an active workflow without complaint); the wizard gains a second path whose failure modes (WAF, disabled API, old version) all need copy.
- Committed to: policy edits in `docs/policies/` in the same epic; stories 40 and 55 are reshaped by the spec that builds on this.
- Built (epic `epic-n8n-provisioning`, 2026-10-13 → 2026-10-16, PRs #106–#144): the n8n client and the connect-by-key section (entry 1), the manual path as the Workflow File section (2, story 40 absorbed), the per-site webhook path with template v6 (3), server-side provisioning (4), rollback with a leftovers record (5), the wizard's key path (6, shipped in 3.9.0), the mis-wiring diagnosis (7), adoption (8), the one-click template update (9), token rotation (10), a refactor sweep (11), the success-signal walk (12). The policies say the provider key is forwarded once and never stored, and that a test ping with a key stored also reads the workflow.
- Learned on the way: n8n 2.42.3 deletes credentials silently but refuses to delete a published workflow (409), and for up to one outbox poll after an unpublish; a workflow whose activation was refused was never published, so its rollback passes at once (seen 2026-10-16). An imported workflow gets the most recently saved credential of each type on every empty node, which is how the 2026-10-13 mis-wiring arose; provisioning links every node by id instead.
- Proven on 2026-10-16 (the owner's Local sandbox against their own n8n instance, three values typed, no n8n UI visit): connect, provision, a forced failure rolled back with nothing left in n8n, adoption, the diagnosis on a hand-made mis-wiring, the update in place, the rotation. Not yet proven: the first review landing on a fresh internet-reachable site (the sandbox cannot be called back) — the production site's reviews land, so the return path is known to work.
