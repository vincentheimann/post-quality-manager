# Post Quality Manager

WordPress content quality dashboard for professional bloggers. It stores AI review results produced by an n8n workflow, schedules automated reviews, and gives a global overview of your blog's content quality — scores, issues, and suggested edits — right in the WP admin.

## How it works

```
kChat command ──► n8n workflow ──► Claude agents (review / rewrite / takeaways /
                      │             sources / seo / audit)
                      ▼
        WordPress REST API (this plugin)
        stores reviews · updates posts · renders the dashboard
```

The plugin can also trigger the n8n workflow itself (from the dashboard or on a WP-Cron schedule) and receives results back on a token-protected callback endpoint. AI prompts are niche-agnostic, built on universal content pillars (Structure, Readability, Engagement, Credibility); per-agent instruction overrides and temperatures are configurable in Settings.

## Features

- **Quality dashboard** — per-post scores, statuses, and top edits with loading/error states
- **Review storage** — results from n8n persisted in a dedicated table
- **On-demand commands** — trigger review/rewrite/etc. for a post from the admin (AJAX, async so the UI never blocks)
- **Scheduled reviews** — WP-Cron batches with configurable frequency, batch size, minimum score, and post age
- **Agent prompt settings** — per-agent custom instructions and temperature overrides

## Requirements

- WordPress 6.0+, PHP 8.0+
- An n8n instance running the workflow from [n8n/](n8n/) (uses the Claude API)
- The companion [Post Quality API](https://github.com/vincentheimann/post-quality-api) plugin (exposes Yoast SEO scores to the workflow)

## Installation

1. Copy the [post-quality-manager/](post-quality-manager/) folder (the inner plugin directory) into `wp-content/plugins/` and activate it.
2. Import [n8n/post_quality_workflow_v2.json](n8n/post_quality_workflow_v2.json) into your n8n instance and set its credentials (WordPress Application Password, Claude API key, kChat webhook).
3. In **Settings → Post Quality**, set the n8n webhook URL; the plugin generates the webhook token used to authenticate the n8n → WordPress callback.

## REST API (`post-quality/v1`)

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/post-score` | GET | `edit_posts` | Post content, metadata, and SEO data for the workflow |
| `/trigger-review` | POST | `edit_posts` | Fire the n8n workflow for a post |
| `/save-review` | POST | webhook token | Callback where n8n stores review results |
| `/reviews/{post_id}` | GET | `edit_posts` | Stored review history for a post |

## Repository layout

- `post-quality-manager/` — the distributable plugin (`admin/`, `includes/`, entry point, uninstall)
- `n8n/` — workflow exports (v2 current), a Mermaid diagram of the flow, node debug snippets
- `docs/brand.md` — brand voice and copy rules for admin UI text
- `_archives/` — legacy material kept for history; not part of the plugin

## Development

Development follows an agent-assisted workflow: [CLAUDE.md](CLAUDE.md) is the entry point, [AGENTS.md](AGENTS.md) holds the engineering guidelines (KISS, WP security practices, `PQM_` prefixes), [WORKFLOW.md](WORKFLOW.md) the task workflow, `memory/` the versioned project memory, and `tasks/` the active plan and lessons log. Reusable workflows live in `.claude/skills/` (`/fix-bug`, `/new-feature`, `/release`).

Contributions follow Conventional Commits on `feat/`–`fix/`–`chore/` branches; releases are cut by `release-please`.

## License

GPL-2.0-or-later
