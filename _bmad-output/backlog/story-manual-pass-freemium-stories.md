---
id: 8
type: story
title: "The freemium stories 1.1–1.5 are confirmed by an interim manual pass"
parent: none
covers: []
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# The freemium stories 1.1–1.5 are confirmed by an interim manual pass

## Description

Five epic-freemium-and-roles stories shipped (feature gate tracer, role capabilities, author quota, settings sections) with CLI harnesses green but every per-story "manual WP Admin pass" still pending — and the story 1.5 review caught a fatal (null-input TypeError on a free-site Editor save) of exactly the class only a real site surfaces. Rather than letting five stories of UI debt land on the epic's final walkthrough (story 1.8), a person runs the plugin on a WordPress install with real Administrator / Editor / Author / Contributor users and either confirms each claim below or files a bug in `backlog/`. Run after PR #6 (`feat/1-5-settings-sections`) is merged; the `pqm_is_pro` mu-plugin filter (`__return_true`) stands in for a license until story 1.3.

## Acceptance Criteria

1. **Gate and roles (stories 1.1–1.2)**
   **Given** a fresh unlicensed install, **when** each role opens the plugin, **then** a Contributor has no menu and gets 403 on direct REST/AJAX; an Author sees free agents only (paid buttons absent, not locked); an Editor sees paid buttons locked with upgrade copy; remapping a `pqm_` capability with a role editor changes access with no code change.
2. **Quota (story 1.4)**
   **Given** an Author with the default quota, **when** they trigger reviews, **then** the counter shows next to the buttons, decrements live, refuses at the limit with the count + reset date in the toast, and a cron batch leaves it unchanged; an Editor sees no counter; quota 0 behaves as unlimited.
3. **Settings sections (story 1.5)**
   **Given** the Settings page, **when** each role opens it, **then** an Editor sees exactly Agent-Specific Overrides + Automated Reviews (free: locked with upgrade copy; licensed: editable), an Author has no Settings menu, and an Editor's save leaves webhook URL/token, models, context and quota untouched; a free-site Editor's Save produces no error.
4. **Settings JS on both views**
   **Given** the Admin and the Editor view, **when** presets, temperature sliders, per-agent reset and model switches are used, **then** all behave as before, including on the reduced page.

## Boundaries

- Must not change: anything — this ticket only observes. Findings become bugs in `backlog/`.
- Does not replace epic story 1.8 (the free-then-licensed Done-when walkthrough after stories 1.3/1.6/1.7); it clears the per-story debt so 1.8 stays a walkthrough, not a bug hunt.

## References

- plans — `_bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/story-*-plan.md`, each one's "Manual checks" section
- facts — memory/freemium-and-roles.md
