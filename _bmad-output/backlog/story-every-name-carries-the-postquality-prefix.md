---
id: 88
type: story
title: "Every name the plugin defines carries the postquality prefix, and existing sites keep their data"
parent: none
covers: [UV7, O9]
after: [89]
assignee: ""
refined: false
hitl: true
risk: high
---

# Every name the plugin defines carries the postquality prefix, and existing sites keep their data

## Description

The WordPress.org review team asks plugins to stop using prefixes of two or three letters, and every name this plugin defines starts with `pqm` / `PQM`. Before the first public release, every PHP and JavaScript name and every stored name moves to `postquality` / `PostQuality` / `POSTQUALITY`, and a one-time upgrade carries each existing site's data to the new names. The person's part is to start the Local sandbox for the upgrade walk; the production site upgrades through the next release like any site.

## Acceptance Criteria

1. **Code names carry the new prefix**
   **Given** the plugin's PHP and JavaScript, `vendor/` excluded
   **When** a harness lists every function, class, constant, global, custom hook (`do_action` / `apply_filters` names), localized script object and `window` global the plugin defines
   **Then** each starts with `postquality_`, `PostQuality_`, `POSTQUALITY_` or `postquality` (camel case in JavaScript), and none starts with `pqm` in any case; the Freemius accessor is `postquality_fs()`
2. **Stored names carry the new prefix**
   **Given** a fresh install of the new version
   **When** the plugin activates and runs its setup, one review and one cron run
   **Then** every option, user meta key, capability, cron hook and the reviews table it writes uses the new prefix (the table is `{$wpdb->prefix}postquality_reviews`), and no `pqm` name is written
3. **An existing site keeps everything**
   **Given** a site on the previous release with a value under every old stored name: settings, the n8n map, lock and leftovers, the setup progress, the versions seen, dismissed notices, author voices, the monthly usage counters, role capabilities, a scheduled cron event and stored reviews
   **When** the new version loads for the first time
   **Then** each value sits under its new name unchanged, the old names are gone, the table keeps every row, each role holds the new capabilities it held under the old names and none of the old ones, and the cron event is scheduled under the new hook at the same time and interval
4. **The upgrade runs once and survives an interruption**
   **Given** an upgrade that stopped part way (a renamed option beside an old one still present, or the table not yet renamed)
   **When** the plugin loads again
   **Then** the upgrade finishes the remaining steps without overwriting a value already under its new name, and once finished, later loads skip it
5. **The Free build still strips the Pro code**
   **Given** the renamed source
   **When** `bin/build-free.php` and the free-build harness run, and CI runs Plugin Check on the Free build
   **Then** the Free build has no Pro code, the harness is green and Plugin Check reports no new error or warning
6. **The n8n contract does not move**
   **Given** a site that imported the workflow before this change
   **When** a review runs after the upgrade
   **Then** it completes with no re-import: the `X-PQM-Token` header, the REST namespace `post-quality/v1`, the template's placeholders and node ids, and the template version are unchanged
7. **Deleting the plugin removes the new names**
   **Given** a site after the upgrade
   **When** the plugin is deleted
   **Then** no option, user meta, capability, cron event or table with either prefix is left
8. **The records say the new prefix**
   **Given** the merged change
   **When** `docs/brand.md` § Naming, `AGENTS.md`'s conventions and `memory/` are read
   **Then** they name `postquality` / `PostQuality` / `POSTQUALITY` as the prefix and keep the header and REST namespace exceptions, and `docs/release-notes/unreleased.md` has one line for site owners

## Boundaries

- Must not change: the slug and text domain `post-quality-manager`; the REST routes and their namespace; the webhook payload and the `X-PQM-Token` header; the workflow template and its version; the feature gate (ADR 0003) and what each role may do; any stored value (only its name moves).
- Out of scope: CSS class names and DOM ids (`pqm-` in markup and stylesheets), which are not global names; the git history (chore 87).

## References

- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (row 13, decision D1)
- external — https://developer.wordpress.org/plugins/wordpress-org/common-issues/ ("Don't try to use two (2) or three (3) letter prefixes anymore")
- naming — docs/brand.md § Naming (the "Prefixes, constants, option keys…" row changes here)
- precedent — memory/freemium-and-roles.md (the versioned capability upgrade, `pqm_caps_version`)
- waiting — story 38 (`after` includes 88; its Boundaries keep the names this story sets)

## Notes

- Decision (owner, 2026-10-24): rename before entry 4.8, while no real users exist; the prefix is `postquality` / `PostQuality` / `POSTQUALITY` (over `pqmgr` and `post_quality`).
- Decision (owner, 2026-10-24): the Freemius SDK 2.13.5 update (chore 89) lands first.
- Scale on 2026-10-24 (main a4ab38d): 84 files and about 4,900 occurrences outside `vendor/`; 11 options, 5 user meta keys, 6 capabilities, 1 cron hook, 4 custom hooks, 9 classes, 25 functions, 1 table, 2 JavaScript globals.
- Assumption: the build splits in two: code names first (criteria 1, 5 and 6, no stored data touched), then stored names and the upgrade (criteria 2, 3, 4 and 7). Both merge before entry 4.8.
- Assumption: Freemius's Free-build processor matches `->is__premium_only()` whatever the accessor is called; verify on the first release after the rename (the release run compares its Free build with `bin/build-free.php`'s).
- Open question: admin page slugs (`admin.php?page=pqm-…`) and script and style handles. They show in the address bar and in the page source, and `docs/brand.md` says "PQM" never reaches a user. Recommendation: rename them too, with no redirect from the old page URLs while no real users exist.
