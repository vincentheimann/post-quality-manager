---
id: 15
type: story
title: "The first public release's notes cover every silent upgrade behavior"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# The first public release's notes cover every silent upgrade behavior

## Description

Retro action A9, widened by the owner on 2026-10-06 to every release. A site upgrading from 2.x, or from a 3.x test build, meets several behaviors nobody tells it about: a monthly quota for authors, a permission the user behind the n8n credential now needs, rights set per role, the end of "View as", and a workflow that must be re-imported. This story writes the release notes that name them, for the first public release (epic-delivery-pipeline entry 4.8), and makes the notes a standing step of every release: a change that existing sites meet without being told adds its line to the notes in the same pull request, and `/release` checks the notes before a release PR is merged. HITL: the owner reviews the wording before the first public release PR is merged.

## Acceptance Criteria

1. **The notes name every silent upgrade behavior**
   **Given** the first public release's notes
   **When** a site owner upgrading from 2.x or from a 3.x test build reads them
   **Then** each of these is named, with what changed and what to do:
   - users who cannot run paid agents get 20 AI actions per month by default; 0 means unlimited; the setting is Settings → Monthly Quota → AI Actions per User
   - the WordPress user whose credentials n8n or any other tool uses to read posts through the plugin's REST API must hold "View reviews", or the read is refused; Administrator, Editor and Author hold it by default
   - each plugin capability's defaults are granted once; rights changed afterwards are kept across deactivation, reactivation and upgrades; they are set on Settings → Roles (Pro) or with a role editor
   - the toolbar "View as" switch is gone; role rights are set on Settings → Roles instead
   - an n8n workflow imported before this release keeps running but must be re-imported; the n8n Workflow page shows when an update is available; the new import needs the webhook token credential, without which its trigger stays unauthenticated
2. **The notes read as the owner's**
   **Given** the notes before the first public release PR is merged
   **When** the owner reviews them
   **Then** every line is in plain words a site owner understands, names no capability slug, function or file, and the owner has approved the wording (the HITL step)
3. **Later changes add their own line**
   **Given** a later pull request whose change existing sites meet without being told (a new default, a new permission, a removed screen, a workflow re-import)
   **When** it is built
   **Then** the project's definition of done tells the builder to add one line to the unreleased notes in that same pull request
4. **`/release` checks the notes**
   **Given** an open release PR for a public release
   **When** `/release` runs on it
   **Then** it reports when no notes file exists for the proposed version, or when the unreleased notes still hold lines, and stops short of the merge; for a pre-release it only reports that the unreleased notes exist and are untouched
5. **Nothing else changes**
   **Given** the plugin and the release workflow
   **When** this story is merged
   **Then** no plugin file and no workflow file changes

## Notes

- Decision (owner, 2026-10-02): the quota default 20 stays; the notes announce it, with no migration special case.
- Decision (owner, 2026-10-06): this story owns the release-notes list for every release, not only the three freemium behaviors. Each template change (bug 31, stories 2, 25, 29, 30) adds its re-import line when it lands; story 25 also adds the new default model and the "(legacy)" model labels. Criterion 3 is where that rule lives after this story is done.
- Change from the original ticket (2026-10-08): the third behavior it named ("reactivation or a caps-version bump re-grants default capabilities, undoing role-editor removals") stopped being true with story 42 (ADR 0012). Criterion 1 announces the current behavior instead. The workflow template does not call `/post-score` itself; the README documents the route for the workflow and other tools, so the line is kept and worded for any caller.
- Decision (owner, 2026-10-08): the notes target the first public release (entry 4.8), written for a site upgrading from 2.x or any 3.x test build. The 3.x test builds shipped without notes and get none after the fact.
- Decision (owner, 2026-10-08): the notes accumulate in `docs/release-notes/unreleased.md`. The PR that prepares a public release (for the launch, the same PR that drops `"prerelease": true` and sets `release-as`) renames it to `docs/release-notes/<version>.md`; the release workflow already appends `<version>.md` when present, so it needs no change. A release-please `docs:` commit opens no release PR.
- Decision (owner, 2026-10-08): the rule in criterion 3 goes in the Definition of Done in `AGENTS.md` (what every build reads) and the check in criterion 4 in the `/release` skill; `memory/delivery-pipeline.md` records that the file exists and where.
- Joins the Freemius release-notes and migration item in `_bmad-output/initiative-post-quality-manager/deferred-work.md` when that ships.
- Gates epic-delivery-pipeline entry 4.8 (the first public release); `tickets.py` cannot express a backlog → epic prerequisite, so the epic's Notes and the entry carry it in prose.

## References

- findings — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles-retrospective.md (a6, a7, a8; answered A9)
- roles behavior — docs/adr/0012-role-rights-set-in-the-plugin-view-as-retired.md
- release steps — .claude/skills/release/SKILL.md
