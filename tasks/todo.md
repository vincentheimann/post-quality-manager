# Task Log

_The agent writes and updates this file for each active task._

---

# Active: Pivot to niche-agnostic plugin + n8n workflow generator

**Goal:** Any blogger (e.g., a technical IT blog) can install the plugin, pick a blog profile, download a pre-configured n8n workflow, import it, and run AI quality reviews — no blog-specific anything left.

**Decisions (validated 2026-07-22):** config-injected workflow export (no wizard builder) · WP admin as primary trigger, chat optional via generic webhook · niche presets with editable fields · distribution via GitHub Releases.

**Already done** (commit `7badb06`): universal pillars (Structure, Readability, Engagement, Credibility) in prompts and admin JS; brand-voice presets; per-agent instruction/temperature overrides; v2 workflow is webhook-triggered with zero kChat references.

## Phase 1 — Blog profile presets (niche tailoring)

- [ ] Add `blog_profile` setting: presets `marketing`, `technical`, `lifestyle`, `custom` (default `custom`)
- [ ] Each preset pre-fills editable defaults: website context template, brand-voice preset, short per-agent instruction hints (e.g., technical → "value code correctness, cite docs, tolerate jargon for a developer audience")
- [ ] Reuse the existing preset-or-custom radio pattern (progressive disclosure — no new UI concepts)
- [ ] Sanitize the new setting; presets defined in one PHP array (single source of truth, KISS)

## Phase 2 — n8n workflow generator (core deliverable)

- [ ] Audit `n8n/post_quality_workflow_v2.json` for site-specific values (credential name `WP - <owner-site>`, hardcoded URLs, tokens) and replace with placeholders: `{{SITE_URL}}`, `{{CALLBACK_URL}}`, `{{WEBHOOK_TOKEN}}`, `{{NOTIFICATION_URL}}`, `{{WP_CREDENTIAL_NAME}}`
- [ ] Store as bundled template `post-quality-manager/templates/n8n-workflow.json` (plugin-distributable path)
- [ ] "Download n8n workflow" button on the settings page: `admin-post.php` handler (nonce + `manage_options`) does a `str_replace` of placeholders from saved settings and serves the JSON as a download — no builder, no runtime dependency
- [ ] Lean 3-step setup checklist at the top of settings (only when webhook URL is empty): 1) Download workflow → 2) Import into n8n and connect WordPress + Claude credentials → 3) Paste the n8n webhook URL here and hit "Test connection"
- [ ] Delete legacy `n8n/post_quality_workflow.json` (v1, 39 niche references; git history keeps it) — the template becomes the single canonical workflow

## Phase 3 — De-brand and document

- [ ] Rewrite `n8n/workflow-diagram.md`: generic webhook trigger and notification webhook instead of kChat
- [ ] Sweep all user-facing strings and docs for niche leftovers (`postquality`, kChat, marketing-specific examples); use a technical IT blog as the running example in docs
- [ ] Update README (features + setup guide reflecting the generator flow)

## Phase 4 — Verify and release

- [ ] Definition of done: no WP debug errors; generated JSON imports cleanly into n8n; full round-trip (trigger review from dashboard → n8n → Claude → `/save-review` → dashboard shows result) on a test post with the `technical` profile
- [ ] Release v3.1.0 via GitHub Release (zip of `post-quality-manager/` incl. `templates/`)

**Out of scope (MVP):** wizard-style workflow builder, WordPress.org submission, chat-provider integrations beyond the generic webhook contract, i18n expansion, multiple workflow variants.

## Review

_(pending — filled in when the task completes)_
