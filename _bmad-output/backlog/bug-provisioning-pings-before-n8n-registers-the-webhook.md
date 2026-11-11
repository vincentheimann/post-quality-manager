---
id: 80
type: bug
title: "The wizard reports a 404 right after provisioning because it pings before n8n has registered the webhook"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P2
---

# The wizard reports a 404 right after provisioning because it pings before n8n has registered the webhook

## Description

The wizard's Set up the workflow step creates the credentials and the workflow in n8n, activates the workflow and pings its production URL in the same request. On the owner's instance the ping came back 404 and the step showed "Your workflow is set up and active in n8n, but the ping did not get through. n8n responded with 404. Check the URL and make sure the workflow is activated in n8n. If your n8n serves webhooks on another address than its editor, copy the production URL from the Webhook Trigger …". A few minutes later the same URL, pinged through the plugin with nothing changed on the site, answered with version 6, up to date. The setup had succeeded; only the first ping was early, and the sentence sends the user to check a URL and an activation that are both right.

**Reproduction (observed 2026-10-19 on the owner's Local sandbox against their n8n instance, Pro build of main 7c7550a):** state 1, Set up n8n, connect with an API key, paste the Anthropic key. The step shows the sentence above; the setup map is stored, the saved URL is the production URL, `pqm_workflow_version_seen` holds `failed` with "n8n responded with 404" and no version. Press Test again on the page, or ping through WP-CLI: Ping OK, version 6. Not reproduced on 2026-10-16 (entry 6.12's walk, same instance): the window is timing-dependent.

**Cause hypothesis:** n8n answers the activation call before the webhook path is registered on its webhook server (the activation runs the registration after replying, or the instance's proxy serves the first request before it lands), so a ping sent in the same second meets a 404. The plugin sends the ping immediately after the activation reply (`provision()` activates, the callback pings), with no wait and no retry, and the 404 sentence names the wrong causes for a workflow the plugin itself just activated.

## Acceptance Criteria

1. **A ping right after provisioning tolerates n8n's registration window**
   **Given** a provisioning whose activation succeeded
   **When** the first ping answers 404
   **Then** the plugin waits briefly and pings again, at most a small fixed number of times, and reports the setup as answering when a retry passes
2. **A real 404 is still reported**
   **Given** a workflow whose production URL answers 404 after the retries
   **When** the step reports
   **Then** it says the setup is active but the webhook did not answer yet, names the retry the user can make (Test again on the page) first, and the editor-address hint second
3. **The page agrees**
   **Given** the retries passed
   **When** the wizard closes
   **Then** the page is in the set-up state with no failure on record
4. **Harness**
   **Given** the n8n API harness
   **When** it runs
   **Then** a row answers 404 once then 200 and asserts the pass with one retry, a row answers 404 throughout and asserts the sentence and the stored failure, and the existing provisioning rows still pass
5. **The other valid outcome**
   **Given** the investigation shows n8n registers the webhook before its activation reply on the versions the plugin supports
   **When** the bug is worked
   **Then** the finding is recorded in `memory/n8n-provisioning.md` with the version and the ticket is closed without a retry, the sentence fix (AC2) still made

## References

- code path — `post-quality-manager/includes/class-pqm-n8n.php` `provision()` (the activation call), `post-quality-manager/includes/class-pqm-rest-api.php` `provision_n8n_callback()` (the ping that follows)
- spec — `_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md`
- sibling — `_bmad-output/backlog/bug-activation-refusal-says-try-again.md` (bug 75: the other activation-time sentence)
- walk — `_bmad-output/backlog/story-n8n-workflow-page-lean-ux-rework-plan.md`, Implementation Notes (story 67's walk, where this was seen)
- copy — `docs/brand.md`

## Notes

- Filed 2026-10-19 during the owner's browser walk of story 67; severity P2: the setup is right and Test again recovers it, but the sentence sends a first-time user to check things that are not wrong, at the funnel's friction wall (A4).
- Assumption: a short wait (about a second) and one or two retries cover the window; the exact figures come from the harness rows and one timed observation on the owner's instance. The rotation and update pings (same `ping_webhook()`) do not activate anything and keep one ping.
- Not ranked by `/prioritize`; a candidate for the 72/73/75 small-fixes build (same code, same harness).
