---
id: 118
type: story
title: "The plugin requires PHP 8.1, and the project's checks run on it"
parent: none
covers: [O9]
after: [104]
assignee: ""
refined: true
hitl: false
risk: low
---

# The plugin requires PHP 8.1, and the project's checks run on it

## Description

ADR 0018 moves the PHP floor from 8.0 to 8.1 before the first public release. It also makes the floor a version the project runs its checks on, and replaces ADR 0017's PHP rule with WordPress core's 5 % share rule.

This story makes that true everywhere:
- the plugin's stated requirement;
- CI's PHP jobs;
- every document and record that states the floor, the website included;
- the `/lcm` check;
- the `/release` check, which compares Freemius's PHP requirement with the header after a floor change;
- a release-notes line for existing sites;
- story 38's ticket, which names the PHP of its clean site.

## Acceptance Criteria

1. **The plugin states 8.1**
   **Given** the plugin's main file and its readme
   **When** their headers are read
   **Then** both give 8.1 as `Requires PHP`
2. **CI checks the plugin on the floor, not on 8.0**
   **Given** a pull request that touches the plugin's PHP
   **When** CI runs
   **Then** the plugin's PHP is linted on 8.1 and on 8.3, the harness sweep runs on 8.1 and on 8.3, each passes, and no step of the project's workflows sets up PHP 8.0
3. **Every current statement of the floor says 8.1**
   **Given** the repository's documents, agent instructions, memory, CI comments and live specs, and the website repository
   **When** they are searched for the PHP the plugin requires
   **Then** every place that states the current floor says 8.1, and the website's docs page and home page name the same PHP version as the readme's `Requires PHP` header
4. **`/lcm` applies the share rule**
   **Given** `/lcm`'s PHP step and a reading of WordPress.org's PHP share
   **When** the step runs
   **Then** the reading goes into the life cycle record, and a ticket that moves the floor by ADR 0018's rule, citing the ADR, is filed exactly when the floor version holds less than 5 %; a floor past upstream end of life alone files nothing
5. **`/release` checks Freemius's PHP after a floor change**
   **Given** the `/release` check of a release whose `Requires PHP` differs from the previous release's, and the PHP requirement Freemius records for that version
   **When** the check reaches the Freemius deployment
   **Then** it reads that value, and files a bug when the value is missing or differs from the header
6. **Existing sites are told**
   **Given** the unreleased release notes
   **When** they are read
   **Then** one line says the plugin now needs PHP 8.1, and that a site on PHP 8.0 keeps its installed version and gets no further updates

## Boundaries

- Must not change: the plugin's behaviour on any PHP from 8.1 up; the WordPress floor (6.6); the PHP the Free-build job and Plugin Check run on.
- Must not change: dated records keep what was true when they were written (the ADRs, the spike findings, plans, the changelog, released notes, a spec's brownfield scan, and the context and scope notes of a spec or epic that describe the state at inception).
- Out of scope: adopting PHP 8.1 language features (enums, `never`). That is separate work, if it is filed.
- Out of scope: reading Freemius's value in this build (Notes).
- Out of scope: story 38's pre-production pass itself.

## References

- parent — none
- decision — docs/adr/0018-php-floor-follows-wordpress-share-rule.md
- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md, Decision (the rule 0018 replaces; its status line already cross-links 0018)
- findings — _bmad-output/backlog/spike-php-floor-review-findings.md, section 5 (every place a move touches, with lines; what is unverified)
- record — memory/lifecycle.md, PHP row
- ticket — _bmad-output/backlog/story-wordpress-org-directory-listing.md, Notes (the clean site's PHP)
- spec — _bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md, CAP-6 (lint on the declared minimum)
- epic — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/epic-delivery-pipeline.md, Done when (the PHP floor named)
- rule — https://github.com/vincentheimann/postquality.com/blob/main/README.md, Documentation drift

## Notes

- Decision (owner, 2026-11-09): the floor becomes 8.1 before the first public release (ADR 0018).
- Decision (agent, owner delegated, 2026-11-09): the harness sweep passes on PHP 8.1. Run on Local's PHP 8.1.29 without a php.ini, so it loads the same extensions as the dev CLI, with the temporary folder in the session scratchpad: 23 files, 0 failed, no PHP deprecation, warning or notice. Every tracked plugin and `bin/` PHP file outside `vendor/` lints clean on it (14 files, plus the git-ignored build's asset file). No harness needs 8.2, so CI runs the sweep on the floor.
- Decision (owner, 2026-11-09): where Freemius takes `requires_programming_language_version` from for Pro updates is read at the next release, not in this build. The SDK hands that value to WordPress's update gate. Criterion 5 gives the read a home in `/release`; how it reads the value (dashboard or API) is settled there.
- Decision (agent, owner delegated, 2026-11-09): criterion 3 compares the PHP version, not a sentence. The docs page's requirements line pairs with the readme's headers, its multisite answer and the plugin README's Requirements line; none of them matches it word for word, and it has passed the site's drift check since that rule and its wording arrived (website, 2026-10-23). Only its PHP version changes.
- Decision (agent, owner delegated, 2026-11-09): CAP-6 of the delivery-pipeline spec keeps its rule (CI lints the plugin on the declared minimum) and names 8.1 per ADR 0018; the epic's Done when names the floor the same way. Only the value changes, so the build edits both by hand and records the edit in its plan, instead of running `bmad-spec`'s update.
- Decision (agent, owner delegated, 2026-11-09): story 38's ticket names PHP 8.1 for its clean site, citing ADR 0018, in this story's records. Its assumption ("the lowest PHP 8.x Local offers") already resolves to 8.1: Local offers 8.1 builds and no 8.0 build.
- Decision (owner's brief for this build, 2026-11-09): the lines of AGENTS.md that state the floor sit in its managed `bmad:context` block. The edit is recorded in the block's header as a hand edit, as #264 did.
- Decision (owner's brief for this build, 2026-11-09): the website's two statements (its docs page and the "PHP 8.0+" chip on its home page) change in the website repository, through its own pull request, on the branch `chore/118-php-floor-8-1` (agent, owner delegated).
- Decision (agent, owner delegated, 2026-11-09): risk low. A wrong PHP setup in CI shows on the pull request at once, every change reverts cleanly, and the `/lcm` and `/release` steps are text read at review.
- Assumption: the Plugin Check action runs on wp-env's default PHP, which the action does not pin (v1.1.9, read 2026-11-09) and which is not 8.0. Not verified (findings, not verified item 6). Criterion 2 covers the PHP the project's own workflows set up.
- Assumption: a site on PHP 8.0 gets no further updates. Read from WordPress core's update gates (findings section 3), not tested on a site; for Pro updates it also rests on the value criterion 5 reads.
