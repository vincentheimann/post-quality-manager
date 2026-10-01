---
title: 'Settings sections follow role and plan'
type: 'feature'
ticket: '5'
created: '2026-10-01'
status: done
baseline_revision: 'cca876621ed8a5e2236ad0af47b537fd20dff4a4'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The whole Settings page sits behind `pqm_manage_settings` (Admin), so Editors cannot reach the per-agent overrides and Automated Reviews the access matrix grants them (spec CAP-3, epic Done-when 3), and nothing on the page reflects the plan tier — paid sections look identical on free and licensed sites.

**Approach:** Open the Settings page to `pqm_manage_agents` (Admin + Editor — the capability story 1.2 reserved for exactly this) and gate each section: role-gated sections are *hidden* (n8n Connection, AI Model, AI Prompts & Context globals, Monthly Quota stay Admin-only), plan-gated sections (Agent-Specific Overrides, Automated Reviews — Paid tier per the matrix) render *visible but locked* with upgrade copy on a free site. The sanitizer carries stored values for every section the saving request could not have posted.

## Boundaries & Constraints

**Always:** Section → gate mapping per the access matrix: n8n Connection + AI Model + AI Prompts & Context (Blog Profile, Brand Voice, Website Context) + Monthly Quota require `pqm_manage_settings` and are absent from the page otherwise; Agent-Specific Overrides + Automated Reviews require `pqm_manage_agents` and are plan-locked (not hidden) when `PQM_Features::is_pro()` is false — for Admins too. Role-hidden beats plan-locked, mirroring `why_not()`. Plan-locking wraps the section in a single `<fieldset disabled>` so no inner control can POST or be re-enabled by the model-caps JS. The option save follows the page: `option_page_capability_pqm_settings_group` → `pqm_manage_agents`; `sanitize_settings()` must carry the *stored* value (never the default) for admin-only keys when the saver lacks `pqm_manage_settings`, and for `cron_*` + `agent_prompts` when the site is not pro — the disabled-controls pitfall, applied per section. A role editor granting `pqm_manage_agents` or `pqm_manage_settings` must change page access with no code change. Settings JS works on the reduced page: every block guards on its own elements, and the effective default model falls back to a localized value when the AI Model section is absent. Upgrade copy per `docs/brand.md`, consistent with the existing "Available with Pro…" strings. n8n Workflow page stays `pqm_manage_settings`.

**Never:** No capability-map or `CAPS_VERSION` change (Editors already hold `pqm_manage_agents`); no change to REST routes, webhook payload, n8n template, cron behavior (story 6), quota logic (built), or the license stub (story 3); no second form or AJAX save for settings sections; no per-field capability matrix beyond the section mapping; no removal of the preset/Custom paths.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Editor, licensed | Opens Settings | Exactly overrides + Automated Reviews, editable; saving an override works | No error expected |
| Editor, free site | Opens Settings | Both sections visible but locked (disabled fieldset) with upgrade copy | No error expected |
| Editor saves | Admin-only fields absent from POST | webhook URL/token, notification URL, models, profile/voice/context, quota all keep stored values | No error expected |
| Free-site save (any role) | Locked `cron_*` + `agent_prompts` absent from POST | Both carried from stored, including `cron_enabled` true | No error expected |
| Author | Direct `options.php` POST to `pqm_settings_group` | Refused — lacks `pqm_manage_agents`; no Settings menu either | WP core 403 |
| Admin, free site | Opens Settings | All six sections; globals editable, overrides + cron locked with upgrade copy | No error expected |
| Admin, licensed | Opens Settings and saves | Today's full behavior unchanged | No error expected |
| Remap | Role editor grants Author `pqm_manage_agents` | Author gets the Editor view, no code change | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- Settings submenu :60-67 cap → `pqm_manage_agents` (Workflow :51-58 stays); `option_page_capability` filter :209 → `pqm_manage_agents`; `sanitize_settings()` :212-261 — restructure per section: `$existing = pqm_get_settings()`, admin-only keys (`n8n_webhook_url`, `webhook_token`, `notification_url`, `blog_profile`, `brand_voice_preset`, `brand_voice`, `website_context`, `default_model`, `custom_model`, `author_quota`) from `$input` only when `current_user_can('pqm_manage_settings')`, else `$existing`; `cron_*` + `agent_prompts` from `$input` only when `PQM_Features::is_pro()`, else `$existing` wholesale (keep the per-knob stored fallback :241-258 for the pro path); `render_settings()` :631 — wrap admin-only sections (n8n Connection :658-699, AI Model :701-732, AI Prompts & Context :733-793, Monthly Quota after :886-cron) in a `pqm_manage_settings` check; promote Agent-Specific Overrides h3 :795 to its own h2 section; wrap overrides and Automated Reviews :886+ each in `<fieldset disabled>` + `.pqm-locked-section` + upgrade description when not pro.
- `post-quality-manager/admin/js/pqm-admin.js` -- `initSettingsPage()` :63-133: drop the page-level `contextArea` bail :64-65; guard the presets block on `contextArea && voiceArea`, keep sliders :107-111 and reset :113-132 running independently; `initModelControls()` :140-194: make `globalSelect`/`customField` :141-144 optional — effective default from `pqmAdmin.defaultModel` when absent, attach :185-186 listeners only when present, keep `refreshAll()` :193 running off `.pqm-agent-box` presence; skip re-enabling controls inside a disabled fieldset is automatic (fieldset wins) — no change needed there.
- `post-quality-manager/admin/class-pqm-admin.php` localize :118-150 -- add `'defaultModel' => ` the effective default (resolve `custom` → `custom_model`).
- `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-locked-section` (muted header lock icon + spacing for the upgrade note), reuse existing `.pqm-locked` tones.
- `README.md` -- the capability table added by story 1.2 names `pqm_manage_settings` for the Settings page; update the Settings row to `pqm_manage_agents` (sections note).
- `memory/freemium-and-roles.md` -- update the `pqm_manage_agents` line ("reserved for story 5") to built, record the section mapping + sanitizer carry rule.
- Don't touch: `includes/` (no REST/cron/feature change), `templates/n8n-workflow.json`, the n8n Workflow page and its AJAX merge-saves, `uninstall.php`.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- menu cap, option-page filter, per-section sanitizer carry, section visibility + locked fieldsets + upgrade copy, `defaultModel` localize -- the whole page follows role and plan.
- [x] `post-quality-manager/admin/js/pqm-admin.js` -- per-block guards + `pqmAdmin.defaultModel` fallback -- overrides stay fully functional on the Editor view.
- [x] `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-locked-section` styling -- locked ≠ broken, visually.
- [x] `README.md` -- Settings capability row -- docs match enforcement.
- [x] CLI harness (WP stubs, as prior stories) -- sanitizer matrix: saver capability × plan × posted sections -- the carry rule is the riskiest logic.
- [x] `memory/freemium-and-roles.md` -- section mapping + carry rule -- DoD.

**Acceptance Criteria:**
- Given an Editor on a licensed site, when they open Settings, then they see exactly the Agent-Specific Overrides and Automated Reviews sections, both editable, and their save changes only those keys.
- Given any saver, when a section was hidden or locked for their request, then every key of that section is identical before and after the save (stored, not default).
- Given a free site, when an Admin or Editor views Settings, then both paid sections show upgrade copy and no control inside them is operable.

## Implementation Notes

Built 2026-10-01 against baseline `cca8766`.

- `class-pqm-admin.php`: Settings submenu cap → `pqm_manage_agents` (Workflow stays `pqm_manage_settings`); `option_page_capability_pqm_settings_group` → `pqm_manage_agents`; `sanitize_settings()` restructured into two section-wise branches — admin-only keys (`n8n_webhook_url`, `webhook_token`, `notification_url`, `blog_profile`, `brand_voice_preset`, `brand_voice`, `website_context`, `author_quota`, `default_model`, `custom_model`) from `$input` only with `pqm_manage_settings`, else carried from `pqm_get_settings()`; `cron_*` + `agent_prompts` from `$input` only when `PQM_Features::is_pro()`, else carried wholesale from stored (per-knob stored fallback for model-locked temperature/effort kept on the pro path; `cron_command` still forced to the fixed default on the pro path). Every branch returns a complete settings array.
- `render_settings()`: `$registry` hoisted above the form (the overrides boxes need it without the AI Model section); admin-only block (n8n Connection, AI Model, AI Prompts & Context) and Monthly Quota wrapped in `current_user_can('pqm_manage_settings')`; the "New here?" setup notice gated the same way (it links to the admin-only Workflow page). Agent-Specific Overrides promoted from `h3` to its own `h2` section; it and Automated Reviews each get, when not pro: a muted `dashicons-lock` in the heading (`.pqm-section-lock`, `aria-hidden`), a `.pqm-locked-note` upgrade description (copy exactly per Design Notes), and one `<fieldset disabled class="pqm-locked-section">` around the whole section body — JS-proof, nothing inside can POST or be re-enabled. Pro output unchanged (no wrapper rendered).
- Localize: `'defaultModel'` added to `pqmAdmin` — the effective global default with `custom` resolved to `custom_model` (falling back to `claude-sonnet-4-6`, mirroring `pqm_effective_model()`); `enqueue_assets()` now reads `pqm_get_settings()` once.
- `pqm-admin.js`: `initSettingsPage()` lost the page-level `contextArea` bail — the presets block now guards on `contextArea && voiceArea` while sliders and reset buttons run off their own selectors; `initModelControls()` lost the `globalSelect` bail — new `effectiveDefault()` reads the select/custom field when present, else `pqmAdmin.defaultModel`; listeners attach only to present elements; `refreshAll()` still runs unconditionally (no-op off `.pqm-agent-box` absence).
- CSS: `.pqm-locked-section` (border/padding reset for the fieldset + the `.pqm-locked` opacity .55 tone), `.pqm-section-lock` muted icon, `.pqm-locked-note` spacing.
- `README.md`: the plan's "capability table added by story 1.2" turned out not to exist (1.2 only re-labelled the REST table's Auth column) — added a small "Admin pages & capabilities" table instead, with the Settings row on `pqm_manage_agents` and the sections note.
- `memory/freemium-and-roles.md`: `pqm_manage_agents` line updated from "reserved for story 5" to built; new "Settings sections (built, story 5)" bullet records the section → gate mapping and the sanitizer carry rule.
- Untouched, per Never: capability map / `CAPS_VERSION`, `includes/`, REST routes, webhook payload, n8n template + Workflow page AJAX, cron behavior, license stub, preset/Custom paths; no second form.
- Review fixes 2026-10-01: (1) `sanitize_settings()` accepts null/mixed input and casts to array first — options.php passes null when a save posts no `pqm_settings` field at all (free-site Editor view); (2) the global "Welcome…" admin banner is gated on `pqm_manage_settings` like the in-form notice (it links to the Admin-only Workflow page); (3) new `settings_page_capability()` resolves the Settings menu + `option_page_capability` filter dynamically (`pqm_manage_settings` when held, else `pqm_manage_agents`) so granting *either* capability opens and saves the page, per the frozen remap clause; (4) the Dashboard cron nudge additionally requires `PQM_Features::is_pro()` — no more pointing free-site Admins at a locked section; (5) the per-agent model dropdown's empty option reads "Default (site default model)" when the AI Model section is absent (Editor view). Lint + harness re-run clean after all five.

## Plan Change Log

## Review Triage Log

### 2026-10-01 — quick lens, pass 1 (verdicts: 1 high, 2 medium, 2 low, 0 false, 0 maybe-false; +1 low found during the matrix audit)

- **high / patch** — `admin/class-pqm-admin.php` `sanitize_settings(array $input)`: WP `options.php` runs `update_option('pqm_settings', null)` for every registered option in the posted group even when `$_POST['pqm_settings']` is absent — exactly what the free-site Editor view posts (all fields hidden or in a disabled fieldset) — and `null` into the non-nullable `array` parameter is an uncaught TypeError → WP critical-error page on Save. Verified against wp-admin/options.php (`$value = null; … update_option(...)` unconditionally). Patch sent: accept null and cast before the branches; harness extended with the null-input case.
- **medium / patch** — `admin_notices()` :73-95: the global "Welcome … set up the n8n workflow first" banner prints on every pqm screen with a link to the Admin-only Workflow page; the newly Editor-reachable Settings page shows it to users who get a WP permissions error on click. The in-form twin was gated on `pqm_manage_settings`; the banner was missed. Patch sent: same gate.
- **medium / patch** — menu :63-70 + `option_page_capability` :222 both hard-require `pqm_manage_agents`, but the frozen Always promises that granting `pqm_manage_agents` *or* `pqm_manage_settings` changes access. A settings-only role has no menu and a dead save. Patch sent: both places resolve the capability dynamically — `pqm_manage_settings` when the current user holds it, else `pqm_manage_agents`.
- **low / patch** — Dashboard cron nudge :393-412 invites enabling scheduled reviews and anchors to `#pqm-cron-settings`, which this change turns into a locked fieldset on a free site — a dead-end CTA every free-site Admin meets. Patch sent: show the nudge only when `PQM_Features::is_pro()`.
- **low / patch** (audit finding) — per-agent model dropdown's first option reads "Default (use the AI Model above)" on the Editor view, where the AI Model section is hidden. Patch sent: contextual label when the saver lacks `pqm_manage_settings` ("Default (site default model)").
- **rejected** — "manual WP Admin pass pending → DoD unmet": true and already recorded in the plan's Verification; the pass is owner-owned (no WP install in this environment), same disposition as stories 1.1–1.4. Not a diff defect; surfaced to the owner at presentation.

## Design Notes

`<fieldset disabled>` is the lock mechanism because it is JS-proof: the model-caps refresh toggles `disabled` on inner controls, and a disabled ancestor fieldset keeps them inert and out of the POST regardless. The sanitizer's carry rule is deliberately section-wise (`$existing` wholesale), not per-field: a section that could not POST can only ever mean "keep what is stored" — per-field merging would re-open the checkbox ambiguity (`cron_enabled` unchecked vs locked). Upgrade copy, brand-aligned with the existing gate strings: section note `Available with Pro. Upgrade to unlock per-agent overrides — Review and Takeaways stay free.` / `Available with Pro. Upgrade to let reviews run on a schedule.`

## Verification

**Commands:**
- `php -l post-quality-manager/admin/class-pqm-admin.php` -- expected: no syntax errors
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no syntax errors
- CLI harness with WP stubs -- expected: sanitize matrix holds — Editor save keeps admin keys, free-site save keeps cron/overrides, pro Admin save behaves as today (including the locked-knob stored fallback).

**Results 2026-10-01:** `php -l` clean on `admin/class-pqm-admin.php` (PHP 8.3.28); `node --check` clean on `admin/js/pqm-admin.js`. CLI harness (scratchpad, WP stubs): 74/74 checks pass (incl. null input from options.php → no fatal, full stored carry) — Editor-pro save carries all ten admin-only keys (stored webhook token, not a fresh default) while applying override + cron changes; Editor-free empty POST leaves every key identical (incl. `cron_enabled` true); Admin-free globals-only save applies globals and carries `cron_*` + `agent_prompts` from stored (both `cron_enabled` true and false fixtures); Admin-pro full save behaves as today incl. the per-knob stored fallback for a model-locked temperature/effort and `cron_command` forced to default; every branch returns a complete settings array. Manual WP Admin pass (Editor/Author menus, locked sections, preset/slider/reset/model-caps JS on both views) still pending.

**Manual checks (if no CLI):**
- Real site: Editor menu shows Settings; Editor view has exactly two sections (free: locked with copy, licensed: editable); Author has no Settings menu; Admin free site sees locked paid sections; preset pre-fill, temp sliders, reset buttons and model-caps toggling still work on both the Admin and Editor views.
