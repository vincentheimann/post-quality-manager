---
type: initiative
title: "Post Quality Manager — continuous content quality for any WordPress blog"
parent: none
covers: [UV1, UV2, UV3, UV4, UV5, UV6, UV7, O1, O2, O3, O4, O5, O6, O7]
after: []
assignee: ""
risk: medium
---

# Post Quality Manager — continuous content quality for any WordPress blog

## Description

The whole product is the initiative (solo developer, no larger goal above it). Bloggers trigger review, rewrite, takeaways, sources, SEO and audit commands from WP Admin or a chat tool; an n8n workflow routes them to Claude; results land back in the plugin's dashboard. The requirement source is the EBM user-paths analysis (`docs/user-paths.md`): its ranked Unrealized Value table says where effort goes, and owner requests recorded in ADRs add the rest. Migrated 2026-10-01 from `tasks/enhancements.md` (see ADR 0004).

## Outcome

For professional bloggers in any niche, the back-catalog stops silently aging: scores lead to edits, and the signal is the share of reviewed posts edited within 14 days (UV1), today unmeasured.

## Requirements

Unrealized Value, ranked (`docs/user-paths.md`, "EBM summary"):

- UV1: Insight → action loop — edit tracking, deep-links, proof that reviews cause edits (B3–B4)
- UV2: Perceived no-re-import — validate the pivot, say it in the UI (B5); shipped 2026-07-24, pending validation
- UV3: Pull-back mechanism — digest / notifications (A6, B1)
- UV4: Guided activation — welcome → checklist → first review (A3–A5); wizard shipped 2026-07-24, pending validation
- UV5: Round-trip connection test — n8n → WP callback with the token verified (A4)
- UV6: Cron for seo/audit commands (B1)
- UV7: Distribution — WordPress.org, updater (A1–A2); deliberately post-MVP

Owner requests:

- O1: Role-based access to plugin features on WordPress core roles, per-user Author quota, Contributors excluded (ADR 0003, 2026-09-22)
- O2: Free plan = Review + Key Takeaways; paid plan = everything else, one build, hosted licensing, per-site license (ADR 0003, 2026-09-22)
- O3: Yoast scores as SEO/Audit agent context, framed as algorithmic signals, plus a per-review Yoast snapshot in `result_data`; the Review agent stays Yoast-free (user-approved 2026-07-23)
- O4: Chat-provider contract — the webhook in/out JSON documented so any chat tool drives the workflow without reading n8n nodes
- O5: Model choice and cost control in a clear Settings page — provider-then-model choice across the main providers, free models, a site-wide monthly token limit, Settings organized in tabs with an unambiguous AI context section (ADR 0009, 2026-10-06)
- O6: An Administrator checks what each default role sees — a "View as" switch in the WordPress toolbar renders the plugin as an Editor, Author or Contributor would get it, with Exit always reachable (owner, 2026-10-06; backlog story 34)
- O7: Two voice layers — site-wide Editorial Standards every post follows, plus each author's own voice written on their WordPress profile and applied to their posts; Pro (ADR 0011, 2026-10-07; backlog stories 26 and 37)

## Done when

1. A dashboard Insights box shows the EBM signals from local data: time-to-first-review, reviewed-posts-edited-within-14-days, cron vs manual share (UV1).
2. A ping from the n8n Workflow page proves the full loop, callback and token included, and says so (UV5).
3. Review + Key Takeaways work for every Author under a visible quota while gated commands are refused in PHP before the webhook call, with one zip for free and paid (O1, O2).
4. A tagged GitHub Release carries a plugin zip built by CI from a template that passed the placeholder and `jsCode` checks (UV7 groundwork).
5. Every claim marked *pending validation* in `docs/user-paths.md` is confirmed or reverted by real usage (UV2, UV4).

## Boundaries

The WordPress plugin, its REST contract and the n8n workflow template it ships. Not the blogger's n8n instance or Claude account (they bring both), not a custom chat client (the webhook contract is the boundary), not a graph-based workflow builder (parked).

- Touch point: `post-quality-manager/templates/n8n-workflow.json` — every REST or payload change is mirrored here with `__PQM_*__` placeholders; owner: whichever epic changes the contract

## References

- requirements — docs/user-paths.md, section EBM summary (UV1–UV7) and per-stage CV/UV
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (O1, O2)
- decision — docs/adr/0002-user-paths-ebm-po-tool.md, why the UV table ranks the work
- facts — memory/MEMORY.md, current-state facts (LLM options architecture, agnostic pivot)
- brand — docs/brand.md, copy rules for any admin UI text

## Notes

- Decision: lightweight BMad — build/spec/ticket/review/retrospective only, no PRD or architecture layer (user, 2026-10-01, ADR 0004).
- Decision: O1 and O2 are the first epic to be spec'd and incepted, but only after `/prioritize` ranks them against UV1–UV7 (ADR 0003 consequence; user, 2026-09-22).
- Decision: `/prioritize` 2026-10-01 — `epic-freemium-and-roles` confirmed first (owner priority O1/O2 per ADR 0003; also the gate WordPress.org distribution waits on). EBM runner-up on pure UV size: `epic-ebm-telemetry` (UV1). The hitl manual passes (backlog stories 1 and 6, UV2/UV4 — the cheapest UV→CV conversions) stay with the owner in parallel; story 6 gates the first release.
- Decision: `/prioritize` 2026-10-06 — next is one real-site walk covering epic-ui-rebuild 5.9 and backlog stories 1, 6 and 8 (UV2/UV4, the cheapest UV→CV conversions; owner pick). O5 added so backlog stories 25–30 rank instead of reading as scope creep. Within O5, story 26 (AI Prompts & Context review) ranks above story 29 (usage and token limit) (owner). The Settings stories build in the order 26 → 27 → 25 (owner).
- Decision: `/prioritize` 2026-10-07 — the 2026-10-06 sitting is done, all green (epic-ui-rebuild 5.8/5.9 and backlog stories 1 and 6 marked done; the epic is done); UV2 and UV4 move to CV as owner-verified. Next, in this order (owner): backlog story 26 (O5, the head of the 26 → 27 → 25 Settings chain, unblocked by story 6), then `epic-delivery-pipeline` toward the first release since 3.0.0 (UV7 groundwork; story 6 no longer gates it; no real users exist, and a release is what makes them possible), then `epic-ebm-telemetry` (UV1, the largest UV; its local signals only pay off once real users exist). The epic envelopes are reordered to match.
- Decision: backlog story 8 dropped (owner, 2026-10-06) — the freemium epic's own manual pass (story 1.8, walked green 2026-10-02) covered its gate, quota and Settings-section checks; its one uncovered check, Settings JS on the Editor's page, moved to story 1.
- Decision (owner, 2026-10-07, ADR 0011): story 26's review surfaced the real need — editorial standards per site plus a voice per author. Story 26 builds the editorial layer; new backlog story 37 (after 26) adds the author voice on the WordPress profile page, Pro. O7 added so both rank instead of reading as scope creep.
- Resolved (owner, 2026-10-07): there are no real-user observations; the "user #1" claims in `docs/user-paths.md` were the owner's intended use and are reworded as such. CV there is owner-verified until real users exist. Done-when 5 is met by owner verification; confirmation by real usage waits for users.
- Parked: wizard-style workflow *builder* (assemble the n8n graph from blocks) — KISS, the config-injected export is the locked MVP approach; the setup *flow* wizard already exists.
- Parked: per-agent custom free-text model IDs — the global Custom ID exists; add per-agent only on a real need (six extra fields otherwise).
- Parked: an update channel inside WordPress (GitHub Releases under Dashboard → Updates) — owner, 2026-10-07, while specifying epic-delivery-pipeline: no external users yet; revisit with the first outside install.
- Parked → filed: WordPress.org distribution is backlog story 38 (2026-10-07), after story 10 (identity), story 15 and epic-delivery-pipeline; the gate exists since epic-freemium-and-roles (ADR 0003: GitHub Releases first).
- Known: workflows imported before 2026-07-22 route `ping` to the Error Fallback — the test passes but n8n logs a junk execution; re-downloading the template fixes it. Not a ticket; the template-version handshake (shipped 2026-07-23) is the mitigation.
- Open question: UV3 (digest / notifications) has no owner epic yet; it waits for UV1 telemetry to show what to pull users back to.
