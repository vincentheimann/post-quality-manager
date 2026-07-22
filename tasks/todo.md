# Task Log

_The agent writes and updates this file for each active task._

---

# Active: Settings polish — test connection, setup wizard, asset & defaults hygiene

**Goal:** Implement four items from `tasks/enhancements.md` on branch `feat/settings-wizard-polish`.

**Scope decision:** "Wizard-style workflow" is implemented as a wizard-style **setup flow** on the settings page (download → import → connect & test), NOT the n8n graph builder — the builder stays deferred per the locked MVP decision (config-injected export, no builder).

## 1. Test connection button

- [x] `PQM_Admin::ajax_test_connection()` — nonce + `manage_options`, POSTs `{command: 'ping'}` to the webhook URL from the field (fallback: saved setting), maps WP_Error / 2xx / 404 / other to friendly messages
- [x] Register `wp_ajax_pqm_test_connection` in the main plugin file
- [x] "Send test ping" button + inline result next to the webhook URL field
- [x] Template: `Parse Input` short-circuits `ping` before required-field checks; `Route Command` gets a `ping` rule → new `Ping OK` NoOp node (fallback index shifts to 8)

## 2. Wizard-style setup flow

- [x] Replace the static "Get started in 3 steps" notice with a 3-step wizard card (Download → Import & connect credentials → Webhook URL & test), shown only while the webhook URL is empty
- [x] Step navigation (Back/Next + step dots) in `pqm-admin.js`, styles in `pqm-admin.css`
- [x] Final step embeds the test-connection action and points at the Save button

## 3. Move inline CSS/JS out of class-pqm-admin.php

- [x] Inline `<script>` block (voice presets, blog profiles, agent resets) → `pqm-admin.js`; data/strings via `wp_localize_script` (`blogProfiles`, `agentDefaults`, `i18n`)
- [x] Inline `style=""` attributes and the range-slider `oninput` → classes in `pqm-admin.css`
- [x] Dashboard + posts pages: same sweep (help icons, pending label, detail-row visibility via `.pqm-hidden`)

## 4. Settings defaults hygiene

- [x] Extract `pqm_default_settings()`; `pqm_get_settings()` and `sanitize_settings()` consume it
- [x] Drop `?? ''` / `?? 'custom'` fallbacks after `pqm_get_settings()` in admin + REST render paths
- [x] Agent reset buttons read default temperatures from the defaults array (fixes review reset: 0.0 → 0.3 mismatch)

## 5. Wrap up

- [x] Static checks: template JSON valid pre/post injection, JS `node --check`, PHP brace/paren balance vs baseline
- [x] `php -l` clean on all plugin PHP files (PHP 8.3 CLI installed 2026-07-22; now part of Definition of Done in WORKFLOW.md)
- [x] Update `tasks/enhancements.md` (remove done items, add new findings)
- [ ] **USER:** manual pass in WP Admin — wizard steps, test ping (active + inactive workflow), profile pre-fill, agent reset, detail row toggle
- [ ] Commit after user approval

## Review

Implemented on branch `feat/settings-wizard-polish` (7 files). Notes:

1. **Test ping** is WP-server → n8n only: 2xx = reachable + active, 404 = wrong URL or inactive workflow, WP_Error = unreachable. The reverse path (n8n → WP token/callback) is not covered — logged in `tasks/enhancements.md` as "full round-trip connection test".
2. The n8n template now short-circuits `ping` to a `Ping OK` node; workflows imported before this change still answer the webhook 200 but log a junk execution on ping (noted in enhancements).
3. Behavior change from defaults hygiene: the Review agent's "Reset to Defaults" button now sets temperature 0.3 (the `pqm_default_settings()` value) instead of the hardcoded 0.0 it used before — the defaults array is the single source of truth.
4. Detail-row show/hide moved from inline `style="display:none"` + `style.display` JS to a shared `.pqm-hidden` class.
5. New findings recorded in enhancements: webhook token regenerates per request until first save; brand-voice preset strings duplicated between admin and REST; JS-generated markup still carries inline styles.

---

# Previous: Pivot to niche-agnostic plugin + n8n workflow generator

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
