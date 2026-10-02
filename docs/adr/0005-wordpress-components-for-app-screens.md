# 0005. Rebuild Dashboard and Post Reviews on @wordpress/components

- Status: accepted (owner approved the ticket-7 UX review decisions D1–D7, 2026-10-02)
- Date: 2026-10-02

## Context

The ticket-7 UX review (`_bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md`) found structural debt in the two app-like admin screens: every badge/pill/status is rendered twice (PHP and ~500 lines of string-built JS with inline styles) and the copies already drift; JS copy is untranslatable and the processing auto-resume breaks on translated sites; tabs, modals, toasts and sort state miss basic accessibility. Patching these one by one keeps the two-render-source architecture that causes them.

Real alternatives weighed: stay vanilla JS + native WP CSS classes (leanest, but keeps the string-building and double-render pain backlog story 5 records); Alpine.js or htmx (small, but foreign to WP admin, no component library, accessibility entirely on us); Vue/Svelte (same foreignness, plus a second runtime WordPress doesn't ship).

## Decision

Rebuild the **Dashboard** and **Post Reviews** pages on WordPress's own React stack — `@wordpress/components` + `@wordpress/element`, built with `@wordpress/scripts` — rendering everything client-side from the existing REST API; PHP keeps only the page shell. **Settings and n8n Workflow stay on the PHP Settings API** (form-shaped pages, no rebuild value).

- One render source: badges, pills, rows, panels exist once, fed by REST.
- i18n via `@wordpress/i18n`, accessibility via the component primitives (TabPanel, Modal, Notice, Snackbar).
- React/components load from WordPress itself (dependency extraction) — no bundled second runtime, WordPress.org-compatible (UV7).

## Consequences

- Easier: brand.md's "native WP admin design language" holds by construction; a11y/i18n stop being hand-rolled; backlog story 5 (CSS classes for JS markup) is subsumed; future screens reuse the same components.
- Harder: a Node/npm build step enters a repo that had none (CI and release zip must build assets); contributors need basic React; the jQuery layer retires on these two pages while Settings keeps its vanilla JS — two JS styles coexist during the transition.
- Committed to: the rebuild lands as its own epic after epic-freemium-and-roles closes; enforcement and data stay in PHP/REST — this ADR changes rendering only, never the gate, quota or webhook contracts.
