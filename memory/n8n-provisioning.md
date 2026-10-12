# n8n provisioning

Decided 2026-10-12 (ADR 0015, from spike 56): the setup wizard will provision n8n through its public API — create the three credentials, create/activate the workflow with per-node credential ids, compute the webhook URL, verify with ping. Template updates become `PUT /workflows/{id}` (auto-republish, ends the re-import handshake), token rotation becomes `PATCH /credentials/{id}`. Not built yet; the spec and epic come next, reshaping backlog stories 40 and 55.

What is true now:

- The n8n API key will be **stored** in settings (owner accepted the blast radius; mitigations: dedicated expiring key, Forget button, HTTPS-only base URL, plugin-identifying `User-Agent` — generic agents get WAF-banned).
- The provider key passes through the plugin **once** during setup: validated by a direct provider call (n8n's credential test is a no-op for generic types), written to the n8n credential, never stored. ADR 0009 stands; `docs/policies/` copy must change to "forwarded once, never stored" in the same epic.
- Adopt-existing lists credentials (metadata only, never secrets) and asks the owner to confirm; deterministic names are trusted only for wizard-created setups.
- The webhook path becomes per-site (static `pqm-execute` collides on a shared instance).
- The manual import path remains the fallback: Cloud trial, disabled API, older n8n. Everything is confirmed on 2.42.3 only; the wizard probes and degrades.
- Rollback order on failed provisioning: deactivate → delete workflow → delete credentials (active workflows refuse DELETE with 409; credentials delete silently even while referenced).

Product decisions for the spec (owner-delegated, 2026-10-12):

- Provisioning is a **free** feature: UV4 is the evaluation funnel, Pro sells outcomes (ADR 0003), the manual path is the fallback, not the free tier.
- Adopt vs fresh: a connected site with a green handshake defaults to "keep existing" (key stored for future updates, credential mapping only with explicit confirmation); an unconfigured site defaults to fresh provisioning; nothing manual is ever auto-deleted.
- The n8n API key lives on the **n8n Workflow page** (page-local merge-save, write-only masked field, "set / not set" + Forget) — never in the Settings form, which re-echoes values.
- Sequencing: story 55 (wizard continuation) builds first; the provisioning epic reworks the n8n connection steps afterwards and absorbs story 40 as the manual-fallback story.

Evidence: `_bmad-output/backlog/spike-n8n-provisioning-api-findings.md`.
