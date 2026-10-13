---
title: 'The Free build contains no Pro code'
type: 'feature'
ticket: 54
created: '2026-10-12'
status: 'built'
baseline_revision: '24c5595875fc14fce5b5c35cf217fea5fe24d765'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick', 'overbuild', 'premium-leak']
review_loop_iteration: 1
context:
- '{project-root}/docs/adr/0014-two-builds-from-one-source.md'
- '{project-root}/docs/adr/0013-pro-sold-through-freemius-before-public-release.md'
- '{project-root}/docs/distribution.md'
- '{project-root}/memory/freemium-and-roles.md'
- '{project-root}/memory/llm-options-architecture.md'
- '{project-root}/_bmad/custom/minimal-code-ladder.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** ADR 0014 decided two builds from one source, but the source has no build markers: every Pro feature is separated only by the runtime gate (`PQM_Features::is_pro()`), the one n8n template carries the paid branches, and nothing proves what a Free zip would contain. WordPress.org guideline 5 rejects a build that ships locked functionality; a missed marker costs a review-queue cycle.

**Approach:** Mark the paid PHP with Freemius's conventions so its deployment strips it, and make the project able to produce the same Free build itself. Paid code moves into `__premium_only` methods and whole premium files, every caller sits inside its own `if ( pqm_fs()->is__premium_only() )` block, and `PQM_Features::registry()` receives the paid entries only through such a method, so the gate, `why_not()`, the carry rules and the capabilities stay as they are and simply have nothing paid to gate. A Free n8n template is derived from the Pro one by a script and checked in; the workflow-version handshake learns a `workflow_variant` field so a Pro site holding the Free template is told to re-import. A root `bin/build-free.php` reproduces the stripping with PHP's tokenizer; a harness runs it, boots the result and asserts that the paid commands, template branches and markers are gone, and proves that an unwrapped paid call fails. CI runs the harness on every PR and Plugin Check on the stripped tree. The release order (Freemius first, generated Free build to SVN) goes into `docs/distribution.md` and the `/release` skill; the three Freemius leak patterns go into the code-review lens and AGENTS.md. The owner closes the loop once: upload a CI zip to Freemius, download the generated Free build, diff it against the local strip, and walk the Free build on Local.

## Boundaries & Constraints

**Always:** The runtime gate stays: `is_pro()`, the `pqm_is_pro` filter, `plan_allows()`, `why_not()`, the `gate_error()` copy, the five capabilities, the quota and `is_quota_subject()`, the sanitizer carry rules and every stored settings key are unchanged in both builds. The Pro build behaves exactly as today, locked sections for a lapsed license included (story 44 AC2). Files keep their names and places; stripping is by Freemius markers only: `*__premium_only()` methods, the `@fs_premium_only` header list for whole files, and `if ( pqm_fs()->is__premium_only() ) { … }` wrappers with nothing else in the condition, never an `else`, never an assignment from a premium method (the three leak patterns). `templates/n8n-workflow.json` stays the canonical Pro template with `__PQM_SITE_HOST__` as its only placeholder. `PQM_WORKFLOW_VERSION` stays 5: the variant field is additive and a template without it counts as Pro. The Free build is a complete product: Review and Key Takeaways end to end, no disabled control, no "Pro" label, the one "Get Pro" paragraph in the License section of Settings and nothing else that upsells. `bin/` lives at the repository root, outside the zip. Harnesses run without WordPress and without the SDK (`tests/harness/fs-stub.php` gains `is__premium_only()`, true by default = the Pro build).

**Never:** No obfuscation, no moving Pro code out of the public repository (ADR 0014). No second template format and no runtime derivation of the Free template from the Pro one. No REST route removed; no DB schema change. No Freemius secret, API token or bearer token in the repository or in CI for this story (the release-time fetch of the generated build is a story 38 step once the SVN deploy exists). No markers inside `vendor/freemius/`. No React bundle changes for stripping (decision D1 below). No automatic Freemius upload.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Pro build, licensed | `is__premium_only()` true, `can_use_premium_code()` true | Every screen, command, cron knob, override and the Roles tab exactly as on `main` today; harness sweep identical | — |
| Pro build, no or lapsed license | `is__premium_only()` true, `is_pro()` false | Locked sections and the lapse copy as today (story 44 AC2); paid commands refused with the plan copy | 403 `pqm_feature_gated` |
| Free build (stripped tree) | no `__premium_only` symbol left, `is_premium` false | `registry()` = review, takeaways, score, apply, all `tier: free`; Settings shows Connection, AI (no Overrides), Usage & Limits; no Automation or Roles tab; License block shows plan Free + "Get Pro" | — |
| Free build, paid command via REST | `command=seo` on trigger-review or save-review | Rejected by the `enum` built from the registry; never reaches the gate or the webhook | 400 `rest_invalid_param` |
| Free build, insert Sources | `/insert-block` with `sources` | Rejected by the `enum` (item keys are takeaways only); Takeaways insert as a plain list | 400 |
| Free build, Post Reviews | `GET /posts` viewer | `gated_commands` and `hidden_commands` carry no paid key; no locked button, no "Available with Pro" note; the Rewrite action absent because the server lists no such command | — |
| Free build, setup dialog | `GET /setup` → `build: 'free'` | No Application Password step, no Schedule step, credential copy names two Claude nodes and no WordPress credential; progress keys of absent steps ignored | — |
| Free build, stale cron | `cron_enabled=1` left by a former Pro install | The `pqm_scheduled_review` event has no callback; nothing runs, nothing is shown; no off-switch (the section is absent) | — |
| Free build, dashboard | `GET /dashboard` | No `cron_nudge` key; no notice | — |
| Free build, template download | n8n Workflow page / `GET /workflow` | Serves `templates/n8n-workflow-free.json` filled with the host; name "Post Quality — host"; Route Command knows score, review, takeaways, ping; Parse Input stamps `workflow_variant: 'free'` | — |
| Pro build, template download | same | Serves `templates/n8n-workflow.json` as today, stamping `workflow_variant: 'pro'` | — |
| Handshake, matching variant | ping answers `{ok, workflow_version: 5, workflow_variant: <build>}` | "Your n8n workflow: version 5" as today | — |
| Handshake, legacy v5 template | ping answers without `workflow_variant` | Treated as `pro`; no notice on either build | — |
| Handshake, Free template on a Pro site | `workflow_variant: 'free'` on the Pro build | Status adds: "Your workflow is the Free variant: it lacks the Sources, SEO, Audit and Rewrite branches. Download the template below and re-import it." Download button unchanged | — |
| Handshake, Pro template on a Free site | `workflow_variant: 'pro'` on the Free build | Reported as current, no notice (the extra branches are harmless) | — |
| save-review callback | body carries `workflow_variant` | Optional arg, enum `free`/`pro`, recorded next to the version; absent = `pro` | 400 on other values |
| Local strip | `php bin/build-free.php <out>` | Copies the plugin folder, deletes `@fs_premium_only` paths and `*__premium_only*` files, removes suffixed methods with their doc comments and single-condition premium `if` blocks, flips `'is_premium' => true` to `false`, exits 0 | exit 1 with file:line if any `__premium_only` token survives outside `vendor/` |
| Local strip, leak patterns | an inline `&& is__premium_only()`, an assignment `$x = …__premium_only()`, an unwrapped `foo__premium_only()` call | Left in place by design, so the survivor check fails: CI red with the file:line | exit 1 |
| Free-build harness | runs the strip into a temp dir | Asserts: no marker token; listed files absent; registry tiers all free; the Free template has none of the 15 paid node names, Route Command rules ⊆ {score, review, takeaways, ping}, `validCommands` has no paid key, connections reference existing nodes only; no quoted paid command key in non-vendor PHP; the negative self-test (inject an unwrapped suffixed call into a copy, expect the strip to fail) passes | the first failed assertion names the file |
| Template drift | Pro template edited, Free file not regenerated | `template-guard-harness` re-derives the Free template in memory and compares parsed JSON with the checked-in file: fails naming the first differing node | exit 1 |
| CI | pull request | `free-build` job: strip with the built `admin/build/` in place, upload the artifact, run Plugin Check on it with no new error; the harness sweep includes the free-build harness | job fails |
| Release (unchanged here) | tag published | `package` job attaches the full zip as today; the Freemius upload and the SVN push are owner steps written in `docs/distribution.md` and `/release` | — |

</frozen-after-approval>

## Decisions for the owner (answered 2026-10-12: D1–D4 as recommended)

- **D1 — React bundle.** Recommended: leave `admin/build/` identical in both builds. The command list, labels, gated and hidden flags, `is_pro`, `cron_nudge` and the new `build` flag all come from the server, so in the Free build the locked UI, the Rewrite button and the Schedule step never render. The strings "sources", "seo", "audit", "rewrite" and "Available with Pro" remain in the minified bundle; they are not functionality. Alternative: wrap them in `/*! <fs_premium_only> */` blocks and override wp-scripts' Terser `comments` option to keep them (a root `webpack.config.js`), relying on Freemius's JS stripper inside array literals. Cost: a build-config file, an untested stripping shape, and the harness would have to grep JS too. AC1's grep is then read as PHP and template; the ticket's AC1 wording is amended to say so if D1 = recommended.
- **D2 — Free template file.** Recommended: generated by `bin/derive-free-template.php` and checked in as `templates/n8n-workflow-free.json`, drift caught by the harness. The Pro build carries the unused Free file (about 20 KB); nothing in Freemius removes files from the premium build. Alternative: generate at zip time only (keeps git smaller, but a Local Free build then needs the script run by hand and the harness still derives in memory).
- **D3 — Stale cron on a Free build.** Recommended: do nothing; a scheduled event without a callback is inert. Alternative: a Free-only cleanup on activation; Freemius has no "free only" PHP marker, so it would be an `if ( ! pqm_fs()->is__premium_only() )` block that survives in both builds. Not worth it.
- **D4 — Harness count for `wp_remote_post(`.** `rest-reviews-harness` expects exactly 3 occurrences across rest-api, cron and admin; cron stays in place, so the count holds. If the implementation moves any call, the harness expectation changes in the same PR, never the other way round.

## Code Map

**Markers and wrappers (PHP, Pro behaviour unchanged):**
- `post-quality-manager/post-quality-manager.php` — header gains `@fs_premium_only /includes/class-pqm-cron.php` (one line in the plugin header docblock); the cron `require`, its `add_action` hooks and the activation and deactivation schedule calls move inside one `if ( pqm_fs()->is__premium_only() ) { … }`; the paid `agent_prompts` defaults and `pqm_agent_max_tokens` entries come from `pqm_paid_agent_defaults__premium_only()` added inside such a block (`$defaults += …`, never an assignment from the premium call); `pqm_workflow_json()` picks `n8n-workflow-free.json` by default and `n8n-workflow.json` inside a premium block.
- `includes/class-pqm-features.php` — `registry()` = free entries `+ self::paid_registry__premium_only()` inside a premium block. Nothing else changes.
- `includes/class-pqm-blocks.php` — `ITEM_KEYS` becomes `item_keys()` (takeaways, plus sources from a premium method); the pattern branch of `insert()` becomes `insert_with_pattern__premium_only()` called inside a premium block; the plain-list path stays.
- `admin/class-pqm-admin.php` — `render_settings()` keeps its shape; the Overrides section (L875–1022, pattern picker included), Automated Reviews (L1026–1127, with `CRON_COMMANDS` turned into `cron_commands__premium_only()`) and the Roles tab (L1146–1202) each become `render_*__premium_only()` called inside premium blocks; the `$tabs` entries `automation` and `roles` are added inside a premium block; in `sanitize_settings()` the cron branch (L406–433), the `agent_prompts` branch (L435–466) and the roles branch (L474–476) are wrapped so that `save_roles()`, `role_cap_labels()` and the paid half of `agent_labels()` carry the suffix, while the carry rules stay outside the wrappers; the credential list and hint lines that name Rewrite and "the six Claude nodes" get Free wording by default and the Pro wording inside a premium block; `ping_webhook()` reads `workflow_variant`; `render_workflow_status()` adds the Free-variant notice (Pro build only, inside a premium block).
- `includes/class-pqm-rest-api.php` — `cron_nudge` (L897–901), the cron keys of `setup_state()` and the cron 403 of `setup_settings_callback()` (L1134–1141) inside premium blocks; `setup_state()` gains `build` (`'free'`, set to `'pro'` inside a premium block); `save_review_args` gains optional `workflow_variant`; `pqm_record_workflow_version()` stores it.
- `includes/class-pqm-license.php` — untouched.
- `tests/harness/fs-stub.php` — `is__premium_only()` returning `$this->premium` (default true).

**Templates:**
- `templates/n8n-workflow.json` — Webhook Trigger `responseData` and Parse Input stamp `workflow_variant: 'pro'`.
- `templates/n8n-workflow-free.json` — new, generated: the 15 paid nodes and their connections removed; Route Command rules 3–6 removed and the output indexes of ping and the fallback re-based; `validCommands` reduced to score, review, takeaways, apply; variant `'free'`; the WordPress credential goes with its only node.

**Tooling (repository root, not in the zip):**
- `bin/derive-free-template.php` — the derivation above, deterministic, writes the Free file.
- `bin/build-free.php` — copy, delete listed and suffixed files, tokenizer-based removal of suffixed functions and methods (with the preceding doc comment) and of `if` blocks whose condition is a single `…__premium_only(…)` call, flip `is_premium`, survivor check. About 150 lines, PHP 8.0 compatible, no dependency.
- `tests/harness/free-build-harness.php` — runs the builder into a temp dir and asserts the matrix rows, including the negative self-test.
- `tests/harness/template-guard-harness.php` — gains the derivation comparison and the Free-template structure checks.
- `.github/workflows/ci.yml` — job `free-build` (needs `build`): download `admin-build` into the plugin folder, run `bin/build-free.php build/free`, upload a `free-build` artifact, Plugin Check with `build-dir: ./build/free/post-quality-manager`; the existing harness sweep picks up the new harness by its glob.
- `.github/workflows/release.yml` — unchanged.

**React (server-driven, small):**
- `src/setup/steps.js`, `src/setup/index.js` — read `state.build`; omit the Application Password step and the Schedule step when `'free'`; credential copy per build. One Jest case in `tests/js/setup-*.test.js`.

**Records:**
- `docs/distribution.md` — new section "Release order (ADR 0014)": tag → Freemius Deployment → Add New Version with the CI zip (OS zip or `zip -r`, never PowerShell) → download the generated Free build (`GET /products/41144/tags/{tag}.zip?is_premium=false`, product-scoped bearer token from Settings → API & Keys, never in the repository) → SVN push of that build (story 38 AC7) → Released. GitHub Releases attach the full zip until the first directory release. The API endpoints for a later automation (`POST /products/{id}/tags.json` multipart `file`; `PUT …/tags/{tag}.json` `release_mode`) are noted for story 38.
- `.claude/skills/release/SKILL.md` — steps 7–9 for the above.
- `_bmad/custom/bmad-code-review.toml` — a `premium-leak` thorough lens: the three leak patterns, "no `else` after a premium `if`", "every paid feature has two guards".
- `AGENTS.md` — one convention line under "Conventions that differ from defaults".
- `docs/release-notes/unreleased.md` — one line: the plugin now exists as a Free build (Review and Key Takeaways) and a Pro build; a Pro site that imported the Free workflow is told to re-import.
- `memory/freemium-and-roles.md`, `memory/delivery-pipeline.md`, `memory/llm-options-architecture.md` (the handshake gains a variant), ticket 54 Notes; ticket 38 Notes gain the readme `fs_premium_only_begin` markers and the Free-build fetch as its steps.

## Implementation sequence

1. **Markers first, behaviour unchanged.** Stub method, then the PHP wrappers and suffixed methods file by file. Gate: `php -l`, full harness sweep green with no expectation changed, `npm run test:unit` green. Manual: the Pro build on Local looks identical.
2. **The local strip and its harness.** `bin/build-free.php`, `free-build-harness.php` with the negative self-test. Gate: the harness passes on the real tree; a deliberately unwrapped call in a scratch copy fails it.
3. **Templates and handshake.** `bin/derive-free-template.php`, the checked-in Free file, the Pro template's variant stamp, the `template-guard-harness` extension, `ping_webhook()` / record / report / save-review arg, `setup_state().build`, the React setup steps and their Jest case. Gate: harness sweep, unit tests; manual on Local: Pro site + Free template imported → the notice; Pro template → no notice.
4. **CI.** The `free-build` job; Plugin Check on the stripped tree must report nothing new. Gate: green PR checks read with `gh pr checks`.
5. **Records.** distribution.md, release skill, code-review lens, AGENTS.md line, release notes, memory, ticket Notes (54 and 38).
6. **HITL (owner, after the PR is green, before merge or right after):** run the Release workflow's manual dispatch for a packaging zip; upload it to Freemius as an unreleased version; download the generated Free build with `is_premium=false`; the agent runs `diff -r` against the `bin/build-free.php` output and records every difference (the unverified assumptions below are settled here); fix the stripper until the trees match except for what Freemius itself rewrites; then the owner activates the Free build on Local and walks every screen (AC4). Only then is CI trusted and the ticket done.

## Verification

- `php -l` on every touched PHP file, `bin/` included; `npm run lint:js`, `npm run test:unit`.
- `for f in tests/harness/*.php; do php "$f" || break; done` — all green, including `free-build-harness.php` and the extended template guard.
- `php bin/build-free.php <scratch>/free && grep -rn "__premium_only" <scratch>/free --exclude-dir=vendor` → no output.
- Plugin Check on the stripped tree in CI; `gh pr checks <n> --repo vincentheimann/post-quality-manager` for the PR state.
- Local, Pro build: Settings, Post Reviews, Dashboard and the setup dialog unchanged; the Free-variant notice appears only with the Free template imported.
- Local, Free build (stripped tree copied into `wp-content/plugins/`): the matrix's Free rows, end to end with the Free template on the owner's n8n.
- Diff against the Freemius-generated Free build (HITL step 6).

## Unverified assumptions, settled by the HITL diff

- Freemius's deployment flips `'is_premium' => true` to `false` in the free build on its own; the local stripper does it regardless.
- Freemius strips `if ( pqm_fs()->is__premium_only() ) { … }` blocks with this receiver name exactly as its `my_fs()->` examples, and removes a suffixed method together with nothing but its own body (the local stripper also removes the doc comment; a leftover docblock in the Freemius build is cosmetic).
- Freemius leaves `vendor/freemius/` as uploaded (or updates it); either way the diff shows it, and `@fs_ignore` is added only if the diff says so.
- Freemius removes non-PHP paths on the `@fs_premium_only` list too (here `/templates/n8n-workflow.json`, added at build time, see the change log).
- Freemius strips a premium `if` block that stands alone inside a `<?php … ?>` island of a template-heavy method (`render_settings()`, `render_credentials_list()`, `render_workflow_status()`) as it does in plain PHP.
- Freemius flips `'is_premium' => true` to false in the Free build (the first assumption above); the dual-activation branch reads `is_premium()` of the build loaded first, so an unflipped Free build would register the wrong basename. The HITL walk (Free active → upload the Pro zip → activate) shows it.
- Freemius removes a `*__premium_only` method whose body leaves PHP for inline HTML (`render_overrides__premium_only()`, `render_automation__premium_only()`, `render_roles__premium_only()`: `?>` … `<?php` inside the braces) as a whole; if it does not, those three move into a file on the `@fs_premium_only` list.
- Freemius leaves the remaining code as written, so whitespace differences in the diff come from the local stripper's line handling only (it drops the removed block's indentation and one line break; leftover blank lines are cosmetic).

## Plan Change Log

- 2026-10-12, slice 1: `PQM_Admin::on_settings_updated()` and `PQM_REST_API::setup_settings_callback()` also call `PQM_Cron::schedule()`; both calls are wrapped (the Code Map named neither, and without the wrap every settings save fatals on the Free build).
- Slice 1: `PQM_Admin::on_settings_updated()` wraps `PQM_Cron::schedule()`; `POST /setup/settings` accepts `cron_enabled` (and runs its 403) in the Pro build only, so the Free build refuses the key as unknown (400) — changed at review (premium-leak lens) from the first build, which left the key and its 403 in both builds.
- Slice 1: the `@fs_premium_only` list also names `/templates/n8n-workflow.json`, so the Free zip holds the Free template only (AC1's grep, AC2); the Free-build harness asserts it.
- Slice 1: the paid agent defaults, output budgets, Insert-into-Post item keys and agent labels are literal arrays inside premium blocks rather than separate suffixed functions (same strip, fewer symbols); `paid_registry__premium_only()` stays as planned, and the pattern branch is `pattern_copy__premium_only()`. `PQM_Blocks::place()` decides first or last by "not takeaways" instead of `'sources' ===`, so the Free PHP quotes no paid key. `PQM_Blocks::patterns()` keeps its name: dead in the Free build, called by a harness.
- Slice 1: copy that names paid agents got a Free default and the Pro wording in a premium block beyond the credential lines: the Blog Profile and Editorial Standards hints, the Workflow File intro (two or three credentials), and the request preview (its rows follow `agent_labels()`).
- Slice 3: the Pro template stays byte-identical and states no variant; only the Free one states `workflow_variant: 'free'` (trigger reply, Parse Input stamp, result callback). Stamping `'pro'` as the matrix row words it would change two template-contract assertions (the trigger reply in `rest-reviews-harness`, the body shape in `template-failure-harness`) for no behaviour: a missing variant already reads as Pro ("a template without it counts as Pro"), and ticket AC2 says the Pro template is unchanged. The stored report carries `variant` only when free, for the same reason (the legacy-read assertion stays exact).
- Slice 3: on the Pro build a ping answered by the Free template is `outdated`, with its own message; otherwise the ping said "up to date" next to the Free-variant notice.
- Slice 4: the `php` job lints `bin/` as well, since the scripts must run on the 8.0 floor.
- Review (2026-10-12, quick + overbuild + premium-leak): also wrapped the Dashboard's cron-nudge dismiss handler; the Usage & Limits hint got Free wording ("for each Author; Editors and Administrators have no limit") with the Pro text in a premium block; stale identifiers fixed in memory and in a JS comment; the stripper lost its by-reference and bodyless-function branches (no such premium function exists; a future one would fail the survivor check), the derivation its one-caller helper, the harness stub its unused `premium` flag (`is__premium_only()` is always true), `pqm_record_workflow_version()` its unused default; `docs/distribution.md` keeps the release-order facts and points to `/release` for the procedure. Rejected: the ticket-13 off-switch stays outside the wrappers (the carry rules are identical in both builds, a Boundary); `PQM_Blocks::patterns()` keeps its name; the stripper keeps the `@fs_premium_only` folder and `*__premium_only.*` file handling and the doc-comment removal (named by this plan; the HITL diff settles the docblock).
- Slice 3b (2026-10-12, after the review; owner: "go ahead" on the four recommendations): (D1) `src/kit/copy.js` `COMMAND_ORDER` keeps only the commands `window.pqmCommandLabels` lists (all six without the print) and `canRender()` requires the command to be listed, so the Free build renders no paid pill, tab or button; `tests/js/command-order.test.js`. (Q1) `settings_page_capability()` returns `pqm_manage_agents` inside a premium block only, so an Editor on the Free build gets no empty Settings page; core `manage_options` still opens it. (Q2) the License section's Free-build copy: a license active there (the SDK's `can_use_premium_code()` is true on the Free build too) says "Your Pro license is active. Install the Pro version to use it" with the Account link; the Pro and lapsed lines are set inside a premium block. (Q3) kept: a missing variant is Pro. (Q4) the main file's `elseif (pqm_fs() instanceof Freemius)` branch calls `set_basename(!pqm_fs()->is_premium(), __FILE__)` and returns, Freemius's dual-activation pattern (SDK 2.13.4: the Pro zip's folder is `post-quality-manager-premium/`, `_activate_plugin_event_hook()` deactivates the other build); the harness stub is not a `Freemius`, so the seam is unchanged. Three harness rows and a probe sub-process in `free-build-harness.php`. The probe showed the early return alone was not enough — PHP declares a file's top-level functions at compile time, so the 21 `pqm_*` helpers were still redeclared; owner chose option B (2026-10-12): every helper moved verbatim to `includes/pqm-functions.php` (plus `pqm_load_textdomain()`, its hook stays in the main file), required first. The one D4 expectation change: `release-header-harness` Row 4 reads the `load_plugin_textdomain()` body from that file.
- HITL (2026-10-13): Freemius refused the 3.5.0 zip, "The readme.txt file is missing" (it reads `Requires at least`, `Tested up to` and `Stable tag` from it). The plan had left `readme.txt` to story 38. Fix on `fix/readme-for-freemius`: a minimal `post-quality-manager/readme.txt` (tagline as short description, Tested up to 7.1), release-please bumping its `Stable tag`, `release-header-harness.php` Row 7, the `no_plugin_readme` ignore dropped from both Plugin Check jobs, a Free-build harness check that the readme ships. The HITL step resumes with the next release zip.
- No harness expectation changed in any slice (D4 never triggered); new rows were added to `render-matrix`, `rest-reviews`, `setup` and `template-guard` harnesses, plus the new `free-build-harness.php`.

## Re-plan of the React command list (D1) — built as slice 3b (owner, 2026-10-12)

Code review (quick lens, verified in the code) found D1's premise false: the React pages do not take their command list from the server. `src/kit/copy.js` `COMMAND_ORDER` hard-codes all six commands; `ProgressPills` renders one pill per entry, the detail panel's tabs are `COMMAND_ORDER` minus rewrite (`src/post-reviews/detail-panel.js` L54), and `ReviewTab` renders a Rewrite button whenever `canRender()` allows it. `hidden_commands` and `gated_commands` come from the registry, which has no paid key on the Free build, so nothing hides them. A Free site's Post Reviews would show Sources, SEO and Audit tabs, a Rewrite button and four extra pills, labelled with raw keys (the label map has no entry for them), and each of those buttons answers 400. That breaks the matrix row "Free build, Post Reviews" and AC4, and the D1 sentence in ticket 54's AC1 ("render only from server data").

Built as proposed, keeping D1's "no stripping markers in the bundle": `COMMAND_ORDER` keeps only the commands the server's registry lists, the keys of `window.pqmCommandLabels` that `enqueue_app()` prints before the bundle (all six when it is absent, as in Jest). About five lines in `src/kit/copy.js` and one Jest case (a label map without paid keys renders no paid pill, tab or Rewrite button); AC1's amended sentence then holds as written. Alternative: a `commands` list in the `GET /posts` viewer block (more code, same effect). Leaving it means the Free build ships broken paid buttons, which a directory reviewer reads as locked functionality (guideline 5).

## Open questions for the owner — answered 2026-10-12 ("go ahead" on every recommendation; built in slice 3b)

1. Free build, Editor role: `settings_page_capability()` still opens Settings for `pqm_manage_agents`, so an Editor on the Free build gets a Settings page with no section. Recommended: on the Free build the page needs `pqm_manage_settings` (one premium block in that method; the Pro build unchanged).
2. Free build with a license activated (a buyer who has not yet installed the Pro download): the License section says "Current plan: Pro — all agents … are unlocked" while that build has none. Freemius shows its own "install the Pro version" notice; recommended: leave it and check it on the HITL walk.
3. Confirm the variant deviation above, or ask for the `'pro'` stamp (two template strings and the two assertions named there).
4. Dual activation (quick lens): `memory/freemium-and-roles.md` assigned Freemius's `set_basename()` pattern to story 54, but this plan never took it in. If the Pro download installs into another folder than the Free one, activating it while the Free build is active redeclares every `PQM_*` class (fatal). Whether that can happen depends on the folder name of the Pro zip Freemius serves, which the HITL step shows. Recommended: check it there; if the folders differ, add the pattern as its own small story before story 38.

## Out of this story

- Automating the Freemius upload or the Free-build fetch in `release.yml`, and the SVN deploy: story 38, once a product-scoped token exists as a GitHub secret.
- `readme.txt` and its `fs_premium_only_begin` markers: story 38 creates the file.
- Stripping copy strings from the React bundle (D1).
- Anything about the license flow (story 44) or the pricing page checkout link (Freemius order step 6).

Status `in-progress` since 2026-10-12: the owner answered D1–D4 with the recommended options; the build runs slice by slice on this branch.
