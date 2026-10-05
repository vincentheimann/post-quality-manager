---
title: 'Status and copy component kit'
type: 'feature'
ticket: '2'
created: '2026-10-02'
status: 'done'
baseline_revision: '361cafe2374116622151d63ed4a8b1e83fad5de9'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick', 'quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Status, pills, quota note and gate/command copy exist in drifting copies (PHP emoji labels, hardcoded-English JS, 5.1's provisional React table), and the React page has no access to quota or gating data at all — the kit both pages will consume does not exist yet.

**Approach:** Build the shared kit in `src/kit/`: StatusBadge and ProgressPills per the mockup's visual rules, a QuotaNote that renders only for users who can trigger, and one module for command labels + gate copy — all strings through `@wordpress/i18n`. Feed it by extending the 5.1 `GET /posts` response with a `viewer` block (can-trigger, quota state, gated/hidden commands), per the spec's prefer-extending-REST rule. The Post Reviews table consumes the kit immediately.

## Boundaries & Constraints

**Always:** Status = icon + label + color per DESIGN.md D5 — WP palette, Processing neutral gray `#646970`, yellow only as a filled badge behind its own dark label, never color alone, no emoji; viewer gating mirrors `PQM_Features` exactly (role → hidden, plan → locked; quota null for exempt users); quota note never renders for a user who cannot trigger; every string through `__()`/`sprintf` with domain `post-quality-manager`, copy per `docs/brand.md`; gate/quota/webhook *semantics* unchanged — the viewer block is read-only reporting of existing state; built assets stay out of git; KISS.

**Never:** No filters, sorting, bulk, detail panel or trigger buttons (stories 5.3–5.5) — the kit ships locked-button *copy*, not the buttons; no Dashboard work (5.6); no change to PHP Settings pages or REST refusal copy — those wordings stay until story 5.7 records their fate (Settings is out of the rebuild entirely); no deletion of legacy PHP/JS; no n8n template change.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Viewer: free-tier author | `GET /posts`, user with only `pqm_run_free_agents`, quota 20, 6 used | `viewer: {can_trigger: true, quota: {limit: 20, used: 6, remaining: 14, resets}, gated_commands: [], hidden_commands: [rewrite, sources, seo, audit]}` | No error |
| Viewer: admin on free plan | User with all caps, free plan | `can_trigger: true`, `quota: null` (exempt), `gated_commands: [rewrite, sources, seo, audit]`, `hidden_commands: []` | No error |
| Viewer: cannot trigger | User with `pqm_view_reviews` only | `can_trigger: false`, `quota: null`, all six commands hidden | No error; UI renders no quota note |
| Quota note, eligible | `can_trigger: true`, quota non-null | "14 of 20 AI actions left this month" right-aligned above the table | No error |
| Quota note, exempt or ineligible | quota null OR `can_trigger: false` | Component renders nothing at all | No error |
| Unknown status value | item `latest_status: "weird"` | Neutral badge showing the raw value — never blank, never color-only | No error |
| Pills, partial progress | commands `[review, seo]` | All six pills in canonical order: review and seo in the ran variant (✓), the other four neutral un-run | No error |

</frozen-after-approval>

## Code Map

- `src/post-reviews-table.js` — 5.1 table; its local `STATUS_MAP`/`STATUS_NONE` :18-45, `COMMAND_ORDER`/`COMMAND_LABELS` :48-56, `StatusBadge`/`ScoreBadge`/`ProgressChips` move to the kit; fetch keeps `per_page=100`.
- `post-quality-manager/includes/class-pqm-rest-api.php` — `list_posts_callback()` :520-571 gains the `viewer` block; mirror `check_can_trigger` :94-104 (any registry cap).
- `post-quality-manager/includes/class-pqm-features.php` — reuse as-is: `quota_state()` :163-181 (null when exempt or limit<1), `gated_commands()` :237-239, `hidden_commands()` :245-247; never reimplement the partition.
- `post-quality-manager/admin/class-pqm-admin.php` — `enqueue_posts_app()` :190-215: also enqueue the build's extracted CSS when present; kit copy reference points: `i18n.lockedTitle` :174, quota-note string :176 ("%1$d of %2$d AI actions left this month").
- `_bmad-output/.../ux-dashboard-post-reviews/mockup.html` — badge anatomy :46-52 (inline-flex pill, 12px, variants b-good/b-warn/b-crit/b-neutral), pills :62-63 + :251 (un-run neutral, ran blue ✓, lowercase labels), quota placements :69/:225, locked copy :281-284; DESIGN.md :40,:46 — palette + yellow dark-label rule, Processing gray.
- `tests/harness/rest-posts-harness.php` — extend: it already stubs caps/registry; `caps-harness.php` :179-207 asserts the hidden/gated partitions the viewer must match.
- Legacy copies being superseded (left in place): PHP `format_status()` emoji labels `class-pqm-admin.php` :1557-1568, JS `statusLabel` drift `pqm-admin.js` :894-896, quota note PHP :647-655 / JS :850-868.

## Tasks & Acceptance

**Execution:**
- [x] `src/kit/copy.js` -- canonical `COMMAND_ORDER`, command labels (Review, Takeaways, Sources, SEO, Audit, Rewrite; `commandLabel()` helper with the SEO casing), and gate copy: locked-button title "Available with Pro — Review and Takeaways stay free." (mockup :281) + visually-hidden suffix -- the one JS source for stories 5.3–5.6.
- [x] `src/kit/status-badge.js` + `src/kit/progress-pills.js` + `src/kit/style.css` -- badge pill per mockup anatomy with Dashicons (ship `yes-alt` good, needs_revision `flag` warn with dark label, major_revision `warning` crit, processing `update` neutral gray, none/unknown `minus` neutral, unknown shows raw value); pills render all six commands, ran = ✓ blue variant, un-run = neutral; CSS imported so `wp-scripts` extracts it.
- [x] `src/kit/quota-note.js` -- renders the quota string via `sprintf` only when `viewer.can_trigger` and `viewer.quota` is non-null; otherwise returns null.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- add `viewer` to the `/posts` response: `can_trigger` (any registry cap), `quota` = `PQM_Features::quota_state()`, `gated_commands`/`hidden_commands` from `PQM_Features` -- read-only reporting, no semantics change.
- [x] `src/post-reviews-table.js` -- consume the kit (delete local status/commands maps), render QuotaNote right-aligned above the table (the mockup's filter-bar slot, which story 5.3 fills).
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- enqueue `build/style-index.css` (filemtime-free: version from the asset manifest) alongside the script when the file exists.
- [x] `tests/harness/rest-posts-harness.php` -- extend for the viewer matrix rows (free-tier author, exempt admin, cannot-trigger viewer) asserting the partition matches `PQM_Features` outputs.

**Acceptance Criteria:**
- Given the real site, when Post Reviews renders for a user who can trigger, then badges and pills follow the mockup's visual rules and the quota note shows the live numbers; for a viewer-only user the note is absent from the DOM.
- Given `npm run build`, then the bundle externalizes all `@wordpress/*` deps and emits the extracted kit CSS; every kit string flows through `__()`.
- Given the harness sweep, then the extended REST harness passes, and the five pre-existing routes plus the 5.1 item shape are byte-identical (viewer is additive).

## Implementation Notes

_2026-10-02, built as planned. Details worth recording:_

- Kit files: `src/kit/copy.js` (COMMAND_ORDER, `commandLabel()`, `LOCKED_TITLE`, `LOCKED_SUFFIX`), `status-badge.js`, `progress-pills.js`, `quota-note.js`, `style.css` — plus `score-badge.js`, which the Code Map lists as moving to the kit; it keeps the legacy `pqm-score-badge` class names so the shared `pqm-admin.css` rules still style it (no visual change, no kit CSS for it).
- Kit CSS uses new class names (`pqm-status-badge`, `pqm-progress-pill[s]`, `pqm-table-topbar`) so the legacy `.pqm-pill` rules in `pqm-admin.css` (still enqueued on the page, left in place per Never) cannot fight the mockup's visual rules. `pqm-quota-note` is reused deliberately — the kit rule duplicates the legacy one verbatim, so the note is styled with or without the legacy sheet.
- Pills render the translated labels lowercased via CSS `text-transform: lowercase` (mockup look, translations intact); unknown ran commands still append after the canonical six so data is never hidden (5.1 behavior kept).
- Processing/none badge text uses `#646970` (the Always constraint) on the mockup's `#f0f0f1` neutral background (≈4.9:1, AA).
- `viewer.quota` is forced to `null` when `can_trigger` is false (matrix row 3) — `quota_state()` alone would return a snapshot for a view-only user; the harness asserts exactly this divergence.
- `wp_style_add_data('pqm-post-reviews', 'rtl', 'replace')` added alongside the style enqueue: `wp-scripts` emits `style-index-rtl.css` and this is the stock WP pairing (the topbar/pill rules are directional).
- `node --check` cannot parse JSX; `npm run build` (webpack compile) is the syntax gate for `src/`, per the 5.1 precedent.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens): 1 finding, verified real, **not yet patched** — owner stopped the session after the lens returned. Resume at step-04 to finish triage.

- **low / pending** — `src/kit/status-badge.js` (`STATUS_MAP[ status ]`) and `src/kit/copy.js` (`commandLabel`) look up plain objects, so inherited keys such as `"constructor"` or `"toString"` resolve to `Object.prototype` members: the badge renders an empty label with class `is-undefined`, and the pill renders a function, which React drops. This breaks the frozen "unknown status → raw value, never blank" row. It is reachable only if n8n sends such a literal status, which `save_review_callback` stores after `sanitize_text_field` alone. The smallest fix is an `Object.hasOwn()` guard in both lookups. Pattern inherited from 5.1, now canonicalized by the kit.

Pass 2 (quick lens, resumed 2026-10-05): 5 findings — 3 patched, 1 deferred, 1 rejected. Verification re-run after the patches: `npm run build` clean, `php -l` clean, 7/7 harnesses green (420 checks).

- **low / rejected** — `Object.prototype` key lookups (status-badge.js:43-45, copy.js `commandLabel`). carried: code unchanged, claim still true. Rejected under the low rule: a literal `constructor`/`toString` status never occurs in everyday use (n8n sends the four known values or `error`) and the fix adds guards rather than correcting a line. Owner may override.
- **low / patch** — `src/kit/style.css` quota-note rule: on RTL sites the note centres. Verified: `pqm-admin.css` is enqueued on the Post Reviews hook without RTL data (`class-pqm-admin.php:123-128`) and keeps `.pqm-quota-note{margin-left:auto}`, while the RTL twin of the kit rule sets `margin-right:auto` — both auto → centred. Patched with an explicit `margin-right: 0` (RTL swap → `margin-right:auto;margin-left:0`, higher specificity wins; LTR unchanged, confirmed in the built `style-index-rtl.css`).
- **low / patch** — `src/kit/status-badge.js` label casing mixed title/sentence inside the one canonical copy source; the mockup is sentence case throughout (`Ready to ship`, `Needs revision`, `Major revision` — mockup.html:150-182, 219-222, 326) and 5.3's filter chips would otherwise diverge from the badges. Patched the three strings to sentence case (legacy PHP stays title case until 5.7 removes it).
- **low / patch** — `memory/admin-ui-rebuild.md` still said the kit was pending and carried no `viewer` contract; DoD requires memory updated in the same change. Patched: kit built, `src/kit/` as the JS copy source, `viewer` block semantics recorded.
- **medium / defer** — stored `error` status renders the raw untranslated word on the neutral badge. Verified reachable (`save_review_callback` :298-299 stores `error`; `latest_status` is the latest `review` row). Pre-existing: legacy `format_status()` rendered the same raw value, the mockup omits error states by design and the spec names none, so the kit has no rule to follow. Deferred to `deferred-work.md` — needs an owner decision on the failed-review badge, natural home story 5.4.

## Design Notes

Why a REST extension and not a localize: the spec prefers extending existing REST responses; the React page deliberately loads no `pqmAdmin`, and the viewer block keeps quota current on every refetch (stories 5.3/5.4 refetch after filters and triggers) instead of a page-load snapshot. The kit consolidates only JS-reachable copy: the Settings-page locked notes and REST refusal wordings stay PHP (Settings is outside the rebuild; refusal copy is server contract) — story 5.7 records their disposition. Processing moves from 5.1's blue to DESIGN.md's neutral gray; pills switch from ran-only to the mockup's full six-pill row.

## Verification

**Commands:**
- `npm run build` -- expected: exits 0; `build/index.asset.php` and the extracted `build/style-index.css` exist
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness green, including the extended viewer checks

**Manual checks (if no CLI):**
- [x] Post Reviews as admin (free plan): badges/pills per mockup, quota note absent (exempt); as a free-tier Author: note shows correct numbers; as a Contributor-like viewer with `pqm_view_reviews` only: no note in the DOM.

**Result (2026-10-05):** PR #22 merged (`da3ab0d`); the owner walked the manual checks on the real site and reported all green — no failures, no bug tickets. Story verify met end to end.
