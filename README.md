# Post Quality Manager

Post Quality Manager reviews your WordPress posts with AI and keeps the results in WP Admin: a score for every post, what to fix first, and the trend across your blog. The reviews run in your own n8n workflow with your own Anthropic API key, and a setup guide creates the workflow for you. Built for professional bloggers, in any niche.

## Free and Pro

The free version includes Review and Key Takeaways, the Dashboard, Post Reviews and Insert into Post. Pro adds the Rewrite, Sources, SEO and Audit agents, per-agent settings, scheduled reviews and the Roles tab ([pricing](https://postquality.com/pricing)). Features marked *(Pro)* below are in Pro only.

## Why this exists

As a blog grows, its back-catalog quietly ages: older posts drift out of date, quality varies from one article to the next, and manually re-reading everything doesn't scale. SEO plugins score keywords and metadata, but nothing tells you **which posts need editorial attention, and why**.

Post Quality Manager closes that gap by making content review a routine: on demand in the free version, on a schedule in Pro. Every post is scored on four universal pillars — Structure, Readability, Engagement, Credibility — the results are stored as history, and the dashboard shows at a glance where to act next. Niche tailoring lives in Blog Profiles, Editorial Standards and editable prompts, never in code, so the same plugin serves a marketing blog, a technical IT blog, or a lifestyle site equally well.

**Vision:** make automated quality control as standard for WordPress content as SEO plugins made keyword optimization — in any niche, with the blogger always in control of the criteria.

## How it works

```
WP admin (dashboard button / WP-Cron) ──► n8n workflow ──► Claude agents
        any chat tool (optional webhook) ──┘   (review / rewrite / takeaways /
                      ▲                         sources / seo / audit)
                      │                              │
                      └── stores reviews ◄── WordPress REST API (this plugin)
```

The free version runs Review and Key Takeaways when you ask; Rewrite, Sources, SEO, Audit and the WP-Cron schedule are Pro.

## Features

- **Dashboard and Post Reviews** — your average score and its trend, the posts to fix next, and every post with its latest score, status and top edits; a Post Quality box on the post edit screen shows the post's review
- **Guided n8n setup** — the setup guide connects to your n8n with an n8n API key and creates the workflow and its credentials for you, or walks you through importing the workflow file by hand. When a plugin update needs a newer workflow, the n8n Workflow page says so and offers **Update workflow** (or the re-import steps for a workflow set up by hand)
- **Claude model choice** — pick the model globally (the current Opus 5.5, Sonnet 5.5, the default, and Haiku 5.5; the earlier Opus 4.8, Sonnet 5, Sonnet 4.6 and Haiku 4.5; or any custom model ID) and *(Pro)* override it per agent; the plugin sends each model only the parameters it supports, so switching models never breaks a request
- **Blog profiles and Editorial Standards** — choose a Blog Profile (Marketing, Technical/IT, Lifestyle, Food & Recipes, Travel, Health & Wellness) for the Website Context and a tone preset (Professional, Casual, Direct) for the Editorial Standards every post follows (Review, and Rewrite on Pro); Settings → AI shows the exact text each agent sends to Claude, and Custom opens a text field for your own
- **On-demand commands** — run Review or Key Takeaways on a post from WP Admin, and *(Pro)* Rewrite, Sources, SEO or Audit
- **Insert into Post** — put the suggested Takeaways, or *(Pro)* Sources, into the post with one confirmed click, as a plain list or *(Pro)* in a pattern you design in Appearance → Patterns; the previous version stays in Revisions
- **Scheduled reviews** *(Pro)* — run Review, SEO or Audit daily or weekly on a batch of posts, picking those whose last result scores below a threshold or is older than a set number of days
- **Agent settings** *(Pro)* — per-agent model, reasoning effort, temperature (where the model supports it), and custom instructions; changes apply on the next command with no workflow re-import
- **Roles tab** *(Pro)* — choose what each role may do, custom roles included ([Admin pages & capabilities](#admin-pages--capabilities))

## Requirements

- WordPress 6.6+, PHP 8.1+
- Your own n8n instance (self-hosted or cloud) and an Anthropic API key. Both bill you directly; a review costs about 3 to 4 cents on the default model (`post-quality-manager/readme.txt`, "What does it cost me in AI calls?")
- WordPress and n8n must reach each other over the network: the plugin calls the n8n webhook, and n8n calls back the site's REST API (results, rewrites). A local or password-protected site cannot be reached from n8n Cloud. Rewrite *(Pro)* also needs HTTPS (or a WordPress environment type of `local`): it signs in with an Application Password, which WordPress core offers only there.
- Yoast SEO (optional — its metadata enriches the review payload, and while it is active the Post Reviews screen shows its SEO and readability scores in a Yoast SEO column)

## Installation

1. Download `post-quality-manager-<version>.zip` from the [latest release](https://github.com/vincentheimann/post-quality-manager/releases/latest), upload it under **Plugins → Add New Plugin → Upload Plugin** and activate it. This is the free version; Pro is the zip you download from [postquality.com](https://postquality.com) after checkout.
2. Click **Set up the workflow** in the welcome notice, or open **Post Quality → n8n Workflow** and click **Set up n8n**. The setup guide asks for three values: your n8n address and an n8n API key (create one for this site alone, with an expiration date, under Settings → n8n API in n8n) in **Connect your n8n**, then your Anthropic API key once in **Set up the workflow**. The plugin checks the key with Anthropic and passes it into an n8n credential without storing it, creates the credentials and the workflow in n8n, publishes the workflow, saves its webhook URL and pings it; then you run your first review from the guide. Until the setup is finished, the same page reopens the guide at the next open step (**Run your first review**, **Finish setting up: …**).
   - **By hand instead:** for an n8n whose public API is off or out of reach, or if you prefer not to hand over a key, choose **Set up by hand instead**. The page opens its **Set up by hand** section with four steps (import the workflow, create the credentials, check the credential in each node, publish and connect); paste the production webhook URL (`/webhook/`, not `/webhook-test/`) in step 4 and click **Save and test**, then run your first review.
3. After the first review, the guide offers your **Blog Profile** and your **Editorial Standards** (or Custom to write your own), and on Pro scheduled reviews, each skippable. Change them any time in Settings: the **AI** tab, under **AI Prompts & Context**, and on Pro the **Automation** tab.

## Help

- Documentation: [postquality.com/docs](https://postquality.com/docs/)
- Bugs and feature requests: [GitHub issues](https://github.com/vincentheimann/post-quality-manager/issues/new/choose)
- Questions: hello@postquality.com (free version: best effort; Pro: within 5 business days)
- Security problems: [SECURITY.md](SECURITY.md), never a public issue

## Build from source

The admin app (the Dashboard, Post Reviews and the n8n Workflow page's setup guide) is React, compiled from `src/` into `post-quality-manager/admin/build/`, which the plugin zip ships and git does not track. A [post-quality-manager/](post-quality-manager/) folder copied from this repository is the full build: build the admin app first (without `admin/build/` the Dashboard and Post Reviews pages show a build notice), and it asks for a license key at activation ([ADR 0014](docs/adr/0014-two-builds-from-one-source.md)). With Node 22.22.2+, 24.15+ or 26+, from the repository root:

1. `npm ci` installs the build tools (`@wordpress/scripts`).
2. `npm run build` writes `post-quality-manager/admin/build/` from `src/`.
3. `npm run zip` builds the Pro zip into `dist/` (it needs a clean working tree and runs the build itself).
4. `php bin/build-free.php <dir>` writes the Free build (the free version's code, as in the zip on GitHub Releases) to `<dir>/post-quality-manager/` (run step 2 first; `<dir>` must not hold a build already).

## REST API (`post-quality/v1`)

The routes n8n and integrations use. The admin app's own routes (`/posts`, `/dashboard`, `/workflow`, `/ping/{id}`, `/n8n/*`) are in `post-quality-manager/includes/class-pqm-rest-api.php`.

| Route | Method | Auth | Purpose |
|---|---|---|---|
| `/post-score` | GET | `postquality_view_reviews` | Post content, metadata, and SEO data for the workflow |
| `/trigger-review` | POST | any `postquality_run_*` capability (per-command gate inside) | Fire the n8n workflow for a post |
| `/insert-block` | POST | `edit_post` on the post + the agent's gate | Insert the latest Takeaways (first) or Sources (last) into the post, in the chosen pattern |
| `/save-review` | POST | webhook token (`X-PQM-Token`), or a signed-in user with `postquality_run_free_agents` | Callback where n8n stores review results |
| `/reviews/{post_id}` | GET | `postquality_view_reviews` | Stored review history for a post |
| `/setup` | GET, POST | `postquality_manage_settings` | Setup guide state (progress, the n8n connection and path); tick a configuration step, mark the setup complete, or store the manual-path choice (`{manual: true}`) or remove it (`{manual: false}`) |
| `/setup/connect` | POST | `postquality_manage_settings` | Save the n8n webhook URL and ping it |
| `/setup/settings` | POST | `postquality_manage_settings` (+ `postquality_manage_agents` on a Pro site to turn on scheduled reviews) | Save the guide's Blog Profile, Editorial Standards or scheduled-reviews choice; only those settings |

## Admin pages & capabilities

Access is driven entirely by five `postquality_*` capabilities, remappable on the Settings → Roles tab *(Pro)* or with any role editor plugin — no code change needed. Activation grants them to these roles by default (Contributor gets none):

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
| Settings | `postquality_manage_settings`, or in Pro also `postquality_manage_agents` — the page shows only the sections the user may manage: License, n8n Connection, AI Model, AI Prompts & Context, and Monthly Quota need `postquality_manage_settings`; Agent-Specific Overrides and Automated Reviews exist in Pro only and need `postquality_manage_agents`, and while a Pro site has no active license they show locked (one exception: with scheduled reviews still on, the Automated Reviews Enable toggle stays live, but only to switch them off) |

**Roles tab** *(Pro)*. Administrators (users with WordPress's `manage_options`) set what each role can do under Settings → Roles: one row per role on the site, custom roles included, and one checkbox per capability above (View reviews, Run Review and Key Takeaways, Run paid agents, Manage agents, Manage settings). Saving the Settings form writes the boxes changed on the form to the roles themselves (a role change made elsewhere since the page loaded is kept), so menus, Settings sections, buttons and REST refusals follow on the role's next request; nothing is stored in the plugin's settings, and per-user grants and WordPress's own capabilities are never touched. Rows of roles holding `manage_options` cannot be changed on the tab, and each Pro save gives those roles all five. Without an active license the tab shows the current grants, locked, and saving changes no role; the free version has no Roles tab, so use a role editor plugin there. Activation and upgrades grant a capability's defaults only while no `manage_options` role holds it yet (a fresh install, or a capability new in a version), so the roles as set are never reset.

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

Contributions: open an issue first; pull requests are accepted by prior agreement ([CONTRIBUTING.md](CONTRIBUTING.md); security reports through [SECURITY.md](SECURITY.md)). Branches are `feat/…`, `fix/…` or `chore/…` with Conventional Commits, merged by rebase; each `feat` and `fix` commit becomes a CHANGELOG line. release-please cuts the releases: merging its release PR tags the version and publishes the free version's zip on GitHub Releases.

## License

GPL-2.0-or-later, see [`LICENSE`](LICENSE).
