---
id: 97
type: bug
title: "The Notification URL never gets a report when a command finishes"
parent: none
covers: [UV3]
after: []
assignee: ""
refined: true
hitl: true
risk: medium
severity: P2
---

# The Notification URL never gets a report when a command finishes

## Description

An administrator enters a chat webhook under Settings → Connection → **Notification URL** ("Optional. kChat/Slack/Teams webhook for formatted reports."). The readme and the privacy policy promise that the workflow posts a short report there each time a command finishes: the post's title and link with the result, or the error. Nothing arrives. The review is saved in WordPress, so the plugin's own screens look fine, and the one channel meant to pull the blogger back stays silent.

## Reproduction

1. Both workflow templates as of main 2b2b8b3 (template version 7), Pro and Free.
2. Static trace, 2026-10-29: the only connection into "Has Notification URL?" comes from "POST Result to WordPress", an HTTP Request node (options: `timeout` only) whose output is the reply of `POST /save-review`, `{success, id, message}` (`includes/class-pqm-rest-api.php` L564–568). "Has Notification URL?" reads `$json.notification_url` and "Send Notification" reads `$json.notification_url` and `$json.chat_message`, so both read fields the reply does not have.
3. Run in n8n, still to do: run a command with a Notification URL set. Expected today: no POST reaches the address.
4. Expected: one POST to the Notification URL carrying the command's report.

## Cause Hypothesis

The notification branch was attached after the WordPress callback and reads `$json`, which by then is WordPress's reply, not the item that the "Format …" / "Parse … Result" nodes built. A second gap sits behind it: the error returns of the "Parse … Result" nodes (model error, invalid JSON) set `error` and `message` but no `chat_message`, so even a working branch would post no text for an error, which the privacy policy promises.

## Acceptance Criteria

1. **A finished command posts its report**
   **Given** a Notification URL is set and a command finishes with a result, in the Pro and the Free template
   **When** the workflow has saved the result to WordPress
   **Then** one POST reaches the Notification URL whose `text` is that command's formatted report
2. **An error is reported too**
   **Given** a Notification URL is set and a command ends in an error (the model's error, invalid JSON, a command the workflow has no step for)
   **When** the workflow has posted the error to WordPress
   **Then** one POST reaches the Notification URL whose `text` states the error
3. **No address, no POST**
   **Given** an empty Notification URL, or a connection test ping
   **When** the workflow runs
   **Then** nothing is posted to any notification address
4. **Sites meet the fix**
   **Given** a site whose workflow is at the version before the fix
   **When** the plugin with the fix is installed
   **Then** the n8n Workflow page reports the workflow as out of date (managed sites get Update workflow, hand-made ones re-import), the Free template is derived from the Pro one as before, and `docs/release-notes/unreleased.md` says why
5. **Tests cover the condition found and fixed**
   **Given** the harness sweep
   **When** it runs
   **Then** a row checking where the notification branch reads its address and text fails on the defect and passes on the fix, for both templates
6. **Or: no change is needed, with proof**
   **Given** the reproduction's step 3
   **When** it is run on the current templates
   **Then** the report already reaches the Notification URL, with the evidence recorded in Notes — this supersedes 1 to 5

## References

- parent — none
- source — `_bmad-output/initiative-post-quality-manager/deferred-work.md` (entry from story 95's quick lens, 2026-10-29)
- related — `post-quality-manager/readme.txt` ("Your notification address (optional)") and `docs/policies/privacy.md` (the notification bullet): the promised behaviour
- related — `tests/harness/template-failure-harness.php` (L119 assumes the item reaches the notification branch unchanged)
- related — `_bmad-output/backlog/story-full-round-trip-connection-test-plan.md` (the last template version bump, 6 → 7)

## Notes

- Decision: owner, 2026-10-29 — today's sprint pick while CI waits for the Actions reset; branch `fix/bug-97-notification-never-sent`.
- Decision: owner, 2026-10-29 — the run in n8n uses the `score` command (no model call); the agent asks before touching the owner's n8n.
- Assumption: Slack, kChat and Teams incoming webhooks accept the current `{"text": …}` body; the body's shape is not this bug's scope.
- Done 2026-11-02: merged in PR #215 (825a0e3), shipped in 3.10.0 and 3.10.1 (template v8). Proven on the owner's n8n before the merge (plan, Verification); CI on main is green from 65c32f9, the first run since the Actions reset. Every site needs Update workflow or a re-import (release notes).
