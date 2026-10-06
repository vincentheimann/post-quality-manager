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

The niche-agnostic pivot (`feat/agnostic-mvp`), the settings/Yoast/import-URL work and the LLM-options pivot (`feat/llm-options-pivot`, 2026-07-23) all shipped with static checks only; their `USER:` validation items were still open in the retired `tasks/todo.md` and were not carried into story 1. The v3.1.0 GitHub Release was gated on the first of them and never happened (no tag, header still 3.0.0). A person runs the full chain on a real WordPress site with a live n8n instance and either confirms each claim or files a bug. Refined 2026-10-06: the Yoast column and the legacy detail-row toggle were rebuilt in React and are checked by the 5.9 checklist (sections 2 and 3), and Reset to Defaults is checked by story 1. Run in one sitting with story 1 and the 5.9 checklist, and before story 25 replaces the model list.

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
   **Given** a workflow imported from the v1 template (`git show` an older `templates/n8n-workflow.json`), then one imported from the current v2 template
   **When** each reports back after a real command
   **Then** the n8n Workflow page shows the outdated notice, then the up-to-date one
5. **Blog Profile and voice presets**
   **Given** the AI Prompts & Context section
   **When** each profile preset is clicked, then a voice preset, then a field edited by hand
   **Then** both fields fill instantly with no dialog, Custom overwrites nothing, manual edits flip the selector to Custom, the save round-trips, and legacy settings still resolve a voice in the n8n payload
6. **Connection test states**
   **Given** the n8n Workflow page
   **When** "Send test ping" runs against an active, then an inactive workflow
   **Then** the result copy matches each state

## Boundaries

- Must not change: anything — this ticket only observes. Findings become bugs in `backlog/`.

## References

- source — `git show f95b45d^:tasks/todo.md` (the file was deleted in f95b45d), sections "Pivot to modern LLM options", "Import from URL", "Settings UX", "Yoast scores on the Post Reviews screen", "Settings polish", "Pivot to niche-agnostic plugin" (their open `USER:` items)
- facts — memory/llm-options-architecture.md, memory/pivot-agnostic.md
- sibling — _bmad-output/backlog/story-manual-pass-lean-ux-and-locked-knobs.md (the 2026-07-24 items; run both passes in one sitting)

## Notes

- Follow-up once this passes and story 15's notes are written: cut the first release since 3.0.0 with `/release` (version bump + GitHub Release; the release-please action is epic-delivery-pipeline's job).
- Unknown: none (validated 2026-10-06).
- Verified 2026-10-06: the registry still exposes exactly the four models in criterion 3. Story 25 replaces them, so run this pass first.
- Criterion 5's findings feed story 26, which reviews the same AI Prompts & Context section.
- Pre-filed (2026-10-06): a failed run showing as "Not analyzed" is bug 31; do not file it again.
