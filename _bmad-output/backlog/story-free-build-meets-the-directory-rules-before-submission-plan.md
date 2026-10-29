---
title: 'The Free build meets the directory rules before submission'
type: 'feature'
ticket: '95'
created: '2026-10-29'
status: 'built'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_commit: 'baa5d506c61fd5d9d212c2e8f208db550f5ec4f0'
baseline_revision: 'baa5d506c61fd5d9d212c2e8f208db550f5ec4f0'
context: ['{project-root}/docs/brand.md', '{project-root}/docs/local-verification.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The Free build is not yet submittable to WordPress.org. Plugin Check warns on the translations-folder header and the loader. The readme has no Installation, External services or Changelog section, and it links no source for the minified admin app. WordPress's privacy policy guide holds no text from the plugin.

**Approach:** Remove the bundled translation path (D2) and let language packs load from WordPress's own folder. Complete the readme under 10 KB, from the story 46 draft and the site's Free vs Pro table, with a harness size check. Register the suggested privacy text on `admin_init`. Add build steps to README.md. Then prove it on the showcase: Plugin Check with `vendor/` included, the readme validator, and the privacy guide.

## Boundaries & Constraints

**Always:** Every behaviour of both builds stays the same, Review and Key Takeaways included. A site with a language pack for the plugin still shows its PHP strings and its admin app in that language. Keep the readme header values (name, tagline, tags, Requires at least 6.6, Requires PHP 8.0, Contributors `postquality` only, Stable tag and its release-please markers). Keep GPL-2.0-or-later. The readme carries no Pro-only block, so `bin/build-free.php` and the release job's Free-build comparison stay untouched. The External services text and the privacy text state the same facts, keep their "on Pro" clauses, and follow `docs/brand.md` ("PQM" never reaches a user). Run the walk on the showcase only, under the `docs/local-verification.md` rules.

**Decision (owner, 2026-10-29, Q1 A):** the Changelog is `= 4.0.0 =`, "First release on WordPress.org", plus a link to GitHub Releases. This PR merges after the 3.10.0 release PR, so the next release cut from main is 4.0.0. Keeping the entry current for later releases is deferred to story 96 (deferred-work.md). **Decision (owner, 2026-10-29):** keep the full plan (about 3,600 tokens) as one build.

**Never:** No rewritten Description (story 60). No translation content. No `.wordpress-org/` folder, deploy or submission (stories 96, 38). No Free vs Pro table in README.md. No change to CI's Plugin Check jobs (`exclude-directories: vendor` stays). The readme and the privacy text never name n8n Cloud as a path: it says "your own n8n instance", because the owner's Cloud walk gates naming it (deferred-work.md, 2026-10-29, `plan-setup-guide-n8n-self-hosting.md`). No edit to the vendored Freemius SDK. No Upgrade Notice.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Readme under the cap | `readme.txt` < 10,240 bytes | Row 7 size check passes; its label names the byte count | — |
| Readme at the cap | `readme.txt` ≥ 10,240 bytes | the size check fails, naming the byte count | harness exits non-zero |
| D2 regression | a `Domain Path` header, a `load_plugin_textdomain(` call outside `vendor/`, or a path argument on `wp_set_script_translations` | Row 4 fails, naming which | harness exits non-zero |
| Language pack present | de_DE fixtures in `WP_LANG_DIR/plugins/`, locale de_DE | PHP strings and the admin app's JSON come back in German | — |
| No language pack | nothing in `WP_LANG_DIR/plugins/` | English, no notice | — |
| Privacy guide | an administrator opens Settings → Privacy → Policy Guide | a "Post Quality Manager" section holds the suggested text | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/readme.txt` -- 2,261 B: header, Description, FAQ (cost, quota), Screenshots. Validator today: 0 errors, notes only (no Changelog, no Upgrade Notice, no donate link).
- `docs/policies/readme-external-services.md` -- story 46 draft: an External services block (~6 KB) and the privacy text. It lacks the notification address. It links Anthropic's consumer terms, though API use falls under https://www.anthropic.com/legal/commercial-terms. It names OpenAI and Google, which neither template calls (both call only `api.anthropic.com`, read 2026-10-29). The site does not copy this file.
- `postquality.com/pricing.html` (workspace clone, read only) -- the Free vs Pro table the FAQ answer follows. Free: Review, Key Takeaways, Dashboard, Post Reviews, Insert into Post. Pro adds the Rewrite, Sources, SEO and Audit agents, per-agent model, effort and instructions, Automated Reviews and the Roles tab.
- `post-quality-manager/includes/pqm-functions.php` L16–26 `postquality_load_textdomain()` -- to delete. L48: `author_quota` 20, 0 = unlimited. L470–493: the webhook payload, which carries `notification_url`.
- `post-quality-manager/post-quality-manager.php` L16 `Domain Path`; L168–169 the Translations hook; L176–185 the `is_admin()` block, where the privacy hook goes.
- `post-quality-manager/admin/class-pqm-admin.php` L241 `wp_set_script_translations(…, POSTQUALITY_PLUGIN_DIR . 'languages')`: drop the third argument so the call reads `WP_LANG_DIR/plugins`. Settings Notification URL field: L826–829.
- `templates/n8n-workflow-free.json` "Has Notification URL?" and "Send Notification" -- POST `{text: chat_message}` (the post title, link and the result summary, or the error) to the address the user sets.
- `tests/harness/release-header-harness.php` -- header comment L10–15; Row 4 L53–63 asserts the D2 consumers (rewrite it); Row 5 fixtures stay; Row 7 L89–131 readme checks (add the size check). Runner: `check()` from `tests/harness/lib/wp-stubs.php`.
- `tests/i18n/README.md` -- the site check copies the fixtures into the plugin's `languages/`; change it to `wp-content/languages/plugins/`.
- `README.md` -- no build steps. `caps-harness.php` Row 7 parses its capability table, so add the new section elsewhere. CI builds with Node 22: `npm ci`, `npm run build`.
- `vendor/freemius/includes/class-fs-plugin-updater.php` L652–685, L905–907 with `class-freemius.php` L4655–4670 -- the Pro build's updater fetches WordPress.org language packs for the slug (org-compliant and freemium). This verifies the ticket's D2 assumption in code; no pack exists to walk it.
- WordPress.org readme parser (`plugin-directory/readme/class-parser.php`, read 2026-10-29) -- an unknown section such as External services is appended to Description, and a section is trimmed at 2,500 words.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/readme.txt` -- Add sections in this order: Description (unchanged), Installation, FAQ, External services, Screenshots (unchanged), Changelog. Installation: three or four steps condensed from README.md, covering install and activate, the setup guide (n8n address, API key, Anthropic key once) and the by-hand path. FAQ: keep both answers; add one sentence to the quota answer: you set it under Settings, the default is 20, 0 means no limit (audit #3). Add "single-site only", "what Free includes / what Pro adds" (the pricing table's wording, with a link to postquality.com/pricing), and "Where is the source code?" (the admin app ships built in `admin/build/`; source and build steps at https://github.com/vincentheimann/post-quality-manager). External services: the draft, shortened with its facts kept. The n8n API calls become one paragraph. Add the notification address. Link Anthropic's commercial terms and drop OpenAI and Google. Keep the Freemius paragraph and the full-policy link. Changelog: `= 4.0.0 =`, then one or two lines ("First release on WordPress.org", what it offers in one clause), then the GitHub Releases link https://github.com/vincentheimann/post-quality-manager/releases. Target ≤ 9,000 bytes, which leaves room for later Changelog lines -- AC2, AC4, AC5
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- Add static `privacy_policy_content()`, which calls `wp_add_privacy_policy_content('Post Quality Manager', …)` with the draft's privacy text plus the notification address. Use one `__()` per paragraph and wrap each in `<p>`. Drop the path argument at L241 -- AC5, D2
- [x] `post-quality-manager/post-quality-manager.php` -- Remove `Domain Path` and the Translations hook. Add `add_action('admin_init', ['PostQuality_Admin', 'privacy_policy_content'])` to the `is_admin()` block -- D2, AC5
- [x] `post-quality-manager/includes/pqm-functions.php` -- Delete `postquality_load_textdomain()` and its section comment -- D2
- [x] `tests/harness/release-header-harness.php` -- Row 4 becomes "translations come from language packs". Assert: no `Domain Path` header; no `load_plugin_textdomain(` in plugin PHP outside `vendor/`; `wp_set_script_translations('postquality-app', 'post-quality-manager')` with no path argument; no `languages/` folder ships. Row 7 gains `check("readme.txt is under 10 KB ($n bytes)", $n < 10240)`. Update the header comment -- AC3, I/O rows 1–3
- [x] `tests/i18n/README.md` -- The site check copies both fixtures into `wp-content/languages/plugins/` (the language-pack folder) and removes them there -- D2 consumer
- [x] `README.md` -- Add a "Build from source" section after Installation: Node 22, `npm ci`, `npm run build` (writes `post-quality-manager/admin/build/` from `src/`), `npm run zip` for the Pro zip, `php bin/build-free.php <dir>` for the Free tree. Add a note to Installation step 1: a copied folder needs this build first -- AC4
- [x] `docs/policies/readme-external-services.md` -- Replace the two drafts with a pointer. The External services text now lives in `post-quality-manager/readme.txt`, the privacy text in `PostQuality_Admin::privacy_policy_content()`. Both stay in step with `privacy.md` -- one source per text
- [x] `docs/release-notes/unreleased.md` -- One paragraph: the plugin now suggests privacy-policy text under Settings → Privacy -- DoD (a change a site meets unprompted)
- [x] Records -- `docs/distribution.md`: L18 Domain Path row (none since story 95; language packs); L31, L40, L42 "story 38 (AC2)" → story 95; L53 External services row (adds the notification address and the key check); L55 Changelog row (D8); L90 legal-links row (readme.txt, opened by story 95). `memory/delivery-pipeline.md`: L35 accepted warnings (W1, W2, AIProvider; W6, W7 resolved); L37 translations; L39 "story 38 packages it" → the header, readme and root LICENSE meet guideline 1. `memory/release-readiness.md` L15/L30 "for story 38" → story 95. `docs/brand.md` L139 "(story 38 AC5)" → "(story 38 AC4)" -- ticket's Records at done

**Acceptance Criteria:**
- Given the Free build on the showcase, when `lwp plugin check post-quality-manager` runs (all categories, `vendor/` included), then it reports 0 errors. Every warning is W1/W2 on the reviews table, `AIProvider.DirectIntegration` (D3), or inside `vendor/freemius`, and its reason is recorded in Implementation Notes. None names `Domain Path` or `load_plugin_textdomain`. An error inside `vendor/` → HALT to the owner.
- Given the new readme, when it is POSTed to the WordPress.org validator (base64 `readme_contents`, as its form does), then it has no error and no warning. The only notes allowed are no Upgrade Notice (D8) and no donate link (data sheet).
- Given the readme, when every legal link is fetched, then each answers 200. OpenAI answers 403 to scripts, so the owner opens that one in a browser if it stays.

## Implementation Notes

Built 2026-10-29 by a subagent (Opus) standing in for `bmad-build`'s implementer dispatch, from the workspace root (workspace CLAUDE.md, 2026-10-16), with this plan as its only input. Every Execution task is done; **AC1 is HALTED to the owner** (errors inside `vendor/freemius`, below). AC2, AC3 and every I/O row hold.

**Deviations from the plan's wording:**
- The privacy text uses `esc_html__()` per paragraph (the escaped form of `__()`), each wrapped in `<p>`. Its last paragraph links the full policy (https://postquality.com/privacy) where the draft said only "as the plugin's privacy policy describes".
- `tests/i18n/README.md` names the JSON copy `post-quality-manager-de_DE-aa00a6c9d8ddb82857c6ba4f44b9853b.json`, the name a language pack gives it. Without a path, core reads `WP_LANG_DIR/plugins/{domain}-{locale}-{md5 of the script's path inside the plugin}.json`, here `md5('admin/build/index.js')`. Read in core's `_load_script_textdomain_from_src()` on the showcase (WordPress 7.1.4).
- `npm run build` refreshed `post-quality-manager/admin/build/` (git-ignored) before the Free build, because it was older than `src/`.

**Readme:** 8,977 bytes, 1,482 words, LF, after the review fixes (8,793 bytes at the walk and the validator run). Sections in order: Description (unchanged), Installation, FAQ, External services, Screenshots (unchanged), Changelog. No "n8n Cloud", OpenAI, Google or "PQM".

**Verification (2026-10-29):**
- `php -l` is clean on `post-quality-manager.php`, `includes/pqm-functions.php`, `admin/class-pqm-admin.php` and `tests/harness/release-header-harness.php`. The harness sweep is green on all 22 files; `release-header-harness.php` has 32 checks (31 before). `npm run test:unit` passes: 10 suites, 174 tests.
- I/O rows 1–3 were run on a scratch copy of the harness and the tree. A `Domain Path` header, a `load_plugin_textdomain(` call in `includes/` (one in `vendor/` is ignored), a path argument and a `languages/` folder each give exactly one failure that names it. The readme at 10,240 bytes fails as "(10240 bytes)", and at 10,239 bytes it passes. In place, the readme was padded to 10,300 bytes (1 failure), then restored; its sha1 is identical and `git diff` is unchanged.
- Validator (POST, base64 `readme_contents`): HTTP 200. Notes only: "No == Upgrade Notice == section was found" and "No donate link was found". No error, no warning (the old readme also noted the missing Changelog).
- Links (Python standard library, GET): GPL, n8n terms and privacy, Anthropic commercial terms and privacy, Freemius terms and privacy, postquality.com /privacy and /pricing/ all answer 200. The repository and its /releases answer 404 while the repository is private (Q8 A: it opens with entry 4.8, and story 38 checks it).
- Showcase walk (`docs/local-verification.md`): backup, the Free build installed (it reads "Free build", with no `languages/`, no `Domain Path` and an 8,793-byte readme), the checks below, `debug.log`, restore. Step 11 read "plugin list: same", "tables, reviews, users, log: same" and "plugin files, wp-config.php: same". `debug.log` held 118,385 lines, all from WP-CLI itself. The scratch folder was deleted. No listener was needed (no trigger).
- **Plugin Check 2.1.0, installed Free build, every category:**
  - **`vendor/` excluded** (Plugin Check's default, as in CI): 0 errors, 20 warnings.
    - W1: 10 `WordPress.DB.DirectDatabaseQuery.DirectQuery`, and W2: 9 `…NoCaching`, all in `includes/class-pqm-db.php`. Accepted: the plugin's own reviews table has no WordPress API, and these are admin reads of live review state.
    - 1 `PluginCheck.CodeAnalysis.AIProvider.DirectIntegration` at `includes/class-pqm-n8n.php:1065` (D3). Accepted: it checks a key once and sends no prompt; `wp_ai_client_prompt()` needs WordPress 7.0, above the 6.6 floor.
    - None names `Domain Path` or `load_plugin_textdomain` (W6, W7 resolved).
    - The Plugin Repo category alone, which the submission form runs: 0 errors, 19 warnings (W1, W2).
  - **`vendor/` included**: `--exec` adds a `wp_plugin_check_ignore_directories` filter at priority 999 that drops `vendor` from Plugin Check's default ignore list. Result: **525 errors** and 1,240 warnings, every one inside `vendor/freemius` (errors in 58 files). The plugin's own 20 warnings are unchanged. The Plugin Repo category alone with `vendor/` gives the same 525 errors. The errors:
    - 514 `WordPress.Security.EscapeOutput.OutputNotEscaped` (most in `templates/debug.php`, `includes/fs-plugin-info-dialog.php`, `templates/account.php`, `templates/account/partials/site.php`, `templates/forms/deactivation/form.php`, `templates/connect.php`);
    - 7 `…ExceptionNotEscaped`;
    - 2 `plugin_updater_detected` ("Plugin Updater detected. These are not permitted in WordPress.org hosted plugins": class `FS_Plugin_Updater` in `includes/class-fs-plugin-updater.php`, and `site_transient_update_plugins` in `includes/class-freemius.php`);
    - 1 `PluginCheck.CodeAnalysis.WriteFile.PluginDirectoryWrite` (`includes/class-freemius.php:25692`);
    - 1 `PluginCheck.Security.DirectDB.UnescapedDBParameter` (`includes/class-fs-logger.php:615`).

    The warnings are mostly `PrefixAllGlobals` (1,221), plus 2 `update_modification_detected`. **HALT (AC1): the owner decides before the build continues.** The SDK stays unmodified (chore 89), and whether the submission scan includes `vendor/` is not known.
- **Finding (tooling, its own ticket):** `lwp plugin check post-quality-manager`, run from the repository root as `docs/local-verification.md` § Variables sets it, checks the repository's own `post-quality-manager/` source folder, not the installed build. When a directory of that name exists in the current folder, Plugin Check takes its argument as that path (`includes/Checker/Abstract_Check_Runner.php` L414, L735). The ticket's 2026-10-26 fact (22 warnings) may therefore describe the Pro source. This walk ran Plugin Check from an empty scratch folder; the paths it reported were the installed build's. Fix candidates for `docs/local-verification.md` § Other checks (untested): pass the basename `post-quality-manager/post-quality-manager.php`, or run from outside the repository.
- **Privacy guide** (I/O row 6): with `WP_ADMIN` defined through `--exec`, `has_action('admin_init', ['PostQuality_Admin', 'privacy_policy_content'])` is true. With the other `admin_init` callbacks removed, `do_action('admin_init')` leaves one section in `WP_Privacy_Policy_Content::get_suggested_policy_text()`: "Post Quality Manager", 5 paragraphs, 2,302 bytes, no "n8n Cloud", no "PQM". The browser view of the guide stays the owner's pass.
- **Language packs** (I/O rows 4–5), under a `locale` filter set to de_DE, through the real `PostQuality_Admin::enqueue_app()` (by reflection) and `wp_scripts()->print_translations()`:
  - No pack: "Post Reviews | Settings", no script translations printed.
  - Both fixtures in `WP_LANG_DIR/plugins/` (the JSON under the md5 name): "Beitragsprüfungen | Einstellungen", and the app's translations hold "Durchschnittliche Bewertung".
  - Fixtures removed: English again, with no notice in `debug.log`.

  `WP_LANG_DIR` did not exist before the walk. The two copies and the two folders the walk created were removed, and the restore check confirmed it.
- **Pro build's language packs:** read in the SDK only (`class-fs-plugin-updater.php` L652–685; `can_fetch_data_from_wp_org()` L905–907: org-compliant and freemium). No pack exists to walk.

**AC1 halt resolved (orchestrator, then the owner, 2026-10-29):**
- **Plugin Check's default (read on the showcase, Plugin Check 2.1.0):** `Plugin_Request_Utility::get_directories_to_ignore()` skips `.git`, `vendor`, `node_modules`, `vendor_prefixed` and `vendor-prefixed` unless the `wp_plugin_check_ignore_directories` filter changes the list.
- **The submission scan (read in WordPress.org's source, `plugin-directory/jobs/class-plugin-scan.php` `run_plugin_check()`, trunk on 2026-10-29):** the upload form calls `wp plugin check --error-severity=7 --warning-severity=6 --include-low-severity-errors --exclude-checks=prefixing --categories=plugin_repo --format=json --slug=<slug> <path>`. That is the Plugin Repo category with the default ignore list, so `vendor/` is not scanned. With that setting the Free build reports 0 errors (above).
- **Decision (owner, 2026-10-29): accept and record.** The 525 errors inside the unmodified Freemius SDK (chore 89) are known and need no change. AC1 counts as met for the plugin's own code. The manual review and story 38's real submission scan are the final word.
- **Tooling finding:** the `lwp plugin check` path finding is confirmed in the code. `get_check_context()` takes the argument as a path whenever `is_dir()` finds it in the current folder (L735–736), and `bin/local-wp.py` runs WP-CLI in the caller's folder. Logged in `deferred-work.md` for its own fix.

**After the review patches (orchestrator, 2026-10-29):**
- The step-04 patch round went back to the same Opus implementer. Seven fixes, rows 2–4 and 6–9 of the triage log.
- Re-verified on the patched tree:
  - `php -l` is clean on the 5 touched PHP files. All 22 harness files pass, and `release-header-harness.php` passes 32 of 32. `npm run test:unit`: 10 suites, 174 tests. Every changed file is LF.
  - The readme validator, on the 8,977-byte text: notes only (no Upgrade Notice, no donate link). Sections in order: Description, Installation, FAQ, External services, Screenshots, Changelog.
  - The disclosure links all answer 200 (n8n, Anthropic, Freemius, postquality.com /privacy and /pricing/). GitHub gives 404 while the repository is private. gnu.org refused the script with 403 this time; that URL is the unchanged license header and answered 200 earlier.
- The privacy text was rendered with stub functions: 5 paragraphs, the policy URL passed through `sprintf()` and left out of the translatable string, and no stray `%`. The showcase walk is not re-run: the patch changed strings and one `sprintf()` inside a function the walk had already shown registered and printing.
- **Site copy:** `docs/policies/privacy.md`'s new bullet goes to postquality.com's `privacy.md` through a site PR (workspace CLAUDE.md: change `docs/policies/` first).
- **Deviation from the workflow:** the build ran from the workspace root with the skill rendered by hand (workspace CLAUDE.md, 2026-10-16), and every subagent ran on Opus (owner, 2026-10-26). Step 05's "NEVER auto-push" and the house override "the user commits" are overridden by the owner's rule of 2026-10-29: the agent commits, pushes and opens the PR without asking, and the merge stays the owner's. PR #212 records that rule in the repository.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-10-29, review loop 0):** one lens, `quick`, on Opus (the owner's 2026-10-26 rule), on the diff from baa5d50. 9 findings: high 0, medium 3, low 4, false 0, maybe-false 0, plus 2 rejected because their fix is an edit to this plan. Routes: 7 patch, 1 defer, 2 reject.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | AC1 is unmet as written; the owner's decision sits only in Implementation Notes | — | reject | The fix is an edit to this plan, and the workflow rejects such findings. The owner's "accept and record" (2026-10-29) is recorded in Implementation Notes, with the source evidence that the submission scan skips `vendor/`. |
| 2 | readme External services intro: "services that you set up and control … nothing is sent to the plugin's author" contradicts its own Freemius paragraph | medium | patch | readme L56 vs L66: the license check and opt-in usage data go to Freemius and reach the author, independent of a webhook address. A text the directory review reads for disclosure accuracy. Fix: scope the intro to post content (n8n and Anthropic), and say Freemius gets nothing before an opt-in or a license key. |
| 3 | The privacy text understates who gets content and when: "the AI provider you chose", "when a review is run" | medium | patch | `postquality_n8n_payload()` goes out for every command, scheduled runs on Pro included; both templates call only `api.anthropic.com`. This text is the one administrators paste into their own policy. Fix: paragraph 1 names every command, the Pro schedule and Anthropic, as the readme does. |
| 4 | `docs/policies/privacy.md` does not disclose the notification post, though the readme and privacy text now do, and the new pointer says all three stay in step | medium | patch | privacy.md L13 lists the notification address only as a field sent to n8n. The full policy says less than its summary in the plugin. Fix: one bullet in § "What the plugin sends", and Last updated 2026-10-29. The site copy (postquality.com `privacy.md`) gets the same bullet through its own PR (workspace CLAUDE.md: change `docs/policies/` first). |
| 5 | The templates' notification nodes read `$json.notification_url` / `$json.chat_message` after "POST Result to WordPress", whose output is the save-review response | medium (unverified in n8n) | defer | Read 2026-10-29. In both templates "Has Notification URL?" follows that HTTP Request node (v4, options `timeout` only), which outputs `{success, id, message}` (`class-pqm-rest-api.php` L564–568). So the Notification URL setting likely never fires. Pre-existing, not caused by this change. The disclosure states the designed flow (over-disclosure is safe). A run in n8n settles it. |
| 6 | The readme writes "Settings → …" where the brand rule wants "Post Quality → …" | low | patch | `docs/brand.md` L123: in-copy cross-references use "Post Quality → n8n Workflow". Readme L32, L40, L64 say "Settings", which a reader takes for WordPress's own menu. The tab names are "Connection" and "Usage & Limits" (`class-pqm-admin.php` L709, L718). Fix: "Post Quality → Settings → …". |
| 7 | Readme Installation: step 2 says the guide "runs your first review"; step 3 "By hand instead" is numbered as a next step | low | patch | `src/setup/steps.js` L511: the guide's step is "Run your first review", which the user runs (README.md says "then you run your first review from the guide"). "By hand" replaces step 2 rather than following it. Fix: the user runs it; fold "by hand" into step 2; renumber. |
| 8 | Three records still describe the pre-D2 translation path | low | patch | `tests/i18n/post-quality-manager-de_DE.l10n.php` L2–3 ("read the plugin's languages/ folder"); `memory/delivery-pipeline.md` L37 ("copies it as `…-postquality-app.json`"); `memory/naming.md` L15 ("copies it under the handle's name"). A handle-named file in `WP_LANG_DIR/plugins` is not read without a path argument (walk, 2026-10-29). Fix: name the language-pack folder and the md5 file name. |
| 9 | Implementation Notes say the privacy text "links" the policy; the URL is plain text inside a translatable string | low | patch (code part); reject (notes part) | The notes part is a plan edit: rejected. Code part: the URL sits inside `esc_html__()`, so a translation can change or drop it. A direct correction keeps it out of the string: `sprintf()` with `%s`, as the WordPress i18n guidance asks. Plain text is fine in the Policy Guide, which administrators copy from. |

## Design Notes

- Notification address: the user picks the service behind it (a chat webhook), so no terms link is possible. The text says what the workflow posts there and when, and that the service's own terms apply.
- Source link: the FAQ, not the Description, because the Description stays as story 41 left it (Q7 A). The repository URL opens once entry 4.8 makes it public (Q8 A); story 38 checks it.
- 10 KB means 10,240 bytes; the harness reads the file's byte size. Description plus External services stay far under the parser's 2,500-word trim.
- The draft file becomes a pointer, so each text has one source. Its readers (the data sheet, older plans) keep a valid link.
- Skipped: a harness check for the readme sections. The validator covers it once, and the size check guards the regression that matters. Add it if a section goes missing in review.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness 0 failing
- `npm run test:unit` -- expected: green
- Copy `readme.txt` aside, pad it past 10,240 bytes, run `php tests/harness/release-header-harness.php`, then restore it and confirm `git diff` is unchanged -- expected: a single failure that names the byte count
- Validator POST, then the legal links check (Python standard library) -- expected: as the ACs above

**Manual checks (if no CLI):**
- Owner: start "Post Quality Showcase" in Local. Agent: the `docs/local-verification.md` walk (backup, Free build install, `lwp plugin check`; the privacy guide through `lwp eval`, loading `class-wp-privacy-policy-content.php` and reading `WP_Privacy_Policy_Content::get_suggested_policy_text()` after `admin_init`; the de_DE fixtures in `WP_LANG_DIR/plugins/` read under a `locale` filter with `__()` and `load_script_textdomain()`, then removed), then restore. The comparisons must read "same".
