---
id: 104
type: spike
title: "Should the plugin's PHP floor move from 8.0?"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# Should the plugin's PHP floor move from 8.0?

## Description

The plugin requires PHP 8.0, whose security support ended on 2023-11-26. WordPress recommends PHP 8.3 or greater. ADR 0017 raises the floor for review in this case, and only an ADR moves it. This spike answers whether to move the floor, and to which version, and records the answer as an ADR. The owner decides.

## Acceptance Criteria

1. **The question is answered with evidence**
   **Given** the PHP versions WordPress sites run (WordPress.org's statistics), the plugin's own PHP use, and the directory's rules
   **When** the spike ends
   **Then** a findings file states what each candidate floor (8.0 kept, 8.1, 8.2, 8.3) costs and gains, with sources
2. **The owner's decision is recorded**
   **Given** the findings
   **When** the owner chooses
   **Then** an ADR records the floor, and when it moves, a follow-up story carries the change (header, readme, CI lint matrix, release notes)

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the PHP rule)
- decision — docs/adr/0018-php-floor-follows-wordpress-share-rule.md (the answer: 8.1, then the 5 % share rule)
- findings — _bmad-output/backlog/spike-php-floor-review-findings.md
- record — memory/lifecycle.md

## Notes

- Fact (`/lcm`, 2026-11-03): endoflife.date gives 8.0 security support until 2023-11-26, 8.1 until 2025-12-31, 8.2 until 2026-12-31, 8.3 until 2027-12-31. The WordPress requirements page says "PHP version 8.3 or greater"; WordPress core accepts 7.4 (version check).
- Waiting on it: nobody yet. The answer is recorded in the ADR and `memory/lifecycle.md`.
