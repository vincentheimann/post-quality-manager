---
id: 34
type: story
title: "The n8n connection is secured and host-agnostic end to end"
parent: none
covers: [UV4, UV5]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The n8n connection is secured and host-agnostic end to end

## Description

A review of the WordPress ↔ n8n link (2026-10-06) confirmed that the n8n host is already the user's choice: the plugin stores whatever production webhook URL is pasted (`n8n_webhook_url`, empty by default), the template fixes only the webhook path `pqm-execute`, and the only host injected at download is the WordPress site's own (`__PQM_SITE_HOST__`). Nothing references the development instance (<owner-n8n>) anywhere in the repo. The same review found four gaps that keep the link from being safe and truly portable for a blogger on any n8n (Cloud, self-hosted, any domain, any scheme) and any WordPress host:

1. **Inbound webhook is unauthenticated.** The Webhook Trigger node has no auth, so anyone who learns the URL can spend the owner's Claude API credit and push edits to guessed post IDs. The return path already carries `X-PQM-Token`; the trigger path carries nothing. Fix: the node uses n8n Header Auth and the plugin sends the existing `webhook_token` on every trigger (`/trigger-review`, cron batch, test ping), so one token secures both directions. The wizard tells the user which header value to put in the n8n credential, in the same breath as the two existing credentials.
2. **HTTPS is hardcoded for the WordPress host.** The rewrite path builds `https://` + the post link's host by hand (`n8n-workflow.json` WordPress HTTP node). A plain-HTTP or non-standard-port site breaks silently. Fix: the payload carries a `site_url` (scheme and port included, from `home_url()` / `rest_url()`), and every WordPress call in the template derives from it; no scheme or host is assembled in the template.
3. **Production vs. test URL.** n8n exposes `/webhook-test/` (editor-only) and `/webhook/` (production). The wizard says "production", but a pasted test URL passes the ping while the editor listens and dies afterwards. Fix: the save/test handlers recognise a `/webhook-test/` path and warn in place; the copy explains the difference once.
4. **Reachability is only proven one way.** The ping proves WP → n8n; a Local/intranet WordPress never receives the callback. Fix lands in story 2 (full round trip); this story only makes sure the wizard and README state the prerequisite plainly: "your WordPress site must be reachable from your n8n instance".

Plus an **agnostic sweep**: no host, domain, scheme, n8n flavour or niche is assumed anywhere in template, wizard copy, README or placeholders (`your-n8n.example.com` stays generic; the token never travels in a query string where it lands in server logs, see story 22's lead on the import URL).

## Acceptance Criteria

Verify:

1. A POST to the n8n webhook without the `X-PQM-Token` header (or with a wrong one) is refused by n8n; the plugin's trigger, cron batch and test ping all succeed with the stored token; rotating the token in Settings and in the n8n credential is documented as one paired step.
2. On a WordPress site served over plain HTTP, or on a non-443 port, the rewrite/apply path updates the post; the template contains no literal `https://` or `http://` pointing at the WordPress site.
3. Pasting a `/webhook-test/` URL in the wizard or Settings shows an inline warning naming the production URL; pasting a production URL on n8n Cloud (`*.app.n8n.cloud`) and on a self-hosted instance under any domain both save and ping without special handling.
4. The wizard and README state the two-way reachability prerequisite; `grep -ri postquality` over the distributable plugin, template, README and docs returns nothing.
5. The template change bumps `PQM_WORKFLOW_VERSION`; a site on the previous template sees the update notice and its old import keeps working until re-imported (unauthenticated trigger remains a documented known limitation for stale imports).

## Boundaries

- Must not change: the callback contract (`save-review`, `X-PQM-Token`), the command whitelist (story 14), the settings sanitizer's section-wise carry rule.
- Does not build the round-trip test (story 2) or the chat-provider contract doc (story 4); it hands them an authenticated trigger to document.

## References

- requirements — docs/user-paths.md, A4 "Connect the chain" and UV4/UV5
- template — post-quality-manager/templates/n8n-workflow.json (Webhook Trigger node, WordPress HTTP node URL)
- trigger — post-quality-manager/includes/class-pqm-rest-api.php (`trigger_review()` dispatch), post-quality-manager/includes/class-pqm-cron.php (batch dispatch), post-quality-manager/admin/class-pqm-admin.php (`ajax_save_webhook_url()`, `ajax_test_connection()`, wizard step 3)
- payload — post-quality-manager/post-quality-manager.php (`pqm_n8n_payload()`, `pqm_workflow_json()` placeholder replacement)
- token — post-quality-manager/includes/class-pqm-activator.php (`webhook_token` generation)
- docs — README.md "Requirements" and "Setup", n8n/workflow-diagram.md
- adjacent backlog — story 2 (round-trip test, also edits the template), story 4 (webhook contract doc), story 22 (generator review; its connection-related findings fold into this story)

## Notes

- Owner request (2026-10-06): the n8n domain must stay the user's to define; add the security, best-practice and agnostic-pivot work that review surfaced as one PBI.
- Decision needed at refine: reuse `webhook_token` for both directions (KISS, one credential to paste) or mint a second inbound token. Default: reuse.
- Sequencing with story 2: both bump `PQM_WORKFLOW_VERSION` and touch the template; whichever builds second rebases on the other. `after` stays empty so the security fix is not held behind a hitl review.
- hitl: a person creates the Header Auth credential in a real n8n instance (Cloud and self-hosted, if both are available) and runs criterion 1 and 3 there; the rest is harness-checkable.
- Lesson link: memory/lessons.md 2026-03-27 (never overfit to the current site) applies to hosts and schemes as much as to prompts.
