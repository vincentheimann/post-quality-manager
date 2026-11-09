---
id: 111
type: story
title: "The plugin keeps working when a blogger's n8n moves to version 3"
parent: none
covers: [UV4, UV5]
after: []
assignee: ""
refined: true
hitl: true
risk: medium
---

# The plugin keeps working when a blogger's n8n moves to version 3

## Description

n8n 2.46.4 announces the rules that tell an instance what n8n 3 will break, and n8n 3.1.0 (a pre-release, 2026-11-03) ships them. Four touch the plugin. Only one breaks it: the editor's **Import from URL** goes away, and the plugin's manual setup leads with it. This story moves the manual setup to the file import, which both n8n 2 and n8n 3 offer, removes the import link from the page and the public text, and records the answer to each rule. Proving the template on n8n 3's first stable release is story 119. The owner walks the manual steps on the showcase site against a disposable n8n 2; the n8n 3 wording rests on the source read at 3.1.0 (hitl).

## Acceptance Criteria

1. **The manual steps name what both n8n versions offer**
   **Given** the n8n Workflow page's manual path (Set up by hand), and an n8n 2 or n8n 3 instance
   **When** a blogger follows its steps
   **Then** they download the workflow file and import it from the workflow menu; the import step names each version's entry (n8n 2.36 and later: "Import", then "From file"; n8n 3: "Import JSON")
   **And** the page offers no import link and nowhere mentions importing from a URL
2. **Public text and existing sites follow**
   **Given** the plugin's readme (WordPress.org), the repository README and the release notes
   **When** they describe the manual setup
   **Then** none offers an import link, and `docs/release-notes/unreleased.md` tells existing sites that the link left the page and the file import replaces it
3. **Each n8n 3 rule has a recorded answer**
   **Given** the four rules and the plugin's public API calls, read at `n8n@3.1.0`
   **When** the story is done
   **Then** `memory/lifecycle.md` and `memory/n8n-provisioning.md` say for each what the plugin changed or leaves alone and why, name the setting that restores n8n 2's behaviour where one exists, and name story 119 for the proof
4. **A harness row guards the steps**
   **Given** the harness sweep
   **When** it runs
   **Then** a row checks that the rendered manual steps name both menu entries and carry no import link

## Boundaries

- Must not change: the workflow template; the route that served the import link (it stays for links already copied; removing it is a later decision); the n8n API calls of the guided setup; the guided setup itself.
- Out of scope: the proof on n8n 3 (story 119); moving from `/activate` to `/publish` (recorded for `/lcm`); user documentation of SSRF protection and the task runner timeout (owner: record only).

## References

- requirements — docs/user-paths.md, A4 (Connect the chain)
- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the n8n rule: a new major becomes a ticket)
- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md (the manual path stays the fallback)
- decision — docs/adr/0010-one-token-authenticates-both-directions.md (the import link carries the token; the route stays)
- next — _bmad-output/backlog/story-template-proven-on-n8n-3.md (story 119: the proof on n8n 3's first stable release)
- source — https://github.com/n8n-io/n8n/tree/n8n@3.1.0/packages/cli/src/modules/breaking-changes/rules/v3
- record — memory/lifecycle.md, memory/n8n-provisioning.md
- copy — docs/brand.md

## Notes

- Fact (chore 105, source read 2026-11-04 at `n8n@2.46.4`): the four rules are announced there and are not defaults in 2.47.0: `workflow-import-url-removed`, `ssrf-default-blocked-ranges`, `docker-only-deployment`, `task-runner-task-timeout`.
- Fact (source read 2026-11-09 at `n8n@3.1.0`, a GitHub pre-release; pre-releases 3.0.0 and 3.0.1 exist too; the four rules are byte-identical to 2.46.4's; no stable 3.x, and npm `latest` is 2.46.7):
  - Import from URL is removed everywhere: the editor's modal, the backend route `GET /rest/workflows/from-url`, and pasting a URL on the canvas. The workflow menu's "Import" submenu ("From URL", "From file") becomes one entry, "Import JSON", a file picker (`editor-ui` `ActionsDropdownMenu.vue` L275-281; `@n8n/i18n` `en.json` L2319). Still there: the command bar's "Import workflow from file" and pasting the JSON on the canvas.
  - SSRF protection stays off by default (`ssrf-protection.config.ts` L98-99). The default blocked list adds 100.64.0.0/10 (Tailscale, carrier-grade NAT), 2002::/16, 64:ff9b::/96 and ::/128; it covers the HTTP Request node, and Code nodes have no network access. `N8N_SSRF_ALLOWED_IP_RANGES` and `N8N_SSRF_ALLOWED_HOSTNAMES` allow a site back.
  - The task runner's timeout default drops from 300 to 60 seconds (`runners.config.ts` L52-53, `N8N_RUNNERS_TASK_TIMEOUT`); Code-node JavaScript always runs in it. The template's Code nodes only build request bodies and parse replies.
  - The `n8n` package is private from v3 and ships only in the Docker image (`packages/cli/package.json` L4; `release-publish.yml` L94-95); Docker Hub has `n8nio/n8n:3.1.0`.
  - The public API calls the plugin makes are unchanged, apart from documented 429 answers; `/activate` and `/deactivate` carry `@Deprecated` since 2026-07-23 and hand off to `/publish` and `/unpublish`; a PUT on a published workflow still republishes it and needs `workflow:activate`. No other v3 rule matches either template: `evaluate-expression-code-node` and `get-paired-item` look for `$evaluateExpression` and `$getPairedItem`, which neither uses; no rule removes its node types (webhook, code, httpRequest, if, switch, noOp), and no node sets Always Output Data.
- Fact (source read 2026-11-09): n8n 2.0.0 to 2.35 show one flat "Import from file…" entry; the "Import" submenu ("From URL", "From file") arrives in 2.36.0. The page already quotes the 2.36+ wording.
- Fact (code, 2026-11-09): the manual steps quote "Import", then "From URL" in `admin/class-pqm-admin.php` (the copy-confirmation string near L209, the step near L1946, the fallback near L1954) and offer the import link through `workflow_import_url()`; README.md L28 names the "Import from URL" link. `admin/js/pqm-admin.js` L600-601 picks that confirmation by the `.pqm-import-url` wrapper; `workflow_import_url()` has one caller, the page (its docblock's GET /setup is stale: `setup-harness.php` L171 asserts /setup returns no `import_url`); `tests/js/workflow-file-copy.test.js` and `render-matrix-harness.php` (near L515-525, L611, L1254) pin the "From URL" copy.
- Correction (2026-11-09): the template's Claude HTTP timeouts are 60 to 180 seconds (Audit and Rewrite 180), not 30 to 120; the callbacks are 10 to 15 seconds.
- Decision (owner, 2026-11-09): the manual path is the file import only, for n8n 2 and n8n 3; the copyable import link leaves the page, and the route behind it stays for now.
- Decision (owner, 2026-11-09): the proof on n8n 3's first stable release is split into story 119; this story closes with criteria 1 to 4.
- Decision (owner, 2026-11-09): SSRF protection's new ranges and the 60-second task timeout are recorded only (memory and the lifecycle record, with the settings above); revisited if a site meets them.
- Decision (agent default, 2026-11-09): the `/activate` deprecation is recorded in `memory/lifecycle.md` for `/lcm`; no ticket until n8n removes the endpoints or the oldest n8n the plugin supports offers `/publish`.
- Decision (agent, on the owner's delegation, 2026-11-09): the build follows story 109's pull request, one build at a time; both change `admin/class-pqm-admin.php`.
- Open question (story 119): whether n8n 3's first stable release keeps the "Import JSON" entry read at 3.1.0, and how long the template's Code nodes run against the 60-second task timeout.
- Open question (later): when the route that served the import link is removed; it stays for links already copied.
- Fact: refined 2026-11-09. The owner delegated the approval ("Define yourself based on best practices"); the agent approved it after the `validate.md` check by a fresh agent the same day (fixes applied: the n8n 2.36 wording, the `en.json` path, the v3-rule wording, the code sweep; suggestions adopted: the walk's n8n, the readme names, ADR 0010, user paths, story 119's wider Given).
