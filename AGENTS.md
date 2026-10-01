# Post Quality Manager — Agent Instructions

## Orientation

WordPress plugin for professional bloggers in any niche: it triggers Claude agents (review, rewrite, takeaways, sources, SEO, audit) through an n8n workflow and stores the results in a WP Admin dashboard. PHP 8.0+ / WordPress 6.0+ (REST API, Options API, WP-Cron), vanilla JS + jQuery in the admin. Work is delivered with the BMad Method (ADR 0004): tickets and plans live in `_bmad-output/`, the skills in `.claude/skills/bmad-*`. You are an expert WordPress plugin developer and UI/UX specialist writing secure, performant, maintainable code under KISS.

## Policy

- Never commit to `main`; branches `feat/`, `fix/`, `chore/`; Conventional Commits (`feat(admin): add bulk action button`); draft PR for anything touching 3+ files.
- Never commit, push, delete or rename files, change the DB schema or file hierarchy, or add a Composer dependency without asking first. Reading, listing, and creating new classes, functions or admin pages need no permission.
- Plans before code: anything beyond a small change goes through `bmad-build`'s plan checkpoint (`_bmad/custom/bmad-build.toml` routes >100 lines, >3 files, or any schema / REST / template change to the "full" route). If something goes sideways mid-build, stop and re-plan; don't keep pushing.
- Bug reports: find the root cause and fix it without hand-holding; no symptom patches, no temporary fixes.
- AI prompts in the n8n workflow stay niche-agnostic — universal pillars Structure, Readability, Engagement, Credibility; niche tailoring comes from user settings (Blog Profile, brand voice), never from code (`memory/lessons.md` 2026-03-27).
- Read `docs/brand.md` before writing any admin UI text, label or copy; read `docs/user-paths.md` before any product or UX decision.

## Where things are

- `post-quality-manager/` — the distributable plugin: `admin/` (dashboard, settings, AJAX), `includes/` (DB, REST API, cron), `templates/n8n-workflow.json` (canonical workflow template, `__PQM_*__` placeholders filled by `PQM_Admin::download_workflow()`), `post-quality-manager.php`, `uninstall.php`.
- `n8n/workflow-diagram.md` — Mermaid overview of webhook → n8n → Claude → WordPress.
- `_bmad-output/initiative-post-quality-manager/` — the product initiative: requirements (UV1–UV7 from `docs/user-paths.md`, O1–O4 owner requests), epic envelopes in `tickets.toml`; `_bmad-output/backlog/` — standalone tickets. `uv run _bmad/method/scripts/tickets.py status` / `next` read them.
- `_bmad/` — BMad runtime and config; team overrides in `_bmad/custom/*.toml` (committed), personal ones in `*.user.toml` (ignored).
- `docs/adr/` — why we decided (immutable, supersede never rewrite; `/adr`); `docs/user-paths.md` — the EBM prioritization tool (`/prioritize`); `docs/brand.md` — copy rules.
- `memory/` — what is true now: `MEMORY.md` index, one file per durable fact, `lessons.md` the self-correction log whose rules override your defaults. Read at session start; update in the same change that makes a fact true.
- `.claude/skills/` — BMad skills (`bmad`, `bmad-build`, `bmad-spec`, `bmad-ticket`, `bmad-review`, `bmad-code-review`, `bmad-retrospective`, `bmad-project-context`, `bmad-customize`) plus project skills `/adr`, `/prioritize`, `/release`.
- `_archives/turbo-drill/` — unrelated finished quiz-app project. Ignore entirely.

## Running and verifying

- `php -l <file>` on every touched PHP file (PHP 8.3 CLI installed via winget; use the full path in a stale shell); `node --check` on every touched JS file. No automated test suite exists: verify manually in WP Admin (loading and error states included) and with REST calls (200, correct JSON, `permission_callback`).
- When a REST route or the webhook payload changes, check `post-quality-manager/templates/n8n-workflow.json` for a matching change, and vice versa. Never put site-specific values in the template.
- BMad scripts run with `uv run` (`uv` 0.11+ installed); never run a workflow source file directly — `bmad-build`'s `SKILL.md` renders it first.
- Definition of Done: KISS; `php -l` / `node --check` clean; no WP debug errors or warnings; REST endpoints answer correctly with permissions; UI loading/error states handled; durable facts updated in `memory/`, significant decisions recorded via `/adr`, `docs/user-paths.md` adjusted when Unrealized Value became Current Value. Ask "would a staff engineer approve this?" before presenting.

## Conventions that differ from defaults

- Prefix every function, class and global `PQM_` / `pqm_`; use WP core APIs (`get_option`, `$wpdb` abstractions) over raw SQL; sanitize (`sanitize_text_field`) and escape (`esc_html`, `esc_attr`) everything; nonces on every form and AJAX request; `permission_callback` on every REST route.
- Native WP admin components and classes (`.wrap`, `.notice`, `.button-primary`); PHP templates carry no inline styles, JS render helpers are being migrated to classes (backlog story 5).
- The Settings page is one Settings-API form whose `sanitize_settings()` rebuilds the whole array; the n8n Workflow page is form-free — page-local saves go through dedicated AJAX merge-saves, never a second form.
- Save is the commit point: never gate an in-form, not-yet-persisted change behind `confirm()`; every preset must stay editable and an explicit "Custom" path visible (`memory/lessons.md` 2026-07-23).
- Knowledge triad, no duplication: ADR = why, `memory/` = what is true now, skill = recurring how. When a decision reveals a missing practice, propose adopting it instead of designing around its absence (`memory/lessons.md` 2026-07-24).
- After any user correction, append the pattern to `memory/lessons.md` (format documented in the file) and, when it is an agent mistake worth a standing rule, record it with `bmad-project-context record`.

## Known pitfalls

- Disabled form controls don't POST: a sanitizer that reads a disabled knob must fall back to the stored value, not the default, or saving silently resets it (locked temperature/effort knobs, 2026-07-24).
- Workflows imported before 2026-07-22 route `ping` to the Error Fallback; the template-version handshake on the n8n Workflow page is how users learn to re-download.
- The former companion "Post Quality API" plugin was retired 2026-07-23; this plugin's `post-quality/v1/post-score` endpoint is canonical. Do not re-create the standalone plugin.
- Prefer a clarifying question to a large speculative change; use subagents for research and exploration to keep the main context clean.
