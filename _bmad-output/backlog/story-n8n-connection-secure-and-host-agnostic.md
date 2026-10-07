---
id: 35
type: story
title: "The n8n connection is secured and host-agnostic end to end"
parent: none
covers: [UV4, UV5]
after: []
assignee: ""
refined: true
hitl: true
risk: medium
---

# The n8n connection is secured and host-agnostic end to end

## Description

A review of the WordPress ↔ n8n link (2026-10-06) confirmed that the n8n host is already the user's choice: the plugin stores whatever production webhook URL is pasted, the template fixes only the webhook path `pqm-execute`, and the only host injected at download is the WordPress site's own. The same review, checked against the code on 2026-10-07, found four gaps that keep the link from being safe and portable for a blogger on any n8n (Cloud or self-hosted, any domain) and any WordPress host:

1. **The inbound webhook is unauthenticated.** The n8n Webhook Trigger has no authentication, so anyone who learns its URL can spend the owner's Claude API credit and push edits to guessed post IDs. The return path already checks `X-PQM-Token`; the trigger path checks nothing. The plugin sends the token today only inside the JSON body, which the trigger does not verify. Fix: the Webhook Trigger uses n8n Header Auth, and the plugin sends the token as a header on every call to n8n — the Run trigger, the scheduled batch and the test ping.
2. **The rewrite path assumes how the WordPress site is served.** The template builds the WordPress address for the rewrite update by hand, with a forced `https://` and a fixed `/wp-json` path. A site on plain HTTP, installed in a subdirectory, or without pretty permalinks fails to receive the rewrite. A non-standard port already survives. Fix: the plugin hands the template the address to use, as it already does for the result callback.
3. **A test URL passes the ping and then fails.** n8n serves `/webhook-test/` only while its editor listens, and `/webhook/` in production. The wizard asks for the production URL but does not explain the difference or catch a test URL. Fix: saving or testing a `/webhook-test/` URL shows an inline warning; the copy explains the difference once.
4. **Reachability is proven one way only.** The ping proves WordPress → n8n. A local or intranet WordPress never receives the result. The full round trip is story 2; this story makes the wizard and README state the prerequisite plainly: "your WordPress site must be reachable from your n8n instance".

The person's step (hitl): create the Header Auth credential in a real n8n instance after importing the new template, then run criteria 1 and 3 there, on n8n Cloud and on a self-hosted instance if both are available.

## Acceptance Criteria

1. **Only the plugin can start a run**
   **Given** a site with an imported workflow of this story's version and its Header Auth credential set to the site's webhook token
   **When** a request reaches the n8n webhook without that token in the header, or with a wrong one
   **Then** n8n refuses it and no agent runs; a Run started from the plugin, a scheduled batch and the test ping all start normally
2. **The wizard says what to put in the new credential**
   **Given** an owner importing the workflow
   **When** they follow the wizard and the credentials hint
   **Then** they are told which credential the Webhook Trigger needs, which header name to use and where to copy the token from, alongside the two existing credentials
3. **The rewrite reaches any WordPress**
   **Given** a WordPress site on any port, installed in a subdirectory, or using plain permalinks, served over HTTPS or in a local environment (the rewrite signs in with an Application Password, which WordPress offers only there)
   **When** a Rewrite is applied
   **Then** the post is updated; the template assembles no scheme, host or path for the WordPress site
4. **A test URL is caught**
   **Given** an owner saving or testing a webhook URL in the wizard or Settings
   **When** the URL's path is a `/webhook-test/` path
   **Then** an inline warning says it only works while the n8n editor listens and points to the production URL; any production URL, on n8n Cloud or on a self-hosted instance under any domain, saves and pings with no warning
5. **The reachability prerequisite is stated**
   **Given** an owner reading the wizard or the README
   **When** they reach the connection step or the requirements
   **Then** both say the WordPress site must be reachable from the n8n instance
6. **Older imports keep working**
   **Given** a site whose n8n still runs the previous template
   **When** the plugin is updated to this story's version
   **Then** that import keeps running reviews unchanged, and the site sees the existing workflow-update notice once a run reports the old version; the README lists "the trigger stays unauthenticated until you re-import" as a known limitation

## Boundaries

- Must not change: the result callback contract (`save-review`, `X-PQM-Token`, the token and callback address carried in the payload), the command whitelist (story 14), the settings sanitizer's section-wise carry rule.
- Out of scope: the round-trip test (story 2) and the chat-provider contract doc (story 4); this story hands them an authenticated trigger to document. Token rotation: the token is read-only in Settings today; a rotate action is a separate ticket if wanted.

## References

- decision — docs/adr/0010-one-token-authenticates-both-directions.md
- requirements — docs/user-paths.md, A4 "Connect the chain" and UV4/UV5
- docs — README.md "Requirements" and "Installation"; n8n/workflow-diagram.md
- architecture — memory/llm-options-architecture.md, workflow version handshake
- adjacent backlog — story 2 (round-trip test, also edits the template), story 4 (webhook contract doc), story 22 (generator review; its connection-related findings fold into this story)

## Notes

- Owner request (2026-10-06): the n8n domain must stay the user's to define; add the security, best-practice and agnostic-pivot work that review surfaced as one PBI.
- Decision (owner, 2026-10-07): one token secures both directions — the inbound Header Auth credential holds the existing webhook token (KISS, one value to copy). Alternative: mint a second inbound token; one more field, and a leaked import URL then no longer opens the trigger.
- Decision (owner, 2026-10-07): the import URL carries the webhook token as a query argument, because n8n's "Import from URL" sends no cookies, so the token can land in server and proxy logs. With this story the same token also opens the trigger. The import mechanism stays with story 22; this story names the risk next to the import URL copy.
- Settled at build (2026-10-07): the settings defaults mint a fresh token on every call when the stored settings lack one. Confirm at build that every site has a stored token, or the inbound check fails on every call.
- Verified 2026-10-07: no host, domain or niche of the development site appears in the plugin, the template, the README or docs (`grep -ri postquality` hits only memory and this ticket). The agnostic sweep therefore reduces to criteria 3–5.
- Build notes (paths recorded at refinement): the three calls to n8n are in the REST trigger callback, the cron dispatch and the admin test-ping handler, each inline; the hand-built address is the "Update WP Post (Rewrite)" node; the render-matrix harness asserts exactly seven credentialed template nodes and the credentials hint names them, so both change with the new credential. No harness covers the request headers or the save/test handlers today.
- Sequencing with story 2: both bump `PQM_WORKFLOW_VERSION` and touch the template; whichever builds second rebases on the other. `after` stays empty so the security fix is not held behind a hitl review.
- Lesson link: memory/lessons.md 2026-03-27 (never overfit to the current site) applies to hosts and schemes as much as to prompts.
- Risk medium (reason): it changes the trigger contract every installed workflow depends on; criterion 6 keeps old imports running, and a revert is clean.
