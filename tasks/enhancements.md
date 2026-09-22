# Enhancements Backlog

_Improvements identified during development, deliberately kept out of the current MVP. Reviewed when planning the next iteration._

## Product

- **Test connection button** — a "Send test ping" next to the webhook URL that round-trips a lightweight command through n8n and reports success/failure inline. Deferred from the agnostic MVP (checklist step 3 currently relies on running a real review).
- **Cron command choice** — `cron_command` is locked to `review`; allow scheduling other commands (audit, seo) once their result storage is on par.
- **Chat-provider contract doc** — document the webhook in/out JSON contract so any chat tool (Slack, kChat, Teams bot) can drive the workflow without reading the n8n nodes.
- **Wizard-style workflow builder** — pick commands/agents and assemble the workflow graph from blocks. Explicitly out of MVP scope (KISS); revisit only on real demand.
- **WordPress.org distribution** — readme.txt, screenshots, i18n completeness, GPL asset review. Deferred; GitHub Releases is the current channel.

## Technical debt / code quality

- **Duplicate REST route with the standalone API plugin** — both plugins register `post-quality/v1/post-score`. Decide the coexistence story: detect the API plugin and skip registration, or namespace differently. Currently undefined behavior if both are active.
- **Move inline CSS/JS out of `class-pqm-admin.php`** — the settings page carries inline `style=""` attributes and an inline `<script>` block (voice presets, blog profiles, reset buttons). Move to `pqm-admin.css` / `pqm-admin.js` for CSP-friendliness and maintainability.
- **release-please CI is referenced but absent** — the `/release` skill and README mention a release-please GitHub Action, but the repo has no `.github/workflows/`. Add the action (release-please + zip artifact of `post-quality-manager/` incl. `templates/`).
- **Settings defaults hygiene** — several render paths still use `?? ''` fallbacks that duplicate `pqm_get_settings()` defaults; rely on the defaults array as the single source of truth.
- **i18n loading** — verify `load_plugin_textdomain()` is wired so the `post-quality-manager` text domain actually loads translations.

## n8n workflow

- **Version pinning & model choice** — Claude model IDs are hardcoded in Code nodes; consider centralizing them in one Set node (or injecting from plugin settings) so model upgrades are a one-line change.
- **Template regression check** — a small CI script that validates `templates/n8n-workflow.json` is valid JSON, contains the expected `__PQM_*__` placeholders, and has zero site-specific strings.
