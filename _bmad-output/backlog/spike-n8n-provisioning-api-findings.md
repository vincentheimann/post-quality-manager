# Spike 56 — findings: provisioning n8n through the public API

Run 2026-10-12 against an owner-provided, self-hosted free n8n **2.42.3** behind Cloudflare, with a dedicated expiring API key (`X-N8N-API-KEY`). The instance URL, the key, and the instance's own object names are deliberately not recorded here.

## Verdict

**Feasible.** The plugin can provision everything itself: create the three credentials, create the workflow from the canonical template with every credentialed node linked by id, activate it, and prove the round trip — without one visit to the n8n UI. The two follow-up paths work too: a template update via PUT auto-republishes in place, and a webhook-token rotation via PATCH takes effect immediately.

## Operations confirmed

| Operation | Call | Observed |
|---|---|---|
| List credentials | `GET /credentials?limit=250` | 200; items carry `id,name,type,shared,createdAt,updatedAt` — never secret data |
| Credential schema | `GET /credentials/schema/{type}` | 200; `httpHeaderAuth` = `{name, value}`, `httpBasicAuth` = `{user, password}` (plus optional `allowedHttpRequestDomains`/`allowedDomains`) |
| Create credential | `POST /credentials` `{name,type,data}` | 200; returns the generated `id` |
| Test credential | `POST /credentials/{id}/test` | 200 but `{"status":"Error","message":"No testing function found for this credential."}` for all three generic types — unusable |
| Update credential | `PATCH /credentials/{id}` `{data}` | 200; rotated token valid on the very next request, old token refused (403) |
| Delete credential | `DELETE /credentials/{id}` | 200 — even while an **active** workflow references it (no guard) |
| Create workflow | `POST /workflows` `{name,nodes,connections,settings}` | 200; per-node `credentials: {id, name}` accepted — all 8 credentialed nodes linked (6 AI + webhook + WP) |
| Activate / deactivate | `POST /workflows/{id}/activate` / `.../deactivate` | 200; `active: true/false` |
| Update workflow | `PUT /workflows/{id}` | 200; stays active, new `versionId`, auto-republished |
| Delete workflow | `DELETE /workflows/{id}` | **409 while active**; 200 after deactivate |

## Round trips (production webhook URL, `X-PQM-Token` header)

- Valid token → `200 {"ok":true,"workflow_version":5}`
- Wrong token → `403 Authorization data is wrong!`
- After PUT (template bumped to version 6) → `200 {"ok":true,"workflow_version":6}`, no re-activation needed
- After PATCH rotation → new token `200` immediately; pre-rotation token `403`

## Findings beyond the happy path

1. **Cloudflare can block the caller by user-agent.** A generic bot UA (python-urllib) got a hard Cloudflare 1010 ban on this instance; a named product UA passed. The plugin must send a distinctive `User-Agent` on every n8n call, and the wizard needs a reachability pre-check whose error copy names "a proxy/WAF in front of n8n" as a likely cause.
2. **The provider key cannot be validated through n8n.** Generic credential types have no test function. The wizard must validate the key by calling the provider's API directly (a cheap request) before creating the credential — this is the moment the plugin "sees" the key, already covered by the pass-through decision.
3. **Adopt-existing cannot trust names.** The real instance's manually created credentials did not follow the deterministic name patterns. Adoption must list candidates (`GET /credentials`, metadata only) and let the owner confirm the mapping; blind name-matching only fits setups our own guided path created.
4. **Rollback order is forced.** Deleting an active workflow fails (409) → deactivate, then delete. But n8n deletes credentials out from under an active workflow without complaint → on any failed provisioning, remove the workflow before the credentials, never the reverse.
5. **The static webhook path collides.** The template's trigger path is `pqm-execute` for every site. Two sites provisioning onto one n8n instance, or provisioning next to an existing manual import, collide. The spike set a custom path per workflow without issue — the epic must make the path per-site (e.g. `pqm-execute-<site-host-hash>`), which also ends the collision latent in today's manual imports.
6. **Version floor unproven.** Everything above is confirmed on 2.42.3 only. Older self-hosted instances may lack `GET`/`PATCH` on credentials or the activate endpoints; the wizard should probe and fall back to the manual import path with a version hint rather than fail.

## Cleanup

Every created object carried a `[PQM spike]` prefix. End state verified via the API: zero spike workflows, zero spike credentials; the instance's own credentials and workflows untouched. The spike API key was revoked by the owner and the findings accepted on 2026-10-12.
