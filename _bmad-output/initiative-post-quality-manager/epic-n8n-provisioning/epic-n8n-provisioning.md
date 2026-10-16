---
type: epic
title: "The wizard sets up and maintains n8n through its public API"
parent: initiative-post-quality-manager
covers: [UV4]
after: []
assignee: ""
status: done
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
- ux — _bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md (story 67, approved 2026-10-14): the n8n Workflow page per site state; § "Where each epic entry lands" places the controls of entries 6.6–6.10

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
- Done (2026-10-14): entry 2 merged as PR #112 (the Workflow File section as the manual path, story 40 absorbed); walked on the owner's production site the same day, Pro and Free builds, all pass. The setup dialog keeps the same outdated n8n labels until entry 6 (deferred-work entry).
- Done (2026-10-14): entry 4 merged as PR #117 (`POST /n8n/provision`, setup map in option `pqm_n8n_setup`); no UI yet, so no walk; the real-site check is entry 12's. Its review deferred the failed-run cleanup to entry 5 (deferred-work entry: deactivate after an `activate` error, revoke the Application Password, no duplicates on retry).
- Done (2026-10-14): entry 5 merged as PR #124 (rollback of a failed provisioning: deactivate, delete the workflow, then the credentials, the Application Password revoked; what n8n refuses kept in `pqm_n8n_leftovers` and removed first by the next run). Harness-verified only: the forced failure through the API on the owner's instance is still open, now or in entry 12's walk (owner's call). n8n 2.42.3's source shows a DELETE right after an unpublish can answer 409 for up to one outbox poll (plan Design Notes).
- Done (2026-10-15): entry 6 merged as PR #127 and shipped in 3.9.0 the same morning (the wizard: Connect your n8n → Set up the workflow, or Set up by hand; the policies say the provider key is forwarded once and never stored). Harness and Jest only; the owner's walk is pending. The owner's first look found two follow-ups, filed as fixes outside the epic: a hand-made site shows the two key-path steps ticked (the "Already connected" rule of the 6.6 plan), and the n8n API key field gives no feedback after Connect (a masked last-4 hint). The ticks follow-up was fixed by PR #129 (2026-10-15). `src/setup/copy-value.js` removed in the 6.6 done chore. The published privacy page sync is backlog story 68.
- Done (2026-10-15): entry 7 merged as PR #134 (the handshake reports a mis-wired workflow in one sentence, read-only through the n8n API). The owner's HITL walk passed on their production site on the list path; the setup-map path (`GET /workflows/{id}`) stays harness-only until a provisioned site exists. The walk renamed the status block's button Check again → Test again, inside the same PR. The review round trip works again on that site.
- Done (2026-10-15): entry 8 merged as PR #137 (an Administrator adopts an existing manual setup: `GET|POST /n8n/adoption`, the map stored with `wizard_created: false`, n8n only read; D3 ties the map to the saved URL's path). The owner adopted their production workflow on 2026-10-15; the agent verified the result from the live state on 2026-10-16 (map stored, n8n workflow untouched, the setup guide's key path ticked, the by-id diagnosis path now live; recorded in the plan). The n8n Instance section's browser checks (`../plan-n8n-connection-states.md`) passed on 2026-10-16, every item, run by the owner in the browser on their production site. Their first look found the n8n Instance section reading two ways at once (a primary Connect on a connected site; the adoption offer sharing one slot with the form), fixed outside the epic by PR #138 the same day (`../plan-n8n-connection-states.md`: two blocks that never swap, a connected summary with Change → Save and check, Forget key closes an open proposal). Once the map is stored, 6.7's setup-map path of the diagnosis (`GET /workflows/{id}`) runs on a real site for the first time; the walk's Test again confirms it. Deferred: the policies do not say a test ping reads the workflow through the n8n API (deferred-work entry; story 68's sync or entry 12).
- Done (2026-10-16): entry 10 merged as PR #140 (an Administrator rotates the webhook token from the plugin: `POST /n8n/token`, PATCH first, the one-key save, the ping; the button in the n8n Instance section's managed block until story 67). The walk on the owner's production site passed the same day, run by the agent at the owner's request on a zip of main the owner installed: the old token refused, the new accepted, the n8n credential updated and the workflow untouched, a Review landed through the return path (recorded in the plan). Built by an Opus 5.5 implementer; the review's one rejected finding (the "setup stopped" wording of `stopped()` on a refused PATCH) goes to story 67's copy pass.
- Done (2026-10-16): entry 9 merged as PR #142 (one click updates the workflow to the bundled template: `POST /n8n/workflow`, one GET then one PUT of the build's template onto the map's workflow, credentials and path kept; **Update workflow** as the update notice's fix on a managed site; provisioning renders through the same `linked_template()`). Harness-only: the bundled and the stored version are both 6, so no site can show the button until the next template version bump; the walk is entry 12's or the first bump's (plan D3). Built by a Fable 5.1 implementer at the owner's choice; the quick review's 4 findings all patched. Entries 1–10 are done: entry 11 (the refactor sweep) is next, then entry 12.
- Done (2026-10-16): entry 11 merged as PR #144 (the refactor sweep: `PQM_N8n` says one sentence per kind of action through `stopped()` and `could_not()`, the shared `label()` / `answer()` / `gone()` / `connected_key()` helpers, the three deferred records closed: the policies name the test ping's workflow read, `AGENTS.md` names both islands, `credential_names()`'s docblock). Behaviour unchanged: no route, reply key, error code, HTTP status, option or n8n call changed; the local gates are the merge evidence while GitHub Actions is blocked until 2026-11-01 — every harness green with counts equal before and after (2,149 checks), Jest 132 tests, lint and build clean. Built by a Fable 5.1 implementer; the quick review's 3 findings: 2 patched, 1 low rejected. Entries 1–11 are done: entry 12 (the success-signal walk and the records) closes the epic.
- Done (2026-10-16): entry 12 merged as PR #146 (the success-signal walk and the records, with the epic's retrospective `epic-n8n-provisioning-retrospective.md` in the same PR). The owner's dry run on their Local sandbox against their own n8n instance: three values typed, no n8n UI visit; connect, provisioning, a forced activation refusal rolled back with nothing left, both diagnosis paths, adoption, the first real template update, rotation and cleanup passed; the first review's landing on a fresh reachable site stays open (the instance cannot call a `.local` host back; retro item A9). Records: user-paths A4, the initiative's UV4 line, ADR 0015 and the Status-line cross-links of 0009 and 0010, entry 5's `unknown`, memory, story 67 refined. Every entry is done; the retrospective's verdict is accepted-with-open-items (Done when 5 waits for the release of entries 7–11, retro item A10) and its fifteen action items are proposed, not filed. The loose plan `../plan-update-notice-primary-action.md` (commit 561d0ae, the Re-import primary action) ran inside the epic's range beside entries 6.6 and 6.9 and is part of what the page shows.
- Decision (owner, 2026-10-14, story 67 sequencing (a)): entries 6.6–6.10 place their controls where `ux-n8n-workflow-page/EXPERIENCE.md` reserves them (6.6 the primary action of the not-set-up states, 6.7 the status sentence, 6.8 in the n8n connection disclosure, 6.9 Update workflow as the outdated fix, 6.10 Rotate token in the Webhook disclosure), as far as the current page allows; story 67 rebuilds the page after entry 6.12.
- Lanes: entry 3 runs beside 1 and 2; entries 9 and 10 run beside each other after 8; every other entry touches the Workflow page, the wizard or the setup map in order.
- Open question (entry 4): on a Pro site without Application Passwords (neither HTTPS nor local), provision without the WordPress credential or refuse.
- Open question (entry 4): an instance whose `WEBHOOK_URL` differs from the editor URL fails the ping on the computed URL; the paste-the-URL step is the fallback.
- Assumption (entry 1): the n8n version is read from `GET <base>/rest/settings` (`versionCli`), not the public API; unverified on 2.42.3. *(Settled 2026-10-13 by the entry 1 probe: n8n exposes no version to an API key, `/rest/settings` carries none before login; the section says "Connected to n8n." and `TESTED_VERSION` appears in copy only. The spec was amended then; this line was not until the 6.12 done chore.)*
- Risk high (reason): the plugin holds an unscoped n8n admin key and deletes objects on n8n during rollback; a mistake can remove an owner's own credentials. Check outside the stories' own criteria: the owner's success-signal walk (entry 12) and the forced-failure runs verified through the API (entry 5).
