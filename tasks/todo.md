# Task Log

_The agent writes and updates this file for each active task._

---

# Done: Institutionalize ADR / skill / living-memory practices (2026-07-24)

**Goal (user):** make ADRs, skills, and living memory explicit first-class practices of the agentic workspace (correction of the user-paths placement decision, see lessons.md 2026-07-24); also add the missing telemetry idea to the enhancements backlog.

## Steps

- [x] Telemetry backlog item (privacy-first, local-only EBM signals) in `tasks/enhancements.md`
- [x] Commit user paths on `feat/llm-options-pivot` (`e065fe2`, user-requested)
- [x] `docs/adr/` — practice guide (README with bar, format, store triad) + ADR-0001 (adopt ADRs) + ADR-0002 (user paths + EBM as PO tool)
- [x] New skills: `/adr` (record a decision) and `/prioritize` (EBM grooming from user-paths UV + backlog)
- [x] AGENTS.md "Documentation & Decision Practices" section (ADR = why, memory = what, skills = how); WORKFLOW.md Definition of Done gains a knowledge-freshness check; CLAUDE.md layout updated
- [x] Memory: agent-conventions fact extended + MEMORY.md index (mirror updated); README references (`docs/adr/`, new skills)
- [x] Lesson captured in `tasks/lessons.md` (don't treat a missing practice as a design constraint)

## Review

Uncommitted (commit not yet requested for this batch). Past major decisions (agnostic pivot, LLM options) intentionally NOT backfilled as ADRs — they stay in `memory/` and get an ADR only if revisited (ADR-0001).

---

# Done: User paths + EBM value map as a PO tool (2026-07-24)

**Goal (user):** draft the user paths for the new user and the existing user to guide plugin design (and the future wizard), annotated with EBM Current Value / Unrealized Value so the Product Owner knows where to put effort; reference them in the README and the agent files.

**Decisions (user, 2026-07-24):** single persona (solo pro blogger, any niche) · CV + UV per stage with candidate signals (no full four-KVA treatment) · existing-user path grounded in real usage (cron + ad-hoc triggers; review/takeaways/sources/seo/audit used, rewrite not; post-review action "too early to say") · new-user path starts at discovery. Key finding: the perceived "settings change ⇒ full workflow re-import" pain was already removed by the 2026-07-23 payload pivot — making that *perceived* is itself top-3 Unrealized Value.

## Steps

- [x] `docs/user-paths.md` — Path A (discovery → habit, 6 stages) + Path B (quality loop, 5 stages), CV/UV/signal per stage, ranked UV table, wizard notes
- [x] README: `docs/user-paths.md` in repository layout + Development pointer
- [x] AGENTS.md: "Do" rule — read user-paths before product/UX decisions (chosen over ADR/skill: repo has no ADR practice, skills are workflows; AGENTS.md + memory are what agents actually load)
- [x] `memory/user-paths.md` + MEMORY.md index line (agent-local mirror updated too)

## Review

Doc is a living artifact: move items UV → CV when friction is removed, update when real usage contradicts a claim. No code touched; no telemetry exists, so all signals are candidates — evidence today is user #1's self-observation.

---

# Active: Pivot to modern LLM options — model + effort registry, payload-driven params, dedicated Workflow page (2026-07-23)

**Goal (user):** temperature — our per-agent knob everywhere — is being removed from modern Claude models (Opus 4.7/4.8 reject it with HTTP 400; Sonnet 5 rejects non-default values). Pivot the plugin and the n8n workflow generation to the options that matter in modern LLMs (model choice, reasoning effort, thinking), in a way that stays scalable, plus a dedicated page to generate the workflow with the useful options.

**Architecture (the scalability core — three layers):**

1. **Model registry (single source of truth).** `pqm_model_registry()` in the main plugin file: curated models + capability flags — label, pricing hint, `supports_temperature`, `supports_effort` (+ allowed levels), thinking behavior, suggested `max_tokens`. Curated list: `claude-opus-4-8` (effort low→max incl. xhigh, no temperature), `claude-sonnet-5` (same + adaptive thinking on by default), `claude-sonnet-4-6` (temperature OK, effort low/medium/high/max), `claude-haiku-4-5` (temperature OK, no effort) — plus **Custom** (free-text model ID, conservative params: omit sampling and effort; escape hatch per lessons.md 2026-07-23). Settings UI, sanitizer, and payload builder all consume this one array; supporting a new model = adding one registry entry.
2. **Server-side request-param builder.** `pqm_llm_params(string $agent, array $settings): array` returns the ready-to-merge Claude request fragment (`model`, `max_tokens`, and `output_config.effort` / `temperature` / `thinking` only when the registry allows them). The webhook payload gains an `llm` map: one fragment per agent.
3. **Dumb workflow.** The six "Build … Body" Code nodes stop hardcoding model/temperature/max_tokens and just spread the fragment: `const body = { ...d.llm.review, system, messages }`. The six parse nodes switch from `content[0].text` to `content.find(b => b.type === 'text').text` (thinking-proof). **After this one re-import, LLM-option changes never require touching n8n again** — they ride the payload.

**Settings pivot (decisions validated 2026-07-23 — per-agent model, temperature kept where supported):**
- **Global default Model** dropdown (registry-driven) + Custom text input. Default `claude-sonnet-4-6` = zero silent behavior change for existing installs; description recommends Sonnet 5.
- **Per-agent Model select** in each agent row: "Default (use global)" + the registry list — e.g. Opus for rewrite, Haiku for audit. Effective model = agent override ?: global.
- Per-agent **"Reasoning effort" select** added next to the existing controls (registry-filtered levels; disabled with a hint when the effective model has no effort support, e.g. Haiku 4.5). Defaults: review/rewrite `high`, takeaways/sources/seo/audit `medium`.
- Per-agent **temperature slider stays**, but is shown/enabled only when the effective model supports temperature (Sonnet 4.6, Haiku 4.5); hidden with a short "not supported by this model — use effort" hint otherwise. Custom model IDs get conservative treatment: both sampling and effort omitted from the request.
- JS: registry capabilities localized via `wp_localize_script`; changing a model select live-toggles which knobs (temperature/effort) are active for that agent.
- **Migration** in `pqm_get_settings()`: keep `instructions` and stored `temperature` (still used where supported), seed `model => ''` (inherit) and effort defaults per agent. Reset buttons + `agentDefaults` follow the new shape.

**Dedicated "n8n Workflow" admin page:** new submenu page consolidating generation concerns (settings page keeps connection fields + AI prompts):
- Download + Import-from-URL rows (moved from settings; wizard step 1 points here).
- **Effective configuration preview**: per-agent table (model, effort, max tokens) showing exactly what n8n will send — rendered from `pqm_llm_params()`, so it can never lie.
- **Version handshake**: template carries a `workflow_version`; the workflow echoes it in its WP callback; plugin stores last-seen version and shows a "workflow outdated — re-import" notice on this page when the bundled template is newer.
- Test ping lives here too.

**Decisions (user, 2026-07-23):** per-agent model selects (with global default + inherit) · temperature kept where the model supports it, hidden otherwise · default model `claude-sonnet-4-6` · dedicated "n8n Workflow" submenu page.

## Steps

- [x] `pqm_model_registry()` + `pqm_model_caps()` + `pqm_effective_model()` + `pqm_llm_params()` + `pqm_llm_map()` in `post-quality-manager.php`; `PQM_WORKFLOW_VERSION` constant; smoke-tested with a stubbed-WP param-matrix harness (24 checks green)
- [x] Settings: global default model dropdown + custom field; per-agent model + effort selects alongside the kept temperature slider (capability-aware initial state rendered server-side); sanitizer + defaults; migration is automatic via `pqm_get_settings()` deep-merge
- [x] Webhook payload: `pqm_n8n_payload()` shared builder in the main plugin file — REST trigger **and** cron now ship identical payloads incl. the `llm` map (cron also gains brand voice/context/agent prompts it was silently missing)
- [x] Template rework (v2): six Build-Body nodes spread `d.llm[agent]` (with per-agent hardcoded fallback for robustness); six parse nodes use `find(b => b.type === 'text')`; Parse Input stamps `workflow_version: 2` and forwards `llm`; callback echoes the version
- [x] New "n8n Workflow" admin page (`pqm-workflow`): wizard (moved from Settings), download + import URL, per-agent request preview table, template-version status + outdated notice, test ping; Settings page slimmed to persisted options with pointers
- [x] Callback handler stores `workflow_version` → option `pqm_workflow_version_seen` (cleaned up in uninstall.php)
- [x] JS: split page-agnostic init (test ping, copy URL, wizard) from settings-only init; `modelRegistry` localized; live knob toggling per selected model; reset buttons restore model/effort/temperature from `agentDefaults`
- [x] Docs: README (features, installation), n8n/workflow-diagram.md (payload contract + version handshake), memory/llm-options-architecture.md (+ index)
- [x] Verify: `php -l` clean on all 5 touched PHP files; `node --check` clean on pqm-admin.js; template JSON valid pre/post injection with all 17 Code nodes syntax-checked; pivot invariants asserted (no leftover hardcoded temperature/model, 6 llm spreads, version stamps)
- [ ] **USER:** manual pass — settings knobs toggle per model, preview table matches, wizard on the new page; round-trip per model (sonnet-4-6, sonnet-5, opus-4-8, haiku) incl. one thinking-capable model proving the parse fix; outdated-notice appears after a v2 import reports back
- [x] Commit on `feat/llm-options-pivot` (user asked to commit ahead of the manual pass, 2026-07-23 — validation checklist above stays open)

## Review

Implemented 2026-07-23 on `feat/llm-options-pivot` (9 files: main plugin file, REST API, cron, uninstall, admin class, admin JS, admin CSS, workflow template, README + diagram + memory). Notes:

1. **The scalability core landed as planned:** registry → param builder → payload `llm` map → workflow spreads it. After this one re-import (template v2), model/effort/temperature changes apply on the next command with no n8n touch. The preview table on the new page renders from `pqm_llm_params()` itself, so it can't drift from reality.
2. **Migration is non-destructive and automatic:** old settings blobs keep `instructions` and `temperature`; missing `model`/`effort` keys fall back to defaults through the existing deep-merge in `pqm_get_settings()`. No upgrade routine needed.
3. **Sonnet 5 gets `thinking: {type: "disabled"}`** — with adaptive thinking on by default, thinking tokens would eat the agents' small `max_tokens` budgets (1000–8000) and the pipeline wants deterministic JSON. Parse nodes are still thinking-proof (`find(type==='text')`) so a custom thinking-always-on model (e.g. Fable) also works.
4. **Effort clamp:** an unsupported stored level (e.g. `xhigh` after switching an agent to Sonnet 4.6) clamps to `high` server-side; the JS mirrors this by disabling unsupported options.
5. **Custom model IDs are conservative by design:** only `model` + `max_tokens` are sent (every current Claude model accepts that shape). Documented in the settings description.
6. **Behavior change (intended): cron payloads now include brand voice, website context, agent prompts, and the `llm` map** — previously cron reviews silently ran without customization because the payload was a diverged copy. Both paths now share `pqm_n8n_payload()`.
7. **Settings form integrity:** all persisted `pqm_settings` fields stay on the single Settings form because `sanitize_settings()` rebuilds the whole array — the n8n Workflow page is deliberately form-free (download/preview/test only).
8. **Old imported workflows keep working** during the transition: build nodes fall back to the previous hardcoded sonnet-4-6 params when `d.llm` is absent, and the version handshake surfaces the "update available" notice once any post-v2 result comes back.

---

# Planned: "Import from URL" next to the workflow download (2026-07-23)

**Goal (user):** At the same level as the "Download n8n workflow" button, generate and display a copyable URL so the workflow can be imported directly in n8n via *Workflow → Import from URL…* — best-practice UX.

**Why it works:** the generated workflow contains no secrets (only `__PQM_SITE_HOST__` is injected), and the plugin already owns a shared secret (`webhook_token`). A token-protected public GET endpoint can serve the same JSON the download serves.

**Design (KISS):**
- **Endpoint:** new REST route `GET pqm/v1/workflow?token=<webhook_token>`. Permission callback: `hash_equals` against the saved `webhook_token` (plus `manage_options` as alternative). Returns the injected workflow JSON with `Access-Control-Allow-Origin: *` (older n8n versions fetch import-URLs from the browser; newer ones fetch server-side — header covers both).
- **Shared builder:** extract the template-read + `__PQM_SITE_HOST__` injection from `PQM_Admin::download_workflow()` into a `pqm_workflow_json()` helper in the main plugin file (admin class isn't loaded on REST requests) — download and REST route both consume it.
- **UI (settings "Workflow File" row + wizard step 1):** keep Download as the primary button; below it, an "or import from URL" line: readonly input with the generated URL + a "Copy" button. UX details: click on the input selects all; Copy uses `navigator.clipboard` (fallback to `select()` + `execCommand`); button flips to "Copied!" for ~2s with `aria-live` feedback; description tells the user exactly where to paste it ("In n8n: Workflow menu → Import from URL…").
- **Copy per docs/brand.md:** concise, action-oriented, native WP classes only.

## Steps

- [x] `pqm_workflow_json()` helper in `post-quality-manager.php`; `download_workflow()` refactored to use it
- [x] REST route `GET post-quality/v1/workflow` in `class-pqm-rest-api.php` (token permission callback via `hash_equals`, CORS header, decoded workflow object response)
- [x] Settings row + wizard step 1: URL field + Copy button markup via shared `render_import_url()` helper (classes only — rendered twice, no IDs), description copy incl. keep-it-private note
- [x] `pqm-admin.js` copy handler (`navigator.clipboard` + `execCommand` fallback, select-on-focus, `aria-live` feedback reusing the test-result pattern); CSS for the URL row
- [x] Verify: `php -l` clean on all 3 touched PHP files, `node --check` clean, injected template decodes with `nodes`+`connections` (what n8n's import requires)
- [ ] **USER:** manual pass — URL shows in wizard + settings row, Copy gives "Copied!" feedback, pasting the URL in n8n *Import from URL…* imports the pre-configured workflow, wrong/missing token gets 401
- [x] Commit after user approval — `a5a6f36` (selective staging, 2026-07-23)

## Review

Implemented 2026-07-23 in the working tree on `feat/settings-context-ux` (5 files: main plugin file, REST API class, admin class, admin JS, admin CSS). Notes:

1. **Auth reuses the existing `webhook_token`** (persisted at activation) as a `?token=` query arg — n8n fetches without WP cookies, so a header/nonce won't do. Compared with `hash_equals`; `manage_options` passes too. No new secret to manage.
2. **The served JSON contains no secrets** — only the site host is injected, same output as the file download (shared `pqm_workflow_json()`); the URL itself carries the token, hence the "keep it private" line under the field.
3. **CORS `Access-Control-Allow-Origin: *`** on this one route: older n8n versions fetch import URLs from the browser; newer ones fetch server-side. Header covers both, and the endpoint is token-gated anyway.
4. Copy feedback follows the existing test-ping pattern (`aria-live` line, shared success/error colors) instead of swapping the button label — consistent with the page's established feedback style.
5. Caveat (by design): the import URL only works when the WP site is reachable from the n8n instance — for local/intranet sites the file download remains the path, which is why Download stays the primary button.

---

# Active: Settings UX — make "AI Prompts & Context" self-explanatory

**Goal:** Remove the ambiguity between Blog Profile, Brand Voice & Tone, and Website Context on the settings page. Branch `feat/settings-context-ux` (off `feat/settings-wizard-polish`, which it builds on).

**Diagnosis (why the section confuses):**
1. **Blog Profile is an action disguised as a state.** Radio buttons imply a persistent mode, but picking one only pre-fills the two fields below — the stored `blog_profile` value is never sent to the AI. "Custom" does nothing at all, and after the user edits a field the radio keeps claiming e.g. "Technical Blog".
2. **Brand Voice hides what the AI actually receives.** Picking a preset hides the textarea, so the user never sees the instruction sent to the AI; any custom text is kept in the hidden textarea but silently ignored.
3. **No visible mental model.** Nothing explains that only Brand Voice + Website Context travel to the AI and that the profile is just a shortcut; field descriptions don't say what the AI does with the text or what good input looks like.

**Design principle:** what you see is what the AI gets; presets pre-fill, the user always owns the final text. Every preset stays fully editable and an explicit "Custom" path stays visible (see lessons.md 2026-07-23 — user decision).

## 1. Blog Profile → 6 niche presets + Custom, state that never lies

- [x] Expand `blog_profiles()` to 6 presets + Custom: Marketing, Technical / IT, Lifestyle, Food & Recipes, Travel, Health & Wellness — each with a `website_context` default and a voice-preset mapping
- [x] Selecting a preset pre-fills Website Context + Brand Voice; selecting Custom overwrites nothing — the always-visible textareas below are the custom prompt. No confirm dialogs: nothing persists until Save (user correction 2026-07-23, see lessons.md)
- [x] Manually editing Website Context auto-flips the profile selection to Custom, so the selection never claims a preset that no longer matches
- [x] Keep persisting `blog_profile`; description explains: presets are starting points, the two fields below are what the AI actually receives

## 2. Brand Voice & Tone → always-visible, WYSIWYG

- [x] Textarea always visible; clicking a preset fills it with the preset's full instruction text (no confirm — Save is the commit point)
- [x] Manual edit of the textarea auto-selects the "Custom" radio
- [x] Single source of truth `pqm_voice_presets()` in the main plugin file; admin UI, JS (`wp_localize_script`), and REST all consume it (fixes the known preset-string duplication)
- [x] Enrich preset strings into real one-line AI instructions (today the AI receives just e.g. "Professional & Authoritative")
- [x] REST legacy resolution: a selected preset always resolves to its instruction; `brand_voice` text is used only when the preset is Custom

## 3. Copy per docs/brand.md

- [x] One intro sentence under the section H2: these fields are sent with every AI command to tailor reviews/rewrites to this site
- [x] Website Context: concrete description (topic + audience + what readers expect, 2–3 sentences) and a `placeholder` example
- [x] Brand Voice description visible in all states

## 4. Verify

- [x] `php -l` on every touched PHP file (all clean); `node --check` on pqm-admin.js (clean)
- [ ] **USER:** manual pass in WP Admin: each profile preset fills both fields instantly (no dialogs), Custom overwrites nothing, voice preset click fills textarea, manual edits flip profile/voice to Custom, save round-trip, legacy settings still resolve a voice in the n8n payload
- [x] Update `tasks/enhancements.md` (preset-duplication item removed, groom note updated)
- [x] Commit after user approval — `0dd22af` (selective staging, 2026-07-23)

## Review

Implemented on `feat/settings-context-ux` (5 files: main plugin file, admin class, admin JS, admin CSS, enhancements backlog). Notes:

1. **User decision honored (lessons.md 2026-07-23):** profiles stay a persistent radio group with an explicit Custom option — presets pre-fill, Custom is the escape hatch; the earlier "one-shot buttons, drop Custom" idea was rejected.
2. **WYSIWYG voice:** the textarea now always shows the exact string sent in the n8n payload. On render, a non-custom preset displays its instruction (any stale hidden custom text from before is superseded on next save — it was already being ignored by REST).
3. **Behavior change (intended):** the AI now receives a full one-line instruction for presets (e.g. "Professional and authoritative: precise, confident wording; …") instead of the bare label "Professional & Authoritative".
4. **No confirm dialogs (user correction 2026-07-23):** preset fills apply instantly since nothing persists until Save; discarding is as simple as not saving. The unused `replaceContext`/`replaceVoice` i18n strings were removed.
5. `pqm_voice_presets()` lives in the main plugin file (loaded everywhere), so admin, JS localization, and REST share one map; `sanitize_settings()` derives valid preset keys from it.

---

# Active 2: Yoast scores on the Post Reviews screen (2026-07-23)

**Goal (user, 2026-07-23):** show Yoast scoring on the Post Reviews screen, side-by-side with the AI score. Automatic when Yoast is active — no settings toggle. Review agent prompt stays untouched (side-by-side decision, avoids anchoring).

**Approach:** a "Yoast SEO" column right next to the AI Score column, reading the **live** post meta (`_yoast_wpseo_linkdex`, `_yoast_wpseo_content_score`) — no DB schema change, no n8n change, works for never-reviewed posts too. Yoast's own quality buckets (>70 good, >40 ok) match `score_class()` thresholds exactly, so the existing badge colors are reused.

## 1. Post Reviews column

- [x] `render_posts()`: `$yoast_active = defined('WPSEO_VERSION')`; prime post-meta cache for the page's posts (one query, not N); `$colspan` 6 → 7 when active (no-posts row + detail rows)
- [x] Conditional `Yoast SEO` header + cell after the Score column; two compact badges (SEO / Readability) with `score_class()` colors, tooltip titles, em-dash placeholder when the post has no Yoast analysis
- [x] CSS: `.pqm-col-yoast` width + small `.pqm-yoast-badge` variant reusing `.pqm-score-*` colors

## 2. Docs

- [x] README "Requirements" Yoast line: mention scores appear on the Post Reviews screen next to the AI score

## 3. Verify

- [x] `php -l` on class-pqm-admin.php (clean)
- [ ] **USER:** manual pass — Yoast active: column shows badges / em-dash for unanalyzed posts; Yoast inactive: no column, layout intact
- [x] Commit after user approval — `cb6594e` (selective staging, 2026-07-23)

**Deferred to `tasks/enhancements.md`:** feed Yoast scores to the SEO + Audit agent prompts (user-approved 2026-07-23); snapshot Yoast scores into `result_data` at save-review time for history.

## Review

Implemented 2026-07-23 in the working tree on `feat/settings-context-ux` (no commit yet). Yoast reads are live post meta — no DB or n8n template change needed for this scope. Yoast's quality buckets (>70 / >40) match `score_class()` thresholds, so badge colors are consistent with the AI score column.

---

# Previous: Settings polish — test connection, setup wizard, asset & defaults hygiene

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
