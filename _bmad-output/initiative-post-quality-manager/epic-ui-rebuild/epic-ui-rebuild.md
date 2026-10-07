---
type: epic
title: "Dashboard and Post Reviews answer 'what next' on one render source"
status: done
parent: initiative-post-quality-manager
covers: [UV1]
after: []
assignee: ""
risk: high
---

# Dashboard and Post Reviews answer "what next" on one render source

## Description

Rebuild of the two app pages on `@wordpress/components` + `@wordpress/element` (`@wordpress/scripts` build), per ADR 0005 and the owner-approved ticket-7 UX review. The Dashboard becomes triage-first (Fix-next queue of 5, filter-linked stat tiles, score sparkline); Post Reviews keeps its table + detail but gains status/score filters, full sorting, bulk "Review selected" and accessible tabs; every badge, pill, row and panel renders client-side from one component source, killing the PHP/JS drift class, the i18n split-brain and the a11y gaps. The brownfield inventory rides along. Requirement source: `spec-ui-rebuild` (CAP-1…CAP-6). This epic delivers the *triage-and-act surface* of UV1 (B2 → B3); the measurement half of UV1 stays with `epic-ebm-telemetry`.

## Outcome

The owner opens the Dashboard, sees the five posts most worth fixing, clicks into a filtered list and acts — and any later copy or status-language change touches exactly one component file.

## Done when

1. The Dashboard shows the Fix-next queue (top 5 by status severity → score ascending → staleness) and every stat tile opens the correspondingly filtered Post Reviews view (CAP-1).
2. Post Reviews filters by status chips + score range, sorts on every column, and bulk "Review selected" runs sequential triggers behind one confirmation with per-post refusals (CAP-2).
3. Both pages render from `@wordpress/components` with PHP shipping only the shell; the duplicated PHP/JS logic (quota note, hidden-command rule, effective model, status/date formats) exists once; backlog story 5 is closed as subsumed (CAP-3).
4. On a translated site every string renders translated and processing auto-resume works; tabs, toggles, toasts and sort state pass the ARIA checks; status is always icon + label + color per the mockup (CAP-4, CAP-5).
5. The brownfield inventory is deleted/moved or re-deferred with a recorded reason; `class-pqm-admin.php` no longer renders the two pages; CI builds the assets into the release zip — no built assets in the repo (CAP-6 + owner decisions 2026-10-02).

## Boundaries

The two app pages, their components, build tooling and the CI asset build. Not the Settings or n8n Workflow pages, not the gate/quota/REST/webhook semantics, not the n8n template (ADR 0005: rendering only). Touch point: `epic-delivery-pipeline` later consumes this epic's CI asset-build job for the release zip. Touch point: `epic-yoast-context` reads entry 10's live `yoast` item field on `GET /posts`; its detail-panel story comes after 5.10.

## References

- requirements — _bmad-output/initiative-post-quality-manager/spec-ui-rebuild/spec-ui-rebuild.md (CAP-1…CAP-6) with companion brownfield-cleanup.md
- design — _bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md (+ mockup.html, the UX reference)
- decision — docs/adr/0005-wordpress-components-for-app-screens.md
- brand — docs/brand.md (copy rules)
- facts — memory/admin-ui-rebuild.md

## Notes

- Decision (owner, 2026-10-02): built assets are not committed — CI builds them into the release zip.
- Decision (owner, 2026-10-02): rebuilt pages replace the old ones outright; old render code deleted in this epic. No toggle.
- Decision (owner, 2026-10-02): identity (backlog ticket 10) does not gate this epic; naming resolution lands before the Dashboard story when available.
- Decision (owner, 2026-10-02): queue length 5; bulk "Review selected" is in the first slice set.
- Decision (owner, 2026-10-05): story 5.7 stripped the legacy Yoast column and Pending marker without porting them; entry 10 restores them, after 5.7 and before the refactor sweep (5.8) and the manual pass (5.9), which now also walks entry 10's verify.
- Assumption (agent, 2026-10-05, re-slice): entry 10 owns its own Yoast meta read for `GET /posts` and leaves the `post-score` route's `(int)` reads untouched; whether `epic-yoast-context` later shares one read helper is settled at that epic's inception.
- Note: `tests/harness/render-matrix-harness.php` asserts the old PHP-rendered Settings page only — untouched; any harness lines pinning the two app pages' PHP output retire with the strip story.
