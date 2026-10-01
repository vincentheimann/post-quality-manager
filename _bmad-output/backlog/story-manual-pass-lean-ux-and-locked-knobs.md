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

Four commits shipped on 2026-07-24 (`70ca1ee`, `d8b960b`, `05ecf82`, `ea41e78`) with `php -l` / `node --check` clean but no manual pass; `docs/user-paths.md` marks their claims *pending validation*. A person runs the plugin on a WordPress install and either confirms each claim or files a bug. Migrated from the 2026-07-24 `USER:` items in the retired `tasks/todo.md`; the older 2026-07-22/23 items are story 6 (`story-manual-pass-agnostic-and-llm-pivots.md`) — run both in one sitting.

## Acceptance Criteria

1. **Dashboard states**
   **Given** a fresh install, then a configured one with zero reviews, then one with reviews
   **When** the dashboard is opened in each state
   **Then** it shows "Set up the n8n workflow", then "Run your first review", then clickable stat cards ("Needs Attention" filters Post Reviews)
2. **Wizard end to end**
   **Given** a fresh install and a live n8n instance
   **When** the n8n Workflow page wizard is followed through step 4
   **Then** step 3 saves the webhook URL inline and pings; a failed ping says "URL saved, but the ping did not get through"; step 4 leads to the first review; the permanent sections are hidden while the wizard is active
3. **Settings perception**
   **Given** the Settings page
   **When** settings are saved
   **Then** the notice says no n8n re-import is needed; "AI Model" is its own section; agent "Reset to Defaults" shows no confirm()
4. **All agents reachable**
   **Given** a post in Post Reviews
   **When** each of Takeaways / Sources / SEO / Audit is run
   **Then** its tab renders the result, the row chips list only the commands actually run, and "Open in Editor" is the primary Review action
5. **Locked knobs keep their value**
   **Given** an agent set to a model without temperature support
   **When** the slider is greyed out, settings saved, then the model switched back
   **Then** the previous temperature is intact (no silent reset)
6. **Cron nudge**
   **Given** at least one stored review and cron disabled
   **When** the dashboard loads
   **Then** the nudge appears, Dismiss persists, and it disappears once cron is enabled

## Boundaries

- Must not change: anything — this ticket only observes. Findings become bugs in `backlog/`.

## References

- source — docs/user-paths.md, lines marked *pending validation* (A3, A4, A5, A6, B2, B3, B5)
- facts — memory/llm-options-architecture.md (locked-knob decision, 2026-07-24)

## Notes

- On confirmation, move the matching lines in `docs/user-paths.md` from Unrealized to Current Value (the `/prioritize` skill does this at step 6).
