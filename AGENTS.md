# Post Quality Manager — Agent Instructions

<!-- bmad:context -->
<!-- Verified 2026-10-12 against 7ae5dca. Managed by bmad-project-context; edits inside this block are replaced on refresh. Keep anything you want preserved outside the markers. -->

## Post Quality Manager

WordPress plugin for professional bloggers in any niche: it triggers Claude agents (review, rewrite, takeaways, sources, SEO, audit) through an n8n workflow and stores the results in a WP Admin dashboard. PHP 8.0+ / WordPress 6.6+ (REST API, Options API, WP-Cron); the Dashboard and Post Reviews are React app pages on `@wordpress/components`, Settings and n8n Workflow use vanilla JS (`admin/js/pqm-admin.js`, no jQuery), and the n8n Workflow page also mounts one React island, the setup dialog (`src/setup/`). Work is delivered with the BMad Method (ADR 0004): tickets and plans live in `_bmad-output/`, the skills in `.claude/skills/bmad-*`.

## Policy

- Never commit to `main`; branches `feat/`, `fix/`, `chore/`; Conventional Commits (`feat(admin): add bulk action button`); draft PR for anything touching 3+ files. Sole exception (owner, 2026-10-01): right after a story PR merges, the one-line `chore(tickets)` status flip of that story's plan may be committed directly to main — nothing else ever is.
- Never commit, push, delete or rename files, change the DB schema or file hierarchy, or add a Composer dependency without asking first. Reading, listing, and creating new classes, functions or admin pages need no permission.
- Plans before code: anything beyond a small change goes through `bmad-build`'s plan checkpoint (`_bmad/custom/bmad-build.toml` routes >100 lines, >3 files, or any schema / REST / template change to the "full" route). If something goes sideways mid-build, stop and re-plan; don't keep pushing.
- Bug reports: find the root cause and fix it without hand-holding; no symptom patches, no temporary fixes.
- AI prompts in the n8n workflow stay niche-agnostic — universal pillars Structure, Readability, Engagement, Credibility; niche tailoring comes from user settings (Blog Profile, brand voice), never from code (`memory/lessons.md` 2026-03-27).
- Read `docs/brand.md` before writing any admin UI text, label or copy; read `docs/user-paths.md` before any product or UX decision.
- Versions belong to release-please: the `Version:` header, `PQM_VERSION` and `package.json` carry its markers and are never bumped by hand; merging its release PR tags and publishes the CI-built zip, and every release stays a pre-release until the public launch (`"prerelease": true` in `release-please-config.json`).

## Where things are

- `post-quality-manager/` — the distributable plugin: `admin/class-pqm-admin.php` (admin pages, Settings API, the two AJAX handlers), `admin/build/` (compiled React app, git-ignored, built by CI), `admin/js/pqm-admin.js` + `admin/css/`, `includes/` (DB, REST API `post-quality/v1`, cron), `templates/n8n-workflow.json` (canonical workflow template, `__PQM_*__` placeholders filled by `PQM_Admin::download_workflow()`), `post-quality-manager.php` (initialises the Freemius SDK, `pqm_fs()`, before anything else), `vendor/freemius/` (the SDK at a tagged release, committed unmodified; plugin deletion runs `PQM_Activator::uninstall()` through its `after_uninstall` hook, so there is no `uninstall.php`). React sources live in `src/` (`dashboard/`, `post-reviews/`, `setup/`, shared `kit/`). The workflow calls the Claude API directly from n8n Code/HTTP nodes.
- `n8n/workflow-diagram.md` — Mermaid overview of webhook → n8n → Claude → WordPress.
- `_bmad-output/initiative-post-quality-manager/` — the product initiative: requirements (UV1–UV7 from `docs/user-paths.md`, O1–O7 owner requests), epic envelopes in `tickets.toml`; `_bmad-output/backlog/` — standalone tickets. Git is the ticket store; `uv run _bmad/method/scripts/tickets.py status` / `next` read them.
- `_bmad/` — BMad runtime and config; team overrides in `_bmad/custom/*.toml` (committed), personal ones in `*.user.toml` (ignored).
- `docs/adr/` — why we decided (immutable, supersede never rewrite; `/adr`); `docs/user-paths.md` — the EBM prioritization tool (`/prioritize`); `docs/brand.md` — copy rules.
- `memory/` — what is true now: `MEMORY.md` index, one file per durable fact, `lessons.md` the self-correction log whose rules override your defaults. Read at session start; update in the same change that makes a fact true.
- `.claude/skills/` — BMad skills (`bmad`, `bmad-build`, `bmad-spec`, `bmad-ticket`, `bmad-review`, `bmad-code-review`, `bmad-retrospective`, `bmad-project-context`, `bmad-customize`, `bmad-walkthrough`, `bmad-ux`, `bmad-correct-course`; `bmod-*` are module records) plus project skills `/adr`, `/prioritize`, `/release`.
- `.github/workflows/ci.yml` (JS lint, unit tests and build; PHP 8.0 and 8.3 lint plus harness sweep; Plugin Check on the built artifact) and `release.yml` (release-please → CI-built zip → GitHub Release); `docs/release-notes/unreleased.md` holds the lines `/release` moves into `<version>.md` at launch.

## Running and verifying

- `php -l <file>` on every touched PHP file (PHP 8.3 CLI installed via winget; use the full path in a stale shell); `node --check` on every touched JS file.
- CLI regression harnesses live in `tests/harness/` (plain PHP + WP stubs, no WP install): run them all (`for f in tests/harness/*.php; do php "$f" || break; done`) after touching gate, quota, or settings code, and add or extend one whenever a story's plan carries an I/O & Edge-Case Matrix. Real WP Admin behavior is still verified manually (loading and error states included) and with REST calls (200, correct JSON, `permission_callback`).
- JS unit tests live in `tests/js/` (Jest + jsdom through `@wordpress/scripts`, no new dependency): `npm run test:unit` after touching `post-quality-manager/admin/js/`, and add or extend a test when a plan's matrix has rows only a browser can show.
- When a REST route or the webhook payload changes, check `post-quality-manager/templates/n8n-workflow.json` for a matching change, and vice versa. Never put site-specific values in the template.
- BMad scripts run with `uv run` (`uv` 0.11+ installed); never run a workflow source file directly — `bmad-build`'s `SKILL.md` renders it first.
- Definition of Done: KISS; `php -l` / `node --check` clean; every harness in `tests/harness/` and `npm run test:unit` green; no WP debug errors or warnings; REST endpoints answer correctly with permissions; UI loading/error states handled; a change existing sites meet without being told (a new default, a new permission, a removed screen, a workflow re-import) adds one line to `docs/release-notes/unreleased.md` in the same PR; durable facts updated in `memory/`, significant decisions recorded via `/adr`, `docs/user-paths.md` adjusted when Unrealized Value became Current Value. Ask "would a staff engineer approve this?" before presenting.
- Report a PR's CI state from `gh pr checks <n> --repo vincentheimann/post-quality-manager` (the check runs the owner sees), never from workflow-run conclusions; to make a CI finding non-blocking, put `continue-on-error` on the reporting step, not the job (`memory/lessons.md` 2026-10-08).

## Conventions that differ from defaults

- Prefix every function, class and global `PQM_` / `pqm_`; use WP core APIs (`get_option`, `$wpdb` abstractions) over raw SQL; sanitize (`sanitize_text_field`) and escape (`esc_html`, `esc_attr`) everything; nonces on every form and AJAX request; `permission_callback` on every REST route.
- Native WP admin components and classes (`.wrap`, `.notice`, `.button-primary`); PHP templates and JS-built markup carry no inline styles — add a class in the page's stylesheet instead. The one exception is a value computed at runtime (a tooltip position, a `width: n%` score bar), which stays inline.
- The Settings page is one Settings-API form, Roles tab included, whose `sanitize_settings()` rebuilds the whole array; the n8n Workflow page is form-free — its page-local save is a single-key merge-save (`save_webhook_url()` behind `POST /setup/connect`), never a second form, and REST writes bypass `sanitize_settings()`, so they sanitize in place.
- Save is the commit point: never gate an in-form, not-yet-persisted change behind `confirm()`. A preset is a read-only choice resolved from code; Custom is the one editable path and stays visible (`memory/lessons.md` 2026-10-07).
- Pro code carries Freemius build markers (ADR 0014): paid PHP lives in `*__premium_only` functions or in files on the `@fs_premium_only` header list, and every caller sits in `if ( pqm_fs()->is__premium_only() ) { … }` with nothing else in the condition, no `else` and no assignment from a premium call outside it; `bin/build-free.php` and `tests/harness/free-build-harness.php` prove the Free build. After editing `templates/n8n-workflow.json`, run `php bin/derive-free-template.php`.
- Knowledge triad, no duplication: ADR = why, `memory/` = what is true now, skill = recurring how. When a decision reveals a missing practice, propose adopting it instead of designing around its absence (`memory/lessons.md` 2026-07-24).
- After any user correction, append the pattern to `memory/lessons.md` (format documented in the file) and, when it is an agent mistake worth a standing rule, record it with `bmad-project-context record`.

## Known pitfalls

- Disabled form controls don't POST: a sanitizer that reads a disabled knob must fall back to the stored value, not the default, or saving silently resets it (locked temperature/effort knobs, 2026-07-24).
- Workflows imported before 2026-07-22 route `ping` to the Error Fallback; the template-version handshake on the n8n Workflow page is how users learn to re-download.
- The former companion "Post Quality API" plugin was retired 2026-07-23; this plugin's `post-quality/v1/post-score` endpoint is canonical. Do not re-create the standalone plugin.
- Prefer a clarifying question to a large speculative change; use subagents for research and exploration to keep the main context clean.
- Before proposing a change to an external system's settings (GitHub repo or Actions, n8n, hosting), read the current value through its API and confirm the mechanism in the tool's docs or source; never ask to apply a combination the documentation does not state as valid (`memory/lessons.md` 2026-10-08).

<!-- /bmad:context -->
