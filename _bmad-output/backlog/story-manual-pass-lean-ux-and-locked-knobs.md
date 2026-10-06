---
id: 1
type: story
title: "The 2026-07-24 UX changes are confirmed by a manual pass in WP Admin"
parent: none
covers: [UV2, UV4]
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# The 2026-07-24 UX changes are confirmed by a manual pass in WP Admin

## Description

Four commits shipped on 2026-07-24 (`70ca1ee`, `d8b960b`, `05ecf82`, `ea41e78`) with `php -l` / `node --check` clean but no manual pass; `docs/user-paths.md` marks their claims *pending validation*. A person runs the plugin on a WordPress install and either confirms each claim or files a bug. Migrated from the 2026-07-24 `USER:` items in the retired `tasks/todo.md`; the older 2026-07-22/23 items are story 6 (`story-manual-pass-agnostic-and-llm-pivots.md`). Refined 2026-10-06: the React Dashboard and Post Reviews (epic-ui-rebuild) replaced the July pages, so criteria 1, 4 and 6 now check the React pages, and anything the 5.9 checklist already covers is pointed there, not repeated. Run in one sitting with story 6 and the 5.9 checklist.

## Acceptance Criteria

1. **Dashboard states**
   **Given** a fresh install, then a configured one with zero reviews, then one with reviews
   **When** the dashboard is opened in each state
   **Then** it shows "Set up the n8n workflow", then "You are all set — run your first review." (only to a user who can run a review), then the tiles and queue (checked in 5.9 section 1)
2. **Wizard end to end**
   **Given** a fresh install and a live n8n instance
   **When** the n8n Workflow page wizard is followed through step 4
   **Then** step 3 saves the webhook URL inline and pings; a failed ping says "URL saved, but the ping did not get through"; step 4 leads to the first review; the permanent sections are hidden while the wizard is active
3. **Settings perception**
   **Given** the Settings page
   **When** settings are saved
   **Then** the notice says no n8n re-import is needed; "AI Model" is its own section; agent "Reset to Defaults" shows no confirm()
4. **All agents reachable**
   **Given** a post in Post Reviews on a site with a License key (Sources, SEO and Audit are Pro)
   **When** each of Takeaways / Sources / SEO / Audit is run from the detail panel
   **Then** its tab renders the result, the Agents run column lists only the commands actually run, and "Open in Editor" is the primary action on the Review tab
5. **Locked knobs keep their value**
   **Given** an agent set to a model without temperature support
   **When** the slider is greyed out, settings saved, then the model switched back
   **Then** the previous temperature is intact (no silent reset)
6. **Cron nudge**
   **Given** an Administrator on a licensed site, at least one stored review, and cron disabled (the nudge never shows on a free site or to an Editor)
   **When** the dashboard loads
   **Then** the scheduled-reviews nudge appears, and it disappears once cron is enabled (Dismiss is checked in 5.9 section 1)
7. **Settings JS on the Editor's reduced page** (moved from story 8, 2026-10-06)
   **Given** a licensed site, the Admin view of Settings, then the Editor view (no AI Model or AI Prompts & Context sections)
   **When** the Admin uses presets, temperature sliders, per-agent Reset to Defaults and model switches, then the Editor uses the per-agent controls
   **Then** each behaves as described, with no console error on either view

## Boundaries

- Must not change: anything — this ticket only observes. Findings become bugs in `backlog/`.

## References

- source — docs/user-paths.md, lines marked *pending validation* (A3, A4, A5, B2, B3, B5)
- facts — memory/llm-options-architecture.md (locked-knob decision, 2026-07-24)
- same sitting — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-manual-pass-epic-done-when-checklist.md (5.9)

## Notes

- Unknown: none (validated 2026-10-06).
- On confirmation, move the matching lines in `docs/user-paths.md` from Unrealized to Current Value (the `/prioritize` skill does this at step 6).
