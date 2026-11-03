---
title: "postquality.com's docs show every feature, and which ones are Pro"
type: 'feature'
ticket: '100'
created: '2026-11-03'
status: 'in-progress'
route: 'full'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
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
- **Captures:** a fresh Pro-build set, every section with a desktop and a phone capture.
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
- [ ] `features.md` -- new: `layout: page`, `permalink: /docs/features/`, unique `description`, toc; h2 per Settings tab, per command and Insert into Post in the admin's order, each h2 the plugin's own label, one or two sentences for the blogger, then its figure -- AC1, AC2.
- [ ] `docs.md`, `index.html`, `_includes/header.html` -- one "Using it" bullet, one homepage link (the decision above), the `hd_from` case -- AC1.
2. Pro mark (site)
- [ ] `features.md` + `site.css` -- the mark (the decision above) on Overrides, Automation, Roles, Sources, SEO, Audit, Rewrite and the Pro parts of Insert into Post, linking `/pricing/#compare-h`; one `{% unless site.launched %}` line at the top -- AC3.
3. Capture walk (showcase; scripts stay in the scratchpad)
- [ ] **Stop and report before this task.** The owner starts the showcase in Local and says go; the implementer resumes from here.
- [ ] Backup; install the Pro build (GitHub Release asset of the current version, as story 98; `npm run zip` fallback); confirm with `class_exists("PostQuality_Cron")`.
- [ ] **Owner:** activates the license in the showcase's WP Admin (the decision above); the implementer waits for the owner's word, then checks the plan with WP-CLI. The key never enters a record or the agent's hands.
- [ ] Seed fictional Sources, SEO, Audit results (shape per the detail tabs in `src/post-reviews/`), a fictional override, schedule and role values; title "Yourblog", user "Demo".
- [ ] Capture each section desktop + phone as story 86 did; inspect every image; alt says what is shown, caption ends "Demo posts, fictional scores." -- AC4.
- [ ] Restore: deactivate the license on the SDK Account page first (a DB restore does not release it), then plugin, DB, wp-config; same-checks -- AC5. **Owner:** confirm the slot is free.
4. Site PR
- [ ] Build both states; screenshots at 1280, 390, 320 px; Lighthouse accessibility; branch `feat/site-features-page`, push via explicit URL, draft PR `--repo vincentheimann/postquality.com`; **Owner** reviews copy and captures, merges.
5. Records
- [ ] Site `README.md` l.5/25 (count, Pro-build note); `docs/user-paths.md` A1-A2 CV; `memory/local-verification.md` (capture pointer, deactivation step); chore 81's list; ticket flip; Implementation Notes.

**Acceptance Criteria:**
- Given either launch state, when the page is built, then every AC2 section has a figure, Pro marks link to pricing, free sections carry none, docs and homepage link to it.
- Given the showcase after the walk, when plugin list, DB and wp-config are compared with the backup, then they match and the Free build runs.

## Implementation Notes

- Planned by a Fable 5.1 subagent (owner's exception for this story); implemented by an Opus 5.5 subagent. bmad-build was rendered by hand from the workspace root. The baseline above is the plugin repository's; the site repository's baseline is `f951757460123166c48586bd3b86705b5a5bf6e7`.

## Plan Change Log

## Review Triage Log

## Verification

**Commands:**
- `env -u MSYSTEM -u MSYS JEKYLL_ENV=production PAGES_REPO_NWO=vincentheimann/postquality.com bundle exec jekyll build --baseurl ""`, then with `--config _config.yml,<launched overlay>` -- expected: `_site/docs/features/index.html` in both.
- `python -I` PNG-header check of every new image -- expected: pixels equal the include's width/height, each file < 250 KB.
- `python -I bin/local-wp.py --site "<showcase>" eval 'echo class_exists("PostQuality_Cron") ? "Pro" : "Free";'` -- expected: `Free` after restore.

**Manual checks (if no CLI):**
- Each capture at full size: "Yourblog", demo posts only, "Post Quality" in the menu, no third-party logo.
- Owner: Freemius activation count back to its pre-walk value.

