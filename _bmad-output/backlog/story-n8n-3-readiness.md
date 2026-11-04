---
id: 111
type: story
title: "The plugin keeps working when a blogger's n8n moves to version 3"
parent: none
covers: [UV4, UV5]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The plugin keeps working when a blogger's n8n moves to version 3

## Description

n8n 2.46.4 ships the migration report for n8n 3: rules under `packages/cli/src/modules/breaking-changes/rules/v3/` that tell an instance what the update will break. Four of them touch the plugin. The editor's **Import from URL** goes away, and the plugin's manual setup path leads with it. SSRF protection blocks more address ranges by default, which can refuse the workflow's callbacks to a site on a private network. Docker becomes the only supported way to run n8n, while chore 105's disposable n8n installs from npm. The task runner's default timeout drops from 300 to 60 seconds, and the template runs its parsing in Code nodes. This story checks each rule against the plugin and changes what breaks, so a blogger whose n8n updates to version 3 keeps a working setup.

## Acceptance Criteria

1. **Each rule has an answer**
   **Given** the four rules, read at n8n 3's first stable tag
   **When** the story is refined
   **Then** each one says what the plugin changes, documents or leaves alone, and why
2. **The manual path works without Import from URL**
   **Given** an n8n 3 instance
   **When** a blogger follows the manual setup steps on the n8n Workflow page
   **Then** every step names something that n8n's editor offers
3. **The template is proven on n8n 3**
   **Given** n8n 3's first stable release
   **When** provisioning, **Update workflow** and one command run against it
   **Then** each succeeds, and `memory/lifecycle.md` and `memory/n8n-provisioning.md` name the version proven

## Boundaries

- Must not change: the workflow template, unless a rule breaks it (then with a template version bump and a release-notes line, ADR 0017); the provisioning API calls, unless n8n 3 changes them.
- Out of scope: the other v3 rules (AI Agent, Slack, Gmail, Edit Image, removed nodes and the rest), unless refinement finds a template node among them; n8n 3's public API changes, which the proof in AC3 surfaces.

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the n8n rule: a new major becomes a ticket)
- decision — docs/adr/0015-plugin-provisions-n8n-via-public-api.md
- source — n8n `packages/cli/src/modules/breaking-changes/rules/v3/`: `workflow-import-url-removed.rule.ts`, `ssrf-default-blocked-ranges.rule.ts`, `docker-only-deployment.rule.ts`, `task-runner-task-timeout.rule.ts`; `packages/@n8n/config/src/configs/ssrf-protection.config.ts`
- source — `admin/class-pqm-admin.php` (the manual steps: "Import", then "From URL", the `workflow_import_url()` copy field, the download button and its "From file" line), `includes/class-pqm-rest-api.php` (`GET /workflow`, served for Import from URL)
- source — `templates/n8n-workflow.json` (the Code nodes; the HTTP Request nodes that call back: POST Result to WordPress, Update WP Post (Rewrite), Send Notification)
- record — memory/lifecycle.md, memory/n8n-provisioning.md, docs/local-verification.md (A disposable n8n)

## Notes

- Fact (chore 105, source read 2026-11-04 at `n8n@2.46.4`): the rules are announced in 2.46.4 and are not defaults in 2.47.0.
  - `workflow-import-url-removed`: "The editor no longer offers importing a workflow from a URL." Recommended instead: the editor's "Import from File". The plugin's manual step 1 leads with From URL and offers the download with "From file" as the fallback.
  - `ssrf-default-blocked-ranges`: the built-in blocked list adds 100.64.0.0/10 (shared address space, also Tailscale's range) and IPv6 transition ranges. In 2.46.4 SSRF protection is off by default (`N8N_SSRF_PROTECTION_ENABLED`, `enabled = false`), and its default list already blocks 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 and 127.0.0.0/8. An instance with protection on cannot call back a site on those addresses.
  - `docker-only-deployment`: "Support for running n8n via npm is removed. The new version must be run via the official Docker image." On 2026-11-04 npm holds no n8n 3.x (`npm view n8n versions`), so chore 105's disposable n8n (npm, no Docker or WSL) cannot run n8n 3.
  - `task-runner-task-timeout`: the default of `N8N_RUNNERS_TASK_TIMEOUT` goes from 300 to 60 seconds. The template's Code nodes (17 in Pro, 8 in Free) build request bodies and parse replies; the Claude calls are HTTP Request nodes (timeouts 30 to 120 seconds), which do not run in the task runner.
- Fact (2026-11-04): n8n@3.1.0 is a GitHub pre-release (2026-11-03); its notes list "Expand default blocked IP ranges for outbound request guard". npm dist-tags: `latest` and `stable` 2.46.5, `beta` and `next` 2.47.0.
- Open questions for refinement: whether n8n 3 turns SSRF protection on by default (the 2.46.4 rule reports only when it is on); whether the manual path drops Import from URL for every n8n version or keeps it for n8n 2; how AC3's proof runs when n8n 3 is Docker-only (Docker on the owner's PC, which chore 105 excluded, or the owner's instance once it updates).
