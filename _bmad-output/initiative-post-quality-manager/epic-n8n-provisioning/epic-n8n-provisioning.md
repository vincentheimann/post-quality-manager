---
type: epic
title: "The wizard sets up and maintains n8n through its public API"
parent: initiative-post-quality-manager
covers: [UV4]
after: []
assignee: ""
status: in-progress
risk: high
---

# The wizard sets up and maintains n8n through its public API

## Description

Setup today means importing the workflow, creating three credentials in the n8n UI, opening seven flagged nodes and pasting the webhook URL back: user path A4, the friction wall of the evaluation funnel. On 2026-10-13 the owner's production site showed how it fails silently, with one Header Auth credential auto-assigned to all seven nodes on import. This epic makes the setup wizard (story 55, the one entry point) provision n8n through its public API, roll back a failed run, adopt a manual setup, update the workflow in place, rotate the webhook token and diagnose mis-wiring, with the manual path kept as the fallback and as the owner's choice. Incepted 2026-10-13 from `spec-n8n-provisioning` (CAP-1…CAP-8, ADR 0015, spike 56).

## Outcome

A blogger evaluating the plugin reaches a first review by typing three values into the plugin and never visiting the n8n UI; the signal is the spec's success-signal walk on the owner's instance.

## Requirements

`spec-n8n-provisioning/spec-n8n-provisioning.md` (+ `failure-modes.md`, `brownfield.md`, the adopted spike findings) owns the requirement ids CAP-1…CAP-8; all map to UV4.

## Done when

1. From an empty n8n project, a fresh site reaches its first review with only the n8n base URL, an n8n API key and the Anthropic key typed into the plugin, and no visit to the n8n UI (CAP-1, CAP-2; the success-signal walk).
2. A forced failure at each provisioning step leaves zero plugin-created credentials and workflows on the instance, verified through the API, and an error naming the step (CAP-3).
3. On an adopted manual setup, a template update, a token rotation and the mis-wiring diagnosis run without deleting or changing anything else on n8n, and the 2026-10-13 mis-wiring is reported in one sentence naming the fix (CAP-4…CAP-7).
4. With the API unavailable or declined, the Workflow File section carries a novice through the manual setup and states why they are there (CAP-8).
5. A release ships the epic in both builds, each provisioning its own template, with `docs/policies/` saying the provider key is forwarded once and never stored (production delivery).

## Boundaries

The n8n Workflow page, the setup wizard's n8n steps, the n8n API client, the workflow template's webhook path and the version handshake. Not: the spec's non-goals (multisite, n8n Cloud trial, providers other than Anthropic, the Pro gate and capabilities, scoped keys, storing the provider key, screenshots of n8n). The wizard's profile, standards and schedule steps (story 55) stay as they are.

- Touch point: `post-quality-manager/templates/n8n-workflow.json`, `bin/derive-free-template.php`, `tests/harness/template-guard-harness.php` and `PQM_WORKFLOW_VERSION` — owner: entry 3, the epic's one version bump.
- Touch point: `docs/policies/privacy.md` and `docs/policies/readme-external-services.md` — owners: entry 1 (stored n8n key), entry 6 (provider key forwarded once; WordPress calls Anthropic and the n8n API).
- Touch point: backlog story 40 — absorbed by entry 2.

## References

- spec — _bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md (+ failure-modes.md, brownfield.md), the requirement source CAP-1…CAP-8
- spike — _bmad-output/backlog/spike-n8n-provisioning-api-findings.md, every confirmed call and response shape on n8n 2.42.3; read before writing any n8n call
- parent — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Requirement UV4
- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md (amends ADR 0009's policy wording, delivers ADR 0010's deferred rotation)
- constraint — docs/adr/0014 (two builds, each with its own template) and docs/adr/0003 (provisioning is free)
- requirement — docs/user-paths.md, A4 and UV4
- backlog — _bmad-output/backlog/story-workflow-file-novice-path.md (story 40, absorbed by entry 2) and _bmad-output/backlog/story-one-button-setup-wizard.md (story 55, the wizard this reworks)
- memory — memory/n8n-provisioning.md, memory/llm-options-architecture.md (the handshake)

## Notes

- Decision (owner, 2026-10-13): the manual path is open by choice, not only after a failed probe; the wizard leads with the key and offers "Set up by hand instead" (spec memlog).
- Decision (owner, 2026-10-13): tracer bullet = entry 1 (connect by key, probe, Forget), with a hitl probe of the owner's instance that settles the version read and the endpoint probe.
- Decision (owner, 2026-10-13): CAP-2 is cut into entries 3 (per-site path, the one `PQM_WORKFLOW_VERSION` bump), 4 (server provisioning) and 6 (the wizard); rollback (entry 5) builds before the wizard, so provisioning is reachable from the UI only once rollback exists.
- Decision (owner, 2026-10-13): policy wording ships with the story that makes it true (entries 1 and 6); entry 12 keeps user paths, memory and ADR consequences.
- Decision (owner, 2026-10-13): CAP-7 finds a manual workflow by the saved webhook URL's path, read-only, so entry 7 runs on the owner's production workflow before adoption (entry 8).
- Decision (owner, 2026-10-13): a closing refactor sweep (entry 11) runs before the success-signal walk (entry 12).
- Decision (owner, 2026-10-13): backlog story 40 is dropped by reference, absorbed by entry 2; its acceptance criteria are entry 2's reference.
- Decision (owner, 2026-10-13): the owner may fix the production mis-wiring now (it unblocks story 54's last walk line); entry 7's check recreates it by hand.
- Decision (owner, 2026-10-13, entry 1 plan D3): the published privacy page is a copy in the `postquality.com` repository; entry 1 updates `docs/policies/` only, and entry 6 syncs the site copy with both changes in one site PR, before any release that ships entry 6.
- Done (2026-10-13): entry 3 merged as PR #109 (per-site webhook path, `PQM_WORKFLOW_VERSION` 6); the owner's walkthrough of it (`../walkthrough-6-3-per-site-webhook-path/`) stopped at block 1 and is kept as an unfinished record.
- Lanes: entry 3 runs beside 1 and 2; entries 9 and 10 run beside each other after 8; every other entry touches the Workflow page, the wizard or the setup map in order.
- Open question (entry 4): on a Pro site without Application Passwords (neither HTTPS nor local), provision without the WordPress credential or refuse.
- Open question (entry 4): an instance whose `WEBHOOK_URL` differs from the editor URL fails the ping on the computed URL; the paste-the-URL step is the fallback.
- Assumption (entry 1): the n8n version is read from `GET <base>/rest/settings` (`versionCli`), not the public API; unverified on 2.42.3.
- Risk high (reason): the plugin holds an unscoped n8n admin key and deletes objects on n8n during rollback; a mistake can remove an owner's own credentials. Check outside the stories' own criteria: the owner's success-signal walk (entry 12) and the forced-failure runs verified through the API (entry 5).
