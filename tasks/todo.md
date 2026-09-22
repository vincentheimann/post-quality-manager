# Task Log

_The agent writes and updates this file for each active task._

---

# Active: Pivot to niche-agnostic plugin + n8n workflow generator

**Goal:** Any blogger (e.g., a technical IT blog) can install the plugin, pick a blog profile, download a pre-configured n8n workflow, import it, and run AI quality reviews — no blog-specific anything left.

**Decisions (validated 2026-07-22):** config-injected workflow export (no wizard builder) · WP admin as primary trigger, chat optional via generic webhook · niche presets with editable fields · distribution via GitHub Releases.

## Phase 1 — Blog profile presets (niche tailoring)

- [x] Add `blog_profile` setting: presets `marketing`, `technical`, `lifestyle`, `custom` (default `custom`)
- [x] Presets pre-fill editable defaults (website context + brand-voice preset) via `PQM_Admin::blog_profiles()` — one PHP array as single source of truth
- [x] Reuse the preset-or-custom radio pattern; JS pre-fill with overwrite confirmation when context is non-empty
- [x] Sanitize `blog_profile` against the profiles array; add missing settings defaults (`blog_profile`, `brand_voice_preset`, `brand_voice`, `website_context`)

## Phase 2 — n8n workflow generator (core deliverable)

- [x] Audit found only ONE site-specific value in the whole workflow (WP credential name) — callback/notification URLs travel in the runtime payload and WP update URLs are derived from post links
- [x] Template at `post-quality-manager/templates/n8n-workflow.json` (git mv from n8n/, history preserved); placeholder syntax `__PQM_SITE_HOST__` (not `{{ }}` — avoids collision with n8n expressions); instance metadata stripped, `active: false`
- [x] `PQM_Admin::download_workflow()` via `admin-post.php` (nonce + `manage_options`), injects site host, serves JSON download
- [x] 3-step setup checklist shown only while the webhook URL is empty + permanent "Workflow File" download row in n8n Connection
- [x] Deleted legacy v1 workflow and `debug_node_020.txt` (git history keeps them)

## Phase 3 — De-brand and document

- [x] `n8n/workflow-diagram.md` rewritten: generic webhook trigger, WP callback, optional notification webhook (Slack/kChat/Teams)
- [x] Niche-string sweep clean (`postquality`, `kchat`, `neuroscience` only remain in history/lessons/examples where legitimate)
- [x] README rewritten around the agnostic pitch + generator flow; removed the incorrect "companion API plugin required" claim
- [x] AGENTS.md / CLAUDE.md updated to the new template location and placeholder rule
- [x] Created `tasks/enhancements.md` — deferred improvements backlog

## Phase 4 — Verify and release

- [x] Static verification: template JSON valid before and after injection, zero placeholders left after replacement, credentials generic; PHP brace/paren balance checked (no local PHP runtime for `php -l`)
- [ ] **USER:** full round-trip on a real site with the `technical` profile: download workflow → import into n8n → credentials → trigger review from dashboard → result appears
- [ ] Release v3.1.0 via GitHub Release after the round-trip passes

**Out of scope (MVP):** wizard builder, WordPress.org, chat providers beyond the generic webhook, i18n expansion — tracked in `tasks/enhancements.md`.

## Review

Implemented on branch `feat/agnostic-mvp`. Key deviations from the original plan, all simplifications:
1. Only one placeholder (`__PQM_SITE_HOST__`) was needed instead of five — the workflow was already payload-driven for URLs and token.
2. Placeholder syntax `__PQM_*__` instead of `{{ }}` to avoid colliding with n8n expression syntax.
3. "Test connection" button deferred to `tasks/enhancements.md`; checklist step 3 relies on running the first real review.
