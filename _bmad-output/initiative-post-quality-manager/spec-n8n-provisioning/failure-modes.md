# Failure modes the wizard must handle

Each row is a condition the spike or the 2026-10-13 walk observed, what the plugin must do, and the capability it belongs to. Copy for every row is written at build time under `docs/brand.md` Copy Rules; none of it names a product the user does not have.

| Condition | How it shows | Required behaviour | CAP |
|---|---|---|---|
| A proxy or WAF in front of n8n bans the caller | Cloudflare 1010 or a non-JSON body on the first probe | Send a plugin-identifying `User-Agent` on every call; on a ban or non-JSON answer, say a proxy or WAF in front of n8n is the likely cause and offer the manual path | 1 |
| Public API disabled on the instance | 404 on `GET /workflows` with a valid-looking key (a 401 there is a refused key, next row; amended 2026-10-16, retrospective A14) | Name "the public API is disabled on this instance" and offer the manual path | 1 |
| Instance older than 2.42.3 lacks an endpoint | The router's generic `404 {"message":"not found"}` (or a 405) on `PATCH /credentials/<unknown>` or `POST /workflows/<unknown>/activate` sent with `{}`; an existing route answers with its handler's message ("Credential not found", a permission refusal) or 400/415 | Probe the endpoints the flow needs before creating anything; degrade to the manual path with the tested version as the hint (no n8n version is exposed to an API key) | 1, 8 |
| Base URL not HTTPS (localhost excepted) | Owner input | Refuse before any call | 1 |
| Key rejected | 401 on `GET /workflows`, or 401 on a route probe after the list passed (the latter reported as unexpected today: backlog story 74; a 403 on a route probe is a scoped key, also story 74; amended 2026-10-16) | Say the key is refused and point to n8n's Settings, n8n API; keep the previous key state unchanged | 1 |
| Anthropic key invalid | 401 from the validation request | Refuse before creating the credential; nothing is written to n8n | 2 |
| Credential creation fails | non-200 on `POST /credentials` | Delete the credentials created so far; report the step | 3 |
| Workflow creation fails | non-200 on `POST /workflows` | Delete the three credentials; report the step | 3 |
| Activation fails | non-200 on `POST /workflows/{id}/activate` | Delete the workflow, then the credentials; report the step | 3 |
| Ping fails after activation | non-200 or a wrong version on the round trip | Keep everything but mark setup incomplete; show the ping result, offer retry and the diagnosis | 2, 7 |
| Deleting an active workflow | 409 | Always deactivate first (forced rollback order) | 3 |
| Credential deleted under an active workflow | n8n allows it silently | Never delete credentials before the workflow; on adoption never delete anything | 3, 4 |
| Credential test unusable | `No testing function found for this credential` for every generic type | Never rely on `POST /credentials/{id}/test`; validate the provider key directly, the webhook token by the ping, the Application Password by a WordPress self-call | 2 |
| Application Password not checked *(amended 2026-10-16, retrospective A14)* | — | No WordPress self-call validates the generated password (entry 4 plan D2): n8n's first Rewrite is the check, and a Pro site without Application Passwords is provisioned with two credentials and Rewrite not ready (D1) | 4 |
| Manual import re-used one credential for every node | Claude nodes carry the trigger's Header Auth credential; AI calls end in milliseconds with an empty error; stored reviews read "could not get an answer from the model" | Diagnosis names the nodes and the credential they share and tells the owner to pick the Anthropic credential on the Claude nodes; the manual section warns before import | 7, 8 |
| A credentialed node with no credential | `credentials` missing on an HTTP Request node | Same diagnosis line | 7 |
| Static webhook path collides on a shared instance | two sites or a manual import next to a provisioned workflow | Per-site path derived from the host; adoption keeps whatever path the manual workflow has | 2, 4 |
| Manual setup does not follow the deterministic names | real instances name credentials freely | Adoption lists candidates by type and asks for the mapping; names are trusted only for wizard-created setups | 4 |
| Key forgotten while a provisioned workflow exists | Forget button | Nothing on n8n changes; CAP-5 to CAP-7 become unavailable until a key is set again; the handshake falls back to ping-only | 1 |
| Owner declines to hand over a key on an instance that would pass the probe | "Set up by hand instead" in the wizard's first n8n step | Land on the manual section saying it was their choice, not a failure; store the choice so a re-walk does not ask for the key again; a key added later leads to adoption | 1, 8 |
