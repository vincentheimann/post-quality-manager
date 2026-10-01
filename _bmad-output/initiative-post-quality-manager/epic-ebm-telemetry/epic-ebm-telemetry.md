---
type: epic
title: "The EBM signals are measured, not self-observed"
parent: initiative-post-quality-manager
covers: [UV1]
after: []
assignee: ""
risk: medium
---

# The EBM signals are measured, not self-observed

<!-- Envelope. Requirements and stories are completed at inception. -->

## Description

`docs/user-paths.md` names candidate signals per journey stage — time-to-first-review, share of reviewed posts edited within 14 days, cron vs manual share, re-review rate, dashboard visits — but nothing measures them; today's evidence is user #1's self-observation. Store counters and events locally in the plugin's own DB (privacy-first, no external calls) and surface them in a small dashboard Insights box, so Current vs Unrealized Value claims become evidence-based.

## Outcome

The product owner ranks work from measured signals instead of recollection; the signal is the Insights box answering "did the last review lead to an edit?" for real posts.

## Done when

1. Each signal listed in `docs/user-paths.md` has a local counter or event and a definition of how it is computed.
2. The dashboard Insights box shows them with honest empty states; no data leaves the site.
3. `docs/user-paths.md` Current/Unrealized Value lines cite the box, not self-observation.

## Boundaries

Local storage and display only. Not a hosted analytics service, not notifications (UV3 — a later epic once the signals say what to pull users back to).

## References

- requirements — docs/user-paths.md, per-stage "signals" lines and the EBM summary (UV1)
- parent — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md
- facts — memory/user-paths.md

## Notes

- Assumption: a small events table beside the reviews table is acceptable schema growth; confirm at inception.
