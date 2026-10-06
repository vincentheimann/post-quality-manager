---
id: 3
type: story
title: "Automated Reviews can schedule seo and audit, not only review"
parent: none
covers: [UV6]
after: [13]
assignee: ""
refined: false
hitl: false
risk: low
---

# Automated Reviews can schedule seo and audit, not only review

## Description

`cron_command` is locked to `review`. Real usage (user #1) runs seo and audit regularly by hand; let the Automated Reviews settings pick the command once those agents' result storage is on par with review's (their results render through the generic `result_data` renderer since 2026-07-24).

## Acceptance Criteria

Verify: a cron batch configured for `audit` stores audit results that appear in the Audit tab and the row chips, and the dashboard counts them.

## References

- requirements — docs/user-paths.md, B1 and UV6
- code — post-quality-manager/includes/ (cron class and the `cron_command` sanitizer)

## Notes

- Decision needed at refine: one command per schedule, or a multi-select — the KISS default is one.
- `after: [13]` (validation, 2026-10-06): both edit the Automated Reviews fieldset and the `cron_command` handling in the sanitizer; the command choice lands on the fieldset story 13 unlocks.
