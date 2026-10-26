---
id: 88
type: story
title: "Every name the plugin defines in code carries the postquality prefix"
parent: none
covers: [UV7, O9]
after: [89]
assignee: ""
refined: true
hitl: false
risk: high
---

# Every name the plugin defines in code carries the postquality prefix

## Description

The WordPress.org review team asks plugins to stop using prefixes of two or three letters, and every name this plugin defines starts with `pqm` / `PQM`. Before the first public release, every PHP and JavaScript name moves to `postquality` / `PostQuality` / `POSTQUALITY`, and so do the admin page slugs, the query argument and the script and style handles that show in the address bar and the page source. This story touches no stored name: options, user meta, capabilities, the cron hook and the reviews table keep their names until story 91 moves them with a one-time upgrade, so a site keeps working between the two.

## Acceptance Criteria

1. **Code names carry the new prefix**
   **Given** the plugin's PHP and JavaScript, `vendor/` excluded
   **When** a harness lists every function, class, constant, global, custom hook (`do_action` / `apply_filters` names), AJAX action, nonce action, localized script object and `window` global the plugin defines
   **Then** each starts with `postquality_`, `PostQuality_`, `POSTQUALITY_` or `postquality` (camel case in JavaScript), and none starts with `pqm` in any case; the Freemius accessor is `postquality_fs()`
2. **What a person sees in the address bar and page source carries the new prefix**
   **Given** an admin opening each plugin page, the setup dialog and the Freemius Account page
   **When** the address bar and the page source are read
   **Then** every admin page slug, the setup query argument and every script and style handle the plugin registers starts with `postquality`, and the old `page=pqm-…` addresses lead to no redirect
3. **A site keeps working between the two builds**
   **Given** a site with settings, an n8n setup, stored reviews and a scheduled cron event
   **When** this story's version replaces the previous one
   **Then** every page, a review, the cron run and the Freemius Account page work as before, reading the same stored names
4. **The Free build still strips the Pro code**
   **Given** the renamed source
   **When** `bin/build-free.php` and the free-build harness run, and CI runs Plugin Check on the Free build
   **Then** the Free build has no Pro code, the harness is green and Plugin Check reports no new error or warning
5. **The n8n contract does not move**
   **Given** a site that imported the workflow before this change
   **When** a review runs after the update
   **Then** it completes with no re-import: the `X-PQM-Token` header, the REST namespace `post-quality/v1`, the template's placeholders and node ids, and the template version are unchanged
6. **The records say the new prefix for code names**
   **Given** the merged change
   **When** `docs/brand.md` § Naming, `AGENTS.md`'s conventions and `memory/` are read
   **Then** they name `postquality` / `PostQuality` / `POSTQUALITY` as the prefix for code names, keep the header and REST namespace exceptions, and say that stored names move with story 91

## Boundaries

- Must not change: the slug and text domain `post-quality-manager`; the REST routes and their namespace; the webhook payload and the `X-PQM-Token` header; the `metadata.pqmInsert` marker that inserted Takeaways and Sources blocks carry in post content; the workflow template and its version; the feature gate (ADR 0003) and what each role may do; every stored name and value (story 91 moves the names).
- Out of scope: CSS class names and DOM ids (`pqm-` in markup and stylesheets), which are not global names; file names; the git history (chore 87).

## References

- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (row 13, decision D1)
- external — https://developer.wordpress.org/plugins/wordpress-org/common-issues/ ("Don't try to use two (2) or three (3) letter prefixes anymore")
- naming — docs/brand.md § Naming (the "Prefixes, constants, option keys…" row changes here)
- next — story 91 (stored names and the upgrade, built after this one)
- waiting — story 38 (`after` includes 88 and 91; its Boundaries keep the names these stories set)

## Notes

- Decision (owner, 2026-10-24): rename before entry 4.8, while no real users exist; the prefix is `postquality` / `PostQuality` / `POSTQUALITY` (over `pqmgr` and `post_quality`).
- Decision (owner, 2026-10-24): the Freemius SDK 2.13.5 update (chore 89) lands first.
- Decision (owner, 2026-10-26): the story splits in two leaves, one plan each: this one (code names) and story 91 (stored names and the upgrade). Both merge before entry 4.8.
- Decision (owner, 2026-10-26): admin page slugs and script and style handles are renamed too, with no redirect from the old page addresses while no real users exist.
- Decision (owner, 2026-10-26): the `pqmInsert` block marker keeps its name, like the `X-PQM-Token` header: it is data inside people's posts, not a global name, and renaming it would rewrite post content.
- Scale on 2026-10-24 (main a4ab38d): 84 files and about 4,900 occurrences outside `vendor/`; 4 custom hooks, 9 classes, 25 functions, 2 JavaScript globals; on 2026-10-26 (main 02c9151) also 2 AJAX actions, 2 nonce actions, 3 page slugs, the setup query argument and the Freemius `menu.slug`.
- Assumption: Freemius keys its stored account by the plugin slug, not by `menu.slug`, so renaming the menu slug moves only the Account page address; verify in the SDK's code before the plan checkpoint.
- Assumption: Freemius's Free-build processor matches `->is__premium_only()` whatever the accessor is called; verify on the first release after the rename (the release run compares its Free build with `bin/build-free.php`'s).
- Done (owner, 2026-10-26): merged in PR #195. AC4 met on the owner's showcase site during story 91's re-walk: Plugin Check on the Free build reported 22 warnings and 0 errors, the 3.9.0 baseline (story 91 plan, Implementation Notes). CI's Plugin Check repeats it on the first run after 2026-11-01; the Freemius-processor assumption above is still checked on the 3.10.0 release run.
