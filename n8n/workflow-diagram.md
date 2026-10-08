# n8n Workflow — Flow Diagram

The canonical workflow template ships with the plugin at
`post-quality-manager/templates/n8n-workflow.json` and is downloaded pre-configured
from the **Content Quality → n8n Workflow** page. Triggers are agnostic: the WordPress
plugin (dashboard button or WP-Cron) POSTs to the webhook; any chat tool or automation
can call the same contract. Results go back to the WordPress callback endpoint
(`/save-review`) and, optionally, to a notification webhook (Slack, kChat, Teams, …).

Contract details, by the workflow version that introduced them:

- **LLM request params ride the payload (v2).** The plugin sends a per-agent `llm` map
  (`model`, `max_tokens`, and — only where the chosen model supports them —
  `temperature`, `output_config.effort`, `thinking`). The "Build … Body" Code nodes
  spread that fragment into the Claude request as-is, so model/effort changes in
  WP settings apply on the next command without re-importing the workflow.
- **Version handshake (v2).** `Parse Input` stamps the template's `workflow_version` and
  the `/save-review` callback echoes it; the plugin flags an outdated import on the
  n8n Workflow page when the bundled template is newer.
- **Failures are reported, not hidden (v3).** The Claude and WordPress HTTP nodes
  "continue on error", so a failed call arrives as an item with `json.error`. The
  Parse nodes, `Format Score`, `Format Rewrite Confirmation` and `Error Fallback` turn
  each failure (empty post, model API error, unparseable agent output, failed rewrite
  save, command without a branch) into an item with `error: true` and a plain
  `message` naming what failed. `POST Result to WordPress` sends
  `error_message: $json.error ? ($json.message || 'The workflow could not complete this command. Check the execution log in n8n.') : ''`;
  the plugin stores a non-empty one as a review with status `error` and the kit shows
  "Could not complete". Boundary: only failures with a post and a command the plugin
  accepts reach `/save-review`. Parse Input rejections (missing `command`/`post_id`,
  or a command outside the `save-review` `command` enum) cannot be stored by contract
  and show only in the n8n execution log. `Rewrite Parsed?` keeps a failed rewrite
  away from `Update WP Post (Rewrite)`, so the post is never PATCHed (or unpublished
  to `pending`) when the agent's output could not be parsed.
- **Authenticated trigger, host-agnostic rewrite (v4).** One token, both directions:
  the plugin sends the webhook token as an `X-PQM-Token` header on every call (Run,
  cron, test ping), and the `Webhook Trigger` checks it with n8n Header Auth
  (credential `Post Quality webhook - <site host>`, header Name field `X-PQM-Token`,
  Value = the token; picked in the Webhook Trigger only — the Anthropic Header Auth
  credential goes in the six Claude nodes); n8n refuses a call without it (401/403) and
  no agent runs. The same header goes back on `/save-review`. Any other client must send
  the header too. The payload carries `post_rest_url` (the post's own REST address from
  `rest_url()`, right for any scheme, port, subdirectory install, permalink setting and
  REST-enabled post type), and `Update WP Post (Rewrite)` PATCHes it as-is — `''` when
  the post type is not in REST, so the node fails and the run reports "Could not save
  the rewrite". The rewrite still needs HTTPS (or a `local` environment type): the node
  signs in with an Application Password, which WordPress core offers only there. Known
  limitation: a v3 import ignores the header and the new field, so it keeps working
  but its trigger stays unauthenticated until you re-import.
- **The test ping reads the version (v5).** The Webhook Trigger answers on receipt with
  `{"ok":true,"workflow_version":5}` (its Response Data option, no extra node), so the
  plugin's test ping learns which workflow runs. An older import answers n8n's default
  body without a version, and the plugin reports it as an older version.

Solid edges are the connections in the template; dotted edges show the failure items
travelling the same connection into `/save-review`.

```mermaid
flowchart TD
    A[Webhook Trigger<br>POST from WP plugin or any client<br>Header Auth: X-PQM-Token] --> B[Parse Input<br>stamps workflow_version 5]
    B --> C{Route Command}

    C -->|score| SC[Format Score] --> CB
    C -->|review| B1[Build Review Body] --> H1[Claude - Review Agent] --> P1[Parse Review Result] --> CB
    C -->|takeaways| B2[Build Takeaways Body] --> H2[Claude - Takeaways Agent] --> P2[Parse Takeaways Result] --> CB
    C -->|sources| B3[Build Sources Body] --> H3[Claude - Sources Agent] --> P3[Parse Sources Result] --> CB
    C -->|seo| B4[Build SEO Body] --> H4[Claude - SEO Agent] --> P4[Parse SEO Result] --> CB
    C -->|audit| B5[Build Audit Body] --> H5[Claude - Audit Agent] --> P5[Parse Audit Result] --> CB

    C -->|rewrite| B6[Build Rewrite Body] --> H6[Claude - Rewrite Agent] --> P6[Parse Rewrite Response] --> RP{Rewrite Parsed?}
    RP -->|true| UP[Update WP Post Rewrite<br>PATCH post_rest_url: content, excerpt, status pending] --> FR[Format Rewrite Confirmation]
    RP -->|false| FR
    FR --> CB

    C -->|ping| PO([Ping OK<br>stops, webhook already answered])
    C -->|fallback| EF[Error Fallback] --> CB

    SC -.->|empty post:<br>error true + message| CB
    P1 & P2 & P3 & P4 & P5 -.->|empty post, model API error or invalid JSON:<br>error true + message| CB
    FR -.->|empty post, model API error, parse failure or save failure:<br>error true + message| CB
    EF -.->|command without a branch:<br>error true + message| CB

    CB[POST Result to WordPress<br>/save-review, error_message when error is true] --> HN{Has Notification URL?}
    HN -->|true| NOTIFY([Send Notification<br>optional webhook: Slack / kChat / Teams])
```
