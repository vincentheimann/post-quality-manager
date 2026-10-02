# Brownfield cleanup inventory — rides the UI rebuild (CAP-6)

_Line-item inventory the kernel cites. Sources: ticket-7 UX review code analysis (2026-10-02) and the epic-freemium-and-roles retrospective (A4 + lens findings). Every item is deleted/moved by the rebuild or explicitly re-deferred with a reason recorded in the build plan._

## Dead code to delete

- Legacy GET trigger path in `render_posts()` (`pqm_action=trigger` + success/error notices) — duplicates the AJAX flow; no current UI emits it.
- JS `apply_` command branch — nothing renders apply buttons.
- Unused CSS blocks: `.pqm-status-bar`, `.pqm-content-list`, `.pqm-rewrite-preview`.
- `review_status` filter param read by the posts query but emitted by no UI (either wire it to the new status chips or delete).
- JS `esc()` helper (drops numeric 0) — replaced by React's escaping.

## Duplicated logic that collapses into one render source (CAP-3)

- Quota note: PHP `render_posts()` header span + JS `quotaNote()`/`quotaNoteText()`; the PHP side lacks the "only next to a rendered button" guard the JS has (retro e9) — the single component gets the guard.
- Hidden-command rule: server-side `$can_review` + client-side `pqmAdmin.hiddenCommands` in `actionBtn()`.
- Effective-model resolution: `pqm_effective_model()` (PHP), inline ternary in `enqueue_assets()` (admin.php:139-141, hardcoded fallback), JS `effectiveDefault()`.
- Status/date formatting drift: PHP `format_status()`/`date_i18n` vs JS `statusLabel` (maps `published`, misses `ship`/`processing`) and ad-hoc date splitting.
- Upgrade copy in 4 wordings (admin.php:156, :899, :1007, rest-api.php:380) — one source of truth for gate copy strings shipped to the components.

## Structure

- `class-pqm-admin.php` (1,504 lines) sheds Dashboard + Post Reviews rendering and the `wp_localize_script` JS bridge for them; Settings/Workflow/AJAX remain. `render_settings()` is untouched by this epic.
- Three ad-hoc polling loops → one shared 3s polling hook with an `aria-live` status region (replaces the `indexOf('Processing')` text-match resume).
- Inline styles throughout JS-built markup → component styling; closes backlog story 5.

## Explicitly not this epic's cleanup (tracked elsewhere)

- Retro action items A5 (copy/i18n micro-fixes on the REST layer), A6 (harness coverage pack), A8 (command whitelist), A11 (three bug tickets) — REST/sanitizer-side; they proceed independently of the rebuild and must not be absorbed into it silently.
