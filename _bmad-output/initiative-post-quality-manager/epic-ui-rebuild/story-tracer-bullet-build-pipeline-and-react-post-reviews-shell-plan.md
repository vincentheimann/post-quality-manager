---
title: 'Tracer bullet: build pipeline and React Post Reviews shell'
type: 'feature'
ticket: '1'
created: '2026-10-02'
status: 'done'
baseline_revision: 'c2671a0ed2f5e78ace301981326943cfcf86ba20'
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

**Problem:** The rebuild epic (ADR 0005) needs its thinnest end-to-end slice proven before component work starts: today there is no JS build, no CI, and Post Reviews renders twice (PHP + string-built JS) with drift, i18n and a11y debt.

**Approach:** Introduce the `@wordpress/scripts` build (assets never committed; a CI job builds them and runs the harness sweep), dependency-extraction enqueue, a PHP page shell, and a read-only React Post Reviews table fed over REST.

**Decision (owner, 2026-10-02):** No list route exists today; add a read-only `GET post-quality/v1/posts` route wrapping the existing `PQM_DB::get_all_posts_with_scores()` query, gated by `check_view_reviews`. Additive only — all existing REST/webhook contracts and the n8n template stay untouched.

## Boundaries & Constraints

**Always:** Rendering only — gate, quota, webhook semantics and existing REST contracts unchanged; React/`@wordpress/components` load from WordPress core via dependency extraction, no bundled runtime; every string through `__()` with text domain `post-quality-manager`, copy per `docs/brand.md`; status renders icon + label + color (WP palette), never color alone, no emoji; built assets stay out of git; KISS.

**Never:** No filters, sorting, pagination UI, bulk actions or detail panel (stories 5.3–5.5); no component-kit formalization (5.2); no deletion of old render code — the new shell lands beside it and the legacy GET trigger path is bypassed, deletion is story 5.7; no n8n template or Settings/Workflow page changes.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| List happy path | GET list route, user with `pqm_view_reviews` | 200 `{items, total, pages}`; items carry post_id, title, edit link, latest_score, latest_status, latest_reviewed, commands run | No error expected |
| No capability | Same request, user lacks `pqm_view_reviews` | 403 `rest_forbidden` | WP REST error envelope |
| Bad params | `orderby=evil`, `page=0`, `per_page=999` | Values clamped/whitelisted to the DB method's accepted set | Silently sanitized, 200 |
| Empty site | No posts match | 200 `items: []` | UI shows brand empty-state copy |
| REST failure | Route errors / network down | — | UI shows gentle, actionable error Notice |
| Assets missing | Fresh clone, no `npm run build` | Page shows admin notice naming the build commands instead of a blank app | No fatal, no broken page |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` — menu reg :42-49 (slug `pqm-posts`, cap `pqm_view_reviews`, hook suffix `content-quality_page_pqm-posts`); `enqueue_assets()` :110-167 (gate `strpos($hook,'pqm-')`, `pqmAdmin` localize); `render_posts()` :447-698 old page incl. legacy GET trigger :450-498 — leave in place, bypass.
- `post-quality-manager/includes/class-pqm-rest-api.php` — `register_routes()` :21; reuse `check_view_reviews` :75-77 as permission; existing routes untouched.
- `post-quality-manager/includes/class-pqm-db.php` — `get_all_posts_with_scores()` :127-224 returns `{items,total,pages}` (args per_page/page/orderby score|reviewed_at|title/order/filter all|needs_review); `get_commands_for_posts()` :72-93.
- `post-quality-manager/post-quality-manager.php` — `PQM_REST_NAMESPACE` :24; admin hooks :49-59.
- `tests/harness/` — plain PHP + inline WP stubs, pass/fail exit codes; sweep = `for f in tests/harness/*.php; do php "$f" || break; done`.
- `.gitignore` (repo root) — currently Python/BMad only.
- No `.github/` exists; release zip = the `post-quality-manager/` subfolder (epic-delivery-pipeline later consumes this CI job).
- `_bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/mockup.html` :234-242 — target table shape; `docs/brand.md` :43 empty-state copy.

## Tasks & Acceptance

**Execution:**
- [x] `package.json` (repo root) -- `@wordpress/scripts` devDependency; `build`/`start` scripts with `--webpack-src-dir=src --output-path=post-quality-manager/admin/build` -- source stays outside the shipped subfolder, so the zip-the-subfolder release convention holds.
- [x] `.gitignore` -- add `node_modules/`, `post-quality-manager/admin/build/`.
- [x] `src/index.js` + `src/post-reviews-table.js` -- mount on `#pqm-post-reviews-root`; `apiFetch` list call; columns Post (edit link), Score badge, Status (Dashicon + label + WP palette color), Progress chips, Last reviewed; `Spinner` loading, `Notice` error, brand empty copy; all strings `__()`.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- read-only `GET /posts` list route: args sanitized to the DB whitelist, permission `check_view_reviews`, response `{items,total,pages}` + commands per post.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- new `render_posts_app()` shell (`.wrap` + h1 + root div; missing-build admin notice per matrix); point submenu callback at it; enqueue built asset on `content-quality_page_pqm-posts` only, deps/version from `build/index.asset.php`, `wp_set_script_translations()`; do not enqueue old `pqm-admin` JS on that hook (CSS stays).
- [x] `.github/workflows/ci.yml` -- on push/PR: node job (`npm ci`, `npm run build`, assert output exists, upload artifact) + php job (`php -l` over plugin PHP, harness sweep loop).
- [x] `tests/harness/rest-posts-harness.php` -- stub WP + `PQM_DB`; assert the I/O matrix rows (sanitization, 403, shape, empty).

**Acceptance Criteria:**
- Given built assets exist, when a user with `pqm_view_reviews` opens Post Reviews on the real site, then the table renders client-side from the REST data with loading, empty and error states handled.
- Given the CI workflow runs on a push, then it produces the built assets as an artifact and the harness sweep and `php -l` pass.
- Given a translated site, when the page loads, then table strings flow through `@wordpress/i18n` (translation wiring in place, catalogs out of scope).
- Given any pre-existing REST route or the webhook payload, then its contract is byte-identical and `templates/n8n-workflow.json` needs no change.

## Implementation Notes

- Built 2026-10-02 on `feat/5-1-tracer-bullet-react-shell`. `@wordpress/scripts` resolved to 30.27.0 (`^30.7.0` in `package.json`); `package-lock.json` is committed so CI's `npm ci` is reproducible. Build emits `build/index.js` (4 KB) + `build/index.asset.php` with dependencies `react-jsx-runtime, wp-api-fetch, wp-components, wp-element, wp-i18n` — all served by core, nothing bundled.
- Enqueue gate: instead of hardcoding the hook suffix `content-quality_page_pqm-posts` (it derives from the *translated* menu title), `register_pages()` captures the return value of `add_submenu_page()` into `PQM_Admin::$posts_hook` and `enqueue_assets()` compares against that. Shared `pqm-admin` CSS stays enqueued on the page (score-badge/pill classes are reused by the React table); the legacy `pqm-admin` JS and its `pqmAdmin` localize are skipped on this hook only.
- REST `/posts`: clamping lives in the callback (per_page 1–100 default 20, page ≥ 1, orderby/order/filter whitelists) rather than only in arg schemas, so the matrix rows are testable without a WP REST server and bad values sanitize silently to 200. Response items additionally carry `latest_reviewed_display` (server-side `date_i18n`, mirrors the legacy `format_date()`) and `edit_link` via `get_edit_post_link(…, 'raw')` (null for viewers who cannot edit — the table then renders plain text).
- `tests/harness/rest-posts-harness.php`: 28 checks green — registration (incl. the five pre-existing routes untouched), permission callback, happy-path shape with commands, bad-param clamping (asserted via the captured `LIMIT %d OFFSET %d` prepare args and ORDER BY SQL), whitelisted pass-through, empty site.
- `node --check` does not parse JSX; the webpack/babel pass inside `npm run build` is the JS syntax gate (CI runs it on every push/PR).
- **Flag for owner:** dependency extraction emits the `react-jsx-runtime` core script handle, which WordPress ships since 6.6. The plugin header still says `Requires at least: 6.0` — on 6.0–6.5 the Post Reviews script would not load. Review fix: `render_posts_app()` now detects the missing `react-jsx-runtime` handle and renders a translated "needs WordPress 6.6 or newer" notice instead of a blank root div. Bumping the header (or a polyfill) remains an owner decision outside this plan's boundaries.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens): 3 medium, 1 low, 0 false, 0 maybe-false — all routed to patch.

- **medium / patch** — `memory/MEMORY.md` Admin-UI-rebuild line still says "not built yet" while the diff updates `memory/admin-ui-rebuild.md` to "tracer built"; verified — the canonical index contradicts the fact file it links to, misleading every next session. Fix: update the index line.
- **medium / patch** — WP 6.0–6.5 renders Post Reviews as a silent blank page: the build's `react-jsx-runtime` dependency is a core handle only since WP 6.6, so `wp_enqueue_script()` silently drops the bundle while the shell emits only the empty root div; verified against the asset manifest and the plugin header `Requires at least: 6.0`. Fix: guard on the handle and show a gentle notice instead of a blank page. The header bump (6.0 → 6.6) stays an owner decision — flagged at presentation, not decided here.
- **medium / patch** — `src/post-reviews-table.js` fetches with the default `per_page` (20) and discards `total`/`pages`, so blogs with >20 posts silently lose access to the rest until story 5.3 (the legacy pagination is already unreachable); verified against the route's clamp (max 100). Fix: request `per_page=100` and show a "showing N of T" line when truncated — not pagination UI.
- **low / patch** — `wp_set_script_translations()` lacks the `$path` argument, so plugin-shipped JSON catalogs would never be found (plugin distributes via GitHub zip, not wordpress.org); no catalogs exist today, so nobody hits it yet, but the fix is a direct one-argument correction.

## Design Notes

Ticket's declared unknown (dependency-extraction/enqueue specifics), settled: `wp-scripts build` emits `build/index.js` + `build/index.asset.php` (dependencies such as `wp-element`, `wp-components`, `wp-api-fetch`, `wp-i18n` — all served by WordPress core — plus a content-hash version); enqueue reads that file, and `wp-components` CSS is enqueued as the style dependency. In wp-admin, core preconfigures `wp-api-fetch` with root URL + nonce middleware, so the tracer needs no new localize bridge. Legacy `render_posts()` and its GET trigger path become unreachable (recorded here for story 5.7's cleanup ledger).

## Verification

**Commands:**
- `npm install && npm run build` -- expected: exits 0, `post-quality-manager/admin/build/index.asset.php` exists
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness green, including the new REST list harness

**Manual checks (if no CLI):**
- WP Admin → Content Quality → Post Reviews renders the React table with real data; browser console free of errors; with `build/` deleted, the guidance notice shows instead.

## Walkthrough checklist (2026-10-02)

**Preconditions:** branch `feat/5-1-tracer-bullet-react-shell` deployed to the real site — run `npm install && npm run build` locally, then upload the `post-quality-manager/` subfolder (the built `admin/build/` rides inside it); WordPress ≥ 6.6; a user with `pqm_view_reviews`.

### CI (tick from GitHub, no site needed)
- [x] PR #20's checks are green: the build job uploaded the `admin-build` artifact, the php job's lint + harness sweep passed.

### Page renders from React (story verify)
- [x] Content Quality → Post Reviews shows the table with real posts: Score badge, Status as icon + label + color (no emoji), Progress chips, Last Reviewed — values match what the old page showed for the same posts.
- [x] A short loading spinner appears on first load; the browser console stays free of errors and the page source ships only the `#pqm-post-reviews-root` shell (no PHP-rendered rows).
- [x] Post titles link to the editor for posts you can edit.

### Volume and truncation
- [x] With more than 20 posts, more than 20 rows render (the old page capped at 20 per page); with more than 100, one line under the table reads "Showing the 100 most recently reviewed of N posts."

### Failure states
- [x] Rename `admin/build/` away → the page shows the build-instructions notice, not a blank app; restore it afterwards.
- [ ] (Optional) With REST unreachable — e.g. DevTools offline after load-less reload — the gentle "Could not load your posts…" notice shows instead of a spinner forever.
- [ ] (Optional, only if a WP 6.0–6.5 site is handy) the "needs WordPress 6.6 or newer" notice shows instead of a blank page.

**Recording:** tick the boxes here; any failure becomes a bug ticket in `_bmad-output/backlog/` instead of a tick.

**Result (2026-10-02):** the owner walked the checklist on the real site and reported it done — no failures, no bug tickets; CI green on GitHub (both jobs, `admin-build` artifact present). The two optional failure-state checks were not claimed and stay unticked. Story verify met end to end.
