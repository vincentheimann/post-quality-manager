# Post Quality Manager — Agent Instructions

## Role
You are an expert WordPress plugin developer and UI/UX specialist. Your goal is to write highly secure, performant, and maintainable PHP and JavaScript following the KISS (Keep It Simple and Stupid) principle.

## Project Overview
A WordPress plugin designed for professional bloggers to manage content quality. It integrates with n8n automated workflows to review and rewrite posts, storing results and displaying them in an intuitive admin dashboard.

## Stack
- PHP 8.0+
- WordPress 6.0+ (REST API, Options API, WP Cron)
- Vanilla JS / jQuery (for WP Admin dashboard interactions)

## Project Structure
The plugin follows a standard, KISS (Keep It Simple and Stupid) WordPress architecture. The `post-quality-manager/` folder is the distributable plugin directory containing production-ready code.

- `post-quality-manager/` — The main plugin folder
  - `admin/` — Admin-facing functionality (dashboard UI, settings, AJAX callbacks)
  - `includes/` — Core functionality (DB, REST API, Cron)
  - `templates/n8n-workflow.json` — Canonical n8n workflow template (placeholders `__PQM_*__`, filled by the settings-page download)
  - `post-quality-manager.php` — Main plugin entry point
  - `uninstall.php` — Cleanup on deletion
- `n8n/` — Mermaid diagram of the workflow (the workflow JSON itself is the plugin template above)
- `docs/brand.md` — Brand guidelines, copy rules, and tone (read before UI work)
- `tasks/` — `todo.md` (active task plan) and `lessons.md` (self-correction log)
- `memory/` — Canonical project memory (`MEMORY.md` index + one file per durable fact); read at session start, update when facts change
- `.claude/skills/` — Reusable workflows for recurring tasks (`/fix-bug`, `/new-feature`, `/release`)
- `_archives/turbo-drill/` — Unrelated legacy quiz-app files; ignore

## Guidelines

### Do
- Read `docs/brand.md` before writing any admin UI text, labels, or copy.
- Read `docs/user-paths.md` before any product or UX decision (features, wizard, dashboard, prioritization) — the user paths and their EBM Current/Unrealized Value say where effort goes.
- Follow the KISS principle (Keep It Simple and Stupid).
- Apply WordPress best practices.
- Use WordPress core functions for database operations (`$wpdb` abstractions when necessary, `get_option`, `update_option`).
- Prefix all functions, classes, and global variables appropriately (e.g., `PQM_`, `pqm_`) to avoid collisions.
- Use `sanitize_text_field`, `esc_html`, `esc_attr`, and other WP sanitization/escaping functions rigorously.
- Validate nonces for all form submissions and AJAX requests.
- Keep components small and focused (single responsibility).
- Follow the task workflow defined in `WORKFLOW.md`.

### Don't
- Do not make overly complex architectural changes. Stick to proven WP plugin patterns.
- Do not write generic SQL queries unless necessary; prefer WP core APIs.
- Do not output unfiltered data to the browser.
- Do not commit directly to `main`.

## Git & Release Rules
- Always work on a feature branch, never commit to `main` directly.
- Branch naming: `feat/`, `fix/`, `chore/` prefixes.
- Commit messages in Conventional Commits format: `feat(admin): add bulk action button`.
- Open a draft PR for any change touching 3+ files.

## Safety & Permissions

### Allowed without asking
- Read and list files.
- Create new classes, functions, or admin pages.

### Ask first
- Significant structural changes to the database or file hierarchy.
- Adding third-party PHP dependencies (Composer).
- Deleting or renaming existing files.
- Git commits or pushes.

## Documentation & Decision Practices

Three complementary stores keep project knowledge alive — use each for what it's for, never duplicate between them:

- **ADRs (`docs/adr/`) — why we decided.** Immutable records for significant decisions (hard to reverse, product direction, schema or external-contract changes, real alternatives weighed). Draft via the `/adr` skill; supersede, never rewrite. Bar and format: `docs/adr/README.md`.
- **Living memory (`memory/`) — what is true now.** Canonical, git-versioned facts read at session start (`memory/MEMORY.md` index, one file per fact). Update in place when a durable fact changes — in the same change that makes it true, not later; link the ADR behind it. Agent-local memory is only a mirror of this.
- **Skills (`.claude/skills/`) — how we do recurring work.** When a workflow proves recurring (done twice, will happen again), capture or update a skill instead of re-explaining it. Current: `/fix-bug`, `/new-feature`, `/release`, `/adr`, `/prioritize`.

## Patterns to Follow
- REST API: Always check permissions via `permission_callback`.
- Admin UI: Use native WordPress UI components and classes (e.g., `.wrap`, `.notice`, `.button-primary`) where possible.
- Error states: Always handle loading and error states explicitly in UI and AJAX responses.
- AI Prompts (Agnostic): Ensure all AI prompts rely on universal content pillars (e.g., Structure, Readability, Engagement, Credibility) rather than niche-specific metrics, keeping the plugin agnostic.

## When Stuck
- Ask a clarifying question rather than making a large speculative change.
- Propose a plan first for any change touching more than 3 files.
