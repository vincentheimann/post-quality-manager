---
title: 'Author quota'
type: 'feature'
ticket: '4'
created: '2026-10-01'
status: done
baseline_revision: 'aeae1320a519e862181a94a16d86e3f2451f47f4'
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

**Problem:** Authors can trigger unlimited free-tier agents; multi-author sites have no cost control over API-spending actions (spec CAP-4, epic Done-when 3). Nothing stores per-user usage, and no counter is visible anywhere.

**Approach:** Track per-user monthly usage, refuse manual triggers at the existing `trigger_review_callback()` chokepoint once the Admin-configured quota (shipped default 20) is spent, and show the remaining count next to the action buttons. Cron never passes through the chokepoint, so it stays outside the quota by construction.

## Boundaries & Constraints

**Decisions (owner, 2026-10-01):** Storage is per-month user meta — one `pqm_monthly_usage` entry per user holding `['month' => 'YYYY-MM', 'count' => N]`, self-resetting by month comparison; no usage table. Quota-exempt = any user holding `pqm_run_paid_agents` (no new capability, `CAPS_VERSION` unchanged); a role editor granting an Author paid agents thereby lifts their quota. Quota value 0 = unlimited, the escape hatch to disable the quota without a second toggle.

**Always:** Quota is per user, per calendar month (site timezone), applies regardless of plan state, and counts only *successful* manual triggers — increment after the webhook POST returns 2xx, never on refusal or n8n failure. Every manual path (REST `POST /trigger-review`, AJAX `ajax_trigger_command`, the admin GET fallback) goes through `trigger_review_callback()`, so enforcement and increment live there only. Non-registry commands (`score`, `apply*`) consume quota too — each successful trigger spends API money. Quota refusal is its own error (`pqm_quota_exceeded`, 429) with the count and the reset month in gentle, actionable copy (`docs/brand.md`); it fires after the feature gate (role/plan refusals keep priority). Setting is Admin-only (lives on the Settings page, already behind `pqm_manage_settings`), sanitized via `sanitize_settings()`. Counter is visible to quota-subject users wherever trigger buttons render; JS updates it after a successful trigger. `PQM_` prefix, WP core APIs, escape everything.

**Never:** No change to cron (`PQM_Cron` posts directly — story 6), to the capability map or `CAPS_VERSION` unless an open-question answer requires it, to the n8n template or payload, to the `/save-review` token path, or to the gate's role/plan logic. No per-agent or per-role quota matrix, no usage history UI or reporting, no admin "reset counter" button, no new REST route.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Author under quota | `review`, 5 of 20 used | Webhook fires; count 6; counter shows 14 left | No error expected |
| Author at quota | `review`, 20 of 20 used | Refused before webhook, copy names count + reset | `pqm_quota_exceeded` 429 |
| Exempt user | Admin/Editor triggers any command | No check, no increment, no counter shown | No error expected |
| n8n unreachable | Author under quota, webhook fails | Count unchanged | existing `pqm_n8n_error` 502 |
| Month rollover | Stored month ≠ current month | Count reads as 0, old value overwritten on next increment | No error expected |
| Cron batch | Scheduled run on any site | No quota check, counters unchanged | Unchanged |
| Quota raised mid-month | Admin sets 20 → 50 | Author immediately has 30 more; existing count kept | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-features.php` -- the gate; add quota helpers beside `why_not()` :103 (`is_quota_subject()`, `quota_state(): ?array` → `['limit','used','remaining','resets']` or null for exempt/unlimited, `consume_quota()`). Registry :76, `is_pro()` :92 untouched.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `trigger_review_callback()` :349: insert quota refusal after the gate block :366-385, before `get_post()` :387; consume after `$status_code` 2xx :414; add `quota_remaining` to the response :416-420. The route's `permission_callback` `check_can_trigger()` :85 stays.
- `post-quality-manager/post-quality-manager.php` -- `pqm_default_settings()` :65: add `'author_quota' => 20`.
- `post-quality-manager/admin/class-pqm-admin.php` -- `sanitize_settings()` :209: absint the new key (pattern :225-227); Settings page: new small section after Automated Reviews :863 with the quota field; `wp_localize_script` :118: add `'quota' => PQM_Features::quota_state()`; Post Reviews :459-473: quota note near the filters for subject users. AJAX `ajax_trigger_command()` :1224 forwards `WP_Error` data already; pass `quota_remaining` through `wp_send_json_success` :1253.
- `post-quality-manager/admin/js/pqm-admin.js` -- `quotaNote()` helper beside `actionBtn()` :826 rendering from `pqmAdmin.quota`; append it where `.pqm-detail-actions` is built (:483-487, :509-511, :567-570) and in `empty()` :821-825; decrement `pqmAdmin.quota.remaining` and refresh notes on trigger success (`triggerCommand` success path); CSS class, no inline styles.
- `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-quota-note` style (muted, small).
- `post-quality-manager/uninstall.php` -- `delete_metadata( 'user', 0, 'pqm_monthly_usage', '', true )` next to the option deletes :17-21.
- Don't touch: `includes/class-pqm-cron.php` (posts via `pqm_n8n_payload()` :89, never the callback), `templates/n8n-workflow.json`, `check_webhook_token()` path.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-features.php` -- `pqm_monthly_usage` user-meta storage + `is_quota_subject()` (lacks `pqm_run_paid_agents`) / `quota_state()` (null when exempt or limit 0) / `consume_quota()` -- CAP-4 logic in the gate class.
- [x] `post-quality-manager/post-quality-manager.php` -- `author_quota` default 20 -- single source of truth.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- refusal + consume + `quota_remaining` in `trigger_review_callback()` -- one chokepoint, cron-free.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- settings field + sanitizer + localized `quota` + Post Reviews note + AJAX pass-through -- Admin config and visibility.
- [x] `post-quality-manager/admin/js/pqm-admin.js` + `admin/css/pqm-admin.css` -- `quotaNote()` next to action buttons, live decrement -- counter next to the buttons.
- [x] `post-quality-manager/uninstall.php` -- remove usage data -- clean uninstall.
- [x] CLI harness (WP stubs, as stories 1.1/1.2) -- exercise the I/O matrix rows -- no WP install here.
- [x] `memory/freemium-and-roles.md` -- record quota mechanics once built -- DoD.

**Acceptance Criteria:**
- Given an Author with quota remaining, when any Post Quality screen renders, then the remaining count is visible next to the action buttons and updates after a successful trigger.
- Given a user at their limit, when they trigger via REST, AJAX or the GET fallback, then all three refuse with the same count-bearing copy and no webhook call.
- Given an Admin changes the quota setting, when it saves, then the new limit applies immediately with no usage reset.

## Implementation Notes

Built 2026-10-01 against baseline `aeae132`.

- `PQM_Features` grew a "Author quota (spec CAP-4)" section after `can()`: `USAGE_META_KEY` const (`pqm_monthly_usage`), `is_quota_subject()` (= `! current_user_can('pqm_run_paid_agents')`), `quota_state(): ?array` (`['limit','used','remaining','resets']`; null when exempt or `author_quota < 1`), `consume_quota()` (no-op when `quota_state()` is null, else writes `['month' => wp_date('Y-m'), 'count' => used + 1]`), plus private `usage_this_month()` (stale month reads as 0 — the self-reset), `current_month()` (`wp_date('Y-m')`, site timezone) and `quota_reset_date()` (first day of next month via `DateTimeImmutable` + `wp_timezone()`, formatted with the site `date_format`).
- `trigger_review_callback()`: quota refusal sits between the feature-gate block and `get_post()` — role/plan refusals keep priority, refusal fires before any webhook. `consume_quota()` + a fresh `quota_state()` run only inside the `2xx` branch, so an unreachable n8n (`pqm_n8n_error` path returns earlier) *and* a non-2xx n8n answer leave the count untouched. Response gained `quota_remaining` (null for exempt/unlimited; pre-trigger value when n8n answered non-2xx).
- Refusal/counter copy exactly per Design Notes; refusal is `pqm_quota_exceeded` with `['status' => 429]`. The AJAX handler already forwards `WP_Error` message + status, so the 429 copy reaches the toast unchanged; its success path now passes `quota_remaining` from the REST response data through `wp_send_json_success`.
- Admin: `author_quota` absint-sanitized next to the cron keys; "Monthly Quota" settings section (one `small-text` number field, `min="0"`, description names the 0 = unlimited escape hatch and that scheduled reviews don't count) after Automated Reviews; `wp_localize_script` ships `'quota' => PQM_Features::quota_state()` and an i18n `quotaNote` template; Post Reviews renders a `.pqm-quota-note` span inside `.pqm-filters` for quota-subject users.
- JS: `quotaNote()` / `quotaNoteText()` / `refreshQuotaNotes()` next to `actionBtn()`, rendering from `pqmAdmin.quota` with the localized `%1$d of %2$d` template; note appended in `renderReview`, `renderAgent`, `renderRewrite` `.pqm-detail-actions` and in `empty()`; `executeCommand()` success path sets `pqmAdmin.quota.remaining` from `resp.data.quota_remaining` (number-typed check) and re-texts every `.pqm-quota-note`. CSS: muted 12px `.pqm-quota-note`, right-aligned in the filter bar, inline in detail actions.
- `uninstall.php`: `delete_metadata('user', 0, 'pqm_monthly_usage', '', true)` beside the option deletes.
- Untouched, per Never: `class-pqm-cron.php`, capability map / `CAPS_VERSION`, n8n template + payload, `/save-review` token path, gate role/plan logic; no new REST route.
- `memory/freemium-and-roles.md`: quota-mechanics bullet added; the "story 4 schema unknown" line marked resolved (user meta).
- Review fixes 2026-10-01: (1) a successful GET-fallback trigger now refreshes the browser-side snapshot via `wp_add_inline_script('pqm-admin', 'pqmAdmin.quota = …', 'after')` — the localized value is captured on `admin_enqueue_scripts`, before the trigger consumes an action, and the footer-printed script still picks the inline data up (`quotaNote()` reads `pqmAdmin.quota` lazily at render time, after the `after`-snippet has run); (2) every JS `quotaNote()` call is now guarded on its adjacent `actionBtn()` returning markup, so hidden commands (paid tabs for quota-subject users) no longer show a floating counter with no button. Lint re-run clean after both.

## Plan Change Log

## Review Triage Log

### 2026-10-01 — quick lens, pass 1 (verdicts: 0 high, 1 medium, 2 low, 0 false, 0 maybe-false)

- **medium / patch** — `admin/class-pqm-admin.php` GET fallback :379-417 vs localize :135: `pqmAdmin.quota` is computed on `admin_enqueue_scripts`, which fires before the page callback; a successful GET-fallback trigger consumes quota during `render_posts()`, so the PHP filter-bar note shows N−1 while every JS-rendered note shows the stale N. Verified against WP admin hook order (enqueue → page callback → footer script print). Patch sent: refresh the localized value with `wp_add_inline_script` after a successful fallback trigger — the script prints in the footer, so the inline data still lands.
- **low / patch** — `admin/js/pqm-admin.js` `renderAgent` / `renderRewrite` / `empty`: `quotaNote()` is appended unconditionally, but for quota-subject users the paid commands are in `hiddenCommands`, so `actionBtn()` returns `''` and four tabs show a floating counter with no button — contradicting the hidden-command semantics and the plan's "counter next to the action buttons". Patch sent: append the note only when the adjacent button rendered.
- **low / rejected** — `includes/class-pqm-features.php` quota check + `consume_quota()` are a non-atomic read-modify-write; two concurrent triggers at 19/20 can both pass and lose one count. Real, but this exact trade-off was presented at the plan checkpoint with the storage options and the owner chose user meta knowing it ("harmless at human scale"); enforcement is cost control, not billing. Fix (atomic increment/locking) exceeds a direct correction, and everyday single-user clicking cannot hit it — rejected per the low-rejection rule.

## Design Notes

Quota check sits *after* the feature gate so hidden/locked semantics stay untouched; a quota refusal is a third reason with its own error code, letting JS and future callers tell "upgrade" from "wait until next month" apart. The increment-after-2xx rule means a user can never lose quota to a misconfigured webhook. Counter copy (brand: concise, encouraging): `%1$d of %2$d AI actions left this month`; refusal copy: `Monthly limit reached (%1$d of %1$d AI actions used). Your quota resets on %2$s — or ask an administrator to raise it.`

## Verification

**Commands:**
- `php -l` on the four touched PHP files + `php -l post-quality-manager/uninstall.php` -- expected: no syntax errors
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: no syntax errors
- CLI harness with WP stubs -- expected: every I/O matrix row holds; exempt users bypass; month rollover resets the read.

**Results (2026-10-01):**
- `php -l`: clean on `post-quality-manager.php`, `uninstall.php`, `class-pqm-features.php`, `class-pqm-rest-api.php`, `class-pqm-admin.php`.
- `node --check admin/js/pqm-admin.js`: clean.
- CLI harness (scratchpad, WP stubs): 32/32 checks pass — all seven matrix rows, plus: n8n non-2xx answer spends nothing, limit 0 = unlimited, gate refusal beats quota refusal for a paid command at the limit, non-registry `score` consumes and is refused at the limit, refusal copy carries "(20 of 20" + the computed reset date. (Harness `wp_date` stub formats in UTC, so its reset-date *rendering* is off by one day vs a real Zurich site — a stub artifact, not plugin behavior.)

**Manual checks (if no CLI):**
- Real site: Author at limit refused with count in the toast; counter visible and decrementing; Admin raising the quota unblocks; cron batch leaves counters unchanged; Editor sees no counter.
