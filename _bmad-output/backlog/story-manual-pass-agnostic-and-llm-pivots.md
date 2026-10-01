---
id: 6
type: story
title: "The 2026-07-22/23 pivots are confirmed by a manual pass on a real site"
parent: none
covers: [UV2, UV4]
after: []
assignee: ""
refined: true
hitl: true
risk: medium
---

# The 2026-07-22/23 pivots are confirmed by a manual pass on a real site

## Description

The niche-agnostic pivot (`feat/agnostic-mvp`), the settings/Yoast/import-URL work and the LLM-options pivot (`feat/llm-options-pivot`, 2026-07-23) all shipped with static checks only; their `USER:` validation items were still open in the retired `tasks/todo.md` and were not carried into story 1. The v3.1.0 GitHub Release was gated on the first of them and never happened (no tag, header still 3.0.0). A person runs the full chain on a real WordPress site with a live n8n instance and either confirms each claim or files a bug.

## Acceptance Criteria

1. **Full round-trip with the `technical` profile**
   **Given** a fresh install with the Technical/IT Blog Profile
   **When** the workflow is downloaded, imported into n8n, both credentials connected, and a review triggered from the dashboard
   **Then** the result appears in Post Reviews and the stored review carries the four pillar scores
2. **Import from URL and token auth**
   **Given** the n8n Workflow page
   **When** the import URL is copied ("Copied!" feedback) and pasted into n8n *Import from URL…*
   **Then** the pre-configured workflow imports; a request with a wrong or missing `X-PQM-Token` gets 401 from `save-review`
3. **LLM options per model**
   **Given** the Agent settings
   **When** each registry model (sonnet-4-6, sonnet-5, opus-4-8, haiku) is selected and a review run
   **Then** the knobs toggle per model, the preview table matches what is sent, every model completes a round trip, and one thinking-capable model proves the response-parse fix
4. **Template version handshake**
   **Given** a workflow imported from a v2 template
   **When** it reports back after a real command
   **Then** the n8n Workflow page shows the outdated/up-to-date notice correctly
5. **Blog Profile and voice presets**
   **Given** the AI Prompts & Context section
   **When** each profile preset is clicked, then a voice preset, then a field edited by hand
   **Then** both fields fill instantly with no dialog, Custom overwrites nothing, manual edits flip the selector to Custom, the save round-trips, and legacy settings still resolve a voice in the n8n payload
6. **Yoast column**
   **Given** Yoast active, then inactive
   **When** Post Reviews loads
   **Then** badges show (em-dash for unanalyzed posts), then no column with the layout intact
7. **Connection test states**
   **Given** the n8n Workflow page
   **When** "Send test ping" runs against an active, then an inactive workflow
   **Then** the result copy matches each state; agent "Reset to Defaults" and the detail-row toggle behave

## Boundaries

- Must not change: anything — this ticket only observes. Findings become bugs in `backlog/`.

## References

- source — git history before 2026-10-01, `tasks/todo.md` sections "Pivot to modern LLM options", "Import from URL", "Settings UX", "Yoast scores on the Post Reviews screen", "Settings polish", "Pivot to niche-agnostic plugin" (their open `USER:` items)
- facts — memory/llm-options-architecture.md, memory/pivot-agnostic.md
- sibling — _bmad-output/backlog/story-manual-pass-lean-ux-and-locked-knobs.md (the 2026-07-24 items; run both passes in one sitting)

## Notes

- Follow-up once this passes: cut the first release since 3.0.0 with `/release` (version bump + GitHub Release; the release-please action is epic-delivery-pipeline's job).
- Decision needed at run time: which Claude model IDs the registry currently exposes — the names above are the 2026-07-23 ones.
