---
id: SPEC-ui-rebuild
companions: [../ux-dashboard-post-reviews/DESIGN.md, brownfield-cleanup.md]
sources: []
---

> **Canonical contract.** This SPEC and the files in `companions:` are the complete, preservation-validated contract for what to build, test, and validate. Source documents listed in frontmatter are for traceability — consult them only if you need narrative rationale or prose color this contract intentionally omits.

# Admin UI rebuild — Dashboard & Post Reviews on @wordpress/components

## Why

A pain to solve plus a decided direction: the owner-approved ticket-7 UX review (DESIGN.md, 2026-10-02) found the Dashboard reports but doesn't prioritize (user-paths B2, the plugin's core loop), and the two app pages are built twice — PHP and ~500 lines of string-built JS — with confirmed drift, untranslatable JS copy (including a functional auto-resume bug on translated sites), and systematic a11y gaps. Patching keeps the two-render-source architecture that causes the class. ADR 0005 (accepted) picked the fix: rebuild both pages on WordPress's own React stack. The freemium epic is closed; this is the next epic.

## Capabilities

- **CAP-1**
  - **intent:** The Dashboard answers "what should I fix next": a Fix-next queue (top 5; priority v1 = status severity, then score ascending, then staleness) with per-row Review / Open-in-Editor actions, compact stat tiles that each link to the correspondingly filtered Post Reviews view, and an average-score sparkline.
  - **success:** Every tile lands on a filtered list; the queue's ordering follows the v1 formula on known data; the owner reaches a post needing work in one click from the Dashboard.
- **CAP-2**
  - **intent:** Post Reviews keeps its table + expandable detail but loses the triage friction: status chips and a score-range filter, every column sortable, bulk "Review selected" (one confirmation, sequential triggers, gate + quota enforced per post with per-post refusals), and the detail tabs as accessible TabPanels.
  - **success:** Filters and sorting cover status/score on the first render; selecting N posts and confirming queues N triggers whose individual refusals are reported; tabs are keyboard-operable with correct ARIA.
- **CAP-3**
  - **intent:** One render source: PHP ships only the page shell and a REST-fed bootstrap; every badge, pill, row and panel renders client-side from shared components, so the logic now duplicated between PHP and JS (quota note, hidden-command rule, effective-model resolution, status/date formatting) exists exactly once.
  - **success:** A status-label or copy change edits one file; no markup for the two pages remains in PHP beyond the shell; backlog story 5 (CSS classes for JS markup) is closed as subsumed.
- **CAP-4**
  - **intent:** i18n and accessibility hold by construction: all copy flows through `@wordpress/i18n` (removing the hardcoded-English JS strings and the `indexOf('Processing')` auto-resume bug), and tablist/aria-expanded/focus/aria-live behavior comes from the component primitives.
  - **success:** On a translated site every string renders translated and processing auto-resume still works; the F4 a11y gaps (tabs, toggles, live regions, sort state) are closed on both pages.
- **CAP-5**
  - **intent:** Status reads as icon + label + color (WP status palette) — never color alone, no emoji; pillar scores are single-hue meters; the sparkline follows DESIGN.md's visual rules.
  - **success:** Every status rendering carries its icon and text label; the mockup's visual language is recognizably implemented.
- **CAP-6**
  - **intent:** The brownfield cleanup assigned to this epic rides the rebuild (see `brownfield-cleanup.md`): dead code deleted, `class-pqm-admin.php` sheds the two app pages and the JS bridge, polling centralizes into one 3-second hook with an aria-live status region, and the quota counter is hidden from users who cannot trigger anything.
  - **success:** Every item in the companion's inventory is deleted/moved or explicitly re-deferred with a reason; `class-pqm-admin.php` no longer contains Post Reviews/Dashboard rendering.

## Constraints

- Rendering only: the feature gate, quota, REST/webhook contracts, the n8n template and all enforcement semantics are unchanged (ADR 0005).
- Settings and n8n Workflow pages stay on the PHP Settings API — out of this rebuild.
- React and `@wordpress/components` load from WordPress core via dependency extraction — no bundled second runtime; WordPress.org-compatible (UV7).
- Native WP admin design language per `docs/brand.md`; all copy follows its rules.
- Queue priority v1 uses only plugin-known data (status, score, staleness) — no traffic/analytics source exists.
- Polling stays 3-second REST (KISS), centralized in one hook; prefer extending existing REST responses over adding routes.
- A Node build step enters the repo: the release zip and CI must build assets; contributors need basic React.

## Non-goals

- Rebuilding Settings or the n8n Workflow page.
- Analytics/traffic integration for queue priority (future; v1 formula stands).
- New agents, template or version-handshake changes, or any change to who may run what.
- WordPress.org submission itself (UV7 stays parked; this epic only keeps compatibility).
- Visual identity work (logo, imagery — backlog ticket 10; sequencing is OQ-1).

## Success signal

The owner opens the Dashboard, sees the five posts most worth fixing, clicks into a filtered list, and acts — and a copy or status-language change afterwards touches exactly one component file. Same zip, translated sites fully functional, harness suite green throughout.

## Assumptions

- Rebuilt pages replace the old ones outright in the same zip — no feature flag or parallel beta page (not addressed by any source; see OQ-3).

## Open Questions

- **OQ-1:** Does plugin identity (backlog ticket 10 — wording/logo/imagery) land before or with this epic? (owner)
- **OQ-2:** Are built assets committed to the repo, or built in CI for the release zip? (decides the release process)
- **OQ-3:** Replace the pages outright on update, or ship the rebuilt pages behind a toggle during a transition period?
