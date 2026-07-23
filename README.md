# Post Quality Manager

WordPress content quality dashboard for professional bloggers — in any niche. It stores AI review results produced by an n8n workflow, schedules automated reviews, and gives a global overview of your blog's content quality — scores, issues, and suggested edits — right in the WP admin. A built-in generator downloads the n8n workflow pre-configured for your site.

## Why this exists

As a blog grows, its back-catalog quietly ages: older posts drift out of date, quality varies from one article to the next, and manually re-reading everything doesn't scale. SEO plugins score keywords and metadata, but nothing tells you **which posts need editorial attention, and why**.

Post Quality Manager closes that gap by making content review continuous and automatic. Every post is scored on four universal pillars — Structure, Readability, Engagement, Credibility — the results are stored as history, and the dashboard shows at a glance where to act next. Niche tailoring lives in Blog Profiles and editable prompts, never in code, so the same plugin serves a marketing blog, a technical IT blog, or a lifestyle site equally well.

**Vision:** make automated quality control as standard for WordPress content as SEO plugins made keyword optimization — in any niche, with the blogger always in control of the criteria.

## How it works

```
WP admin (dashboard button / WP-Cron) ──► n8n workflow ──► Claude agents
        any chat tool (optional webhook) ──┘   (review / rewrite / takeaways /
                      ▲                         sources / seo / audit)
                      │                              │
                      └── stores reviews ◄── WordPress REST API (this plugin)
```

Content is scored on four universal pillars — **Structure, Readability, Engagement, Credibility** — so the same workflow serves a marketing blog, a technical IT blog, or a lifestyle site. Niche tailoring happens through **Blog Profiles** and editable prompts, never hardcoded.

## Features

- **Quality dashboard** — per-post scores, statuses, and top edits with loading/error states
- **n8n workflow generator** — download the bundled workflow pre-configured for your site from Settings; import, connect credentials, done
- **Blog profiles** — Marketing, Technical/IT, Lifestyle, or Custom presets that pre-fill voice and context (everything stays editable)
- **On-demand commands** — trigger review/rewrite/etc. for a post from the admin (AJAX, async so the UI never blocks)
- **Scheduled reviews** — WP-Cron batches with configurable frequency, batch size, minimum score, and post age
- **Agent prompt settings** — per-agent custom instructions and temperature overrides

## Requirements

- WordPress 6.0+, PHP 8.0+
- An n8n instance (self-hosted or cloud) and a Claude API key
- Yoast SEO (optional — its SEO and readability scores appear on the Post Reviews screen next to the AI score, and its metadata enriches the review payload)

## Installation

1. Copy the [post-quality-manager/](post-quality-manager/) folder (the inner plugin directory) into `wp-content/plugins/` and activate it.
2. In **Content Quality → Settings**, follow the 3-step checklist: download your pre-configured workflow, import it into n8n and connect the two credentials (WordPress Application Password + Claude API key), then paste the workflow's production webhook URL back into Settings.
3. Pick your **Blog Profile** (e.g., Technical/IT for a developer blog), adjust the pre-filled context if needed, and save. Run your first review from the **Post Reviews** page.

## REST API (`post-quality/v1`)

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/post-score` | GET | `edit_posts` | Post content, metadata, and SEO data for the workflow |
| `/trigger-review` | POST | `edit_posts` | Fire the n8n workflow for a post |
| `/save-review` | POST | webhook token | Callback where n8n stores review results |
| `/reviews/{post_id}` | GET | `edit_posts` | Stored review history for a post |

## Repository layout

- `post-quality-manager/` — the distributable plugin (`admin/`, `includes/`, `templates/` with the n8n workflow template, entry point, uninstall)
- `n8n/` — Mermaid diagram of the workflow
- `docs/brand.md` — brand voice and copy rules for admin UI text
- `_archives/` — legacy material kept for history; not part of the plugin

## Development

Development follows an agent-assisted workflow: [CLAUDE.md](CLAUDE.md) is the entry point, [AGENTS.md](AGENTS.md) holds the engineering guidelines (KISS, WP security practices, `PQM_` prefixes), [WORKFLOW.md](WORKFLOW.md) the task workflow, `memory/` the versioned project memory, and `tasks/` the active plan, lessons log, and enhancements backlog. Reusable workflows live in `.claude/skills/` (`/fix-bug`, `/new-feature`, `/release`).

Contributions follow Conventional Commits on `feat/`–`fix/`–`chore/` branches; releases are cut by `release-please`.

## License

GPL-2.0-or-later
