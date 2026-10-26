---
id: 91
type: story
title: "Every stored name carries the postquality prefix, and existing sites keep their data"
parent: none
covers: [UV7, O9]
after: [88]
assignee: ""
refined: true
hitl: true
risk: high
---

# Every stored name carries the postquality prefix, and existing sites keep their data

## Description

Story 88 moves the names in code to `postquality` / `PostQuality` / `POSTQUALITY`; the names the plugin stores in the database still start with `pqm`. This story moves every option, user meta key, capability, the cron hook and the reviews table to the new prefix, and a one-time upgrade carries each existing site's data to the new names. The person's part is to start the Local sandbox for the upgrade walk; the production site upgrades through the next release like any site.

## Acceptance Criteria

1. **Stored names carry the new prefix**
   **Given** a fresh install of the new version
   **When** the plugin activates and runs its setup, one review and one cron run
   **Then** every option, user meta key, capability, cron hook and the reviews table it writes uses the new prefix (the table is `{$wpdb->prefix}postquality_reviews`), and no `pqm` name is written
2. **An existing site keeps everything**
   **Given** a site on the previous release with a value under every old stored name: settings, the n8n map, lock and leftovers, the setup progress, the versions seen, dismissed notices, author voices, the monthly usage counters, role capabilities, a scheduled cron event and stored reviews
   **When** the new version loads for the first time
   **Then** each value sits under its new name unchanged, the old names are gone, the table keeps every row, each role holds the new capabilities it held under the old names and none of the old ones, and the cron event is scheduled under the new hook at the same time and interval
3. **The upgrade runs once and survives an interruption**
   **Given** an upgrade that stopped part way (a renamed option beside an old one still present, or the table not yet renamed)
   **When** the plugin loads again
   **Then** the upgrade finishes the remaining steps without overwriting a value already under its new name, and once finished, later loads skip it
4. **Deleting the plugin removes the new names**
   **Given** a site after the upgrade
   **When** the plugin is deleted
   **Then** no option, user meta, capability, cron event or table with either prefix is left
5. **The records say the new prefix for stored names**
   **Given** the merged change
   **When** `docs/brand.md` § Naming, `AGENTS.md`'s conventions and `memory/` are read
   **Then** they name the new prefix for stored names too, and `docs/release-notes/unreleased.md` has one line for site owners

## Boundaries

- Must not change: any stored value (only its name moves); what each role may do; the REST routes and their namespace; the webhook payload and the `X-PQM-Token` header; the workflow template and its version.
- Out of scope: Freemius's own stored data, which the SDK keys by the plugin slug; the `metadata.pqmInsert` marker in post content, which keeps its name (story 88's Notes); the git history (chore 87).

## References

- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (row 13, decision D1)
- previous — story 88 (code names; this story builds on its source)
- precedent — memory/freemium-and-roles.md (the versioned capability upgrade, `pqm_caps_version`)
- waiting — story 38 (`after` includes 91)

## Notes

- Decision (owner, 2026-10-26): split from story 88 so each build has its own plan; both merge before entry 4.8.
- Scale on 2026-10-24 (main a4ab38d): 11 options, 5 user meta keys, 6 capabilities, 1 cron hook, 1 table.
- Assumption: no real users exist, so the upgrade needs to carry only sites on the latest release (3.9.0 or the 3.10.0 that carries story 88); the owner's sites are the ones it meets first.
- Done (owner, 2026-10-26): merged in PR #196. The hitl upgrade walk ran on the owner's showcase site (Local, Free build, WP 7.1.3), started by the owner and run by the agent at the owner's request: walks A–F and the before/after page comparison passed (plan, Implementation Notes). The owner accepted it as this story's walk; the production site upgrades through the 3.10.0 release like any site.
