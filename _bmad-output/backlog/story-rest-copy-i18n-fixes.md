---
id: 11
type: story
title: "REST-layer refusal and success copy is translated, labeled and correct"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# REST-layer refusal and success copy is translated, labeled and correct

## Description

Retro action A5 (epic-freemium-and-roles retrospective, 2026-10-02). Three copy defects in `class-pqm-rest-api.php`: the trigger success `message` sprintf is untranslated; refusal copy labels commands with `ucfirst($command)` producing raw strings like "Apply_takeaways is not available…"; the quota-exceeded copy prints the limit twice (`%1$d of %1$d`), understating usage when an admin lowers the limit mid-month. Fix: wrap the message in `__()`, use one shared command→label source, print `used` vs `limit`.

## Acceptance Criteria

Verify: every user-facing REST string is translatable, command labels come from one shared map, and the quota refusal names actual used vs limit; harnesses asserting copy updated in the same change.

## Notes

- Sequencing: coordinate the shared label source with epic-ui-rebuild story 5.2 (component kit) so PHP and JS read one list — don't invent two "single sources".

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (a14, pattern-divergence i18n)
