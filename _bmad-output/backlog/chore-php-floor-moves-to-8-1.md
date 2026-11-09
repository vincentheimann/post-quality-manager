---
id: 118
type: story
title: "The plugin requires PHP 8.1, and the project's checks run on it"
parent: none
covers: [O9]
after: [104]
assignee: ""
refined: false
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
- a release-notes line for existing sites.

## Acceptance Criteria

1. **The plugin states 8.1**
   **Given** the built plugin
   **When** its main file header and its readme are read
   **Then** both say `Requires PHP: 8.1`, and the release header check still passes
2. **CI checks the plugin on the floor, not on 8.0**
   **Given** a pull request that touches the plugin's PHP
   **When** CI runs
   **Then** the plugin's PHP is linted on 8.1 and on 8.3, the harness sweep runs on 8.1 as well as on 8.3, each passes, and no job runs PHP 8.0
3. **Every statement of the floor says 8.1**
   **Given** the repository's documents, agent instructions and memory, and the website repository
   **When** they are searched for the PHP the plugin requires
   **Then** every place that states the floor says 8.1, and the website's docs page still matches the readme's requirements word for word
4. **`/lcm` applies the share rule**
   **Given** `/lcm`'s PHP step
   **When** it runs
   **Then** it reads WordPress.org's PHP share. It proposes a new floor, citing ADR 0018, only when the floor version holds less than 5 %. A floor past upstream end of life alone files nothing.
5. **Existing sites are told**
   **Given** the unreleased release notes
   **When** they are read
   **Then** one line says the plugin now needs PHP 8.1, and that a site on PHP 8.0 keeps its installed version and gets no further updates

## Boundaries

- Must not change: the plugin's behaviour on any PHP from 8.1 up; the WordPress floor (6.6).
- Out of scope: adopting PHP 8.1 language features (enums, `never`). That is separate work, if it is filed.

## References

- parent — none
- decision — docs/adr/0018-php-floor-follows-wordpress-share-rule.md
- findings — _bmad-output/backlog/spike-php-floor-review-findings.md, section 5 (every place a move touches, with lines; what is unverified)
- record — memory/lifecycle.md, PHP row

## Notes

- Decision (owner, 2026-11-09): the floor becomes 8.1 before the first public release (ADR 0018).
- Open question: does the harness sweep pass on PHP 8.1? The spike did not run it, because three harnesses write temporary files. Answering it is part of this ticket. A harness that needs 8.2 or later contradicts ADR 0018's "checks run on the floor" and goes back to the owner.
- Open question: where Freemius takes `requires_programming_language_version` from for Pro updates. The SDK hands that value to WordPress's update gate. Read it from the Freemius tag at the first release after this ships (the `/release` check); if it is not 8.1, file a bug.
- The agent-instruction lines that state the floor sit in AGENTS.md's managed `bmad:context` block. Record the edit in the block's header as a hand edit, as #264 did.
- The website's two statements (its docs page and the "PHP 8.0+" chip on its home page) change in the website repository, through its own pull request.
- Story 38's pre-production pass then runs on PHP 8.1: Local offers 8.1 builds and no 8.0 build.
