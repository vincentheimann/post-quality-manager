---
title: 'The n8n connection is secured and host-agnostic end to end'
type: 'feature'
ticket: '35'
created: '2026-10-07'
status: 'built'
baseline_revision: '309fefd63b2e0dca2719819ad327b2d0bd98747f'
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

**Problem:** Anyone who learns the n8n webhook URL can start runs (Claude credit, rewrites of guessed post IDs); the rewrite path forces `https://` + `/wp-json` on the WordPress address; a pasted `/webhook-test/` URL passes the ping then dies; the reachability prerequisite is unstated.

**Approach:** The plugin sends the existing webhook token as an `X-PQM-Token` header on every call to n8n, and the template's Webhook Trigger checks it with n8n Header Auth (one token, both directions — owner 2026-10-07). The payload carries the post's REST address and the template uses it as-is. A test URL gets an inline warning; wizard and README state the prerequisite. `PQM_WORKFLOW_VERSION` 3 → 4.

## Boundaries & Constraints

**Always:** Template keeps `__PQM_*__` placeholders only, never site values. Old (v3) imports keep running unchanged: the extra header and payload field are ignored by them. Copy follows docs/brand.md. Every touched PHP file passes `php -l`, JS `node --check`, all harnesses pass.

**Never:** Change the `save-review` callback contract, the command whitelist, or the sanitizer's section-wise carry rule. No token rotation UI. No change to the import-URL mechanism (story 22); only its copy names the risk. No round-trip ping (story 2).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Run / cron / ping | any call from the plugin to n8n | request carries `X-PQM-Token: <webhook_token>` | — |
| Unauthenticated call | POST to n8n without or with a wrong header (v4 import) | n8n refuses (401/403), no agent runs | existing non-2xx copy (bugs 32/33) |
| Rewrite, any WP | plain HTTP, subdirectory install, plain permalinks | PATCH goes to `rest_url()` of the post's own REST route | post type not in REST → empty address → node fails → existing "Could not save the rewrite" path |
| Test URL, ping | ping with a URL whose path contains `/webhook-test/` (wizard or Settings) | no request sent; error with the warning copy; wizard stays on step 3 | — |
| Test URL, Settings save | Settings form saved with such a URL | saved, plus a warning notice | — |
| Production URL | `*.app.n8n.cloud` or any self-hosted domain | saves and pings, no warning | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php` -- `PQM_WORKFLOW_VERSION` (L28); `pqm_n8n_payload()` (L316+, add the REST address); new helpers go beside it.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- `trigger_review_callback()` `wp_remote_post` (~L517). Do not touch `check_webhook_token()` or the import-URL `check_workflow_token()`.
- `post-quality-manager/includes/class-pqm-cron.php` -- `trigger_single_review()` `wp_remote_post` (~L99).
- `post-quality-manager/admin/class-pqm-admin.php` -- `ajax_test_connection()` (~L1240), `sanitize_settings()` admin keys (~L339), Settings token description (~L597), wizard steps 2–3 (~L1030–1051), `render_import_url()` copy (~L1162), `render_credentials_hint()` (~L1173). `ajax_save_webhook_url()` stays as is.
- `post-quality-manager/templates/n8n-workflow.json` -- Webhook Trigger (L4–18), Parse Input jsCode (passes fields through; `workflow_version: 3`), Update WP Post (Rewrite) url (L528).
- `tests/harness/` -- `quota-harness.php` and `cron-gate-harness.php` stub `wp_remote_post` (ignore args today); `render-matrix-harness.php` L383–389 asserts 7 credentialed nodes and the hint's node list; `rest-reviews-harness.php` holds the static template contract; `template-failure-harness.php` runs Code nodes in node; `sanitize-matrix-harness.php` covers the sanitizer. No shared stubs file: each harness stubs WP inline.
- Docs: `README.md` Requirements + Installation; `n8n/workflow-diagram.md` contract notes; `memory/llm-options-architecture.md` handshake.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- bump to 4; add `pqm_n8n_headers(array $settings)` (Content-Type + X-PQM-Token) and `pqm_webhook_url_warning(string $url): string` ('' or the warning copy, path match on `/webhook-test/`); payload gains `post_rest_url` = `rest_url(rest_get_route_for_post($post))`, '' when the route is empty -- one home for header and copy, three/two callers.
- [x] `includes/class-pqm-rest-api.php`, `includes/class-pqm-cron.php` -- send `pqm_n8n_headers($settings)` -- trigger and cron authenticate.
- [x] `admin/class-pqm-admin.php` -- ping uses the headers and returns the warning as an error before sending for a test URL; sanitizer adds a `warning` settings error for one; wizard step 2 lists the third credential (Header Auth, name `X-PQM-Token`, value = the token, shown inline); step 3 states the reachability prerequisite; token description says both directions; import-URL copy names that the token also opens the n8n trigger; hint lists the Webhook Trigger -- criteria 1, 2, 4, 5.
- [x] `templates/n8n-workflow.json` -- Webhook Trigger `authentication: headerAuth` + `httpHeaderAuth` credential `Post Quality webhook - __PQM_SITE_HOST__`; Parse Input passes `post_rest_url`, stamps 4; rewrite url `={{ $('Parse Input').first().json.post_rest_url }}` -- criteria 1, 3.
- [x] `tests/harness/*` -- quota + cron stubs record headers and assert the token header; render-matrix: 8 credentialed nodes incl. Webhook Trigger, wizard copy (credential, prerequisite), ping/test-URL static check; rest-reviews: trigger has headerAuth, rewrite url assembles no scheme/host; sanitize-matrix: warning helper true/false cases incl. Cloud and self-hosted production URLs; stub `rest_get_route_for_post` where the payload runs -- I/O matrix.
- [x] `README.md`, `n8n/workflow-diagram.md`, `memory/llm-options-architecture.md` -- prerequisite, three credentials, v4 contract, known limitation "the trigger stays unauthenticated until you re-import" -- criteria 5, 6.

**Acceptance Criteria:**
- Given a site on a v3 import, when the plugin updates, then reviews keep running and the update notice appears once a run reports v3.
- Given the downloaded v4 template, when inspected, then it contains no literal `http://`/`https://` aimed at the WordPress site and only `__PQM_*__` placeholders.

## Implementation Notes

- The three-credential list is one private helper, `PQM_Admin::render_credentials_list($token)`, rendered in wizard step 2 and also in the connected page's Workflow File section: a site on a v3 import never sees the wizard, and it is the one re-importing, so it needs the Header Auth name and token there too. The section's description line now says "connect these three credentials:".
- `render_settings()` calls `settings_errors('pqm_settings')` inside its own save notice block: core prints settings errors only under Settings → *, and this is a top-level page, so the sanitizer's test-URL warning would otherwise never show.
- Harnesses: quota/cron stub `pqm_n8n_headers()` with the real shape (they cannot load the bootstrap, they stub `pqm_n8n_payload()`); the real helper, the real `pqm_n8n_payload()` (`post_rest_url` for a page and for a type outside REST) and a source check that all three `wp_remote_post` calls use the helper live in `rest-reviews-harness.php`. The ping is executed through `ajax_test_connection()` in `render-matrix-harness.php` (test URLs refused before sending, Cloud and self-hosted production URLs sent with the token). `template-failure-harness.php` updated for the v4 stamp and the `post_rest_url` pass-through.
- `docs/user-paths.md` A4 said "two credentials"; now three.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens): high 0, medium 3, low 3, false 0, maybe-false 0.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | Two Header Auth credentials, copy never says which goes in which node; README "named X-PQM-Token" reads as the credential title | medium | patch | Template: six Claude nodes + Webhook Trigger both `httpHeaderAuth`. Patched list, hint and README to name the header Name field and the node per credential. |
| 2 | Docs claim the rewrite works on plain HTTP | medium | patch | `Update WP Post (Rewrite)` uses an Application Password; core offers them only with `is_ssl()` or environment type `local`. Docs, memory, payload comment and README Requirements now say the address is right everywhere and the rewrite needs HTTPS (or `local`). Flagged to the owner against ticket criterion 3. |
| 3 | Wizard stores a test URL before the ping refuses it; after reload no warning remains; "URL saved, but the ping…" is wrong | medium | patch | Smallest fix (3 lines, existing helper, no new surface): `ajax_save_webhook_url()` refuses a `/webhook-test/` URL before saving. Harness: refused and not stored; production stored. Code Map line "stays as is" superseded. |
| 4 | MEMORY.md index still says handshake v3 | low | patch | Index line updated to v4. |
| 5 | No ADR for the trigger auth model | low | patch | Security model + accepted limitation meet docs/adr/README.md bar (external contract). Wrote ADR 0010, indexed, linked from memory. |
| 6 | quota/cron harnesses stub `pqm_n8n_headers()` | low | reject | The real helper and all three call sites are checked in rest-reviews-harness.php; tying the stubs to it means loading the main plugin file into those harnesses (more than a direct correction). |


## Design Notes

- One token for both directions: n8n's Header Auth compares a header to a stored value, so the owner pastes the token they already have. The header name matches the return path (`X-PQM-Token`), so one name to remember.
- `rest_get_route_for_post()` (WP 5.5, rung 4) gives the right route for any REST-enabled post type; `rest_url()` handles scheme, port, subdirectory and plain permalinks (`?rest_route=`). The old composition dropped all of them.
- Test URL: the ping refuses before sending. Sending it would succeed while the editor listens and teach the wrong lesson; the wizard then stays on step 3. The Settings form still saves (Save never loses input), with a notice.
- Token storage (ticket open question, settled by reading): activation stores the token; a site without one already fails every `save-review` callback today, so the new check adds no failure mode. Skipped: token backfill on upgrade — add when a site reports it.
- Skipped: a 403-specific hint on Run when the header is refused — the existing non-2xx copy already sends admins to Settings/n8n; add when owners hit it.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass.
- `php -l` on each touched PHP file; `node --check post-quality-manager/admin/js/pqm-admin.js` if touched; `node -e "JSON.parse(require('fs').readFileSync('post-quality-manager/templates/n8n-workflow.json'))"` -- expected: no errors.

**Manual checks (hitl, owner):**
- In a real n8n (Cloud and self-hosted if available): import v4, create the Header Auth credential, then Run, cron and ping succeed; a curl without the header gets 403; a `/webhook-test/` URL shows the warning.
