---
name: user-paths
description: docs/user-paths.md is the PO tool for prioritization — two journeys (new user, existing user) with EBM Current/Unrealized Value per stage; consult before product/UX work
metadata:
  type: project
---

Since 2026-07-24, `docs/user-paths.md` is the canonical product-prioritization doc: the solo-blogger persona's two journeys (Path A: discovery → first review → habit; Path B: the ongoing quality loop), each stage annotated with EBM Current Value, Unrealized Value, and a candidate signal. No real users yet (owner, 2026-10-07): every CV claim is owner-verified in development, not observed in use; the former "user #1" statements describe the owner's intended use (cron + ad-hoc triggers; review/takeaways/sources/seo/audit agents; rewrite unused; insight→action loop unproven).

Top-ranked Unrealized Value: (1) the insight→action loop B3–B4, (3) a pull-back mechanism (digest/notifications); (2) the perceived no-re-import pivot and (4) guided activation were converted to CV on 2026-10-07 (owner-verified). `/prioritize` 2026-10-07 order: backlog story 26 → epic-delivery-pipeline (first release) → epic-ebm-telemetry.

Since 2026-10-01 the ranked UV table is also the requirement source of the BMad initiative (`UV1`–`UV7` in `_bmad-output/initiative-post-quality-manager/`); epics and tickets cite those ids in `covers`.

Agents: read the doc before product/UX decisions; update it when a friction is removed (UV → CV) or real usage contradicts a claim. Related: [[pivot-agnostic]], [[llm-options-architecture]], [[bmad-setup]].
