# 0010. One webhook token authenticates both directions of the n8n link

- Status: accepted
- Date: 2026-10-07

## Context

Until workflow v3 the n8n Webhook Trigger had no authentication: anyone who learned its URL could start runs, spend the owner's Claude API credit and push rewrites to guessed post IDs. The return path was already guarded: n8n posts results to `/save-review` with the site's webhook token in an `X-PQM-Token` header, and the token travelled to n8n inside the JSON body, where nothing checked it. Story 35 had to close the trigger. Real alternatives: reuse the existing token as an n8n Header Auth credential; mint a second, inbound-only token; or verify the body's token in the workflow's first Code node. A Code-node check runs after n8n has accepted the request and cannot answer 401/403, and a second token adds a field the owner must copy and keep in sync. The owner chose reuse (2026-10-07).

## Decision

One token, both directions, always in the `X-PQM-Token` header. Every call from the plugin to n8n (Run, cron batch, test ping) sends it; the template's Webhook Trigger checks it with n8n Header Auth (credential `Post Quality webhook - <site host>`, header name `X-PQM-Token`, value = the token); `/save-review` keeps checking the same header on the way back. Workflow version 4.

- Imports older than v4 keep working: they ignore the header, so their trigger stays unauthenticated until the owner re-imports. The README names this limitation.
- The import-from-URL link keeps carrying the token as a query argument (n8n's import sends no cookies); its copy now says the token also opens the trigger. Changing that mechanism belongs to story 22.

## Consequences

- Easier: one value to copy, one header name to learn; any chat tool driving the workflow (story 4) authenticates the same way.
- Harder: anyone holding the token (or the import link) can both start runs and post results. Setup needs a third n8n credential of the same type as the Claude one, so the owner must pick the right one per node. Rotating the token means changing it in Settings and in the n8n credential together; there is no rotate action yet.
- Not done until a site reports a need: backfilling a missing stored token on upgrade, and a 403-specific hint when n8n refuses a Run.
