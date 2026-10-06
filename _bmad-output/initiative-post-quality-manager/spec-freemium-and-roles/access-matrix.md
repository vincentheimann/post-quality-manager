# Access matrix — role × feature × tier

Effective access = plugin capability (role columns) **and** plan tier, both read by `PQM_Features::can()` / `why_not()`. On an unlicensed site every Paid row refuses regardless of role. Role refusal is checked first (hidden); plan refusal renders locked with upgrade copy.

## Capabilities (default map)

| Capability | Administrator | Editor | Author | Contributor |
|---|---|---|---|---|
| `pqm_view_reviews` | ✓ | ✓ | ✓ | — |
| `pqm_run_free_agents` | ✓ | ✓ | ✓ | — |
| `pqm_run_paid_agents` | ✓ | ✓ | — | — |
| `pqm_manage_agents` | ✓ | ✓ | — | — |
| `pqm_manage_settings` | ✓ | — | — | — |

Defaults only: role editors remap any capability, per role or per user, and every check follows. `CAPS_VERSION` bumps re-register the defaults on upgrade (`maybe_upgrade()` on `init`); deactivation keeps caps; uninstall strips them from every role and every user's direct grants.

## Features

| Feature | Tier | Capability | Administrator | Editor | Author | Contributor |
|---|---|---|---|---|---|---|
| Dashboard, Post Reviews, read endpoints (`/posts`, `/reviews`, `/dashboard`, `/post-score`) | Free | `pqm_view_reviews` | ✓ | ✓ | ✓ | — |
| Trigger Review / Key Takeaways | Free | `pqm_run_free_agents` | ✓ | ✓ | ✓ (quota) | — |
| Trigger Score / Apply | Free | `pqm_run_free_agents` | ✓ | ✓ | ✓ (quota) | — |
| Trigger Rewrite / Sources / SEO / Audit | Paid | `pqm_run_paid_agents` | ✓ | ✓ | — | — |
| Insert into Post (Takeaways free, Sources paid) | per command | command's cap + `edit_post` on the post | ✓ | ✓ | Takeaways, own editable posts | — |
| Settings page (open + save) | — | `pqm_manage_settings`, else `pqm_manage_agents` | ✓ | ✓ | — | — |
| Settings: License, n8n Connection, AI Model, AI Prompts & Context, Monthly Quota | — | `pqm_manage_settings` | ✓ | — | — | — |
| Settings: Agent-Specific Overrides, Automated Reviews (render + save) | Paid | `pqm_manage_agents` | ✓ | ✓ | — | — |
| Dashboard cron nudge + dismiss | Paid | `pqm_manage_agents` | ✓ | ✓ | — | — |
| n8n Workflow page, workflow download, webhook save, connection-test `ping` | — | `pqm_manage_settings` | ✓ | — | — | — |
| `POST /save-review` (n8n callback) | — | `pqm_run_free_agents` (WP auth) or `X-PQM-Token` | ✓ | ✓ | ✓ | — |
| `GET /workflow` (n8n import) | — | `pqm_manage_settings` (WP auth) or token | ✓ | — | — | — |

## Command rules

- Registry = whitelist: `review`, `takeaways`, `score`, `apply` (free), `rewrite`, `sources`, `seo`, `audit` (paid). REST `command` enums are its keys (400 otherwise); an unknown command is refused for every caller, cron included. `apply_*` has no entry.
- `POST /trigger-review` is the one trigger path; its route admits any registry capability, the exact decision runs in the callback.
- Outside the gate by design: `ping` (runs no agent, `pqm_manage_settings` only); cron (plan half only — no user, no quota; a plan-gated stored `cron_command` skips the whole batch, no fallback).
- The n8n credential user calling `GET /post-score` needs `pqm_view_reviews`.

## Quota

- Subject: anyone lacking `pqm_run_paid_agents` — free-site Editors are exempt though plan-blocked.
- Per user, per calendar month (site timezone), visible; Admin-configured `author_quota`, shipped default 20 (existing installs included), `0` = unlimited.
- Checked after the gate; spent only when n8n answers 2xx to a manual trigger, `score`/`apply` included. Refusals, unreachable n8n, non-2xx answers, Insert into Post and cron never spend.
- Stored as self-resetting user meta `pqm_monthly_usage` (no table, no schema change).

## Plan-locked sections and cron

- On a free site the agent sections render visible but locked (disabled fieldset), for Admins too; stored cron settings keep running.
- Exception: while stored cron is on, the Enable toggle stays live outside the lock as an off-switch only, for `pqm_manage_agents` holders, plan-independent.
- Saves carry every section the saver could not post — role-hidden, plan-locked, or absent from a pro-site POST — from stored values, never defaults.
