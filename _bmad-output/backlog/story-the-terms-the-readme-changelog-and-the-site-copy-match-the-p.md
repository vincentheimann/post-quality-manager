---
id: 150
type: story
title: "The Terms, the readme Changelog and the site copy match the plugin, and the vendor scan scope is decided"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: low
estimate: ""
---

# The Terms, the readme Changelog and the site copy match the plugin, and the vendor scan scope is decided

## Description

Four items from story 38's pre-production pass need the owner's word before any text changes: the Terms say only Pro needs n8n, the readme's Changelog lists 4.0.0 only, three site copy lines read awkwardly, and Plugin Check's new codes on the vendored Freemius SDK may or may not fall under the owner's earlier acceptance. The owner decides each (hitl); the agent then edits `docs/policies/` first, the site repository after, and the readme.

## Acceptance Criteria

1. **Terms §7 names what needs n8n**
   **Given** `docs/policies/terms.md` L39 ("The Pro features need a working n8n instance …") while the Free build needs n8n too
   **When** the owner approves a wording
   **Then** the policy and the site's copy of it say the same, per the rule that policies change in this repository first
2. **The readme's Changelog follows the owner's rule**
   **Given** a Changelog that lists 4.0.0 only
   **When** the owner decides what it lists (every release, or the latest few, or a link to the release notes)
   **Then** the readme follows it, and the rule is recorded where story 96 can keep it
3. **The site copy reads as the owner wants**
   **Given** the email sentence under the buttons, the flow line's wrap, and "at most" next to "about 3 to 4 cents"
   **When** the owner rules on each
   **Then** the site repository carries the approved copy in a draft PR
4. **The vendor scan scope is decided**
   **Given** Plugin Check 2.2.0 with `vendor/` reporting 527 errors, all in `vendor/freemius`, two of its codes (`inlined_react`, `inlined_react_dom`) new since the owner's 2026-10-29 acceptance of 525
   **When** the owner decides whether that acceptance covers the new codes
   **Then** the decision is recorded in `docs/distribution.md` or story 131's Notes

## References

- parent — none
- source — story 38's plan, Implementation Notes: the 2026-11-19 E looks sittings D and E, the T1 frame
- policies — `docs/policies/terms.md`; the site repository `postquality.com/`
- related — story 96 (each release reaches the WordPress.org listing), story 131 (the release runs the directory form's scan)
- deferred — `_bmad-output/initiative-post-quality-manager/deferred-work.md` L125–127 (the Changelog)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch as one story for the owner's decisions; does not block the WordPress.org submission.
- Assumption: risk low — text only, each change reviewed by the owner.
