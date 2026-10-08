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

Content is scored on four universal pillars — **Structure, Readability, Engagement, Credibility** — so the same workflow serves a marketing blog, a technical IT blog, or a lifestyle site. Niche tailoring happens through **Blog Profiles**, **Editorial Standards** and editable prompts, never hardcoded.

## Features

- **Quality dashboard** — per-post scores, statuses, and top edits with loading/error states
- **n8n workflow generator** — a dedicated **n8n Workflow** page with the pre-configured download, an "Import from URL" link, a live preview of what each agent sends to Claude, and an update notice when the bundled workflow changes
- **Claude model choice** — pick the model globally (Opus 4.8, Sonnet 5, Sonnet 4.6, Haiku 4.5, or any custom model ID) and override it per agent; the plugin sends each model only the parameters it supports, so switching models never breaks a request
- **Blog profiles and Editorial Standards** — choose a Blog Profile (Marketing, Technical/IT, Lifestyle, Food & Recipes, Travel, Health & Wellness) for the Website Context and a tone preset (Professional, Casual, Direct) for the Editorial Standards every post follows (Review and Rewrite); the page shows the exact text the AI receives, and Custom opens a text field for your own
- **On-demand commands** — trigger review/rewrite/etc. for a post from the admin (REST API `POST /trigger-review`, async so the UI never blocks)
- **Insert into Post** — put the suggested Takeaways or Sources into the post with one confirmed click, as a plain list or in a pattern you design in Appearance → Patterns; the previous version stays in Revisions
- **Scheduled reviews** — WP-Cron batches with configurable frequency, batch size, minimum score, and post age
- **Agent settings** — per-agent model, reasoning effort, temperature (where the model supports it), and custom instructions; changes apply on the next command with no workflow re-import

## Requirements

- WordPress 6.6+, PHP 8.0+
- An n8n instance (self-hosted or cloud) and a Claude API key
- WordPress and n8n must reach each other over the network: the plugin calls the n8n webhook, and n8n calls back the site's REST API (results, rewrites). A local or password-protected site cannot be reached from n8n Cloud. The rewrite also needs HTTPS (or a WordPress environment type of `local`): it signs in with an Application Password, which WordPress core offers only there.
- Yoast SEO (optional — its metadata enriches the review payload, and while it is active the Post Reviews screen shows its SEO and readability scores in a Yoast SEO column)

## Installation

1. Copy the [post-quality-manager/](post-quality-manager/) folder (the inner plugin directory) into `wp-content/plugins/` and activate it.
2. In **Content Quality → n8n Workflow** (the welcome notice links there), click **Set up the workflow**. A guide walks you through seven steps, each with the exact n8n menu items, a Copy button for every value and a Done mark; it remembers where you stopped: get an n8n instance, import your pre-configured workflow (by URL or file), create the three credentials (the guide can create the WordPress Application Password for you), link each credential to its node, publish the workflow and copy its production webhook URL (`/webhook/`, not `/webhook-test/`), paste it into the guide (saved and pinged in place), then run your first review from the guide. Reopen it any time from the same page (**Setup guide**). The credentials, and the nodes they go in:
   - **Post Quality webhook** — Header Auth: its header **Name** field is `X-PQM-Token`, its **Value** your webhook token. Pick it in the **Webhook Trigger** only; then only your site can start the workflow.
   - **Anthropic** — a second Header Auth: Name `x-api-key`, Value your Claude API key. Pick it in the six **Claude** nodes.
   - **WordPress** — Basic Auth with an Application Password for a user who can edit posts. Pick it in **Update WP Post (Rewrite)**.
   - Upgrading from a workflow imported before template version 4: the plugin already sends the token, but the old trigger does not check it — it stays unauthenticated until you re-import the workflow and add the Header Auth credential.
3. In the **AI** tab of Settings, under **AI Prompts & Context**, choose your **Blog Profile** (e.g., Technical/IT for a developer blog) and your **Editorial Standards** — or Custom to write your own — and save; the guide's last step links there and to scheduled reviews.

## REST API (`post-quality/v1`)

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/post-score` | GET | `pqm_view_reviews` | Post content, metadata, and SEO data for the workflow |
| `/trigger-review` | POST | any `pqm_run_*` capability (per-command gate inside) | Fire the n8n workflow for a post |
| `/insert-block` | POST | `edit_post` on the post + the agent's gate | Insert the latest Takeaways (first) or Sources (last) into the post, in the chosen pattern |
| `/save-review` | POST | webhook token (`X-PQM-Token`) | Callback where n8n stores review results |
| `/reviews/{post_id}` | GET | `pqm_view_reviews` | Stored review history for a post |
| `/setup` | GET, POST | `pqm_manage_settings` | Setup guide state (progress, values to copy); tick a step or mark the setup complete |
| `/setup/connect` | POST | `pqm_manage_settings` | Save the n8n webhook URL and ping it |

## Admin pages & capabilities

Access is driven entirely by five `pqm_*` capabilities, remappable with any role editor — no code change needed. Activation grants them to these roles by default (Contributor gets none):

| Capability | Default roles |
|---|---|
| `pqm_view_reviews` | Administrator, Editor, Author |
| `pqm_run_free_agents` | Administrator, Editor, Author |
| `pqm_run_paid_agents` | Administrator, Editor |
| `pqm_manage_agents` | Administrator, Editor |
| `pqm_manage_settings` | Administrator |

| Page | Capability |
|---|---|
| Dashboard, Post Reviews | `pqm_view_reviews` |
| n8n Workflow | `pqm_manage_settings` |
| Settings | `pqm_manage_agents` or `pqm_manage_settings` — the page shows only the sections the user may manage: License, n8n Connection, AI Model, AI Prompts & Context, and Monthly Quota need `pqm_manage_settings`; Agent-Specific Overrides and Automated Reviews need `pqm_manage_agents` and stay locked until the site is on the Pro plan (one exception: on a free site with scheduled reviews still on, the Automated Reviews Enable toggle stays live, but only to switch them off) |

**View as.** Administrators (users with WordPress's `manage_options`) can check what an Editor, Author or Contributor sees: the toolbar's "View as" menu narrows their own five `pqm_*` capabilities to those the chosen role holds on the site right now, so menus, Settings sections, buttons, the quota note and the plugin-capability REST refusals follow that role. It only removes plugin capabilities, never adds one, and leaves WordPress's own capabilities and every role untouched. So checks on WordPress's own capabilities do not follow the view: Insert into Post also needs the right to edit the post, so in the Author view the Administrator still sees and runs Insert on every post, where a real Author can only on their own posts. The view stays on for that Administrator only, across sign-ins, until "Exit" in the toolbar. It is real, not a preview: allowed actions run, and a trigger in the Author view spends the Administrator's own monthly quota.

**Single-site only.** Multisite networks are not supported: the monthly quota counter is stored as network-wide user meta, while the quota setting is per site.

## Repository layout

- `post-quality-manager/` — the distributable plugin (`admin/`, `includes/`, `templates/` with the n8n workflow template, entry point, uninstall)
- `n8n/` — Mermaid diagram of the workflow
- `docs/adr/` — Architecture Decision Records (why significant decisions were made)
- `docs/brand.md` — brand voice and copy rules for admin UI text
- `docs/user-paths.md` — new-user and existing-user journeys with their EBM Current/Unrealized Value; the Product Owner's guide to where effort goes next
- `_bmad-output/` — the product initiative, its epics, the backlog tickets and build plans (BMad Method, git-backed); `_bmad/` — BMad runtime and team overrides
- `_archives/` — legacy material kept for history; not part of the plugin

## Development

Development follows the [BMad Method](https://github.com/bmad-code-org/BMAD-METHOD) (ADR 0004): [CLAUDE.md](CLAUDE.md) is the agent entry point, [AGENTS.md](AGENTS.md) holds the engineering guidelines (KISS, WP security practices, `PQM_` prefixes, Definition of Done), `memory/` the versioned project memory and lessons log, and `_bmad-output/` the ticket tree — one initiative whose requirements are the EBM value analysis in [docs/user-paths.md](docs/user-paths.md). Changes go through `bmad-build` (plan → implement → review); larger work through `bmad-spec` and `bmad-ticket`. Project skills `/prioritize`, `/adr` and `/release` live in `.claude/skills/`; significant decisions are recorded in [docs/adr/](docs/adr/). Agent prerequisites: `uv` and Node (for `npx skills`).

Contributions follow Conventional Commits on `feat/`–`fix/`–`chore/` branches; releases are GitHub Releases cut by hand with the `/release` checklist until the planned `release-please` action lands (epic `epic-delivery-pipeline`).

## License

GPL-2.0-or-later
