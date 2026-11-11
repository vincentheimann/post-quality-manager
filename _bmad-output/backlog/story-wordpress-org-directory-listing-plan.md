---
title: 'The plugin is accepted in the WordPress.org plugin directory'
type: 'chore'
ticket: '38'
created: '2026-11-11'
status: 'in-progress'
baseline_revision: 'b2294d3781c434143a00f54ff0a30122d9fe722f'
route: 'full'
route_source: 'pinned'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context:
  - '{project-root}/docs/local-verification.md'
  - '{project-root}/docs/distribution.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** 4.0.0's Free build is public on GitHub only, so the directory's exposure (UV7 A1–A2) is missing. No release has had the pre-production pass yet: both builds on a clean WordPress 6.6 site, plus every plan's manual checks that still describe shipped behaviour.

**Approach:** Run the pass on the owner's clean Local site as three walks, each in `docs/local-verification.md`'s frame:
1. Free, carrying the inventoried manual checks for the Free build.
2. Pro, carrying the Pro checks.
3. The rows that need another plugin or an older build.

Then the owner submits the same Free build, and the agent carries the review to approval. No plugin code changes: a failure becomes its own ticket and a patch release.

## Decisions (owner, 2026-11-11)

- **Q1 a, the site:** "WordPress66".
  - The owner switches its PHP to 8.1.29 in Local.
  - The agent deletes 4.0.0 as an administrator (`--user` on `deactivate` and `uninstall`).
  - Then the agent shows nothing of it remains: no `postquality_*` or `fs_*` option, table, capability, user meta or cron event, and no other plugin. Any trace left means a fresh site.
- **Q2 b, the license:** a sandbox purchase, as story 44 did, made by the owner's Claude in Chrome from an agent-written prompt.
  - The owner activates the license on the site by its key; the key never reaches the agent.
  - The owner then expires it in the Freemius dashboard for the three lapse rows.
  - The purchase, the license and the install go on chore 81's list.
- **Q3 a, n8n:** a disposable n8n on this PC with the owner's Anthropic key, read from `.secrets/anthropic-key.txt` in the workspace and never printed. The walk's fictional sample posts go to Anthropic.
- **Q4 a, the third walk:** rows that need another plugin (Yoast SEO, Classic Editor, Plugin Check) or an older build run in a third walk after the two. Each plugin or older build is installed there only and removed by that walk's restore. These rows are recorded as outside the "no other plugin" condition.
- **Plan size:** the whole plan is kept, about 3,100 tokens.

## Boundaries & Constraints

**Always:**
- Builds of release v4.0.0 only; a newer release replaces both, on the owner's word.
  - Free: the Release asset `post-quality-manager-4.0.0.zip`, sha256 `776cd175f513597c0282cdaaedd47a6a6b9f320c29710c3a28c1bc28d1b14c3b`, checked after download. `release.yml` L267–270 copies Freemius's Free zip byte for byte. This is also the zip the owner submits.
  - Pro: the artifact `post-quality-manager-4.0.0-pro.zip` of release run 43019155750 (kept until 2027-02-09).
- Each walk: backup → build → checks → `debug.log` → restore → three "same" (`docs/local-verification.md` steps 1–11).
- Files, the Edge profile, `TEMP`/`TMP` and PHP's `sys_temp_dir` stay in the session scratchpad.
- The listener binds 127.0.0.1:8765. Nothing site-specific goes into the repository.
- Plugin Check runs from the scratchpad, never from the repository root. It is installed for its run only, after the walk's `debug.log` read.
- Never print a file that holds secrets: compare `wp-config.php` with `cmp` only. Stop only the processes this session started, by recorded PID.
- A failing check is filed through `bmad-ticket` and blocks the submission until a release fixes it. Every result names the environment it ran on.
- Owner steps (hitl):
  - start the site for each walk;
  - look at the plugin's pages while each walk is paused;
  - make the sandbox purchase through Claude in Chrome, activate the license by its key, and expire it;
  - set the `postquality` display name;
  - turn on two-factor sign-in;
  - submit, and read the slug the form shows;
  - answer the review team.

**Never:**
- Change plugin code, `readme.txt` or `release.yml` in this story.
- Walk on the production site, its copy or the showcase.
- Hold the WordPress.org password or a license key.
- Commit a capture from the clean site.
- Create SVN credentials, `.wordpress-org/` or any deploy: those are story 96's.
- Edit draft #7's files.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected | Error handling |
|---|---|---|---|
| Free, nothing set | Free build installed and activated, opt-in skipped, no webhook; every plugin page loaded; one WP-Cron run | the request logger records no outgoing request whose backtrace enters the plugin's folder (SDK included) | a logged request → ticket |
| Free agents | webhook = listener; Review, then Key Takeaways, as an administrator | two captures carrying `X-PQM-Token`; `sources`, `seo`, `audit`, `rewrite` answer 400 `rest_invalid_param` | — |
| Free pages | every plugin page in the browser | no Pro command or setting; Settings' "Get Pro" section is the only upsell | — |
| Readme links | the 11 unique URLs in the Free build's `readme.txt` | each ends with a 200 on a page that asks for no sign-in | a scripted GET refused (403) → retried in Playwright |
| Form scan | the directory form's command on the installed Free build | 0 errors | an error → ticket |
| Pro | Pro build with a license; the four paid agents; walk step 8 | four captures; the cron run captures up to 5 reviews | — |
| Log | `debug.log` after each walk | no line from the plugin's folder (WP-CLI's own lines excluded) | a line → ticket |
| Restore | after each walk | three "same"; the logger, the sample posts and Plugin Check are gone | a difference → restore again, else stop and report |
| Busy site | port 8765 busy, `pq-smoke-*` users, or a must-use file already present | stop and ask the owner | — |
| Slug | the form shows a slug other than `post-quality-manager` | the owner corrects it with the review team before approval | — |

</frozen-after-approval>

## Code Map

- `bin/local-wp.py` -- WP-CLI on a site found by its Local name. It reached "WordPress66" on 2026-11-11, so the previous session's `wpl.sh` is not needed.
- `bin/webhook-capture.py` -- the listener (walk step 3).
- `docs/local-verification.md` L56–201 -- the frame, used verbatim. L205–207: Plugin Check from `$S`, plugin deletion with `--user`, plans' manual checks in the same frame. L209–285: the must-use file pattern (Preview 0 and 4) the logger reuses.
- `node_modules/playwright-core` -- browser rows: headless Edge, a session cookie WP-CLI mints for user 1 (`memory/local-verification.md` L19–21).
- `_bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission-plan.md` L119–125 -- the form's scan, from WordPress.org's source:
  `wp plugin check --error-severity=7 --warning-severity=6 --include-low-severity-errors --exclude-checks=prefixing --categories=plugin_repo --format=json --slug=post-quality-manager <path>`.
  It does not scan `vendor/`. The owner accepted the SDK's 525 errors on 2026-10-29.
- `post-quality-manager/readme.txt` -- 11 unique links, 9,442 bytes, `Tested up to: 7.1`, four tags.
- `docs/distribution.md` -- records at done: L26–30, L39, L58, L146. L30 and L58 still say story 38 creates the SVN secrets and uploads the assets; both have been story 96's since 2026-10-29.
- `memory/delivery-pipeline.md` L57 and `memory/local-verification.md` -- records at done.
- Scratchpad `inv/result-{a,b,c,d}.md` -- criterion 3's inventory, moved into this plan by task 1. Counted from the tables on 2026-11-11: 115 plan files, 345 rows.
  - States: 324 shipped, 19 retired (each names its ticket), 1 n/a, and 1 unclear (chore 87's look before the push: a one-time step that leaves nothing on a site, so it is recorded as n/a).
  - Of the shipped rows, 16 run only outside the site (GitHub, Freemius's dashboard, postquality.com). They keep their last recorded evidence and are not re-run.
  - The other 308 run on the site: 157 need only WP-CLI or a browser, 79 need n8n, 40 need Freemius or a license, 36 need the owner's eye.
  - By build: 200 both, 57 Pro, 28 Free, 39 n/a.
- Facts the inventory surfaced:
  - Without a license, the Pro build shows the SDK's activation screen instead of the plugin's pages (story 65 plan L92), so the license comes first in the Pro walk.
  - The "Free site" rows of stories 13, 42 and 55 describe sections that now exist only in the Pro build on the Free plan. They run on the Pro build after its license is deactivated.
  - Dual activation (story 54 B9) cannot be shown through `wp plugin activate`; it needs the Plugins screen.
  - Some rows need another plugin: Yoast SEO (5 rows), Classic Editor (1), Plugin Check (6).
  - About 12 rows need an older build or older state first (3.9.0 → the story 91 upgrade, bug 94, story 65's update paths, an older template).
  - Two rows need language packs, and three need a lapsed license (bug 93, story 44).

## Tasks & Acceptance

**Execution:**
- [x] Pass list -- this plan's `## Pre-production pass` section: the merged inventory (plan, check, build, means, `shipped` or `retired-by-<ticket>` with evidence) -- criterion 3's Given.
- [x] Site gate (hitl) -- Decision Q1:
  1. The owner switches the PHP and starts the site.
  2. The agent deletes 4.0.0 as an administrator and runs the leftover check.
  3. The agent checks: WordPress 6.6.x, PHP 8.1.x (`cli info`), MySQL, an empty `plugin list`, no `pq-smoke-*` user, nothing on port 8765.
  
  This is criterion 1's Given.
- [x] Builds -- `gh release download v4.0.0` and `gh run download 43019155750 -n post-quality-manager-4.0.0-pro.zip`, into the scratchpad. Record each sha256; the Free one must equal the release digest.
- [ ] Free walk -- one frame:
  1. install and activate the Free asset;
  2. add the logger;
  3. Skip the opt-in in the browser;
  4. matrix rows 1–5 and 7;
  5. the Free and either-build checks of the pass list;
  6. pause for the owner's look;
  7. the form scan, last;
  8. restore.
- [ ] Pro walk -- one frame, from the same state as the Free walk:
  1. the Pro build, then the owner activates the sandbox license (Decision Q2);
  2. matrix row 6, the pass list's Pro checks and walk step 8;
  3. the owner expires the license, and the three lapse rows run;
  4. the license is deactivated on the Account page;
  5. the "Free site" rows run on the Pro build, now on the Free plan;
  6. `debug.log`, then restore.
- [ ] n8n rows -- a disposable n8n (`docs/local-verification.md` "A disposable n8n") inside the walk whose build each row names, provisioned with the key file (Decision Q3). It is stopped and deleted at that walk's end, and what it created is removed by id.
- [ ] Third walk -- one frame for the rows that need another plugin or an older build (Decision Q4). Each plugin or older build is installed from its source (the directory, or the GitHub Release asset with its digest checked) and removed by the restore.
- [ ] Results -- each result goes into the pass list, and each failure into a ticket. The pass list ends with "submission: clear" or "submission: blocked by <tickets>".
- [ ] Submission prep -- these checks are the story Notes' open questions at submission:
  - the slug is still free (`wordpress.org/plugins/post-quality-manager/` answers 404, and the plugins API reports none);
  - `Tested up to` is the current major (api.wordpress.org's version check);
  - the tag "content quality" is checked against the directory's tag page;
  - the data sheet's CHECK markers.
- [ ] Owner (hitl) -- display name, two-factor sign-in, submit the checked Free asset, read the slug the form shows.
- [ ] Review loop -- the agent drafts a reply for each email and files each finding through `bmad-ticket`. Each fix ships in a patch release, and that release's checked Free build goes with the reply.
- [ ] Records at done -- `docs/distribution.md`:
  - row 0 (submitted and approved, with dates);
  - the slug row (approved);
  - L30 and L58 pointed to story 96;
  - the account and tag rows checked.
  
  Also: `memory/delivery-pipeline.md` L57 names stories 95, 38 and 96; `memory/local-verification.md` gets the clean site's facts; the Freemius and WordPress.org records the pass created are listed for chore 81.

**Acceptance Criteria:**
- Given the pass list, when it is read, then every manual check of the inventoried plans has a state with its evidence, and every shipped check has a result on the build it names.
- Given the review team's approval email, when it is read, then it names Post Quality Manager and `post-quality-manager`.
- Given each walk has ended, then the site reads "same" three times and the scratchpad holds nothing of the walk.

## Design Notes

- **Why a must-use logger.** Browser page loads never go through WP-CLI, so a `--require` logger cannot see them. A `pre_http_request` filter with a backtrace check tells the plugin's own requests (its folder, SDK included) from core's (update checks, the dashboard feed). It logs the method, host, path and attribution only, never headers or bodies. The restore removes it, the way Preview 4 removes the preview file.
- **Sample posts.** Each walk creates its own fictional posts after the backup, so the restore removes them and the clean baseline stays as the owner made it.
- **Plugin Check last.** It is itself a plugin, so its run comes after the walk's `debug.log` read and never overlaps it.

## Verification

**Commands:**
- The walk steps as `docs/local-verification.md` gives them -- expected: its expected outputs, and three "same" per walk.

**Manual checks (if no CLI):**
- The owner looks at the plugin's pages during each walk (the E rows). The owner reads the slug the form shows.

## Pre-production pass

Criterion 3's list (task 1, 2026-11-11): 345 manual checks from 115 plans. Four Opus agents inventoried them read-only against main b2294d3, which carries v4.0.0's plugin code unchanged (`git diff v4.0.0 b2294d3` is empty for `post-quality-manager/`, `src/` and `package.json`). The agent checked that every commit a retired row cites exists and names that ticket.

- **Means:** W = WP-CLI, REST or options through `bin/local-wp.py`; B = a browser, functional (Playwright); E = the owner's eye; N = the disposable n8n with the owner's key; F = Freemius (license, opt-in); X = outside the site.
- **Result:** `pending` until its walk runs. `not run (retired)` names the retiring ticket in the state column. `not re-run` marks a check outside the site, whose last evidence is kept.
- **Counts at task 1:** 309 pending, 18 retired, 2 n/a, 16 outside the site.

| # | plan | ticket | check | build | means | state | evidence | result |
|---|---|---|---|---|---|---|---|---|
| P001 | bug-a-lapsed-pro-license-shows-free-with-no-renew-link-plan.md | 93 | L126: sandbox license expired, SDK sync run; Settings → License shows lapse copy with "Renew Pro"; SDK notice carries the link | Pro | W F | shipped | `admin/class-pqm-admin.php:827`; `post-quality-manager.php:144-152` (`sticky_message_license_expired` filter). The Freemius fixture was removed after the 2026-10-29 walk, so the owner needs a new sandbox license and an expiry | pending |
| P002 | bug-ai-tab-shows-custom-model-field-on-phones-plan.md | 107 | L67: Settings → AI at 400/1280 px: Custom model ID field hidden until "Custom model ID…" is chosen | Free | B | shipped | `admin/css/pqm-admin.css:20` (`.pqm-hidden … !important`); `admin/class-pqm-admin.php:893-895` | pending |
| P003 | bug-deactivation-feedback-opts-in-a-site-that-skipped-plan-2.md | 90 | L152: opted in, answered form, opted out of "Diagnostic Info": no form; delete → uninstalled, no reason in dashboard | Free | B W F | shipped | `post-quality-manager.php:128-131` (form only while sharing), `:164` (`uninstall_` hook at priority 9); `includes/class-pqm-license.php:85-104` | pending |
| P004 | bug-deactivation-feedback-opts-in-a-site-that-skipped-plan-2.md | 90 | L152: same with "Communication" opted out: no form; delete → nothing new for the site in Freemius | Free | B W F | shipped | same lines; ticket 90 Notes, path C walked 2026-10-28 | pending |
| P005 | bug-deactivation-feedback-opts-in-a-site-that-skipped-plan.md | 90 | L107: Skip at activation, Deactivate → no form; delete → nothing new in the Freemius dashboard | Free | B W F | shipped | `post-quality-manager.php:128-131`; ticket 90 Notes, path A walked 2026-10-27 | pending |
| P006 | bug-deactivation-feedback-opts-in-a-site-that-skipped-plan.md | 90 | L107: an opted-in site → the deactivation form shows | Free | B F | shipped | same filter; the opt-in needs the owner to confirm the email | pending |
| P007 | bug-failed-n8n-run-stored-as-never-analyzed-plan.md | 31 | L103: Review with an invalid Anthropic key → Post Reviews shows "Could not complete" with the API's reason | both | N B | shipped | `src/kit/copy.js:87`; template Parse nodes read `resp.error?.error?.error?.message`. Template is now v11 (the plan said "import v3") | pending |
| P008 | bug-failed-n8n-run-stored-as-never-analyzed-plan.md | 31 | L103: Rewrite with a broken model → the post stays published | Pro | N F W | shipped | `templates/n8n-workflow.json`: the "Rewrite Parsed?" node skips "Update WP Post (Rewrite)". Rewrite is paid (`includes/class-pqm-features.php:126`), so it needs a license | pending |
| P009 | bug-failed-n8n-run-stored-as-never-analyzed-plan.md | 31 | L103: n8n Workflow page on a site that recorded an older workflow version shows the update notice | both | W B | shipped | `admin/class-pqm-admin.php:1585-1589`. Copy is now "Your workflow is version %1$d; this plugin needs %2$d." (11) | pending |
| P010 | bug-failed-start-copy-names-a-step-non-admins-cannot-take-plan.md | 33 | L93: Webhook at 127.0.0.1:9, Run Review: Author gets "Ask an administrator…", admin gets "Check your Webhook URL…" | both | W B | shipped | `includes/class-pqm-rest-api.php:733-735` | pending |
| P011 | bug-failed-start-copy-names-a-step-non-admins-cannot-take-plan.md | 33 | L94: workflow inactive (HTTP 404): Author and the Review selected line ask an administrator; admin gets "Check that the workflow is active" | both | N W B | shipped | `includes/class-pqm-rest-api.php:776-778`; `src/kit/runs.js:151`. Any URL that answers 404 can stand in for an inactive n8n workflow | pending |
| P012 | bug-failed-start-copy-names-a-step-non-admins-cannot-take-plan.md | 33 | L95: URL and workflow restored: a normal run is unchanged | both | W B | shipped | `src/kit/runs.js:179-183` ("requested" copy); the listener (200) stands in for n8n | pending |
| P013 | bug-first-admin-page-after-the-stored-names-upgrade-is-refused-plan.md | 94 | L111: build ≤ 3.9.0 updated to current; n8n Workflow page, then Dashboard, as the first admin request open | Free | W B | shipped | `includes/class-pqm-activator.php:142-152` (`for_site()` reload in `maybe_upgrade()`). Needs a pre-story-91 build (v3.9.0) installed first: an upgrade path, not a clean install | pending |
| P014 | bug-model-select-cuts-the-selected-label-plan.md | 116 | L62: Settings → AI captures at 790 and 1280 px: the model select labels read whole | Pro | E B | shipped | `admin/css/pqm-admin.css:63-64` (`max-width: 100%`) | pending |
| P015 | bug-model-select-cuts-the-selected-label-plan.md | 116 | L62: Custom selected at 1024/1280 px: Custom model ID field beside or under the select, never over it | Pro | E B | shipped | `admin/css/pqm-admin.css:62-64` | pending |
| P016 | bug-notification-url-never-gets-a-report-plan.md | 97 | L123: `score` with callback and notification URLs → 1 callback and 1 notification carrying the score report | both | N X | shipped | Both templates: "POST Result to WordPress" → "Has Notification URL?" → "Send Notification". The plan ran it on the owner's n8n (X); a disposable n8n plus a capture workflow can stand in | pending |
| P017 | bug-provisioning-lock-passes-two-simultaneous-runs-plan.md | 82 | L112: two `POST /n8n/provision` in the same millisecond, bogus key → one 400, one 409; lock option gone | both | W | shipped | `includes/class-pqm-n8n.php:113`, `:856-915` (INSERT IGNORE claim). Option is now `postquality_n8n_provisioning`. Needs a stored n8n connection; the 400 comes from api.anthropic.com | pending |
| P018 | bug-provisioning-timeout-leaves-the-workflow-and-says-nothing-was-left-plan.md | 112 | L112: Start over with removal → n8n lists no workflow or credential for the site | both | N W | shipped | `includes/class-pqm-n8n.php:1638-1675` (`start_over()`); `includes/class-pqm-rest-api.php:1577` ("removed here and in n8n") | pending |
| P019 | bug-provisioning-timeout-leaves-the-workflow-and-says-nothing-was-left-plan.md | 112 | L112: a timed-out first creation → the message says what was left (only if a fresh n8n reproduces it) | both | N | shipped | `includes/class-pqm-n8n.php:1291-1298`. The plan rests on harness rows; it cannot be reproduced on demand | pending |
| P020 | bug-reply-without-results-runs-forever-or-gets-a-made-up-score-plan.md | 121 | L147: all five commands once each on workflow v11 against a disposable n8n; each stores its result | Pro | N F W B | shipped | `post-quality-manager.php:95` (`WORKFLOW_VERSION` 11); passed 2026-11-10 (Implementation Notes). The owner activates the license before and deactivates it after | pending |
| P021 | bug-roles-table-cut-on-phones-plan.md | 108 | L82: Settings → Roles at 360–1280 px: no page overflow, frame scrolls, checkboxes toggle; table fills ≥ 782 px | Pro | B F | shipped | `admin/class-pqm-admin.php:1323` (`.pqm-roles-scroll`); `admin/css/pqm-admin.css:58`. On an unlicensed Pro build the table sits in a disabled fieldset (`:1313-1315`), so checking the toggles needs a license | pending |
| P022 | bug-run-error-missed-when-n8n-unset-or-unreachable-plan.md | 32 | L107: webhook URL cleared: Post Reviews shows "n8n is not connected yet…", runs disabled; Author told to ask an admin | both | W B | shipped | `src/kit/not-connected-notice.js:28-50`; `src/post-reviews-table.js:35` | pending |
| P023 | bug-run-error-missed-when-n8n-unset-or-unreachable-plan.md | 32 | L108: URL at 127.0.0.1:9: Run Review → dismissible warning in the panel; the same notice in a Dashboard queue row | both | W B | shipped | `src/post-reviews/detail-panel.js:403-408`; `src/dashboard/fix-next-queue.js:105-113`; `includes/class-pqm-rest-api.php:733` | pending |
| P024 | bug-run-error-missed-when-n8n-unset-or-unreachable-plan.md | 32 | L109: real URL restored: Run Review shows the "requested" toast, no notice | both | W B | shipped | `src/kit/runs.js:179-183`; the listener stands in for n8n | pending |
| P025 | bug-setup-button-sits-below-the-key-field-plan.md | 115 | L68: setup step 2 at 1280/400 px: button level with the key field or under it; help line (and Pro sentence) below | both | W B | shipped | `src/setup/steps.js:348-396` (help `<p>` after the button, `aria-describedby`). Step 2 is staged through a stored placeholder n8n connection | pending |
| P026 | bug-sources-and-audit-fail-on-a-valid-reply-plan.md | 117 | L147: not run live; Sources and Audit on real replies were left to the next release walk | Pro | N F | shipped | Both templates' Parse nodes parse from the first `{` to the last `}` (`lastIndexOf('}')`, 5 in Pro, 2 in Free). Bug 121's 2026-11-10 walk ran Sources and Audit live on v11 | pending |
| P027 | bug-two-provisioning-runs-orphan-the-first-plan.md | 72 | L135: Pro provision from state 1: the step passes on the first or a retried ping, no 404 sentence | Pro | N W | shipped | `includes/class-pqm-rest-api.php:1672-1725` (ping retried 2 s apart). The plan used the owner's n8n; a disposable n8n stands in | pending |
| P028 | bug-two-provisioning-runs-orphan-the-first-plan.md | 72 | L135: profile lists one "Post Quality Manager (n8n)" password; uninstall → the password is gone | Pro | N W | shipped | `includes/class-pqm-n8n.php:1164`; `includes/class-pqm-activator.php:106`. The name now ends with a UTC timestamp. Uninstall needs `--user=<admin>` | pending |
| P029 | bug-two-provisioning-runs-orphan-the-first-plan.md | 72 | L135: map and URL deleted by WP-CLI, provision again → the 409 sentence names the held path and two ways out | Pro | N W | shipped | `includes/class-pqm-n8n.php:1013-1018` | pending |
| P030 | bug-update-workflow-fails-after-free-to-pro-plan.md | 113 | L125: Free setup, Pro build over it, Update workflow → updated, ping OK, Review and Rewrite land; map gains credentials | both (Free→Pro) | N F W | shipped | `includes/class-pqm-n8n.php:1541`, `:1594` (`connect_wordpress__premium_only()`). Rewrite needs a license: the 2026-11-04 walk got 403 without one | pending |
| P031 | bug-update-workflow-reports-the-old-version-until-test-again-plan.md | 99 | L157: Pro provision, Free→Pro update or stand-in, `POST /n8n/workflow` → new version, or "still switching" | Pro | N W | shipped | `includes/class-pqm-rest-api.php:1877-1879` ("n8n is still switching…"); `admin/class-pqm-admin.php:1532` (`render_workflow_body()`) | pending |
| P032 | bug-upgrade-links-lead-nowhere-plan.md | 59 | L21/L23: the SDK's Upgrade links and the Settings "Get Pro" link open https://postquality.com/pricing/, never "not allowed" | both | W B | shipped | `POSTQUALITY_PRICING_URL` main file L90; the `pricing_url` filter L124 (null probe kept); Settings Get Pro at admin L815; setup copy config-steps.js L24 | pending |
| P033 | bug-upgrade-links-lead-nowhere-plan.md | 59 | L32 AC2: on a licensed site, the Account page's Upgrade links open the public page, and Renew stays in-admin, no dead link | Pro | B F | shipped | "Not walked" (plan L32); the filter answers null to null, so the hidden pricing page stays registered. Story 44's pass on 2026-10-28 does not record this link | pending |
| P034 | chore-build-tooling-current-majors-plan.md | 103 | L111: Dashboard cards, Post Reviews lists and filters, setup dialog steps; no page or console errors | both | B | shipped | `package.json:18` (`@wordpress/scripts ^37.1.0`); `admin/build/` bundle | pending |
| P035 | chore-build-tooling-current-majors-plan.md | 103 | L111: Plugin Check output the same as main's | both | W | shipped | Needs the Plugin Check plugin on the site, which conflicts with the clean site's "no other plugin"; story 38 AC1 runs it | pending |
| P036 | chore-freemius-sdk-2-13-5-plan.md | 89 | L89: activate Free: opt-in screen renders, Skip works, Account page loads, no PHP notice with WP_DEBUG | Free | B W | shipped | `vendor/freemius/start.php:18` (`2.13.5`); `post-quality-manager.php:50-52` (account menu). The Account page may only appear after an opt-in (F) | pending |
| P037 | chore-history-names-the-new-project-name-at-go-live-plan.md | 87 | L154: the owner looks at the AC8 flags, the swapped image and the PDF before the push | n/a | E X | n/a (one-time step, leaves nothing on a site) | A one-time repository step, done 2026-11-11 (plan Implementation Notes, "Closed"). No plugin behaviour to run on a site | not run (n/a) |
| P038 | chore-php-floor-moves-to-8-1-plan.md | 118 | L90: release step 6: the Free zip installs and activates on a clean WordPress 6.6 (now PHP 8.1) and shows the version | Free | W | shipped | Header `Requires PHP: 8.1` (main file L10) and readme.txt L6; /release SKILL step 6; plan L90: "step 6's clean WordPress 6.6 install is story 38's" | pending |
| P039 | chore-php-floor-moves-to-8-1-plan.md | 118 | L90: Freemius Deployment page, "Min PHP version" 8.1 for the release (owner read) | n/a | X F | shipped | /release SKILL step 7: the job prints Freemius's `requires_programming_language_version` from 3.13.1 on | pending |
| P040 | spike-hosted-workflow-plan.md | 110 | no manual check: a go/no-go analysis spike, nothing built | n/a | — | n/a | Ticket fields only (`id: 110`, `refined: false`); filed in e5b7739, "Not ranked yet" | not run (n/a) |
| P041 | story-add-plugins-card-and-details-plan.md | 70 | L129 (task L61): the owner approves the Add Plugins card and More Details in the directory preview | Free | E B W | shipped | `bin/directory-preview.php`; `docs/local-verification.md:209-285`; approved 2026-11-10 (`docs/distribution.md:67`). The preview needs Plugin Check active and fetches the directory's Markdown | pending |
| P042 | story-admin-views-plugin-as-lower-role-plan.md | 34 | L110: View as Editor/Author/Contributor: menu, pages, buttons, quota note match the README access tables | n/a | B | retired-by-42 | Commit 357c931 "retire View as" (story 42, ADR 0012); `includes/class-pqm-activator.php:32,52` deletes the `pqm_view_as` meta | not run (retired) |
| P043 | story-admin-views-plugin-as-lower-role-plan.md | 34 | L110: Exit the Contributor view from the toolbar; a second Administrator is unaffected | n/a | B | retired-by-42 | Same commit; no View as code left in `includes/`, `admin/` or `src/` | not run (retired) |
| P044 | story-ai-prompts-context-ux-review-plan.md | 26 | L136 (matrix L53): Custom text saved, switch to a preset, Save, back to Custom → the text is back | both | W B | shipped | `admin/class-pqm-admin.php:399`, `:907-934` (Blog Profile and Editorial Standards radios) | pending |
| P045 | story-ai-prompts-context-ux-review-plan.md | 26 | L136 (matrix L54): switch radios: preview updates, textarea only under Custom, with its stored text and focus | both | B | shipped | `admin/js/pqm-admin.js:173-196` (`bindPresetChoice`). Focus now moves only after a pointer pick (WCAG 3.2.2) | pending |
| P046 | story-ai-prompts-context-ux-review-plan.md | 26 | L136: one Review's payload carries the expected `brand_voice` and `website_context` | both | W N | shipped | `includes/pqm-functions.php:317-332`, `:518`. The listener's capture shows the payload; the n8n execution view is optional | pending |
| P047 | story-by-hand-choice-can-return-to-guided-setup-plan.md | 106 | L118: choice stored; "Use the guided setup instead" → dialog on Connect your n8n, state 1; by hand → state 3 | Free | W B | shipped | `admin/class-pqm-admin.php:1694-1708`; `src/setup/index.js:137-170`, `:343-353` | pending |
| P048 | story-cron-command-choice-plan.md | 3 | L98: Automated Reviews set to Audit, cron run → audit rows in the Audit tab; a second run picks other posts | Pro | W F N | shipped | `admin/class-pqm-admin.php:430-445` (`cron_command`); `includes/class-pqm-cron.php:67-80` (plan gate: Audit is paid, so it needs a license). The hook is now `postquality_cron_review_posts` | pending |
| P049 | story-dashboard-feedback-block-plan.md | 79 | L118: the feedback card on the Dashboard at 1280 and 390 px | both | E B | shipped | `src/dashboard/feedback.js`; `src/dashboard.js:181` | pending |
| P050 | story-dashboard-feedback-block-plan.md | 79 | L118: Send by email opens the mail client with the text shown | both | B | shipped | `src/dashboard/feedback.js:61-71`, `:339-344` (mailto href). Headless Edge can only read the href | pending |
| P051 | story-directory-banner-and-screenshot-2-read-right-in-the-listing-plan.md | 122 | L109: the owner approves the banner and screenshot 2 in the More Details preview | Free | E B | shipped | `assets/banner-772x250.png`, `banner-1544x500.png`, `screenshot-2.png`; approved 2026-11-10 (`docs/distribution.md:69`). The preview needs Plugin Check (story 70's recipe) | pending |
| P052 | story-every-name-carries-the-postquality-prefix-plan.md | 88 | L122: Plugin Check on the Free build (CI or the showcase) | Free | W X | shipped | Plugin Check is another plugin on the clean site; story 38 AC1 runs it in WP Admin | pending |
| P053 | story-every-name-carries-the-postquality-prefix-plan.md | 88 | L123: optional owner look: each page, `?postquality_setup=1`, the Account page | both | B E | shipped | `admin/class-pqm-admin.php:41` (`SETUP_QUERY_ARG`); `src/setup/index.js:53`. The Account page may need an opt-in | pending |
| P054 | story-free-build-contains-no-pro-code-plan.md | 54 | L109/L122: Pro build, Settings, Post Reviews, Dashboard and setup dialog unchanged by the markers | Pro | B E | shipped | Markers change only the Free build. The old baseline is gone, so this is now "every Pro screen renders with no notice in debug.log" | pending |
| P055 | story-free-build-contains-no-pro-code-plan.md | 54 | L111/L122/L183 (B12): Pro site with the Free template: ping shows the Free-variant notice; Pro template: notice gone | Pro | W N B | shipped | admin L1593 "Your workflow is the Free variant: it lacks the Sources, SEO, Audit and Rewrite branches."; ping L2282. B12 "not run" on 2026-10-13 (L164). The 127.0.0.1 listener can answer `workflow_variant: free` instead of n8n | pending |
| P056 | story-free-build-contains-no-pro-code-plan.md | 54 | L114 HITL 6: upload to Freemius, download the generated Free build, diff it against `bin/build-free.php` | n/a | X F | shipped | Automated by story 58: release.yml "Check the Free build Freemius generated" runs on every release (ticket 54 Notes, done 2026-10-28) | pending |
| P057 | story-free-build-contains-no-pro-code-plan.md | 54 | L170 A1: install and activate the Free zip fresh: no notice or fatal; opt-in shows the plugin name; Skip works | Free | W B | shipped | `is_org_compliant` true and the `plugin_icon` filter (main file L47, L119). Never walked: the 2026-10-13 run replaced a copy in place, and A1 was left to the owner (L151, L160) | pending |
| P058 | story-free-build-contains-no-pro-code-plan.md | 54 | L171 A2: Dashboard renders: no schedule notice, no locked tile | Free | W B | shipped | `cron_nudge` only inside `is__premium_only()` (rest-api L1079). Copy now "Post Quality → Dashboard" (was "Content Quality") | pending |
| P059 | story-free-build-contains-no-pro-code-plan.md | 54 | L172 A3: Post Reviews shows only Review and Key Takeaways pills, tabs and buttons; no "Pro" label | Free | W B | shipped | src/kit/copy.js L27: `COMMAND_ORDER` is filtered by the server's labels; the Free registry has no paid key, so `GET /posts` returns an empty `gated_commands` | pending |
| P060 | story-free-build-contains-no-pro-code-plan.md | 54 | L173 A4: Settings tabs Connection, AI, Usage & Limits; no disabled control; License: plan Free and one Get Pro | Free | W B | shipped | admin L730–744 (Automation and Roles tabs only in premium blocks), L815. Copy now: "Current plan: Free — Review and Key Takeaways are included. Get Pro" | pending |
| P061 | story-free-build-contains-no-pro-code-plan.md | 54 | L174 A5: n8n Workflow serves the Free template, asks for two credentials; setup shows no Application Password or Schedule step | Free | W B N | shipped | templates/ holds the Free file; two credentials (admin L1925); steps.js L566 drops `schedule`. Copy now: Connect your n8n → Set up the workflow, or Set up by hand (ADR 0015). Running the provisioning needs N | pending |
| P062 | story-free-build-contains-no-pro-code-plan.md | 54 | L175 A6: import the Free template, connect, ping: current version, no variant notice | Free | N W | shipped | Copy now: version 11 (`POSTQUALITY_WORKFLOW_VERSION`, main file L95), was 5. The listener can stand in for the ping reply | pending |
| P063 | story-free-build-contains-no-pro-code-plan.md | 54 | L176 A7: one post end to end: Review stores a score, Key Takeaways runs, Insert into Post adds a plain list | Free | N W B | shipped | blocks L82: the pattern exists only in a premium block, so Free inserts the plain list. Never passed: the 2026-10-13 runs returned model errors and Insert was not run (L159) | pending |
| P064 | story-free-build-contains-no-pro-code-plan.md | 54 | L177 A8: nothing upsells besides the one Get Pro link: no notice, toast or dashboard widget | Free | B E | shipped | The "Available with Pro" notes sit in `render_*__premium_only()` (admin L1041, L1193, L1304); config-steps' Get Pro sits in the schedule step, which Free drops. Never walked (L160) | pending |
| P065 | story-free-build-contains-no-pro-code-plan.md | 54 | L180 B9: Pro zip beside the active Free build: activating either deactivates the other; no fatal | both | B W* | shipped | `set_basename()` branch, main file L71–80. `wp plugin activate` cannot show it (L161: the hooks register only under `is_admin()`); use the Plugins screen or a `WP_ADMIN` process | pending |
| P066 | story-free-build-contains-no-pro-code-plan.md | 54 | L181 B10: settings and review rows kept across every Free/Pro switch | both | W | shipped | Passed on production 2026-10-13 (L162); no later change to activation or uninstall named | pending |
| P067 | story-free-build-contains-no-pro-code-plan.md | 54 | L182 B11: Pro without a license: paid sections locked with the plan copy; with a license they unlock | Pro | W B F | shipped | admin L1050, L1215–1216, L1314 "Available with Pro…"; license line L819. The licensed half needs the owner's key (story 44's pass, 2026-10-28) | pending |
| P068 | story-free-build-meets-the-directory-rules-before-submission-plan.md | 95 | L174: `lwp plugin check` on the Free build: 0 errors, only the accepted warnings (AC at L77) | Free | W | shipped | Needs Plugin Check on the site; the accepted warnings are W1/W2, `AIProvider.DirectIntegration` and `vendor/freemius` | pending |
| P069 | story-free-build-meets-the-directory-rules-before-submission-plan.md | 95 | L174: privacy guide text read through `get_suggested_policy_text()` after `admin_init` | Free | W | shipped | `admin/class-pqm-admin.php:616-630`; `post-quality-manager.php:175` | pending |
| P070 | story-free-build-meets-the-directory-rules-before-submission-plan.md | 95 | L174: de_DE fixtures in `languages/plugins/` read under a locale filter with `__()` and `load_script_textdomain()` | Free | W | shipped | `admin/class-pqm-admin.php:241` (no path argument); `tests/i18n/README.md` (md5 file name); no `load_plugin_textdomain` in the plugin | pending |
| P071 | story-free-site-cron-off-switch-plan.md | 13 | L87 Site without Pro plan, cron on: Enable toggle live, rest locked; uncheck + save → toggle locked, cron event gone | Pro | W B F | shipped | admin/class-pqm-admin.php:1206-1229 inside `render_automation__premium_only`; event cleared only on Pro (`on_settings_updated` :597); Free build has no Automation tab (story 54, 5c4b1b9, :733); hook now `postquality_cron_review_posts` (story 91); "no plan" on Pro needs a lapsed license or the `postquality_is_pro` filter | pending |
| P072 | story-freemius-notice-matches-what-the-site-needs-plan.md | 65 | L37,L43 Pro on update path (1b/3b), no license: "Pro is installed. Enter your license key…" + link opens license screen | Pro | W B | shipped | post-quality-manager.php:134-141 (filter inside `is__premium_only`); needs an older build updated in place first | pending |
| P073 | story-freemius-notice-matches-what-the-site-needs-plan.md | 65 | L38 Free on update path (path 1): SDK opt-in sticky text unchanged; Skip removes it | Free | W B | shipped | filter stripped from Free (main:134; free-build-harness rows); walk record L62 | pending |
| P074 | story-freemius-notice-matches-what-the-site-needs-plan.md | 65 | L39 Pro new install (path 3): license screen, "Complete … Activation Now", no opt-in sticky | Pro | B | shipped | SDK path, filter not reached; walk record L63 | pending |
| P075 | story-freemius-notice-matches-what-the-site-needs-plan.md | 65 | L70 Path 2: Free opted in (confirmation email), Pro activated, license activated: no opt-in or license notice left | Pro | B F | shipped | `admin_init` closure main:157-161 | pending |
| P076 | story-freemius-notice-matches-what-the-site-needs-plan.md | 65 | L42 Licensed Pro on a registered site (2b): first admin page drops the stored `connect_account` notice | Pro | W B F | shipped | main:159 `remove_sticky('connect_account')` | pending |
| P077 | story-full-round-trip-connection-test-plan-2.md | 2 | L108 Setup dialog first-review step: Run Review, no callback, 3 min → warning naming both addresses; Run Review enabled | both | W B | shipped | src/setup/first-review.js:55-75 `gaveUpText`; class-pqm-rest-api.php:1279 `rest_url`; src/kit/use-polling.js:27 | pending |
| P078 | story-full-round-trip-connection-test-plan-2.md | 2 | L109 Close and reopen the dialog on the review step → the warning at once | both | W B | shipped | first-review.js takes the start from the row's `reviewed_at` | pending |
| P079 | story-full-round-trip-connection-test-plan.md | 2 | L128 Outdated workflow: Update workflow → "Your workflow answers. Version N · checked just now by a test." | both | N B | shipped | copy now: outdated reads "Your workflow is version %1$d; this plugin needs %2$d." (admin:1588, story 67 7c7550a); version 11 (main:95); post-update 404 fixed by bug 84 | pending |
| P080 | story-full-round-trip-connection-test-plan.md | 2 | L129 Test again → the proven sentence within seconds | both | N B | shipped | admin:1569 "Your workflow answers.", :1638 Test again | pending |
| P081 | story-full-round-trip-connection-test-plan.md | 2 | L130 Wrong X-PQM-Token in n8n's POST Result node → refused sentence; restore → proven again | both | N B | shipped | admin:1577 "The last test %1$s did not pass: %2$s" | pending |
| P082 | story-full-round-trip-connection-test-plan.md | 2 | L131 Set up by hand → step 4 Save and test → proven sentence in the dialog, step ticked | both | N B | shipped | src/setup/steps.js:122; admin:1823 | pending |
| P083 | story-guided-setup-dialog-plan.md | 36 | L125 Walk the seven steps from a fresh site; every label matches n8n | both | N B | retired-by-n8n-provisioning-6 | 9ff4cbc (entry 6.6) replaced the manual steps with Connect your n8n + Set up the workflow; manual steps now in the page's Set up by hand (admin:1901) | not run (retired) |
| P084 | story-guided-setup-dialog-plan.md | 36 | L125 Application Password step works over HTTPS, explains itself over HTTP | both | B | retired-by-n8n-provisioning-6 | step dropped in 9ff4cbc; Pro provisioning creates the App Password (steps.js:387-392, bug 73) | not run (retired) |
| P085 | story-guided-setup-dialog-plan.md | 36 | L125 First review lands from the dialog; the welcome notice disappears | both | N W B | shipped | first-review.js; welcome notice admin:128-135 until `postquality_setup_complete()` | pending |
| P086 | story-guided-setup-dialog-plan.md | 36 | L125 Reopen the guide afterwards: settings untouched | both | W B | shipped | src/setup/index.js:10-28 (progress per site, reopens) | pending |
| P087 | story-launch-content-demo-cost-guidance-announcement-plan.md | 49 | L117 Owner: cost figures read right; the announcement channel list is theirs | n/a | E | shipped | docs/cost-example.md; readme.txt:35 FAQ; docs/distribution.md:140-150 (communities "OWNER: to name") | pending |
| P088 | story-launch-content-demo-cost-guidance-announcement-plan.md | 49 | L117 Readme Screenshots and site How it works read as the loop | n/a | E X | shipped | readme.txt:69-73; postquality.com index.html How it works | pending |
| P089 | story-local-wp-cli-test-environment-plan.md | 9 | L143 None: the story's checks are the CLI runs | n/a | n/a | shipped | plan states no manual check; bin/local-wp.py is the tool | pending |
| P090 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 plan is a stub, no steps; ticket AC1: technical profile, n8n import, review → four pillar scores | both | N B | shipped | profile `technical` admin:303; review prompt's four pillars in the template | pending |
| P091 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 stub; ticket AC2a: import URL copied ("Copied!") and pasted into n8n Import from URL | both | N B | retired-by-111 | 086a013: Set up by hand imports from a file (admin:1955); plan 111 L105 "the link left" | not run (retired) |
| P092 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 stub; ticket AC2b: wrong or missing X-PQM-Token → 401 from save-review | both | W | shipped | class-pqm-rest-api.php:328 `check_webhook_token` permission callback | pending |
| P093 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 stub; ticket AC3: each registry model: knobs toggle, preview matches, round trip, thinking reply parses | both | N B | shipped | copy now: registry adds Opus/Sonnet/Haiku 5.5 (pqm-functions.php:85-125, story 25); preview admin:1839; thinking strip in template Parse node | pending |
| P094 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 stub; ticket AC4: older template then current → outdated notice, then up to date | both | N W | shipped | copy now "Your workflow is version X; this plugin needs 11." (admin:1588); WP-CLI can plant the seen version | pending |
| P095 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 stub; ticket AC5: profile/voice presets fill instantly; manual edit flips selector to Custom | both | B W | retired-by-26 | presets survive (admin:398-413), but story 26 made the radio the setting: Custom reveals the field, no flip (pqm-admin.js:168-187) | not run (retired) |
| P096 | story-manual-pass-agnostic-and-llm-pivots-plan.md | 6 | L1-5 stub; ticket AC6: "Send test ping" against active, then inactive workflow: copy per state | both | N B | shipped | copy now "Test again" / "Test the connection" (admin:1638, story 67); failure sentence admin:1577 | pending |
| P097 | story-manual-pass-freemium-stories-plan.md | 8 | L1-5 stub, no steps: interim pass of freemium stories 1.1–1.5 (gate, quota, sections, Settings JS) | both | W B | retired-by-8 | dropped 2026-10-06 (87eae48): freemium story 1.8 walk (2026-10-02) covered it; Settings JS moved to story 1 AC7 | not run (retired) |
| P098 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub, no steps; ticket AC1: Dashboard states: "Set up the n8n workflow" → "You are all set…" → tiles | both | W B | shipped | src/dashboard.js:80, :227 | pending |
| P099 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub; ticket AC2: wizard step 3 saves URL and pings; "URL saved, but the ping did not get through" | both | N B | retired-by-n8n-provisioning-6 | 9ff4cbc + story 67 (7c7550a) replaced the wizard; copy now "URL saved, but the test did not pass." on Set up by hand (rest-api:1414) | not run (retired) |
| P100 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub; ticket AC3: save notice "No n8n re-import needed"; AI Model own section; Reset without confirm() | both | B | shipped | admin:759, :884; pqm-admin.js:143 (Reset sits in Pro overrides) | pending |
| P101 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub; ticket AC4: Takeaways/Sources/SEO/Audit tabs render; Agents run column; Open in Editor primary | Pro | N W B F | shipped | src/post-reviews-table.js:588; src/kit/copy.js:128; results can be posted to save-review in n8n's place | pending |
| P102 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub; ticket AC5: model without temperature greys slider; switch back keeps the temperature | Pro | B F | shipped | pqm-admin.js:202-230 per-agent knob lock | pending |
| P103 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub; ticket AC6: licensed Admin, a review, cron off → nudge; gone once cron is on | Pro | W B F | shipped | src/dashboard.js:297 | pending |
| P104 | story-manual-pass-lean-ux-and-locked-knobs-plan.md | 1 | L1-5 stub; ticket AC7: Settings JS on Admin and Editor views, no console error | Pro | W B F | shipped | admin:194 (Editor view lacks AI Model); Editor as a real user (View as retired, ADR 0012) | pending |
| P105 | story-n8n-3-readiness-plan.md | 111 | L108 Set up by hand against a disposable n8n 2: download, "Import" → "From file", workflow lands | both | N B | shipped | admin:1955 (also "Import JSON" for n8n 3) | pending |
| P106 | story-n8n-connection-secure-and-host-agnostic-plan.md | 35 | L105 Import the workflow, create the Header Auth credential; Run, cron and ping succeed | both | N B | shipped | template headerAuth (templates/n8n-workflow.json:8,22,114); now v11; cron is Pro only | pending |
| P107 | story-n8n-connection-secure-and-host-agnostic-plan.md | 35 | L105 curl to the webhook without the header → 403 | both | N | shipped | Header Auth on the Webhook node (template:8) | pending |
| P108 | story-n8n-connection-secure-and-host-agnostic-plan.md | 35 | L105 A /webhook-test/ URL shows the warning | both | W B | shipped | copy now a refusal: pqm-functions.php:559-569, admin:2006 "so this site refuses it" | pending |
| P109 | story-n8n-connection-state-tells-the-truth-plan.md | 74 | L133 Revoke key in n8n, Check again → refusal, Rotate/Update gone; new key, Connect → all back | both | N B | shipped | src/n8n-connection/index.js:538, :591; class-pqm-n8n.php:207 | pending |
| P110 | story-n8n-connection-state-tells-the-truth-plan.md | 74 | L134 Passing key revoked in n8n, Rotate → outcome names refused key; managed actions gone | both | N B | shipped | admin:1751; class-pqm-n8n.php:207-226 | pending |
| P111 | story-n8n-setup-map-start-over-plan.md | 71 | L134 Provision, Start over with removal → n8n empty for host, no plugin App Password, state 2 | both | N W B | shipped | n8n-connection/index.js:648-717; class-pqm-n8n.php:1617; App Password Pro only (n8n.php:1039) | pending |
| P112 | story-n8n-setup-map-start-over-plan.md | 71 | L134 Provision again, delete the workflow in n8n by API, Test again → gone sentence with Start over | both | N B | shipped | admin:2209 | pending |
| P113 | story-n8n-setup-map-start-over-plan.md | 71 | L134 Start over without removal on an adopted map → n8n untouched | both | N B | shipped | class-pqm-n8n.php:1617 ff. | pending |
| P114 | story-n8n-workflow-page-lean-ux-rework-plan.md | 67 | L135 States 1 → 2 → 4 through the wizard | both | N B | shipped | admin:1548-1570 state sentences | pending |
| P115 | story-n8n-workflow-page-lean-ux-rework-plan.md | 67 | L135 State 3: "Set up by hand instead", then Save and test | both | W B | shipped | admin:1691, :1823 | pending |
| P116 | story-n8n-workflow-page-lean-ux-rework-plan.md | 67 | L135 State 5: older version planted with WP-CLI; a URL that does not answer | both | W B | shipped | admin:1572-1590 | pending |
| P117 | story-n8n-workflow-page-lean-ux-rework-plan.md | 67 | L135 Rotate token and Update workflow from the Webhook disclosure and the status block | both | N B | shipped | admin:1641, :1751 | pending |
| P118 | story-n8n-workflow-page-lean-ux-rework-plan.md | 67 | L135 Settings → Connection shows URL read-only; saving Settings keeps URL and token | both | W B | shipped | admin:849 read-only; sanitizer carries them (admin:394, :412) | pending |
| P119 | story-one-button-setup-wizard-plan.md | 55 | L122 After a fresh setup, walk profile/standards/schedule (pick, skip, custom); Settings mirrors each pick | both | W B | shipped | src/setup/config-steps.js; Free build has no schedule step (steps.js:557-566); first review needs n8n or a save-review stand-in | pending |
| P120 | story-one-button-setup-wizard-plan.md | 55 | L122 A no-change walk leaves settings untouched | both | W B | shipped | config-steps.js:1-9 (Skip writes no setting) | pending |
| P121 | story-one-button-setup-wizard-plan.md | 55 | L122 Site without Pro plan shows the Pro wording on the schedule step | Pro | B F | shipped | config-steps.js:197-212; absent from the Free build since story 54 (5c4b1b9, steps.js:557-566) | pending |
| P122 | story-pattern-picker-explains-itself-plan.md | 24 | L128 Takeaways box: "Pattern to use"; new List-block pattern under "Your patterns" after reload; Edit pattern | Pro | B F | shipped | admin:1139-1160 in `render_overrides__premium_only` (:1041); Free build has no picker | pending |
| P123 | story-pattern-picker-explains-itself-plan.md | 24 | L128 No eligible pattern: "Create a pattern" opens the new-pattern screen | Pro | B F | shipped | admin:1163 | pending |
| P124 | story-plugin-icon-plan.md | 51 | L11/L15: the menu shows the Q-seal mono icon, unclipped, in default, current-item and light schemes | both | B E | shipped | `PostQuality_Admin::MENU_ICON` (admin L48), used by `add_menu_page` L61. The owner approved it on production 2026-10-12, never on Local | pending |
| P125 | story-plugin-icon-plan.md | 51 | L15: icon-512x512.png uploaded as the Freemius product image | n/a | X F | shipped | assets/icon-512x512.png is at the repo root; the upload is recorded in plan L15 and ticket 51 Notes | pending |
| P126 | story-post-editor-meta-box-shows-and-runs-the-review-plan.md | 109 | L117 Block editor: the box under the content, pane open/closed, root survives | both | W B | shipped | admin/class-pqm-meta-box.php; main:183-184; walked on WP 7.1 | pending |
| P127 | story-post-editor-meta-box-shows-and-runs-the-review-plan.md | 109 | L117 Classic Editor plugin enabled for the walk: the box renders and runs | both | W B | shipped | same class; needs the Classic Editor plugin installed | pending |
| P128 | story-post-editor-meta-box-shows-and-runs-the-review-plan.md | 109 | L117 Every matrix row reachable (result, running, failed, not reviewable, dirty save, refused, quota, roles) | both | W B | shipped | src/meta-box/*.js; matrix L35-50; webhook listener capture | pending |
| P129 | story-post-editor-meta-box-shows-and-runs-the-review-plan.md | 109 | L117 Post Reviews link narrows the list to the post, panel open; "Show all posts" clears | both | B | shipped | src/post-reviews-table.js:38-84 `postquality_post` | pending |
| P130 | story-post-editor-meta-box-shows-and-runs-the-review-plan.md | 109 | L117 Plugin Check from the scratch folder | Free | W X | shipped | CI plugin-check job (.github/workflows/ci.yml:4); needs the Plugin Check plugin | pending |
| P131 | story-post-editor-meta-box-shows-and-runs-the-review-plan.md | 109 | L117 The owner judges the look of the box | both | E | shipped | owner's eye | pending |
| P132 | story-production-site-runs-each-release-as-licensed-pro-plan.md | 98 | L158 Owner confirms the production site after the update | Pro | X F | shipped | process on the owner's production site; no clean-site behaviour | pending |
| P133 | story-public-home-site-pricing-docs-plan.md | 45 | L16/L18: postquality.com and its pages answer 200 over HTTPS; www and plain HTTP redirect to the apex | n/a | X | shipped | Verified 2026-10-12 (L18); the site repo still has its CNAME and the pages | not re-run: outside the site, last evidence kept |
| P134 | story-public-home-site-pricing-docs-plan.md | 45 | L16: the owner reads the published pages (hitl) | n/a | X E | shipped | Done 2026-10-12 (L18, ticket 45 Notes); the pages were rewritten since by story 86's builds | pending |
| P135 | story-real-license-unlocks-pro-plan.md | 44 | L85 Activate → opt-in → Skip → Free; License "Current plan: Free …" + "Get Pro" | Free | B | shipped | admin:814-815; on the Pro build this path went with story 54: Pro opens the license screen (story 65 walk L63) | pending |
| P136 | story-real-license-unlocks-pro-plan.md | 44 | L85 Sandbox key on the SDK screen → Pro; License "Current plan: Pro — …" | Pro | B F | shipped | admin:820 | pending |
| P137 | story-real-license-unlocks-pro-plan.md | 44 | L85 Expire the test license in the Freemius dashboard → lapse copy, settings intact | Pro | B F | shipped | copy now "Current plan: Free — your Pro license has lapsed… Renew Pro" (admin:826, bug 93); main:145-152 | pending |
| P138 | story-real-license-unlocks-pro-plan.md | 44 | L86 Delete from WP Admin: reviews table, options, usage meta and caps gone | both | B W | shipped | class-pqm-activator.php:101-125 via SDK `after_uninstall`; names `postquality_*` + legacy `pqm_*` (story 91); `wp plugin uninstall` skips it (triage row 2) | pending |
| P139 | story-release-uploads-zip-to-freemius-plan.md | 58 | L88: secret set; the release run log shows sha256, deployment id, "release mode pending" | n/a | X | shipped | release.yml L110–121 (Deploy to Freemius step, `FREEMIUS_API_TOKEN` guard); green on 3.10.2 (L118) | not re-run: outside the site, last evidence kept |
| P140 | story-release-uploads-zip-to-freemius-plan.md | 58 | L88: Freemius → Deployment lists the version unreleased with both builds; the two artifacts download | n/a | X F | shipped | The owner saw 3.10.2 listed once, pending (L118); /release SKILL steps 7–8 | pending |
| P141 | story-release-uploads-zip-to-freemius-plan.md | 58 | L88: re-run the package job: "already on Freemius", no second deployment | n/a | X | shipped | Passed on 3.10.2, attempt 2 (L118), after the `tags/latest.json` lookup fix (L117) | not re-run: outside the site, last evidence kept |
| P142 | story-repository-records-name-no-owner-host-plan.md | 66 | L152 Read each changed line in its paragraph: it states the same fact as before | n/a | E X | shipped | repository records only, no plugin behaviour | pending |
| P143 | story-rights-tab-sets-role-capabilities-plan.md | 42 | L134 Roles tab: Admin unchecks Author · View reviews, saves → Author sees no Dashboard or Post Reviews | Pro | W B F | shipped | admin:530 "View reviews"; Roles tab admin:741-745 | pending |
| P144 | story-rights-tab-sets-role-capabilities-plan.md | 42 | L134 The Administrator row cannot be changed | Pro | B F | shipped | admin:1317 locked rows | pending |
| P145 | story-rights-tab-sets-role-capabilities-plan.md | 42 | L134 On a site without Pro plan the Roles tab is locked | Pro | B F | shipped | admin:1308-1316; absent from the Free build (story 54, admin:741) | pending |
| P146 | story-rights-tab-sets-role-capabilities-plan.md | 42 | L134 The View as toolbar node is gone | both | B | shipped | no View as code left, only its meta deleted at upgrade/uninstall (activator:32, :52; ADR 0012) | pending |
| P147 | story-settings-tabs-plan.md | 27 | L110 the Editor's Settings tabs (was checked through "View as Editor") | Pro | B | shipped (method: a real Editor user; View as retired by 42) | View as retired (ADR 0012, activator:32); a real Editor user replaces it | pending |
| P148 | story-settings-tabs-plan.md | 27 | L110 Tabs, arrow keys, a save on each tab returning to it (free and pro sites) | both | B | shipped | pqm-admin.js:44-90 (keys :83, referer hash) | pending |
| P149 | story-settings-tabs-plan.md | 27 | L110 Dashboard nudge opens Settings on the Automation tab | Pro | B F | shipped | src/dashboard.js:297 `#pqm-cron-settings`; pqm-admin.js:40 | pending |
| P150 | story-settings-tabs-plan.md | 27 | L110 Setup dialog's Blog Profile link opens the AI tab | both | B | retired-by-55 | 99fdbdf removed the link (the dialog holds the profile step); anchor `#pqm-context-settings` still at admin:906 | not run (retired) |
| P151 | story-settings-tabs-plan.md | 27 | L110 Bad webhook URL saved from the AI tab opens Connection | both | B | retired-by-67 | 7c7550a: the URL is read-only on Settings (admin:849); the `invalid` listener remains (pqm-admin.js:93-96) | not run (retired) |
| P152 | story-settings-tabs-plan.md | 27 | L110 JavaScript off shows the whole page | both | B | shipped | admin:777 `.no-js` | pending |
| P153 | story-site-features-page-marks-pro-plan.md | 100 | L154 Each capture at full size: "Yourblog", demo posts only, "Post Quality" menu, no third-party logo | n/a | E X | shipped | postquality.com features.md | pending |
| P154 | story-site-features-page-marks-pro-plan.md | 100 | L155 Owner: Freemius activation count back to its pre-walk value | n/a | F X | shipped | Freemius dashboard read | pending |
| P155 | story-site-hygiene-no-dead-ends-plan.md | 85 | L149 Re-crawl postquality.com with the link/heading script; record status codes | n/a | X | shipped | site repo: 404.html, robots.txt, sitemap | not re-run: outside the site, last evidence kept |
| P156 | story-site-hygiene-no-dead-ends-plan.md | 85 | L149 Open /nope/ and record the favicon request | n/a | X | shipped | postquality.com 404.html, assets/icon.svg | not re-run: outside the site, last evidence kept |
| P157 | story-site-navy-and-check-plan.md | 86 | L89/L91: home first screen at 1280 px matches the direction; 320 px: no horizontal scroll, header wraps | n/a | X B E | shipped | postquality.com `_includes/header.html` L25 `details.menu`; `_config.yml` L20 `launched: false` | pending |
| P158 | story-site-navy-and-check-plan.md | 86 | L93: the owner's screenshot review approves before the draft PRs | n/a | X E | shipped | "Approved, go ahead" 2026-10-23 (L210); site PR #3 merged (L212) | pending |
| P159 | story-site-navy-and-check-plan.md | 86 | L198: the Freemius plans keep free localhost and staging activations (Terms § 3) | n/a | X F | shipped | Confirmed 2026-10-28, "Unlimited localhost license activations" on (memory/freemium-and-roles.md L25); a `.local` Pro activation then uses no seat | pending |
| P160 | story-site-navy-and-check-plan.md | 86 | L203: one look at the Menu's in-flow panel in Firefox and Safari | n/a | X E | shipped | The Menu is live (header.html L25); no record that the look ever ran | pending |
| P161 | story-stored-names-move-to-the-postquality-prefix-plan.md | 91 | L122 Site on 3.9.0 with full state, replaced by this build: every page, review, Roles, card, cron the same | Pro | W B N X F | shipped | legacy map class-pqm-activator.php:32-60; 3.9.0 zip from GitHub Releases; bug 94 fixed the first admin page after the upgrade | pending |
| P162 | story-stored-names-move-to-the-postquality-prefix-plan.md | 91 | L122 Delete the plugin → no `pqm_`/`postquality_` option, meta, capability or table left | both | B W | shipped | class-pqm-activator.php:101-125 | pending |
| P163 | story-stored-names-move-to-the-postquality-prefix-plan.md | 91 | L123 Plugin Check on the Free build: CI after 2026-11-01 | Free | X | shipped | .github/workflows/ci.yml plugin-check job | not re-run: outside the site, last evidence kept |
| P164 | story-support-privacy-terms-refund-policies-plan.md | 46 | L16: the owner approves the policy wording before publication (hitl) | n/a | X E | shipped | docs/policies/{privacy,terms,refund,support}.md; the copies are on postquality.com | pending |
| P165 | story-support-privacy-terms-refund-policies-plan.md | 46 | L16: the third-party legal URLs are opened and confirmed at readme time (story 38) | n/a | X | shipped | readme.txt L53 `== External services ==`. Copy now: n8n, Anthropic, Freemius only; OpenAI and Google dropped (distribution.md L95, still CHECK) | not re-run: outside the site, last evidence kept |
| P166 | story-takeaways-sources-block-choice-plan.md | 23 | L166 Pick the "Key Takeaways" pattern; insert Takeaways twice (one copy, no duplicate spacer), Sources appended | Pro | W B F | shipped | includes/class-pqm-blocks.php (`pqmInsert`); results via save-review stand-in; walked on WP 7.1.2 | pending |
| P167 | story-takeaways-sources-block-choice-plan.md | 23 | L166 Editor: no invalid-block warning; `pqmInsert` kept after an editor save; Revisions | Pro | B E | shipped | class-pqm-blocks.php | pending |
| P168 | story-update-workflow-asks-on-an-adopted-workflow-plan.md | 76 | L108 Adopted workflow, older version planted, Update workflow → Modal; Cancel leaves n8n untouched | both | N W B | shipped | admin:1641 `data-pqm-confirm="update"` when adopted | pending |
| P169 | story-update-workflow-asks-on-an-adopted-workflow-plan.md | 76 | L108 Confirm → workflow updated; the page shows the new version | both | N B | shipped | admin:1569-1588 | pending |
| P170 | story-upgrade-release-notes-pack-plan.md | 15 | L80 Read the release notes as a site owner: what changed, what to do, product named first | n/a | E | shipped | file now docs/release-notes/4.0.0.md (renamed at launch); line 3 names "Post Quality Manager" | pending |
| P171 | story-upgrade-release-notes-pack-plan.md | 15 | L81 Read `/release` step 3 as the agent: rename, file name, two stop conditions unambiguous | n/a | E | shipped | .claude/skills/release/SKILL.md:12 | pending |
| P172 | story-visual-identity-and-copy-sweep-plan.md | 41 | L130 Owner views the banners at 1×/2× and the two lockup SVGs on GitHub | n/a | E X | shipped | assets/banner-772x250.png, banner-1544x500.png, lockup-b.svg, lockup-b-dark.svg | pending |
| P173 | epic-delivery-pipeline/story-ci-guards-the-plugin-versions-and-the-php-8-0-floor-plan.md | 2 | L84 Two throwaway CI commits on the pushed branch turn the version / PHP-floor guard red before merge | n/a | X | shipped | ci.yml:94-126 php job runs release-header-harness; floor now 8.1 (chore 118), not 8.0 | not re-run: outside the site, last evidence kept |
| P174 | epic-delivery-pipeline/story-refactor-sweep-plan.md | 7 | L109 `gh pr checks` shows build, PHP, Plugin Check green; Plugin Check after build | n/a | X | shipped | ci.yml jobs build/php/plugin-check/free-build; PHP lint now 8.1 + 8.3 (chore 118) | not re-run: outside the site, last evidence kept |
| P175 | epic-delivery-pipeline/story-the-first-public-release-is-published-plan.md | 8 | L241 Release PR proposes 4.0.0, changelog starts after v3.13.3 | n/a | X | shipped | one-off, recorded done plan L171-172 (PR #4, 2026-11-11) | not re-run: outside the site, last evidence kept |
| P176 | epic-delivery-pipeline/story-the-first-public-release-is-published-plan.md | 8 | L241 `/release` steps 5–9 after the release (GitHub Release, Freemius deployment Released) | n/a | X F | shipped | one-off, recorded plan L171-181 (run 43019155750, deployment 172983) | pending |
| P177 | epic-delivery-pipeline/story-the-first-public-release-is-published-plan.md | 8 | L241 Install check: v4.0.0 zip installs, activates, WP_DEBUG on, plugin pages 200, no notice, Free tabs only | both | W B | shipped | plan L174-180: walked on "WordPress66" with PHP 8.2.29 (not 8.1), Free zip only; Pro zip comes from Freemius | pending |
| P178 | epic-delivery-pipeline/story-the-plugin-passes-plugin-check-and-the-check-blocks-ci-plan.md | 5 | L78 Dashboard sparkline and a post's review history load as before (the two `%i` queries) | both | W B | shipped | class-pqm-db.php:58, :413-421 `%i` (needs WP ≥ 6.2) | pending |
| P179 | epic-delivery-pipeline/story-the-plugin-passes-plugin-check-and-the-check-blocks-ci-plan.md | 5 | L78 Deleting the plugin drops the reviews table | both | W | shipped | class-pqm-activator.php:95-101 via Freemius `after_uninstall` (story 44, no uninstall.php); copy now `wp_postquality_reviews` (story 91) | pending |
| P180 | epic-delivery-pipeline/story-the-repository-is-ready-to-go-public-plan.md | 9 | L97 Owner deletes or regenerates the old workflow's kChat slash command and incoming webhook | n/a | X | shipped | one-off owner housekeeping in the chat tool; nothing on the site | not re-run: outside the site, last evidence kept |
| P181 | epic-delivery-pipeline/story-the-workflow-template-is-guarded-in-ci-plan.md | 3 | L85 Throwaway commit seeding a template breakage turns CI php 8.3 job red naming the cause | n/a | X | shipped | tests/harness/template-guard-harness.php run by ci.yml php job | not re-run: outside the site, last evidence kept |
| P182 | epic-delivery-pipeline/story-tracer-bullet-release-pr-and-a-ci-built-plugin-zip-plan.md | 1 | L109 Repo Settings → Actions: allow GitHub Actions to create and approve pull requests | n/a | X | shipped | GitHub setting; release PR #4 opened 2026-11-11 (entry 8 plan L171) | not re-run: outside the site, last evidence kept |
| P183 | epic-delivery-pipeline/story-tracer-bullet-release-pr-and-a-ci-built-plugin-zip-plan.md | 1 | L110 Built zip installs and activates on clean WP 6.6 Local (WP_DEBUG on), shows its version | both | W | shipped | copy now: v4.0.0 release zip (Free) / Freemius Pro zip, not a 3.0.0 artifact; same as entry 8's install check | pending |
| P184 | epic-delivery-pipeline/story-tracer-bullet-release-pr-and-a-ci-built-plugin-zip-plan.md | 1 | L111 After merge the release PR "release 3.0.0" is open | n/a | X | shipped | one-off; release-please still opens release PRs (release.yml) | not re-run: outside the site, last evidence kept |
| P185 | epic-delivery-pipeline/story-translations-load-from-the-plugin-s-languages-folder-plan.md | 6 | L80 tests/i18n README rows 1–2: de_DE fixtures → PHP submenu/heading and React tile in German | both | W B | shipped | copy now: fixtures go to `wp-content/languages/plugins/`, JSON under md5 name (tests/i18n/README.md:12-14); plugin-folder loader removed by story 95 (e6098fe) | pending |
| P186 | epic-delivery-pipeline/story-translations-load-from-the-plugin-s-languages-folder-plan.md | 6 | L80 README rows 3–4: fixtures removed / default install → English, no notice with WP_DEBUG | both | W B | shipped | tests/i18n/README.md:15; `wp_set_script_translations` admin.php:241 | pending |
| P187 | epic-freemium-and-roles/story-author-quota-plan.md | 4 | L116 Author at the limit is refused, count in the toast | both | W B | shipped | rest-api.php:699 "Monthly limit reached (%1$d of %2$d AI actions used)…" | pending |
| P188 | epic-freemium-and-roles/story-author-quota-plan.md | 4 | L116 Counter visible next to the buttons, decrements after a trigger | both | B | shipped | src/kit/quota-note.js:21 "%1$d of %2$d AI actions left this month" | pending |
| P189 | epic-freemium-and-roles/story-author-quota-plan.md | 4 | L116 Admin raising the quota unblocks the Author | both | W B | shipped | `author_quota` in Usage & Limits tab (admin.php:740); features.php:223 | pending |
| P190 | epic-freemium-and-roles/story-author-quota-plan.md | 4 | L116 Cron batch leaves the quota counters unchanged | Pro | W | shipped | cron Pro-only (post-quality-manager.php:190-196, story 54); consume_quota on manual trigger only (features.php:243) | pending |
| P191 | epic-freemium-and-roles/story-author-quota-plan.md | 4 | L116 Editor sees no counter | both | B | shipped | features.php:208 quota only without `postquality_run_paid_agents`; Editor holds it (features.php:35) | pending |
| P192 | epic-freemium-and-roles/story-cron-under-the-gate-plan.md | 6 | L92 Unlicensed: gated `cron_command` set by option, cron run sends nothing to the webhook | Pro | W | shipped | class-pqm-cron.php:74 plan gate; copy now `wp cron event run postquality_cron_review_posts` (no WP Crontrol); Free build has no cron | pending |
| P193 | epic-freemium-and-roles/story-cron-under-the-gate-plan.md | 6 | L92 With the is_pro mu-plugin filter the same batch fires; quota counters unchanged | Pro | W | shipped | features.php:148; copy now filter `postquality_is_pro` (story 88) | pending |
| P194 | epic-freemium-and-roles/story-feature-gate-tracer-plan.md | 1 | L96 No license: Review runs end to end | both | B N | shipped | features.php:110 free tier; result lands only through n8n's callback | pending |
| P195 | epic-freemium-and-roles/story-feature-gate-tracer-plan.md | 1 | L96 Rewrite/Sources/SEO/Audit buttons locked with upgrade copy | Pro | B | shipped | detail-panel.js:118, copy.js:113 "Available with Pro — Review and Takeaways stay free."; Free build shows none (story 54, features.php:113) | pending |
| P196 | epic-freemium-and-roles/story-feature-gate-tracer-plan.md | 1 | L96 Direct REST trigger-review `command=rewrite` refused, nothing reaches the webhook | both | W | shipped | Pro unlicensed: 403 `pqm_feature_gated` (rest-api.php:303-318); Free: 400 `rest_invalid_param` (local-verification.md step 7) | pending |
| P197 | epic-freemium-and-roles/story-feature-gate-tracer-plan.md | 1 | L97 is_pro mu-plugin filter: Rewrite reaches the webhook unchanged; removing it relocks | Pro | W | shipped | features.php:148 `postquality_is_pro` | pending |
| P198 | epic-freemium-and-roles/story-feature-gate-tracer-plan.md | 1 | L98 AJAX trigger copied as fetch, command swapped to rewrite → 403 | both | B | retired-by-ui-rebuild/7 | `ajax_trigger_command` removed 9faad9f (story 5.7); only two AJAX actions remain (post-quality-manager.php:179-180) | not run (retired) |
| P199 | epic-freemium-and-roles/story-feature-gate-tracer-plan.md | 1 | L98 Ping (Test again) on the n8n Workflow page passes | both | B N | shipped | admin.php:2287 round-trip Ping OK (story 2 needs n8n's callback; the listener cannot pass it) | pending |
| P200 | epic-freemium-and-roles/story-license-provider-behind-is-pro-plan.md | 3 | L105 Enter any key → paid buttons unlock, paid command reaches n8n; clear it → relock | Pro | F | retired-by-44 | stub key never read (class-pqm-license.php:10, b3858fb); successor: Freemius license or trial (F) | not run (retired) |
| P201 | epic-freemium-and-roles/story-license-provider-behind-is-pro-plan.md | 3 | L105 Unlicensed: cron batch still runs `review` | Pro | W | shipped | class-pqm-cron.php:74 `plan_allows('review')` free tier | pending |
| P202 | epic-freemium-and-roles/story-license-provider-behind-is-pro-plan.md | 3 | L105 is_pro mu-plugin override unlocks with no license | Pro | W B | shipped | features.php:148 `postquality_is_pro` | pending |
| P203 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Plan L1-5 has no steps; checklist lives in the ticket file (rows below, ticket line numbers) | n/a | — | shipped | ticket story-manual-pass-of-the-epic-s-done-when.md L22-47, owner walked 2026-10-02 | pending |
| P204 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L27 No license: "Current plan: Free"; Review and Key Takeaways reach n8n | both | W B | shipped | copy now admin.php:814 "Current plan: Free — Review and Key Takeaways are included." | pending |
| P205 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L28 Paid buttons render locked with upgrade copy; clicking fires no request | Pro | B | shipped | detail-panel.js:86-125 aria-disabled no-op; Free build has none (story 54) | pending |
| P206 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L29 REST trigger-review `command=seo` → 403 `pqm_feature_gated`, no webhook call | both | W | shipped | rest-api.php:308 (Pro unlicensed); Free: 400 `rest_invalid_param` | pending |
| P207 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L30 AJAX copy-as-fetch with `rewrite` → 403 upgrade copy | both | B | retired-by-ui-rebuild/7 | AJAX trigger removed 9faad9f | not run (retired) |
| P208 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L33 Enter any key → "Current plan: Pro", Overrides + Automated Reviews unlock | Pro | F | retired-by-44 | successor: Freemius license → admin.php:820 "Current plan: Pro — …" (filter gives the unlock, not the line) | not run (retired) |
| P209 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L34 Each of the six commands runs once and stores a result | Pro | B N | shipped | features.php:110-129; needs license or `postquality_is_pro` filter | pending |
| P210 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L35 n8n Workflow page: version handshake quiet, no re-import prompted | both | B N | shipped | admin.php:1585-1590 outdated sentence only below version 11 | pending |
| P211 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L38 Author: quota counter decrements; paid buttons absent; no Settings menu | both | B | shipped | features.php:35, :208; admin.php:353-365 | pending |
| P212 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L39 Editor: Settings shows only agent sections, editable; Editor save keeps admin values | Pro | B W | shipped | copy now: AI + Automation tabs (story 27, admin.php:730-739); no license key stored since 44 | pending |
| P213 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L40 Contributor: no plugin menu; direct REST call → 403 | both | W | shipped | menu cap `postquality_view_reviews` (admin.php:55-62); local-verification.md step 6 | pending |
| P214 | epic-freemium-and-roles/story-manual-pass-of-the-epic-s-done-when-plan.md | 8 | Ticket L43 Grant Author paid-agents cap → paid buttons appear, counter gone; revoke restores | Pro | B W | shipped | copy now: Settings → Roles tab (story 42, ADR 0012, admin.php:743) or `wp cap add` | pending |
| P215 | epic-freemium-and-roles/story-refactor-sweep-plan.md | 7 | L90 None: no runtime behaviour change | n/a | — | shipped | plan states no manual check | pending |
| P216 | epic-freemium-and-roles/story-role-capabilities-replace-binary-checks-plan.md | 2 | L108 Contributor has no menu; POST /trigger-review → 403 | both | W | shipped | features.php:33-37; admin.php:55-62 | pending |
| P217 | epic-freemium-and-roles/story-role-capabilities-replace-binary-checks-plan.md | 2 | L108 Author runs Review, sees no paid buttons; direct `rewrite` → 403 role copy | both | W B | shipped | rest-api.php:309-312 role copy (Pro); Free answers 400 `rest_invalid_param` | pending |
| P218 | epic-freemium-and-roles/story-role-capabilities-replace-binary-checks-plan.md | 2 | L108 Editor sees paid buttons locked unlicensed, runs them with is_pro true | Pro | B W | shipped | features.php:35, :148 | pending |
| P219 | epic-freemium-and-roles/story-role-capabilities-replace-binary-checks-plan.md | 2 | L108 Settings and Workflow menus Admin-only | both | W B | shipped | Workflow: `postquality_manage_settings` (admin.php:88); Settings opens to Editors on Pro since freemium/5 (admin.php:353-365), Admin-only on Free | pending |
| P220 | epic-freemium-and-roles/story-role-capabilities-replace-binary-checks-plan.md | 2 | L108 A role editor granting paid-agents cap to Author makes paid buttons appear | Pro | B W | shipped | copy now: Roles tab (story 42) or `wp cap add`; no role-editor plugin on the clean site | pending |
| P221 | epic-freemium-and-roles/story-role-capabilities-replace-binary-checks-plan.md | 2 | L109 Delete caps-version option, load any page, caps are back | both | W | shipped | activator.php:147, :328; copy now `postquality_caps_version` (91); since story 42 only caps no admin role holds are granted | pending |
| P222 | epic-freemium-and-roles/story-settings-sections-follow-role-and-plan-plan.md | 5 | L112 Editor menu shows Settings | Pro | W B | shipped | admin.php:358-364; on Free an Editor gets no Settings (ADR 0014) | pending |
| P223 | epic-freemium-and-roles/story-settings-sections-follow-role-and-plan-plan.md | 5 | L112 Editor view has exactly the two agent sections: locked unlicensed, editable licensed | Pro | B F | shipped | copy now: AI (overrides) + Automation tabs (story 27); licensed half via Freemius or the filter | pending |
| P224 | epic-freemium-and-roles/story-settings-sections-follow-role-and-plan-plan.md | 5 | L112 Author has no Settings menu | both | W B | shipped | admin.php:353-365 | pending |
| P225 | epic-freemium-and-roles/story-settings-sections-follow-role-and-plan-plan.md | 5 | L112 Admin on an unlicensed site sees the paid sections locked | Pro | B | shipped | admin.php:984-1026 `__premium_only` renders with `$is_pro`; Free build has none (story 54) | pending |
| P226 | epic-freemium-and-roles/story-settings-sections-follow-role-and-plan-plan.md | 5 | L112 Presets, temperature sliders, reset buttons, model-caps toggling work on Admin and Editor views | Pro | B | shipped | pqm-admin.js:128-153, :211-244; copy now: the preset radio is the setting (story 26) | pending |
| P227 | epic-n8n-provisioning/story-an-administrator-adopts-an-existing-manual-setup-plan.md | 8 | L136 Hand-made setup, key stored: offer shows; proposal names workflow, credentials, "Nothing in n8n changes." | both | B N | shipped | src/n8n-connection/index.js:787, :831, :861 | pending |
| P228 | epic-n8n-provisioning/story-an-administrator-adopts-an-existing-manual-setup-plan.md | 8 | L136 Confirm → setup map stored (`wizard_created` false); n8n workflow's updated time unchanged | both | W N | shipped | index.js:842 "Manage this workflow"; copy now option `postquality_n8n_setup` (91) | pending |
| P229 | epic-n8n-provisioning/story-an-administrator-adopts-an-existing-manual-setup-plan.md | 8 | L136 Setup guide shows Connect your n8n and Set up the workflow ticked | both | B W | shipped | src/setup/steps.js step labels | pending |
| P230 | epic-n8n-provisioning/story-an-administrator-adopts-an-existing-manual-setup-plan.md | 8 | L136 Test again reads the workflow by id (GET /workflows/{id}) | both | W N | shipped | n8n-managed / n8n-adoption harnesses; n8n side seen only in its log | pending |
| P231 | epic-n8n-provisioning/story-an-administrator-rotates-the-webhook-token-from-the-plugin-plan.md | 10 | L131 Managed block's button rotates: status re-renders with version; new `webhook_token` stored | both | B W N | shipped | copy now button "Rotate token" (admin.php:1751, story 67); rest-api.php:1831 "Token rotated. %s" | pending |
| P232 | epic-n8n-provisioning/story-an-administrator-rotates-the-webhook-token-from-the-plugin-plan.md | 10 | L131 n8n: webhook credential's updated time moves, workflow's not; old token 403, new 200 | both | N | shipped | plan L132 walk; class-pqm-n8n.php rotate_token | pending |
| P233 | epic-n8n-provisioning/story-an-administrator-rotates-the-webhook-token-from-the-plugin-plan.md | 10 | L131 A Review on a post goes through after rotation | both | B N | shipped | round trip through n8n's callback | pending |
| P234 | epic-n8n-provisioning/story-one-click-updates-the-workflow-to-the-bundled-template-plan.md | 9 | L130 Outdated managed workflow: Update workflow → new version, same URL, credentials kept, Review goes through | both | B W N | shipped | admin.php:1642; rest-api.php:1879; adopted workflow asks first (story 76); force by lowering the stored version (plan L131) | pending |
| P235 | epic-n8n-provisioning/story-refactor-sweep-plan.md | 11 | L114 None: harness-pinned refactor, copy read in the diff | n/a | — | shipped | plan states no site check | pending |
| P236 | epic-n8n-provisioning/story-the-handshake-reports-a-mis-wired-workflow-in-one-sentence-plan.md | 7 | L119 Webhook credential on the Claude nodes, test ping: the one-sentence diagnosis shows | both | B N | shipped | class-pqm-n8n.php:563 "In n8n, the webhook credential is set on %s instead of…" | pending |
| P237 | epic-n8n-provisioning/story-the-handshake-reports-a-mis-wired-workflow-in-one-sentence-plan.md | 7 | L119 Restore the Anthropic credential, publish, test again: status clean | both | B N | shipped | copy now "Test again" on the page (admin.php:1481-1491) | pending |
| P238 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L117 Connect: "Connected to n8n. Checked …", probe ok, key hint shown | both | B W N | shipped | class-pqm-n8n.php:389; index.js:381, :903 | pending |
| P239 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L118 Provision: three credentials, one active per-site workflow, App Password, URL saved, ping OK, no key stored | both | W N | shipped | pqm-functions.php:357 path; Free build never walked (plan L127); Pro needs Application Passwords on the site | pending |
| P240 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L119 First review lands through n8n's callback (not proven on the sandbox) | both | B N | shipped | provable here: n8n on the same PC reaches the .local site (local-verification.md "n8n 6") | pending |
| P241 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L120 Forced failure: second run refused at activate (409), message, nothing left, its App Password revoked | both | W N | shipped | copy now class-pqm-n8n.php:1018 "…another active workflow … already listens on this site's webhook path." (bug 75) | pending |
| P242 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L121 Mis-wiring list path: ping finds the workflow by path, judges clean, records version | both | W N | shipped | copy now version 11 (post-quality-manager.php:95), not 6 | pending |
| P243 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L122 Adoption: offer, proposal, confirm, managed line; workflow untouched | both | B N | shipped | index.js:509, :787, :842, :861 | pending |
| P244 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L123 Mis-wiring setup-map path: sentence as notice-error, Test again the one primary | both | B N | shipped | class-pqm-n8n.php:563 | pending |
| P245 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L124 Update workflow (forced by lowering stored version): updated, node repaired, version recorded | both | B W N | shipped | copy now rest-api.php:1879 "Workflow updated. n8n is still switching…" variant (bugs 84, 99) | pending |
| P246 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L125 Rotation: "Token rotated. Ping OK"; credential time moves; old token 403, new 200 | both | B N | shipped | rest-api.php:1831 | pending |
| P247 | epic-n8n-provisioning/story-the-success-signal-walk-passes-and-the-records-say-so-plan.md | 12 | L126 Cleanup: workflow, credentials, map, URL, version record, App Password removed; Forget key | both | B W N | shipped | copy now "Start over" (index.js:648, story 71) and "Forget key" (:550); App Password revoke (bug 73) | pending |
| P248 | epic-n8n-provisioning/story-the-wizard-connects-and-provisions-n8n-or-hands-over-to-the-plan.md | 6 | L145 Wizard key path: Connect your n8n, Set up the workflow, to the first review | both | B N | shipped | src/setup/steps.js labels "Connect your n8n", "Set up the workflow", "Run your first review" | pending |
| P249 | epic-n8n-provisioning/story-the-wizard-connects-and-provisions-n8n-or-hands-over-to-the-plan.md | 6 | L145 By hand by choice: "Set up by hand instead" leads to the manual steps | both | B | shipped | steps.js "Set up by hand instead", "You chose to set up n8n by hand." (story 106 adds the way back) | pending |
| P250 | epic-n8n-provisioning/story-the-wizard-connects-and-provisions-n8n-or-hands-over-to-the-plan.md | 6 | L145 Wrong Anthropic key leaves nothing in n8n; the refused step shows | both | B N | shipped | class-pqm-n8n.php:1123, :1143 "…Nothing was created in n8n." | pending |
| P251 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L122 Pro: four steps in order, three credential blocks with Copy rows, six Claude nodes + Update WP Post | Pro | B W | shipped | copy now "Set up by hand" disclosure (story 67); admin.php:1936-2006 | pending |
| P252 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L122 Free: two credential blocks, "the two Claude nodes", no WordPress block | Free | B W | shipped | admin.php:1927 Free nodes hint | pending |
| P253 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L122 Every Copy works with its announcement | both | B | shipped | admin.php:2052-2067 `render_copy_value` | pending |
| P254 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L122 Each link opens its destination | both | B X | shipped | external targets (Anthropic Console, n8n docs) need internet; profile/Settings links local | pending |
| P255 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L122 A stored failure shows its dated line above the steps | both | W B | shipped | copy now admin.php:1561 "On %1$s, the n8n connection check did not pass: %2$s" (story 67) | pending |
| P256 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L123 Import URL row, its "keep it private" line and the From-URL import | both | B | retired-by-111 | 086a013: n8n 3 dropped Import from URL; step 1 now Download, then Import → From file | not run (retired) |
| P257 | epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md | 2 | L123 Downloaded JSON: per-site trigger path, current version, no placeholder, credential names | both | W | shipped | copy now 31 nodes Pro / 16 Free (templates/*.json), not 30/15; version 11 | pending |
| P258 | epic-n8n-provisioning/story-the-workflow-template-carries-a-per-site-webhook-path-plan.md | 3 | L135 Seed `__PQM_X__` or reset trigger path in a template copy: the guard fails naming it | n/a | X | shipped | repo-side guard mutation, no site (plan L136); template-guard-harness.php:160, :202 | not re-run: outside the site, last evidence kept |
| P259 | epic-n8n-provisioning/story-tracer-bullet-an-administrator-connects-an-n8n-instance-by-k-plan.md | 1 | L129 Instance shows connected and the key as set | both | B W N | shipped | class-pqm-n8n.php:389; index.js:381 "API key: set, ending in %s" | pending |
| P260 | epic-n8n-provisioning/story-tracer-bullet-an-administrator-connects-an-n8n-instance-by-k-plan.md | 1 | L129 Forget key returns "not set", address kept | both | B W N | shipped | index.js:373, :550 | pending |
| P261 | epic-n8n-provisioning/story-tracer-bullet-an-administrator-connects-an-n8n-instance-by-k-plan.md | 1 | L130 REST refusals: non-local http:// → `insecure`, nothing sent; bad key → `key` | both | W N | shipped | http allowed for localhost/127.0.0.1 only (local-verification.md "n8n 6") | pending |
| P262 | epic-ui-rebuild/story-bulk-review-selected-plan.md | 5 | L112 Low-quota Author selects more than remaining: first posts requested, rest show the limit message | both | B | shipped | bulk-bar.js:153 Modal; rest-api.php:699 | pending |
| P263 | epic-ui-rebuild/story-bulk-review-selected-plan.md | 5 | L112 Rows switch to Processing and completion toasts follow | both | B N | shipped | src/kit/runs.js:262 "%1$s complete — score %2$d, %3$s." | pending |
| P264 | epic-ui-rebuild/story-bulk-review-selected-plan.md | 5 | L112 View-reviews-only user sees no checkboxes and no bulk bar | both | B W | shipped | needs a custom role with `postquality_view_reviews` only (WP-CLI) | pending |
| P265 | epic-ui-rebuild/story-bulk-review-selected-plan.md | 5 | L112 Select-all skips in-flight rows | both | B | shipped | post-reviews-table.js:473 | pending |
| P266 | epic-ui-rebuild/story-bulk-review-selected-plan.md | 5 | L112 Changing page mid-batch clears the selection, report still completes | both | B | shipped | post-reviews-table.js:392; needs > 20 posts | pending |
| P267 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L122 Not connected: only the Connect n8n card; button for admins, sentence otherwise | both | B | shipped | src/dashboard.js:63-90 "Connect n8n to start reviewing" | pending |
| P268 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L123 Five tiles; each status tile opens Post Reviews with its chip pressed, counts equal | both | B | shipped | stat-tiles.js; copy.js:80-90 labels | pending |
| P269 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L124 Average score tile → Post Reviews sorted by score ascending | both | B | shipped | stat-tiles.js | pending |
| P270 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L125 Queue: ≤ 5 posts, documented order, never Ready-to-ship; row content | both | B W | shipped | fix-next-queue.js:51-57; seed statuses with WP-CLI | pending |
| P271 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L126 1–4 posts to fix → only those; none → "Nothing to fix next…" | both | B | shipped | dashboard.js:212 | pending |
| P272 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L127 Review from queue: toast → Processing → completion; tile and sparkline update; reload resumes | both | B N | shipped | runs.js, use-polling.js | pending |
| P273 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L128 After a bulk run, Dashboard shows no completion toast for a review still running | both | B | shipped | copy now "%1$s complete — score…" (runs.js:262) | pending |
| P274 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L129 View-only user: no Review button in the queue | both | B | shipped | fix-next-queue.js:5 | pending |
| P275 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L130 Sparkline heading, hover tooltip centred (also RTL); < 2 weeks → "No score history yet…" | both | B E | shipped | dashboard.js:274 "Average score — last %d weeks"; sparkline.js:36, :152 | pending |
| P276 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L131 First-review nudge; cron nudge (admin, cron off): Enable → cron section, Dismiss for good | both (cron nudge Pro) | B | shipped | dashboard.js:227, :294; admin.php:642-648 Pro-only dismiss | pending |
| P277 | epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md | 6 | L132 REST unreachable → "Could not load the dashboard…" notice | both | B | shipped | dashboard.js:170 | pending |
| P278 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | Plan L1-5 has no steps; checklist in story-manual-pass-epic-done-when-checklist.md (rows below, CL lines) | n/a | — | shipped | passed 2026-10-07 per memory index; the checklist file's Result block (CL L76-80) was never filled | pending |
| P279 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L27-28 Fix-next queue order; per-row Review runs with progress and toast; Author without Review sees none | both | B N | shipped | fix-next-queue.js; runs.js | pending |
| P280 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L29-30 Status tiles open Post Reviews with chip and matching count; Average tile sorts lowest first | both | B | shipped | stat-tiles.js | pending |
| P281 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L31 Sparkline trend, hover/focus gives week and value; < 2 weeks note | both | B | shipped | sparkline.js:36, :152 | pending |
| P282 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L32 Nudges show on their condition; a dismissal survives reload | both | B | shipped | dashboard.js:227, :294; feedback.js:115 (story 79 card) | pending |
| P283 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L36 Status chips filter, counts match, All resets, state survives reload | both | B | shipped | filter-bar.js:72, :91 | pending |
| P284 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L37 Score range narrows the list without "Could not load your posts" | both | B | shipped | copy now bands Any / Below 41 / 41–70 / 71+ (filter-bar.js:38-56), not min/max fields | pending |
| P285 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L38-39 Sort asc/desc on each sortable column survives reload; pagination with a filter | both | B | shipped | sortable-header.js; post-reviews-table.js:63 | pending |
| P286 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L40 Details opens/closes, tabs switch, meters and top edits render, buttons follow role and plan | both | B | shipped | detail-panel.js; locked buttons Pro only | pending |
| P287 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L41 Review selected: one Modal, sequential runs, per-post quota refusals | both | B | shipped | bulk-bar.js:153 | pending |
| P288 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L42 Quota note only beside a rendered Review button; Editor sees none | both | B | shipped | quota-note.js; features.php:208 | pending |
| P289 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L46 Yoast active: SEO/readability badges or placeholder; Details full width | both | B | shipped | rest-api.php:462 `WPSEO_VERSION`; needs Yoast SEO on the site (not on a no-other-plugin site) | pending |
| P290 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L47 Yoast deactivated: column gone, layout holds | both | B E | shipped | post-reviews-table.js:220 `yoastActive` | pending |
| P291 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L48 Pending Review post shows "— Pending" after its title | both | B | shipped | post-reviews-table.js:250 | pending |
| P292 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L49-50 Narrow window hides columns in order; checked with and without bulk column | both | B E | shipped | post-reviews/style.css:62-69 | pending |
| P293 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L56-58 Pseudo pack (fr_FR): every plugin string on both pages bracketed; component chrome French | both | W B | shipped | copy now: pack goes to `wp-content/languages/plugins/` under the md5 name (story 95), handle `postquality-app` (88); generator `pseudo-l10n.js` lived in an old scratchpad | pending |
| P294 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L59 Long translated strings wrap inside their cells | both | E | shipped | post-reviews/style.css:282 | pending |
| P295 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L60 Auto-resume: reload mid-run, polling resumes, result lands, both pages | both | B N | shipped | use-polling.js, use-review-wait.js | pending |
| P296 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L64-65 Tabs by arrow keys with focus ring; Details aria-expanded/controls; chips aria-pressed | both | B | shipped | post-reviews-table.js:292; filter-bar.js:8 | pending |
| P297 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L66-67 Toasts announced via speak(), "updating" line role=status; aria-sort on the active column | both | B | shipped | toasts.js:8; sortable-header.js:3 | pending |
| P298 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L68 Keyboard only reaches every control; bulk Modal traps and returns focus | both | B | shipped | bulk-bar.js:153 core Modal | pending |
| P299 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L69 Status never colour alone: icon + label + colour, no emoji; single-hue meters | both | E | shipped | status-badge.js; copy.js:80-90 | pending |
| P300 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L73 he_IL quick pass of sections 1–3: mirrored, quota note side, nothing clipped | both | E | shipped | built `style-index-rtl.css` | pending |
| P301 | epic-ui-rebuild/story-manual-pass-of-the-epic-s-done-when-plan.md | 9 | CL L74 Back in LTR nothing differs from before 5.8 | both | E | shipped | baseline is a 2026-10 look; only meaningful against the owner's memory | pending |
| P302 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L35 React pages load without the legacy admin CSS/JS | both | W B | shipped | admin.php:150-160 Dashboard/Post Reviews return before `postquality-admin` assets | pending |
| P303 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L36 Score badges 20/55/85/none → red/orange/green/grey | both | B E | shipped | src/kit/score-badge.js:2 | pending |
| P304 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L37 Free-plan Author: quota note right in the top bar (left in RTL) | both | B E | shipped | quota-note.js | pending |
| P305 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L38 Settings JS: presets, model select, Reset agent unchanged | both (Reset agent Pro) | B | shipped | pqm-admin.js:128-153; copy now: ping lives on the n8n Workflow page (story 67) | pending |
| P306 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L39 Workflow JS: wizard steps, Save & test, Copy import URL, fade animation | both | B | retired-by-67 | "Save & test" removed 7c7550a (67); wizard → setup dialog a8900c6 (36); Copy import URL removed 086a013 (111) | not run (retired) |
| P307 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L40 Old deep link with `&filter=needs_review` → unfiltered list, no error | both | B | shipped | copy now slug `postquality-posts` (story 88, 868c6ee); the old `pqm-posts` slug is no longer registered | pending |
| P308 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L41 POST admin-ajax `action=pqm_trigger_command` → 0/400, nothing runs | both | W | shipped | no such action (post-quality-manager.php:179-180) | pending |
| P309 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L153 Regression: Dashboard checklist items 2, 4, 9, 11 and the single-run AC | both | B N | shipped | see dashboard plan rows L123, L125, L130, L132 | pending |
| P310 | epic-ui-rebuild/story-php-strip-and-dead-weight-plan.md | 7 | L153 Settings and Workflow pages (setup on a disconnected site) with console open: no errors | both | B | shipped | admin.php:712, :1402 | pending |
| P311 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 Details → tabs by keyboard | both | B | shipped | detail-panel.js | pending |
| P312 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 Run Review → toast, Processing row, Details disabled, row updates in place, completion toast | both | B N | shipped | runs.js; detail-panel.js:91-99 | pending |
| P313 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 Reload mid-run → polling resumes | both | B | shipped | use-polling.js (listener leaves a processing row) | pending |
| P314 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 Rewrite shows locked with the Pro title | Pro | B | shipped | copy.js:113; Free shows no Rewrite (story 54) | pending |
| P315 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 Free-tier Author: quota note beside the buttons, decrements after a run | both | B | shipped | quota-note.js:21 | pending |
| P316 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 View-only user: Details works, no buttons, no quota note | both | B W | shipped | custom role via WP-CLI | pending |
| P317 | epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md | 4 | L126 Translated site: auto-resume still works | both | B N | shipped | needs a core language pack (internet) | pending |
| P318 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 Each chip filters and shows its count | both | B | shipped | filter-bar.js:72 | pending |
| P319 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 Score band narrows the list | both | B | shipped | filter-bar.js:38-56 | pending |
| P320 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 Every data column sorts both ways, arrow and aria-sort update | both | B | shipped | sortable-header.js:3 | pending |
| P321 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 Page 2 of a 20+ post site works | both | B W | shipped | post-reviews-table.js:63 PER_PAGE 20; seed posts with WP-CLI | pending |
| P322 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 URL deep link restores the state | both | B | shipped | post-reviews-table.js query args | pending |
| P323 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 Dashboard "needs review" card lands on the filtered list | both | B | shipped | copy now: Dashboard status tiles (ui-rebuild/6, stat-tiles.js) | pending |
| P324 | epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md | 3 | L118 View-only user: everything works, no quota note | both | B W | shipped | quota-note.js | pending |
| P325 | epic-ui-rebuild/story-post-reviews-yoast-column-and-pending-marker-plan.md | 10 | L112 Yoast on: column after Score; two coloured badges, or grey "—" with tooltip | both | B | shipped | post-reviews-table.js:173-204; needs Yoast SEO, which the clean site lacks | pending |
| P326 | epic-ui-rebuild/story-post-reviews-yoast-column-and-pending-marker-plan.md | 10 | L112 Pending post shows " — Pending" | both | B | shipped | post-reviews-table.js:250 | pending |
| P327 | epic-ui-rebuild/story-post-reviews-yoast-column-and-pending-marker-plan.md | 10 | L112 Details row spans the full width, Yoast on and off | both | B E | shipped | post-reviews/style.css | pending |
| P328 | epic-ui-rebuild/story-post-reviews-yoast-column-and-pending-marker-plan.md | 10 | L112 Yoast deactivated: no column, layout intact | both | B E | shipped | rest-api.php:462 | pending |
| P329 | epic-ui-rebuild/story-post-reviews-yoast-column-and-pending-marker-plan.md | 10 | L112 With and without the bulk checkbox column (Admin vs a role without Review) | both | B | shipped | post-reviews-table.js:552-557 | pending |
| P330 | epic-ui-rebuild/story-refactor-sweep-plan.md | 8 | L45 Single run on Post Reviews: toasts, busy state, Processing row, completion toast | both | B N | shipped | runs.js, use-single-run.js | pending |
| P331 | epic-ui-rebuild/story-refactor-sweep-plan.md | 8 | L46 Single run from the Dashboard queue (checklist item 6) | both | B N | shipped | fix-next-queue.js | pending |
| P332 | epic-ui-rebuild/story-refactor-sweep-plan.md | 8 | L47 Bulk: 3 selected, sequential triggers, per-post report | both | B | shipped | bulk-bar.js | pending |
| P333 | epic-ui-rebuild/story-refactor-sweep-plan.md | 8 | L48 Gated / hidden buttons the same on both pages (free plan, view-only user) | both (locked Pro) | B | shipped | detail-panel.js:86-125 | pending |
| P334 | epic-ui-rebuild/story-refactor-sweep-plan.md | 8 | L132 Both pages LTR and RTL, console open; Settings and Workflow unchanged | both | B E | shipped | style-index-rtl.css | pending |
| P335 | epic-ui-rebuild/story-status-and-copy-component-kit-plan.md | 2 | L111 Admin: badges and pills per mockup; no quota note (exempt) | both | B E | shipped | progress-pills.js, status-badge.js; features.php:208 | pending |
| P336 | epic-ui-rebuild/story-status-and-copy-component-kit-plan.md | 2 | L111 Free-tier Author: note shows correct numbers | both | B | shipped | quota-note.js:21 | pending |
| P337 | epic-ui-rebuild/story-status-and-copy-component-kit-plan.md | 2 | L111 View-only user: no note in the DOM | both | B W | shipped | quota-note.js | pending |
| P338 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L113 PR checks green, `admin-build` artifact uploaded | n/a | X | shipped | ci.yml build job | not re-run: outside the site, last evidence kept |
| P339 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L106/L116 Post Reviews renders the React table: score badge, status icon + label, progress chips, Last reviewed | both | B | shipped | admin.php:654-656 | pending |
| P340 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L117 Spinner on first load; console clean; page source ships only the root div | both | B W | shipped | admin.php:665-673 `pqm-post-reviews-root` | pending |
| P341 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L118 Post titles link to the editor for editable posts | both | B | shipped | post-reviews-table.js | pending |
| P342 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L121 > 20 posts render > 20 rows; > 100 shows "Showing the 100 most recently reviewed…" | both | B | retired-by-ui-rebuild/3 | pagination, 20 per page (8d195d0; post-reviews-table.js:63) | not run (retired) |
| P343 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L106/L124 `admin/build/` renamed away → build-instructions notice | both | W B | shipped | admin.php:687-708 "This page has not been built yet…" | pending |
| P344 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L125 (optional, unclaimed) REST unreachable → "Could not load your posts…" | both | B | shipped | post-reviews-table.js:441 | pending |
| P345 | epic-ui-rebuild/story-tracer-bullet-build-pipeline-and-react-post-reviews-shell-plan.md | 1 | L126 (optional) WP 6.0–6.5 → "needs WordPress 6.6 or newer" notice | n/a | — | retired-by-ui-rebuild/8 | removed 84183dc (5.8); header `Requires at least: 6.6` (post-quality-manager.php:9) blocks activation below | not run (retired) |

## Implementation Notes

- Deviation: the BMad skills registered mid-session, so bmad-build ran through the Skill tool, rendered with `--set workflow.route=full`. Subagents run on Opus (model split).
- Investigation (2026-11-11): four Opus agents inventoried the manual checks of 115 plans: the 104 that say "manual check" and 11 that describe owner walks in other words. Their reported totals (358 rows) differed from their tables (345), so the tables are what counts. A batch B claim, that uninstall cleanup runs only from WP Admin, contradicts `docs/local-verification.md` L206 (`--user` on both commands); the deletion check settles it.
- Draft #7 is not relied on: R5's limit (no update notice for GitHub installs) ends with story 96's first deploy, its criterion 7.
- Slip (2026-11-11): one planning command wrote a list to `/tmp/x`. The next command deleted it by its exact path.
- Approved 2026-11-11: answers 1a, 2b, 3a, 4a; the whole plan kept; "Approve and continue".
- Deviation (step 03): the agent implements directly instead of dispatching one implementer, because the owner's steps interleave with every walk (plan 87's precedent). Subagents may still run single walks or batches of rows, one at a time, never two walks on the site at once.
- Deviation (step 03): the approved plan is committed and pushed in a draft PR at approval (plan 87's precedent), because the story waits days or weeks on the directory's review queue. The owner's standing rule allows the push; step 03's "no push" holds for everything else until step 05.
- Task 1 (2026-11-11): the four tables were merged by a script in the scratchpad.
  - Ticket ids were normalized.
  - The unclear row (chore 87's look before the push) became n/a.
  - P147 went back to shipped: story 42 retired only its method ("View as"), and a real Editor user runs it.
  - The other 18 retired rows were checked: each cited commit exists and its subject names the retiring ticket.
  - A hygiene scan against the private pattern found no site address. It did find the generic ".local" and "WordPress66"; the latter is already in entry 8's plan on main.
- Plan PR: draft #8 (a84193b), 2026-11-11.
- Builds (2026-11-11, into the session scratchpad):
  - **Free:** `post-quality-manager-4.0.0.zip`, sha256 `776cd175f513597c0282cdaaedd47a6a6b9f320c29710c3a28c1bc28d1b14c3b`, equal to the release digest. 255 files under `post-quality-manager/`, `Version: 4.0.0`, no cron class, the Free template only.
  - **Pro:** `post-quality-manager-4.0.0-pro.zip` from run 43019155750, sha256 `0416455841a6b8a199f7d3e90b012cdbc0e8ebba8da01346abe75a870a51b583`. 257 files under **`post-quality-manager-premium/`**, the folder Freemius serves to a customer, so it installs beside the Free folder, not over it. `Version: 4.0.0`, with the cron class and both templates.
- Frame on a clean site:
  - The site starts with no plugin, so the backup records an empty plugin list and a checksum list of `wp-content/plugins/` and `wp-content/mu-plugins/` instead of zipping a plugin folder.
  - The restore deletes the walk's plugin folders by their exact paths (`post-quality-manager/`, `post-quality-manager-premium/`, Plugin Check's, and the logger file), then imports the dump and copies `wp-config.php` back.
  - Commands that name the slug take `post-quality-manager-premium` in the Pro walk.
  - The leftover check after the 4.0.0 deletion is the baseline's proof.
- Sandbox purchase (owner, through Claude in Chrome, 2026-11-11):
  - The checkout came from Plans → Pro → USD → Single Site → "Checkout Link" → "Sandbox / Testing" (`…/licenses/1/?sandbox=true`).
  - It showed Pro, Annual, Single Site, $49.00 USD, and "Prefill Form (Only visible in Sandbox mode)". The test card ended in 4242. A required "Confirm email address" field appeared after the email.
  - The result is one Pro license for 1 site, expiring 2027-11-12, with its user and subscription. The dashboard's Licenses page does not mark it as sandbox.
  - Its IDs are in the agent's private notes for chore 81. The key never reached the agent.
- Site gate (2026-11-11, WordPress66 after the owner's switch):
  - The site: PHP 8.1.29, WordPress 6.6 with the 6.6 database schema (`db_version` 57155), MySQL 8.4.0, one user, only 4.0.0 installed, no must-use folder, port 8765 free, `WP_ENVIRONMENT_TYPE` local.
  - A database export went to the scratchpad first. Then 4.0.0 was removed with `plugin deactivate` and `plugin uninstall`, both with `--user=1`.
  - After the deletion: no `postquality_*` option, no table, capability, user meta, post meta or cron event, and no plugin folder. This matches `docs/policies/privacy.md` L29.
  - **Six Freemius SDK options stayed:** `fs_accounts`, which still names this plugin (its `plugin_data`, `plugins`, `file_slug_map` and product id), plus `fs_active_plugins`, `fs_api_cache` (an empty array, so no API reply was cached), `fs_debug_mode` and the two `fs_garbage_collection` site transients.
  - Under Decision Q1 that trace sends the question back to the owner.
  - **Owner, 2026-11-11 (5a):** the six options are deleted by exact name, which keeps the site's genuine 6.6 database. The check then read all zeros: no plugin or Freemius option, table, capability, meta, cron event, plugin folder or must-use folder; one user; `debug.log` none. **The site gate passed.**
  - Owner, 6b: the SDK's behaviour goes into `memory/freemium-and-roles.md` (done), and a docs ticket adds one sentence to the privacy policy; it is filed with the walk's findings.
  - Why the SDK keeps its state: `_uninstall_plugin_event()` ends with `// @todo Decide if we want to delete plugin information from db.` (SDK 2.13.5, `class-freemius.php` L9773). `_uninstall_plugin_hook()` removes only the SDK reference, then fires `after_uninstall` (L9855, L9878). The SDK's `delete_account_event()` clears that state but sends `DELETE` to the API (L8097), so the plugin cannot call it at deletion without a request from a site that skipped the opt-in (ADR 0013 D8).

- Frames: the Free walk splits into frames, each from its own backup to its own "same" restore. F1 carries criterion 1, the owner's look and the form scan. Later frames carry the pass list's rows in batches, one frame on the site at a time.
- **F1, first run (2026-11-11), discarded.** Activation, the opt-in and the page reads passed on 6.6.
  - Then `wp cron event run --all` ran core's `wp_maybe_auto_update`. Major auto-updates are on by default for new installs (`auto_update_core_major` enabled), so WordPress upgraded itself to 7.1.4, files and database, and row 2 ran on 7.1.4.
  - Recovery:
    1. The listener was stopped by its recorded PID, and the walk's restore brought the database back to the 6.6 schema (57155).
    2. The core files went back to 6.6 through `core download --version=6.6 --force --skip-content`, run with `-d sys_temp_dir=<scratchpad>`. WP-CLI removed 747 files 6.6 does not have.
    3. Checksums verify against 6.6, `wp-content` was untouched (every theme dates from the site's creation), and the three "same" checks passed.
    4. Two half-extracted folders from failed download attempts were removed from `C:\Windows\TEMP` by their exact names.
  - Guard: `AUTOMATIC_UPDATER_DISABLED` true is now in the site's `wp-config.php`, part of the baseline (owner told 2026-11-11). Each walk checks `wp core version` at its start and end.
- **F1, rerun (2026-11-11, folder `walk-f1b`, WordPress 6.6, PHP 8.1.29), through the owner's pause:**
  - **Row 1 passes.** The checked Free asset was installed by WP-CLI and activated from the Plugins screen in headless Edge. The activation opened Freemius's opt-in ("Never miss an important update"), and Skip landed on the plugin's Dashboard. Every plugin page and tab was then read, along with Posts, a post's editor, Profile, the privacy guide and Plugins, followed by one run of all 12 cron events. The request logger recorded 13 outgoing requests, all from core (api.wordpress.org, rpc.pingomatic.com, wp-cron). None came from the plugin's folder, the SDK included.
  - **Row 2 passes.** Review (post 8) and Key Takeaways (post 9) reached the listener with `X-PQM-Token`. `sources`, `seo`, `audit` and `rewrite` answered 400 `rest_invalid_param`. Per role, `GET /posts` answered 200 for administrator, editor and author and 403 `rest_forbidden` for contributor.
  - **Row 3 passes** (the agent's DOM read; the owner's look is still to come):
    - No page holds a Pro command or setting.
    - The only upsell is Settings → License, "Current plan: Free — Review and Key Takeaways are included. Get Pro" (→ postquality.com/pricing/), shown on every Settings tab.
    - The Plugins row reads "Opt In | Deactivate"; the SDK's opt-in link is not an upsell.
    - The Settings tabs on Free are Connection, AI and Usage.
    - The browser's only other-host requests are core's gravatar and the block editor's welcome image.
    - The Free build has 20 REST routes (`routes-free.txt` in the scratchpad), to be compared with Pro's.
  - **Row 4 passes.** All 11 links in the asset's `readme.txt` end with a 200 on a page that asks for no sign-in (Python GET, redirects followed). The GPL link redirects to GNU's old-licenses page. The repository and its Releases page now open.
  - Row 7 so far: no `debug.log` at all.
- **F1, after the pause (2026-11-11):**
  - The owner's look ran through Claude in Chrome from an agent-written prompt with 8 steps; every expected result was quoted from a fresh DOM read of the same state. Result: 5 pass, 3 differ. The look-and-feel notes go to the triage below.
  - **Row 7 passes.** After the look, `debug.log` still does not exist. The logger recorded 18 core requests in total and only the plugin's two webhook POSTs. WordPress was still 6.6.
  - **Row 5, the form's scan:** Plugin Check 2.2.0, installed after the `debug.log` read and run from the scratchpad on the installed asset with the form's exact arguments, reports **0 `ERROR`** and **32 `ERROR_LOW_SEVERITY`**:
    - 22 `WordPress.WP.I18n.MissingTranslatorsComment`: 8 in `admin/class-pqm-admin.php`, 8 in `includes/class-pqm-n8n.php`, 6 in `includes/class-pqm-rest-api.php`;
    - 10 `WordPress.Security.EscapeOutput.OutputNotEscaped` in `admin/class-pqm-admin.php`. Build lines 643, 1018, 1305, 1321 (twice), 1337, 1353, 1649, 1714 and 1814 echo variables built earlier (`$license_line`, `$guided_link`, `$primary`, `$secondary`, `$by_hand_link`, `$n8n`, `$nodes_pick`, `$json`, and `self`).
    - **What the form does with them** (WordPress.org trunk, read 2026-11-11):
      - Only a record whose type is exactly `ERROR` sets the verdict to false (`plugin-directory/jobs/class-plugin-scan.php`, `run_plugin_check()`), so these do not block the upload.
      - The upload handler (`shortcodes/class-upload-handler.php`, `check_plugin()`) shows them as "ERROR*" with "This may be a false-positive, and will be manually checked by a reviewer."
      - The scan runs under `timeout -k 15s 45s`.
    - Story 95's "0 errors" (2026-10-29) came from a default-severity run. The form's `--error-severity=7 --include-low-severity-errors` was never run, and Plugin Check reports these 32 below the default threshold, so neither CI nor that run showed them.
  - **Restore:** the listener was stopped by its PID. Plugin Check and the plugin were deleted, the logger and its folder removed, the database and `wp-config.php` restored. The three checks read "same", and WordPress is 6.6. Both walk folders are deleted; the scan output, the route list and the page reads stay in the scratchpad's `s38/findings/`.
- Tooling findings, filed in one batch through `bmad-ticket` with the walk's other findings:
  1. `docs/local-verification.md` step 4's `option patch insert` waits forever when stdin is an open pipe, as in agent shells. WP-CLI 2.12.0 reads the value with `get_value_from_arg_or_stdin( $args, -1 )`, which never finds index -1, so it reads stdin to its end. `</dev/null` fixes it.
  2. Local's `php.ini` pins `sys_temp_dir` to `C:\Windows\TEMP`. `TEMP`/`TMP` don't move it, and `bin/local-wp.py` cannot pass `-d`.
  3. The pre-production site's one-time setup must turn automatic updates off.
  4. The docs ticket from 6b (one privacy policy sentence).

## Plan Change Log

## Review Triage Log
