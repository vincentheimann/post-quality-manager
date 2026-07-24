# Enhancements Backlog

_Improvements identified during development, deliberately kept out of the current MVP. Reviewed when planning the next iteration._

_Last groomed: 2026-07-23 — shipped on `feat/llm-options-pivot`: LLM options pivot (model registry, per-agent model/effort, payload-driven request params, n8n Workflow page, template v2 with version handshake). Closes the "Version pinning & model choice" item and the "template version marker" half of the stale-ping item. Earlier same day on `feat/settings-context-ux`: AI Prompts & Context UX rework (closes the brand-voice preset duplication item); the standalone Post Quality API plugin was retired (closes the duplicate `post-score` route item)._

## Product

- **Usage telemetry for the EBM signals** — `docs/user-paths.md` defines candidate signals per journey stage (time-to-first-review, % of reviewed posts edited within 14 days, cron vs. manual share, re-review rate, dashboard visits…) but nothing measures them; today's evidence is user #1's self-observation. Start locally and privacy-first: store counters/events in the plugin's own DB (no external calls), surface them on a small "Insights" box, so Current vs. Unrealized Value claims become evidence-based.
- **Yoast scores as SEO/Audit agent context** — user-approved 2026-07-23: append the numeric Yoast scores (already in the n8n payload) to the `Build SEO Body` and `Build Audit Body` user messages, framed as algorithmic signals, not ground truth. The Review agent stays Yoast-free by design (side-by-side decision, avoids score anchoring).
- **Yoast score snapshot per review** — store `{seo_score, readability_score}` from post meta into `result_data` at save-review time, so past reviews keep the Yoast standing they were compared against (the Post Reviews column shows live values only).
- **Full round-trip connection test** — the "Send test ping" button verifies WP → n8n (reachability + workflow active). It does not verify the reverse path (n8n → WP callback with the `X-PQM-Token`). A `ping` command that calls back to a dedicated endpoint would validate the whole loop, including the token.
- **Cron command choice** — `cron_command` is locked to `review`; allow scheduling other commands (audit, seo) once their result storage is on par.
- **Chat-provider contract doc** — document the webhook in/out JSON contract so any chat tool (Slack, kChat, Teams bot) can drive the workflow without reading the n8n nodes.
- **Wizard-style workflow builder** — pick commands/agents and assemble the workflow graph from blocks. Still deferred (KISS; config-injected export is the locked MVP approach). Note: a wizard-style *setup flow* (download → import → connect & test) now lives on the n8n Workflow page — this item is only about the graph builder.
- **Per-agent custom model IDs** — the Custom free-text model ID exists globally; per-agent overrides are limited to the curated registry. Add per-agent custom IDs only if a real need shows up (six extra text fields otherwise).
- **WordPress.org distribution** — readme.txt, screenshots, i18n completeness, GPL asset review. Deferred; GitHub Releases is the current channel.

## Technical debt / code quality

- **Inline styles in JS-generated markup** — `pqm-admin.js` still builds HTML strings with `style=""` attributes (rewrite confirmation card, action buttons, summary paragraph). The PHP templates are clean now; sweep the JS render helpers into `pqm-admin.css` classes for full CSP-friendliness.
- **Webhook token before first save** — `pqm_default_settings()` calls `wp_generate_uuid4()`, so until the settings are saved once, every request sees a *different* token and `check_webhook_token()` can never match. Harmless in practice (n8n is unusable before setup anyway), but generating and persisting the token on activation would be cleaner.
- **Ping only in the new template** — workflows imported before 2026-07-22 route `ping` to the Error Fallback: the test still passes (the webhook answers 200 on receipt), but n8n logs a junk execution and may POST an empty result to an empty callback URL. Re-downloading the workflow fixes it. The template-version marker requested here shipped 2026-07-23 (v2 handshake on the n8n Workflow page); note the version only reports after a real command, since ping short-circuits before the callback.
- **release-please CI is referenced but absent** — the `/release` skill and README mention a release-please GitHub Action, but the repo has no `.github/workflows/`. Add the action (release-please + zip artifact of `post-quality-manager/` incl. `templates/`).
- **i18n loading** — verify `load_plugin_textdomain()` is wired so the `post-quality-manager` text domain actually loads translations.

## n8n workflow

- **Template regression check** — a small CI script that validates `templates/n8n-workflow.json` is valid JSON, contains the expected `__PQM_*__` placeholders, has zero site-specific strings, and syntax-checks every Code node's `jsCode` (a working local prototype was used to verify the 2026-07-23 template v2 — worth committing alongside the release-please action).
