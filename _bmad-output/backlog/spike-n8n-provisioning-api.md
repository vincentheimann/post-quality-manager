---
id: 56
type: spike
title: "Can the plugin provision the n8n credentials and workflow itself through the n8n public API?"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# Can the plugin provision the n8n credentials and workflow itself through the n8n public API?

## Description

Setup is the plugin's hardest step: import the workflow, create three credentials in n8n, open seven flagged nodes, paste the webhook URL back. n8n's public API documents everything needed for the plugin to do all of it: create credentials, create the workflow with credentials linked per node, activate it, update it later. The docs describe the current version; nothing proves the calls behave as documented on a real self-hosted free instance. This spike proves or refutes each call empirically. The answer feeds a new ADR and the `bmad-spec` for a provisioning epic; stories 40 (Workflow File novice path) and 55 (one-button setup wizard) are reshaped by it.

## Approach

Time box: one session. The agent runs the calls; the owner provides a dedicated, expiring API key and revokes it afterwards (the hitl part, with sign-off). Every object the spike creates is named with a `[PQM spike]` prefix and deleted at the end; nothing else on the instance is touched, read-only listing excepted.

1. Probe: confirm on the owner-provided instance (self-hosted n8n 2.42.3) which of the needed endpoints exist and answer as documented.
2. Schemas: fetch the credential schemas for the Header Auth and Basic Auth types; record the exact `data` shapes.
3. Create: the three credentials with spike names — webhook token (real value), WordPress Application Password (real, generated on the owner's test site), AI provider key (dummy). Run the credential test call on each and record what it can and cannot verify.
4. Workflow: fill the canonical template with the spike credentials' ids, create it via the API, activate it, and run the existing ping round trip against its trigger with the `X-PQM-Token` header. Ping needs no AI call, so no real provider key is needed.
5. Update paths: change the workflow via PUT (the future one-click template update) and rotate the webhook token via credential PATCH; confirm the active workflow keeps running with the new values.
6. Adopt-existing: list credentials and workflows via the API and confirm a manual setup can be found by the deterministic name patterns (`Post Quality webhook - <site host>`, `WordPress - <site host>`, `Anthropic - Claude`), with the host resolved at runtime — nothing site-specific.
7. Cleanup: delete every spike-created object; the owner revokes the spike key.

Good enough: every call the setup flow needs is confirmed or refuted on 2.42.3 with its request and response shapes recorded, the ping round trip is green on an API-created workflow, and every blocker found is named with its fallback (manual import stays the floor).

## Acceptance Criteria

1. **Each provisioning operation the epic needs is confirmed or refuted on a real self-hosted free n8n**
   Verify: the findings file beside this ticket records, per operation, the call, the observed response shape, and the verdict
2. **An API-created workflow with linked credentials answers the ping round trip, survives a PUT update and a token rotation via PATCH**
   Verify: the findings file records each run with its result
3. **The answer and its consequences are recorded where the epic's spec will find them**
   Verify: `backlog/spike-n8n-provisioning-api-findings.md` exists and a new ADR drafted via `/adr` cites it; the ADR supersedes the setup-related parts of ADR 0009/0010 wording and lists the policy-copy edits
4. **The owner has accepted the findings and the spike leaves no trace on the instance**
   Verify: a dated `Decision:` line in Notes names the acceptance; the findings file's cleanup section lists every created object as deleted and the key as revoked

## References

- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md (the decision this spike produced)
- decision — docs/adr/0009-multi-provider-models-and-token-limit.md
- decision — docs/adr/0010-one-token-authenticates-both-directions.md
- n8n public API, Credential — https://docs.n8n.io/connect/n8n-api/credential.md
- n8n public API, Workflow — https://docs.n8n.io/connect/n8n-api/workflow.md

## Notes

- Decision (owner, 2026-10-12): the plugin will store the n8n API key; the blast radius (an unscoped key in `wp_options` makes a WP compromise reach n8n) is accepted with the mitigations: dedicated key, expiration date, a Forget button, HTTPS-only base URL.
- Decision (owner, 2026-10-12): adopting an existing manual setup must stay site-agnostic — matching only via the deterministic credential name patterns resolved from each site's own host.
- Decision (owner, 2026-10-12): the manual import path stays as the fallback — n8n Cloud trial has no API, self-hosted instances can disable it, older versions may lack endpoints.
- Decision (owner, 2026-10-12): the spike runs against an owner-provided test instance with a time-boxed dedicated key; the instance URL and the key never enter the repository.
- Decision (owner, 2026-10-12): findings accepted as recorded in `spike-n8n-provisioning-api-findings.md`; the spike API key is revoked. The provider-key validation call and the plugin's own `User-Agent` on n8n calls are plugin-side work for the epic.
- Risk is medium because the spike talks to the owner's live n8n instance and a wrong delete cannot be undone (the API never returns secrets); the outside check is the owner's sign-off on the cleanup list (criterion 4).
- Open question: the minimum n8n version per endpoint — the findings name the floor and what the plugin does below it.
- Open question: what the credential test call can actually verify for each of the three credential types.
- Assumption: the ping round trip exercises trigger auth and the respond path without any AI call, so a dummy provider key suffices for the spike.
