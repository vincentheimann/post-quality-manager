---
id: 29
type: story
title: "A Settings tab shows AI usage and limits what model calls may consume"
parent: none
covers: []
after: [27]
assignee: ""
refined: false
hitl: false
risk: medium
---

# A Settings tab shows AI usage and limits what model calls may consume

## Description

Owner request (2026-10-06). Today the only limits are on command counts: the Monthly Quota (manual triggers per user) and the Automated Reviews batch size. Nothing measures what the model calls consume. The n8n workflow receives the provider's token usage with every response but does not send it back to the plugin. This story fills the Usage & Limits tab that story 27 creates (it already holds the Monthly Quota). The tab shows the current month's tokens, per agent and per model, and lets the Admin set one site-wide monthly token limit that stops new commands once it is reached and tells the user why.

## Acceptance Criteria

Verify: after commands run, the tab shows the month's usage per agent and per model, matching the token counts in the provider responses. With a limit set and reached, manual triggers and the cron are refused with a message naming the limit, and no webhook call is made; commands already running finish and their tokens are counted. The usage resets with the new month. A site with no limit set behaves as today.

## References

- code — post-quality-manager/includes/class-pqm-features.php (per-user monthly quota, the existing pattern for a self-resetting monthly counter)
- code — post-quality-manager/includes/class-pqm-rest-api.php, `save_review_callback` (where n8n posts results back)
- code — post-quality-manager/templates/n8n-workflow.json (POST Result to WordPress body; the provider's `usage` is not forwarded)

## Notes

- Decision (owner, 2026-10-06): limits are measured in tokens.
- Decision (owner, 2026-10-06): available on both the free and the pro plan.
- Decision (owner, 2026-10-06): one site-wide monthly limit, 0 = unlimited like the Monthly Quota. Manual and scheduled commands draw from the same pool; no per-agent or per-user limits.
- Decision (2026-10-06): a token is a token — input plus output as reported by the provider, whatever the model. A cheap and an expensive model count the same; accepted for simplicity.
- Decision (2026-10-06): the Monthly Quota row lives in this tab; story 27 places it there.
- Assumption: usage shown is an estimate from the reported tokens, not the provider's invoice.
- Forwarding `usage` changes the n8n template and the save-review contract. That needs a template version bump and an entry in the release notes (story 15).
