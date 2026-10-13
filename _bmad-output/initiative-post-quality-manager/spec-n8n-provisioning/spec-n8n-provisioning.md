---
id: SPEC-n8n-provisioning
companions:
  - failure-modes.md
  - brownfield.md
  - ../../backlog/spike-n8n-provisioning-api-findings.md
sources:
  - ../../../docs/adr/0015-plugin-provisions-n8n-via-public-api.md
  - ../../../memory/n8n-provisioning.md
  - ../../backlog/story-workflow-file-novice-path.md
  - ../../backlog/story-one-button-setup-wizard.md
---

> **Canonical contract.** This SPEC and the files in `companions:` are the complete, preservation-validated contract for what to build, test, and validate. Source documents listed in frontmatter are for traceability — consult them only if you need narrative rationale or prose color this contract intentionally omits.

# n8n provisioning: the wizard sets up and maintains n8n through its public API

## Why

A pain to solve, on the plugin's hardest step. Setup today means importing the workflow in n8n, creating three credentials in its UI, opening seven flagged nodes to pick the right one, and pasting the webhook URL back; user path A4 calls it the friction wall of the evaluation funnel (UV4), and there are no real users yet to climb it. On 2026-10-13 the owner's own production site showed how it fails silently: n8n assigned the one existing Header Auth credential to all seven nodes on import, every AI call died in 24 ms, and nothing in the plugin could tell. Spike 56 proved on a self-hosted n8n 2.42.3 that the public API can do all of the setup, and ADR 0015 (2026-10-12) decided the plugin will. The owner scheduled this epic on 2026-10-13, spec first, slicing and build in a fresh session.

## Capabilities

- **CAP-1**
  - **intent:** An Administrator connects the plugin to an n8n instance by entering its base URL and an n8n API key on the n8n Workflow page, and sees whether that instance can be provisioned.
  - **success:** With a valid key the page shows the instance as connected with its version, the key as "set", and a Forget button that removes it. With a WAF ban, a disabled public API, or a version lacking an endpoint, the page names that cause and offers the manual path (CAP-8). An owner who declines to hand over a key can choose the manual path from the same step, and a re-walk does not ask again.
- **CAP-2**
  - **intent:** From the setup wizard, an Administrator with a connected instance provisions everything n8n needs by typing their Anthropic key once: the plugin validates the key, generates a WordPress Application Password, creates the three credentials, creates and activates the workflow with every credentialed node linked and a per-site webhook path, saves the webhook URL and token, and proves the round trip with a ping.
  - **success:** The success-signal walk: a fresh site reaches its first review with no visit to the n8n UI.
- **CAP-3**
  - **intent:** A provisioning that fails at any step leaves nothing behind on n8n and tells the Administrator which step failed and why.
  - **success:** A forced failure at each step (credential creation, workflow creation, activation, ping) ends with zero plugin-created credentials and workflows on the instance, verified through the API, and an error naming the step.
- **CAP-4**
  - **intent:** An Administrator whose site already runs a manual setup adopts it: the plugin lists the instance's workflows and credentials by metadata, proposes a mapping, and stores the key once the owner confirms, so CAP-5 to CAP-7 work on that setup.
  - **success:** The owner's current manual setup is adopted without deleting or changing anything on n8n, and a template update, a token rotation and the diagnosis then run against it.
- **CAP-5**
  - **intent:** When the plugin ships a newer workflow template, the n8n Workflow page offers one click that updates the provisioned or adopted workflow in place, credentials and path preserved, replacing today's "download and re-import" notice.
  - **success:** After a template version bump, the click makes the ping report the new version; the workflow stays active and no re-import happens.
- **CAP-6**
  - **intent:** An Administrator rotates the webhook token from the plugin: a new token is generated, written to the n8n credential and saved in settings in one action.
  - **success:** The old token is refused by the trigger and the new one accepted on the next request, with no edit in n8n.
- **CAP-7**
  - **intent:** The workflow-version handshake also reads the workflow's node credentials and reports mis-wiring: a Claude node carrying the trigger's credential, or a credentialed node with none.
  - **success:** The owner's 2026-10-13 workflow is reported in one sentence that names the fix; a workflow the wizard provisioned reports clean.
- **CAP-8**
  - **intent:** When the API path is unavailable, the Workflow File section carries a novice through the manual setup as ordered steps with Copy buttons and accurate links, and warns that n8n assigns the first existing credential of a type to every node on import.
  - **success:** Story 40's acceptance criteria hold, the warning is present, a site whose instance failed the CAP-1 probe lands on this section with the probe's cause stated, and an owner who chose the manual path lands on it told it was their choice.

## Constraints

- Provisioning is a free feature (ADR 0003: UV4 is the evaluation funnel, Pro sells outcomes); the manual path is the fallback, never the free tier. It is also open by choice: the wizard's first n8n step leads with the key and offers "Set up by hand instead" as a secondary link, because the stored key is unscoped and an owner on a shared instance may refuse it.
- The n8n API key is stored in settings (ADR 0015; blast radius accepted) with these mitigations, all mandatory: the docs tell the owner to use a dedicated key with an expiration date; a Forget button removes it; the base URL must be HTTPS, localhost excepted; every n8n call sends a plugin-identifying `User-Agent`.
- The key lives on the n8n Workflow page only, as a write-only masked field with a page-local merge-save that shows "set / not set"; it never enters the Settings form, which re-echoes values. The base URL and key are stored in their own option (autoload off, deleted on uninstall), not in `pqm_settings`, which the Settings save rebuilds from the posted form.
- Policy wording ships with the story that makes it true: the stored n8n key with CAP-1; "forwarded once during setup, never stored" and the disclosure that WordPress itself calls Anthropic and the n8n API with the story that exposes provisioning in the wizard.
- The provider key passes through once: validated by one Anthropic request, written into the n8n credential, never stored or logged. No secret ever travels in a run payload. The policy copy in `docs/policies/` changes from "the plugin never sees the key" to "forwarded once during setup, never stored" in this epic; ADR 0009 stands.
- Adopt vs fresh: a connected site with a green handshake defaults to "keep existing"; an unconfigured site defaults to fresh provisioning; nothing the owner created by hand is ever deleted or changed without an explicit confirmation. Deterministic names are trusted only for setups the wizard created.
- The webhook path is per-site, derived from the site host; the template gains a path placeholder next to `__PQM_SITE_HOST__`, and `templates/n8n-workflow.json` stays the canonical Pro template with placeholders only, the Free one derived (ADR 0014). Each build provisions its own template.
- Rollback order is forced: deactivate, then delete the workflow, then delete the credentials (the API answers 409 for an active workflow and deletes referenced credentials silently). A rollback also revokes the Application Password the run generated. Provisioning is reachable from the UI only once rollback exists.
- CAP-7 finds the workflow through the stored setup map, else through the saved webhook URL (the workflow whose Webhook node path matches it); read-only, never by name, so it runs on a manual setup before adoption.
- Every call is confirmed on self-hosted n8n 2.42.3 only: the wizard probes before it provisions and degrades to CAP-8 with a version hint rather than failing mid-way.
- Credentials and the workflow carry the deterministic names: `Post Quality webhook - <host>`, `WordPress - <host>`, `Anthropic - Claude`, workflow `Post Quality - <host>`.
- Story 55's wizard stays the one entry point; this epic reworks its n8n connection steps and keeps the profile, standards and schedule steps, resumable, with nothing moving silently on a re-walk. Story 40 is absorbed by CAP-8.
- The `PQM_WORKFLOW_VERSION` handshake stays and gains CAP-7; the REST contract of the workflow callbacks does not change.

## Non-goals

- Multisite.
- n8n Cloud trial, which has no public API; paid Cloud is in scope only insofar as its API matches 2.42.3.
- Providers other than Anthropic: ADR 0009's own epic.
- Any change to the Pro gate, tiers or capabilities.
- Scoped or project-bound n8n keys (Enterprise): the stored key is unscoped by design.
- Storing the provider key anywhere in WordPress.
- Screenshots or illustrations of n8n inside the plugin (story 36 decision, 2026-10-07).

## Success signal

- A fresh site reaches its first review with only three values typed into the plugin, the n8n base URL, an n8n API key and the Anthropic key, and no visit to the n8n UI. Proven by a HITL walk on the owner's instance from an empty n8n project, recorded with the environment the owner names.

## Assumptions

- `GET /workflows/{id}` returns each node's `credentials` as `{id, name}`; the spike confirmed listing and that POST/PUT accept the shape, not the single-GET read.
- The cheap Anthropic validation call is `GET /v1/models` with the key: an authenticated 200 proves it, a 401 refuses it. Confirm against the Claude API reference at build time.
- The n8n public API takes the key in the `X-N8N-API-KEY` header on every call, as in the spike.
- The n8n version is readable from `GET <base>/rest/settings` (`versionCli`), the editor's internal pre-login call, not the public API; unverified on 2.42.3. If it fails, CAP-1 shows "version unknown" and relies on the endpoint probe.

## Open Questions

- On a Pro site where WordPress offers no Application Password (neither HTTPS nor a local environment), does provisioning proceed without the WordPress credential, saying Rewrite is unavailable, or refuse? Settled at the provisioning story's plan checkpoint.
- When the instance sets `WEBHOOK_URL` apart from the editor URL, the webhook URL computed from the base URL fails the ping; the paste-the-URL step is the fallback. Whether the API exposes the real webhook base is checked at the provisioning story's plan.
