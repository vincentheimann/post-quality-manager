---
id: 15
type: story
title: "The next release's notes cover every silent upgrade behavior"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# The next release's notes cover every silent upgrade behavior

## Description

Retro action A9, owner default decision 2026-10-02 (quota default 20 stays — the notes announce it, no migration special-case). The next tagged release ships several behaviors existing installs meet silently; its release notes / changelog must name them: Authors now work under a default 20/month quota (0 = unlimited, Settings → Monthly Quota); the n8n credential user must hold `pqm_view_reviews` or `/post-score` answers 403; reactivation or a caps-version bump re-grants default capabilities, undoing role-editor removals and explicit denies on default roles. HITL: the owner reviews the wording before the release is cut; `/release` verifies the notes are present.

## Acceptance Criteria

Verify: the release-please changelog (or release body) for the next tag names the three behaviors, owner-reviewed; `/release` checklist extended to check it.

## Notes

- Joins the Freemius release-notes/migration item already in `_bmad-output/initiative-post-quality-manager/deferred-work.md` when that ships.
- Gates the next release — not the rebuild epic.
- Decision (owner, 2026-10-06): this story owns the release-notes list for every release, not only the three freemium behaviors. Each template change (bug 31, stories 2, 25, 29, 30) adds its re-import entry here when it lands; story 25 also adds the new default model and the "(legacy)" model labels.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (a6, a7, a8; answered A9)
