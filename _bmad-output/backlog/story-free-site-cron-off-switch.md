---
id: 13
type: story
title: "A free-site Admin can switch leftover scheduled reviews off"
parent: none
covers: []
after: [16]
assignee: ""
refined: false
hitl: false
risk: low
---

# A free-site Admin can switch leftover scheduled reviews off

## Description

Retro action A7, owner default decision 2026-10-02: the "stored cron settings keep running on a free site" decision stands, but today the Enable checkbox sits inside the plan-locked disabled fieldset, so a lapsed/free site keeps consuming scheduled AI runs with no supported off-switch. Exempt the Enable toggle (and only it) from the lock so a free-site Admin can disable Automated Reviews; the sanitizer must accept that one key from a free-site POST (narrow exception to the section-wise carry rule, carrying everything else).

## Acceptance Criteria

Verify: on a free site with stored `cron_enabled=true`, the Admin unchecks Enable and saves — cron stops and every other locked key is carried; the sanitize matrix gains the case.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (adversarial a1, answered A7)

## Notes

- `after: [16]` (validation, 2026-10-06): the Enable exception is a narrow exception to the carry rule that bug 19 and story 16 reshape in the same sanitizer block, so it lands on their final version.
