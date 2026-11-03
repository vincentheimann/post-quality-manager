---
title: "postquality.com's docs show every feature, and which ones are Pro"
type: 'feature'
ticket: '100'
created: '2026-11-03'
status: in-review
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
baseline_revision: 'c0e7b109dd71e406fe9f26d6fe49d91a86e2f424'
context: ['{project-root}/docs/brand.md', '{project-root}/docs/local-verification.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** `/docs/` gives the Settings features two lines and shows no Pro screen, so a visitor the homepage interested cannot tell what the plugin does or what Pro adds.

**Approach:** Add `/docs/features/` to postquality.com: one section per Settings tab, per command and for Insert into Post, each with a desktop and phone capture from the showcase site running the licensed Pro build; Pro sections carry one consistent mark linking to pricing; docs and homepage link to the page in both launch states; the showcase is restored and the license slot freed afterwards.

**Decisions (owner, 2026-11-03, plan checkpoint):**
- **Pro mark:** the site's `.chip` as a link reading "Pro" after each Pro heading, to `/pricing/#compare-h`, with a focus style.
- **License:** the owner activates the current 1-site lifetime license on the showcase (Freemius counts `.local` sites as free activations); the quota is raised to 2 only if Freemius refuses. The owner enters the key in the showcase's WP Admin, so the key never reaches the agent. It is deactivated before the restore.
- **Captures:** a fresh Pro-build set, every section with a desktop capture, and a phone crop where the screen fits a phone: Insert into Post, Connection, Agent-Specific Overrides, Automation, Usage & Limits. The six Post Reviews sections (the panel is wider than a phone), AI (bug 107) and Roles (bug 108) show their desktop capture on phones, as the site's `review` and `review-list` do (owner, 2026-11-03, review pass 1, renegotiating "every section with a desktop and a phone capture"). AI and Roles may get phone crops back once those bugs are fixed.
- **Copy rule:** the site README's drift rule in full: labels are the interface strings, facts come from `readme.txt`, the hint strings and the gate message.
- **Homepage link:** one line under the Free/Pro lists, beside "See Pro pricing", in both launch states.
- **Size:** one plan, kept whole (about 2,000 tokens, above the 1,600 guideline: the owner's choice).

## Boundaries & Constraints

**Always:** name the repository per task; copy per `docs/brand.md` (full name first, no "PQM", no third-party mark, no unmeasured KPI); captures only from the showcase, every image inspected for a site title, post title, user name or URL before commit (lesson 2026-10-24); no owner domain, site title, host, key or credential in any record; the Pro/free split as the code has it; pre-launch wording reuses `docs.md:87`; LF files; Opus subagents.

**Never:** change `pricing.html`, `launched`, the docs setup steps (beyond the link) or plugin code; a homepage features block beyond one link; translations; a walk against anything but the showcase; leaving the license active or the Pro build installed there.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Pre-launch | `launched: false` | Page renders, marks link to pricing, one "goes on sale" line, no install or buy link | Build fails on a figure without width/height |
| Launched | overlay `launched: true` | Same page without that line; interest email names "the features page" | — |
| Walk end | showcase after captures | Free build, DB and wp-config as backed up; license deactivated; same-checks pass | Deactivation refused: stop, report; owner frees the slot in Freemius |

</frozen-after-approval>

## Code Map

Site repo `postquality.com/`:
- `docs.md` -- "Using it" l.75-81 (bold label + sentence, Pro as plain "(Pro)"); pre-launch sentence l.87; include call l.54. Pages are root `.md` files with `layout: page` + `permalink`.
- `_includes/screenshot.html` -- params `src width height phone phone_width phone_height alt caption variant priority`; files `assets/img/NAME.{png,webp}`, `NAME-phone.*`. Existing captures (Free build): `dashboard`, `setup` with phone crops; `review-list`, `review` without.
- `_includes/header.html:1-11` -- `page.url` → `hd_from` case; add `/docs/features/`. No nav entry; sitemap automatic.
- `index.html` -- Free/Pro lists l.138-166, "See Pro pricing" l.167; `hero-link` l.24; closing l.194-208.
- `assets/css/site.css` -- tokens l.8-23, `.chip` l.476-492, `.text-page` l.644-660; no badge class. `DESIGN.md` (ux-postquality-website): L468 no pills, L518 chip, L523 plan-note, L544 no scarcity badges.
- `README.md` -- l.19 drift rule, l.25 capture recipe ("six plugin pictures"), l.17/29 builds in both states.

Plugin repo `post-quality-manager/` (code read-only):
- `admin/class-pqm-admin.php` -- tabs l.712-729: the Free build has Connection, AI, Usage & Limits; Pro adds Automation and Roles. **Pro sections:** Agent-Specific Overrides l.1033-1181 (model, effort, temperature, instructions, Pattern to use), Automated Reviews l.1189-1292, Roles l.1300-1358; locked copy l.1042, 1210-1212, 1310. All else on the tabs is free, Notification URL included.
- `includes/class-pqm-features.php:108-131` -- Review, Takeaways free; Sources, SEO, Audit, Rewrite Pro. `class-pqm-rest-api.php:288-319` + `class-pqm-blocks.php:48-108`: Insert into Post follows the command's tier, the pattern choice is Pro. One-liners: `readme.txt:19-20`, the prompts in `templates/n8n-workflow.json`.
- `includes/class-pqm-db.php:25` `save_review( post_id, command, score, status, result )` -- story 86 seeded fictional rows with it (`wp eval-file`, meta `_pqm_demo`).
- `docs/local-verification.md` -- backup l.70-81, Pro build l.41-54, restore l.168-179, same-checks l.181-201. `memory/local-verification.md` l.18 (scripted Edge, WP-CLI cookie, key typed key by key); `memory/freemium-and-roles.md` l.16 `deactivate_license`, l.25 `.local` hosts are free activations.
- Story 86 recipe: `_bmad-output/backlog/story-site-navy-and-check-plan-3.md` l.78-97 (1440 px desktop, 800 px phone, CSS hides admin bar/footer/notices, 2x, PNG + WebP < 250 KB).

## Tasks & Acceptance

**Execution:**
1. Page and copy (site)
- [x] `features.md` -- new: `layout: page`, `permalink: /docs/features/`, unique `description`, toc; h2 per Settings tab, per command and Insert into Post in the admin's order, each h2 the plugin's own label, one or two sentences for the blogger, then its figure -- AC1, AC2.
- [x] `docs.md`, `index.html`, `_includes/header.html` -- one "Using it" bullet, one homepage link (the decision above), the `hd_from` case -- AC1.
2. Pro mark (site)
- [x] `features.md` + `site.css` -- the mark (the decision above) on Overrides, Automation, Roles, Sources, SEO, Audit, Rewrite and the Pro parts of Insert into Post, linking `/pricing/#compare-h`; one `{% unless site.launched %}` line at the top -- AC3.
3. Capture walk (showcase; scripts stay in the scratchpad)
- [x] **Stop and report before this task.** The owner starts the showcase in Local and says go; the implementer resumes from here.
- [x] Backup; install the Pro build (GitHub Release asset of the current version, as story 98; `npm run zip` fallback); confirm with `class_exists("PostQuality_Cron")`.
- [x] **Owner:** activates the license in the showcase's WP Admin (the decision above); the implementer waits for the owner's word, then checks the plan with WP-CLI. The key never enters a record or the agent's hands.
- [x] Seed fictional Sources, SEO, Audit results (shape per the detail tabs in `src/post-reviews/`), a fictional override, schedule and role values; title "Yourblog", user "Demo".
- [x] Capture each section desktop + phone as story 86 did; inspect every image; alt says what is shown, caption ends "Demo posts, fictional scores." -- AC4.
- [x] Restore: deactivate the license on the SDK Account page first (a DB restore does not release it), then plugin, DB, wp-config; same-checks -- AC5. **Owner:** confirm the slot is free.
4. Site PR
- [ ] Build both states; screenshots at 1280, 390, 320 px; Lighthouse accessibility; branch `feat/site-features-page`, push via explicit URL, draft PR `--repo vincentheimann/postquality.com`; **Owner** reviews copy and captures, merges.
5. Records
- [x] Site `README.md` l.5/25 (count, Pro-build note); `docs/user-paths.md` A1-A2 CV; `memory/local-verification.md` (capture pointer, deactivation step); chore 81's list; ticket flip; Implementation Notes.

**Acceptance Criteria:**
- Given either launch state, when the page is built, then every AC2 section has a figure, Pro marks link to pricing, free sections carry none, docs and homepage link to it.
- Given the showcase after the walk, when plugin list, DB and wp-config are compared with the backup, then they match and the Free build runs.

## Implementation Notes

- Planned by a Fable 5.1 subagent (owner's exception for this story); implemented by an Opus 5.5 subagent. bmad-build was rendered by hand from the workspace root. The baseline above is the plugin repository's; the site repository's baseline is `f951757460123166c48586bd3b86705b5a5bf6e7`.
- 2026-11-03, tasks 1-2 (Opus 5.5 subagent), site branch `feat/site-features-page`, commit `a96aced` (local, not pushed: the push and the draft PR wait for the captures, task 4). Stopped before task 3 as the plan says; nothing ran against the showcase.
  - `features.md`: the six commands in the Post Reviews order (`COMMAND_ORDER`: Review, Takeaways, Sources, SEO, Audit, Rewrite), then Insert into Post, then the Settings tabs (Connection, AI, Automation, Usage & Limits, Roles). "The admin's order" read as the menu's: Post Reviews comes before Settings. Headings are the registry and tab labels. Two Pro parts inside free sections got an h3 under their own interface label, each with the mark: **Agent-Specific Overrides** under AI (planned with its own figure, `feature-overrides`) and **Pattern to use** under Insert into Post (no own figure: it points to the Overrides, whose capture opens the Key Takeaways Agent with that control). 13 figures planned, 26 images (since review pass 1, 18 ship: 13 desktop captures and 5 phone crops).
  - Figures: each section carries a Liquid comment `FIGURE feature-<slug> + feature-<slug>-phone (story 100, task 3): <what to show>`, so the branch builds before the captures; task 3 replaces each with the `screenshot.html` include (sizes, alt, caption).
  - The mark: one `{% capture pro %}` at the top of the page, `<a class="chip chip-link" href="/pricing/#compare-h">Pro</a>`, inside the heading after its label (headings read "Sources Pro"). `.chip-link` in `site.css`: inline-block, no underline, navy fill and white text on hover and on `:focus-visible`, the global ring on top. 8 marks: Sources, SEO, Audit, Rewrite, Pattern to use, Agent-Specific Overrides, Automation, Roles.
  - Homepage: `<p class="plan-link">` "See every feature →" right under **See Pro pricing** in the Pro card, before its footnote, in both states. Docs: a sixth "Using it" bullet. Header: `/docs/features/` → "the features page".
  - Outside the Code Map's file list: `_includes/screenshot.html` now fails the build when `width`/`height` (or a phone crop's `phone_width`/`phone_height`) is missing, by including a file that does not exist (`ERROR-screenshot-needs-width-and-height`); this is the matrix's "Build fails on a figure without width/height". Tested with a temporary page: exit 1 with that name in the error, the page removed after.
  - Copy sources (drift rule): Review = `readme.txt` l.19 + the Review prompt's status rubric; Takeaways = l.20 + its prompt (3 to 5 items, 25 words, the existing block); Sources, SEO, Audit, Rewrite = their prompts in `templates/n8n-workflow.json` (Rewrite's Pending Review and revisions = Format Rewrite Confirmation; Yoast = readme External services; HTTPS = the Application Passwords string, `class-pqm-admin.php` l.2000); Insert into Post = the confirmation's strings in `src/post-reviews/insert-block.js`; Pattern to use = its hint; Connection = the n8n Connection description + readme's Notification URL paragraph; AI = the Default Model, AI Prompts & Context and agent-table hints; Overrides = its intro; Automation = the cron hints + the Pro quota hint; Usage & Limits = readme FAQ + the quota hint; Roles = the column labels and the two hints; intro = `LOCKED_TITLE`; pre-launch line = `docs.md` l.87.
  - Verified: both states build (`launched.yml` overlay in the scratchpad): `docs/features/index.html` in both, the "goes on sale" line only before launch, 8 marks in each, no install or buy link before launch, the interest email body "Sent from the features page.", `sitemap.xml` lists the page; the homepage line and the docs bullet in both. Looked at 1280 px (window) and 320 / 390 px (an iframe of that width: headless Edge's window has a minimum layout width above 320, so `--window-size=320` alone clips): the chips sit on the heading line, wrap cleanly, no horizontal scroll. Lighthouse waits for task 4, with the figures in.
  - Captions (owner via the coordinator, 2026-11-03): captures that show posts or scores end "Demo posts, fictional scores."; the Settings captures end "Demo site."
- 2026-11-03, task 3 started on the owner's go (the showcase started in Local). Pre-checks: port 8765 free, no `pq-smoke-*` user. Backup per `docs/local-verification.md` step 1 into the session scratchpad: the showcase ran the 3.9.0 Free build (2 plugins active, 13 tables, `wp_pqm_reviews` 30 rows, 1 user, no `debug.log`, 215-line checksum list), dump with 13 `CREATE TABLE`, the plugin folder zipped, `wp-config.php` copied (never printed; compared by checksum only). Pro build: the GitHub Release asset `post-quality-manager-3.10.2.zip` (sha256 `e4ab80b1…8afa`, equal to the release's digest; root `post-quality-manager/`), installed with `--force`; `WP_DEBUG`, `WP_DEBUG_LOG` on and `WP_DEBUG_DISPLAY` off (so no notice reaches a capture). `class_exists("PostQuality_Cron")`: Pro build; version 3.10.2; the stored-names upgrade renamed the table to `wp_postquality_reviews`; Freemius: anonymous, not registered, not paying, `PostQuality_Features::is_pro()` false; no `debug.log`. Waiting for the owner's license activation.
- 2026-11-03, task 3 resumed on the owner's word that the license is active. WP-CLI: registered, paying, `can_use_premium_code` true, `PostQuality_Features::is_pro()` true, plan `pro` (no key or secret read). The showcase already had "Yourblog", user "Demo", 26 demo posts and 30 rows from story 86.
  - Seed (`wp eval-file`, scratchpad `seed.php`, refuses a second run): for one demo post, fictional Takeaways, Sources, SEO, Audit results shaped as the template's Parse nodes store them (scores and statuses by their rules) and a Rewrite row (`rewritten`); a published pattern "Key takeaways box" with a List block; settings: a placeholder webhook URL on a reserved `.example` host (never called), Blog Profile Technical / IT, Editorial Standards Direct, an override for the Key Takeaways Agent (Haiku 4.5, temperature 0.2, two sentences of instructions, the pattern), Automated Reviews on (weekly, 5, Review, 70, 90 days); the Contributor role given View reviews. The `updated_option` hook was removed in the seed and `DISABLE_WP_CRON` set, so no cron event was scheduled (checked: none). The Sources row was trimmed to two claims after a first look, so its tab fits one crop.
  - Captures: Playwright from `node_modules/playwright-core` driving Edge (persistent profiles in the scratchpad), a WP-CLI session cookie for user 1 kept in a scratchpad file, never printed; capture-time CSS hides the admin bar, the footer, screen options and non-inline notices only. Desktop: a 790 px window at 2x, crops of the post's detail panel or of Settings (heading, tabs, panel), 720 px wide (1440 px), the confirmation 758 px (1516 px); phone: a 400 px phone viewport at 2x (762 to 800 px). Heights capped (desktop 700 to 900 px CSS, phone up to 1078): the Audit crop ends before its takeaways' Rewritten items, the AI crop after the Blog Profile, the Overrides crop after Pattern to use (phone: from the Key Takeaways Agent). Every one of the 26 looked at full size: no site title, user name or post title in frame, URLs only the `.example` placeholder and the plugin's own field placeholder, no logo. Exported as PNG + WebP, each under 250 KB (`feature-ai-phone.png` as a 256-colour palette), copied to the site's `assets/img/`, the figures filled in `features.md` (sizes, alt with the frame's figures, captions per the owner's rule; Insert into Post's confirmation ends "Demo site.": it shows no post or score).
  - Found, not fixed (the walk changes no plugin code; to file as bug tickets, ids left to the coordinator because another session files tickets in parallel): (1) on a phone (WordPress's admin below 783 px), Settings → AI shows the empty "Custom model ID" field under Default Model although Custom is not selected: core's mobile rule for text inputs in `.form-table` outranks `.pqm-hidden { display: none; }` (`admin/css/pqm-admin.css` l.20); it showed in the AI phone crop, dropped in review pass 1 (bug 107). (2) On a phone, the Roles table is wider than the screen and cuts its last column (bug 108; its phone crop dropped in review pass 1); Post Reviews' table scrolls sideways by design (min-width 51em), so its phone crops ended at the screen's edge and were dropped too.
  - Restore: **Deactivate License** on the Account page (Playwright, confirm accepted): "Your license was successfully deactivated, you are back to the Free plan."; WP-CLI then read not paying, plan `free`, no license attached. `debug.log`: 53 lines, all WP-CLI's own. Plugin zip (`--skip-plugins`), `import` (13 tables dropped, 13 now), `wp-config.php` copied back, `debug.log` deleted. Same-checks: plugin list same; tables, review rows, users, log same; plugin files and `wp-config.php` same (`cmp` of the checksum lists, and `cmp` of `wp-config.php` with its backup); `class_exists("PostQuality_Cron")`: Free. The owner confirmed (2026-11-03) that Freemius's activation count is back to its pre-walk value; Freemius keeps the install record the activation created (chore 81).
- 2026-11-03, tasks 4-5. Both states built; 13 figures and 8 marks in each, the line only before launch; a check of the built page: marks only on the eight Pro headings, each to `/pricing/#compare-h`, a figure in each of the 12 h2 sections (13 with the Overrides). Image check: 52 files (36 since review pass 1), pixels equal to the includes' sizes, each under 250 KB. Screenshots of `/docs/features/`, `/` and `/docs/` at 1280, 390 and 320 px in both states (Playwright's viewport, so 320 needs no iframe): no horizontal scroll, every image loaded. Computed styles: the mark navy on white at rest; navy fill with white text on hover and on keyboard focus, plus the 3 px navy ring 2 px out on focus (14.44:1 each); 52 × 35 px. "See every feature" white on the navy Pro card (14.44:1), underlined. Lighthouse 12.8.2, accessibility, headless Edge, both states × the three pages × mobile and desktop: 12 reports, all 100, no failing audit. Site commits `a96aced`, `8480c12` on `feat/site-features-page`, not pushed: the coordinator runs the review lens, pushes and opens the draft PRs (owner's instruction via the coordinator). Records: site README (file list, drift rule, Captures), `docs/user-paths.md` A1 and A2 CV, `memory/local-verification.md` (licensed Pro captures, restore order), chore 81's Notes. Deviation: README's drift rule (l.19) also names `features.md` and its sources, beyond the plan's l.5/25. Scratchpad files deleted by path at the end; no browser or server of this run left running.
- 2026-11-03, review pass 1 fixes (same implementer, site commit `5eab9e2`): the 8 phone crops the owner's revised Captures decision drops are gone from `features.md` and `assets/img/` (16 files, `git rm`); the Overrides alt now opens on the Key Takeaways Agent, true of both its crops; the Overrides paragraph's last sentence says what the capture shows (an unsupported Reasoning Effort or Temperature is turned off, with a note to use the other; from the two hint strings); `docs.md`'s Insert into Post bullet marks Sources and the pattern "(Pro)"; the README's drift rule binds `docs.md` and `features.md`, its Captures paragraph counts 13 desktop captures and 5 phone crops. Checked: both states rebuilt, 13 figures, 5 phone crops and 8 marks in each; 36 image files, sizes equal to the includes, each under 250 KB, no feature file unused. No new walk.

## Plan Change Log

- 2026-11-03, review pass 1, intent_gap (triage rows 1–3, group A): 8 of 13 phone crops could not show their screen whole (the Post Reviews panel is wider than a phone; bugs 107 and 108 on AI and Roles). The owner renegotiated the frozen **Captures** decision: phone crops only for the 5 screens that fit, the desktop capture on phones for the other 8. Known-bad state avoided: phone visitors seeing cut scores, a missing Roles column and a stray field, under an alt text written for the desktop crop. KEEP: the page, its copy and order, the 8 marks and `.chip-link`, the links, the 13 desktop captures and the 5 fitting phone crops (no new walk), the include's guard, the README Captures recipe, the records. Deviation from the step's "revert code changes": nothing is reverted, because the captures need the owner's license on the showcase; the same implementer applies the decision and the patches (rows 4–7) in place, then the lens runs again.

## Review Triage Log

Quick lens (Opus 5.5), pass 1, 2026-11-03, on the diff of both repositories (plugin `c0e7b10..9b16249`, site `f951757..8480c12`).

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | The six Post Reviews phone crops cut their panel: scores, edits, claims cut at the screen's edge; the shared alt is not true of them | medium | intent_gap (group A) | `feature-review-phone.png` ends before the four pillar scores and cuts edits 2 and 3; the panel is wider than a phone (min-width 51em), so no phone crop can show it whole. Story 86 kept `review` and `review-list` without a phone crop; the frozen decision asks for one on every section. |
| 2 | `feature-roles-phone` drops the Manage settings column; the alt lists it | medium | intent_gap (group A) | The crop ends at "Manage agents", half cut; the alt says "Editor all but Manage settings". The plugin's table overflows a phone (bug 108). |
| 3 | `feature-ai-phone` shows the stray Custom model ID field (bug 107) and a cut select | low | intent_gap (group A) | Seen in the image; the desktop crop does not show it. |
| 4 | The Overrides copy says "Only the controls the selected model supports are shown"; its capture shows Reasoning Effort greyed out with "Not available for the selected model" | low | patch | `class-pqm-admin.php` renders the effort select `disabled`, never hidden; the sentence copies the plugin's intro, itself inexact (deferred). |
| 5 | `docs.md` "Using it" says Insert into Post puts Takeaways or Sources in "a plain list or in a pattern" with no Pro mark; the new page marks both Pro | low | patch | Sources is in `paid_registry__premium_only()`, the pattern copy in `pattern_copy__premium_only()`; the bullet is not a setup step, and the new page next to it exposes the conflict. |
| 6 | The README drift rule's binding sentence still names only `docs.md` | low | patch | Site `README.md` l.19: "each fact sentence in `docs.md` … every bold label in `docs.md`". |
| 7 | Chore 81 and the plan state the Freemius slot released before the owner checked | low | patch | Chore 81's new line says "(the activation released on Freemius)"; the plan's restore task is ticked including the owner's check, while its notes list the check as open. |
| 8 | The build guard lets `width=""` through | low | reject | No include passes an empty size; catching it needs extra conditions in the guard, for a mistake no one makes in use. |
| 9 | Bugs 107 and 108 are untracked, and the notes leave their ids open | low | patch | Files exist only in the working tree; the coordinator filed them after the walk's report. |

## Verification

**Commands:**
- `env -u MSYSTEM -u MSYS JEKYLL_ENV=production PAGES_REPO_NWO=vincentheimann/postquality.com bundle exec jekyll build --baseurl ""`, then with `--config _config.yml,<launched overlay>` -- expected: `_site/docs/features/index.html` in both.
- `python -I` PNG-header check of every new image -- expected: pixels equal the include's width/height, each file < 250 KB.
- `python -I bin/local-wp.py --site "<showcase>" eval 'echo class_exists("PostQuality_Cron") ? "Pro" : "Free";'` -- expected: `Free` after restore.

**Manual checks (if no CLI):**
- Each capture at full size: "Yourblog", demo posts only, "Post Quality" in the menu, no third-party logo.
- Owner: Freemius activation count back to its pre-walk value.

