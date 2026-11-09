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
- **n8n workflow generator** — a dedicated **n8n Workflow** page with the pre-configured workflow file to import in n8n, a live preview of what each agent sends to Claude, and an update notice when the bundled workflow changes
- **Claude model choice** — pick the model globally (the current Opus 5.5, Sonnet 5.5, the default, and Haiku 5.5; the earlier Opus 4.8, Sonnet 5, Sonnet 4.6 and Haiku 4.5; or any custom model ID) and override it per agent; the plugin sends each model only the parameters it supports, so switching models never breaks a request
- **Blog profiles and Editorial Standards** — choose a Blog Profile (Marketing, Technical/IT, Lifestyle, Food & Recipes, Travel, Health & Wellness) for the Website Context and a tone preset (Professional, Casual, Direct) for the Editorial Standards every post follows (Review and Rewrite); the page shows the exact text the AI receives, and Custom opens a text field for your own
- **On-demand commands** — trigger review/rewrite/etc. for a post from the admin (REST API `POST /trigger-review`, async so the UI never blocks)
- **Insert into Post** — put the suggested Takeaways or Sources into the post with one confirmed click, as a plain list or in a pattern you design in Appearance → Patterns; the previous version stays in Revisions
- **Scheduled reviews** — WP-Cron batches with configurable frequency, batch size, minimum score, and post age
- **Agent settings** — per-agent model, reasoning effort, temperature (where the model supports it), and custom instructions; changes apply on the next command with no workflow re-import

## Requirements

- WordPress 6.6+, PHP 8.1+
- An n8n instance (self-hosted or cloud) and a Claude API key
- WordPress and n8n must reach each other over the network: the plugin calls the n8n webhook, and n8n calls back the site's REST API (results, rewrites). A local or password-protected site cannot be reached from n8n Cloud. The rewrite also needs HTTPS (or a WordPress environment type of `local`): it signs in with an Application Password, which WordPress core offers only there.
- Yoast SEO (optional — its metadata enriches the review payload, and while it is active the Post Reviews screen shows its SEO and readability scores in a Yoast SEO column)

## Installation

1. Copy the [post-quality-manager/](post-quality-manager/) folder (the inner plugin directory) into `wp-content/plugins/` and activate it. A folder copied from this repository needs the admin app built first ([Build from source](#build-from-source)): `admin/build/` is not tracked, and without it the Dashboard and Post Reviews pages show a build notice instead.
2. In **Post Quality → n8n Workflow** (the welcome notice links there), click **Set up the workflow**. The setup guide asks for three values: your n8n address and an n8n API key (create one for this site alone, with an expiration date, under Settings → n8n API in n8n) in **Connect your n8n**, then your Anthropic API key once in **Set up the workflow**. The plugin checks the key with Anthropic and passes it into an n8n credential without storing it, creates the credentials and the workflow in n8n, publishes the workflow, saves its webhook URL and pings it; then you run your first review from the guide. Reopen it any time from the same page (**Setup guide**).
   - **By hand instead:** for an n8n whose public API is off or out of reach, or if you prefer not to hand over a key, choose **Set up by hand instead**. The **Workflow File** section of the same page holds the step-by-step manual setup (import the workflow, create the credentials, pick them in the nodes, publish it); the guide then takes the production webhook URL (`/webhook/`, not `/webhook-test/`) and runs your first review.
3. After the first review, the guide offers three more steps, each skippable: your **Blog Profile** (e.g., Technical/IT for a developer blog), your **Editorial Standards** — or Custom to write your own — and scheduled reviews (Pro). Change them any time in Settings: the **AI** tab, under **AI Prompts & Context**, and the **Automation** tab.

## Build from source

The admin app (the Dashboard, Post Reviews and the n8n Workflow page's setup guide) is React, compiled from `src/` into `post-quality-manager/admin/build/`, which the plugin zip ships and git does not track. With Node 22.22.2+, 24.15+ or 26+, from the repository root:

1. `npm ci` installs the build tools (`@wordpress/scripts`).
2. `npm run build` writes `post-quality-manager/admin/build/` from `src/`.
3. `npm run zip` builds the Pro zip into `dist/` (it needs a clean working tree and runs the build itself).
4. `php bin/build-free.php <dir>` writes the Free build, the one the WordPress.org directory hosts, to `<dir>/post-quality-manager/` (run step 2 first; `<dir>` must not hold a build already).

## REST API (`post-quality/v1`)

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/post-score` | GET | `postquality_view_reviews` | Post content, metadata, and SEO data for the workflow |
| `/trigger-review` | POST | any `postquality_run_*` capability (per-command gate inside) | Fire the n8n workflow for a post |
| `/insert-block` | POST | `edit_post` on the post + the agent's gate | Insert the latest Takeaways (first) or Sources (last) into the post, in the chosen pattern |
| `/save-review` | POST | webhook token (`X-PQM-Token`) | Callback where n8n stores review results |
| `/reviews/{post_id}` | GET | `postquality_view_reviews` | Stored review history for a post |
| `/setup` | GET, POST | `postquality_manage_settings` | Setup guide state (progress, the n8n connection and path); tick a configuration step, mark the setup complete, or store the manual-path choice (`{manual: true}`) or remove it (`{manual: false}`) |
| `/setup/connect` | POST | `postquality_manage_settings` | Save the n8n webhook URL and ping it |
| `/setup/settings` | POST | `postquality_manage_settings` (+ `postquality_manage_agents` on a Pro site to turn on scheduled reviews) | Save the guide's Blog Profile, Editorial Standards or scheduled-reviews choice; only those settings |

## Admin pages & capabilities

Access is driven entirely by five `postquality_*` capabilities, remappable on the Settings → Roles tab or with any role editor — no code change needed. Activation grants them to these roles by default (Contributor gets none):

| Capability | Default roles |
|---|---|
| `postquality_view_reviews` | Administrator, Editor, Author |
| `postquality_run_free_agents` | Administrator, Editor, Author |
| `postquality_run_paid_agents` | Administrator, Editor |
| `postquality_manage_agents` | Administrator, Editor |
| `postquality_manage_settings` | Administrator |

| Page | Capability |
|---|---|
| Dashboard, Post Reviews | `postquality_view_reviews` |
| n8n Workflow | `postquality_manage_settings` |
| Settings | `postquality_manage_agents` or `postquality_manage_settings` — the page shows only the sections the user may manage: License, n8n Connection, AI Model, AI Prompts & Context, and Monthly Quota need `postquality_manage_settings`; Agent-Specific Overrides and Automated Reviews need `postquality_manage_agents` and stay locked until the site is on the Pro plan (one exception: on a free site with scheduled reviews still on, the Automated Reviews Enable toggle stays live, but only to switch them off) |

**Roles tab.** Administrators (users with WordPress's `manage_options`) set what each role can do under Settings → Roles: one row per role on the site, custom roles included, and one checkbox per capability above (View reviews, Run Review and Key Takeaways, Run paid agents, Manage agents, Manage settings). Saving the Settings form writes the boxes changed on the form to the roles themselves (a role change made elsewhere since the page loaded is kept), so menus, Settings sections, buttons and REST refusals follow on the role's next request; nothing is stored in the plugin's settings, and per-user grants and WordPress's own capabilities are never touched. Rows of roles holding `manage_options` cannot be changed on the tab, and each Pro save gives those roles all five. The tab is a Pro feature: on a free site it shows the current grants, locked, and saving changes no role. Activation and upgrades grant a capability's defaults only while no `manage_options` role holds it yet (a fresh install, or a capability new in a version), so the roles as set are never reset. The former toolbar "View as" switch is retired; a stored view choice is ignored.

**Single-site only.** Multisite networks are not supported: the monthly quota counter is stored as network-wide user meta, while the quota setting is per site.

## Repository layout

- `post-quality-manager/` — the distributable plugin (`admin/`, `includes/`, `templates/` with the n8n workflow template, entry point, `vendor/freemius/` with the licensing SDK)
- `n8n/` — Mermaid diagram of the workflow
- `docs/adr/` — Architecture Decision Records (why significant decisions were made)
- `docs/brand.md` — brand voice and copy rules for admin UI text
- `docs/distribution.md` — every field the WordPress.org directory, the licensing provider, GitHub and the website ask for, with its value or its owner
- `docs/policies/` — privacy, terms, refund and support texts published on postquality.com, plus the readme's External services block
- `docs/user-paths.md` — new-user and existing-user journeys with their EBM Current/Unrealized Value; the Product Owner's guide to where effort goes next
- `_bmad-output/` — the product initiative, its epics, the backlog tickets and build plans (BMad Method, git-backed); `_bmad/` — BMad runtime and team overrides

## Development

Development follows the [BMad Method](https://github.com/bmad-code-org/BMAD-METHOD) (ADR 0004): [CLAUDE.md](CLAUDE.md) is the agent entry point, [AGENTS.md](AGENTS.md) holds the engineering guidelines (KISS, WP security practices, `PostQuality_` / `postquality_` prefixes, Definition of Done), `memory/` the versioned project memory and lessons log, and `_bmad-output/` the ticket tree — one initiative whose requirements are the EBM value analysis in [docs/user-paths.md](docs/user-paths.md). Changes go through `bmad-build` (plan → implement → review); larger work through `bmad-spec` and `bmad-ticket`. Project skills `/prioritize`, `/adr` and `/release` live in `.claude/skills/`; significant decisions are recorded in [docs/adr/](docs/adr/). Agent prerequisites: `uv` and Node (for `npx skills`).

Checks on a real WordPress run on Local sites ([docs/local-verification.md](docs/local-verification.md)), and its "A disposable n8n" section starts a throwaway n8n on the same PC for a full round trip, deleted after each check (no n8n stays installed).

Contributions follow Conventional Commits on `feat/`–`fix/`–`chore/` branches and are merged by rebase (every branch commit reaches the CHANGELOG); releases are GitHub Releases cut by release-please: merging its release PR tags the version and publishes a CI-built zip (`/release` checks it).

## License

GPL-2.0-or-later, see [`LICENSE`](LICENSE).
