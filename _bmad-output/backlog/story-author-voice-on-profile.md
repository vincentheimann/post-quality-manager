---
id: 37
type: story
title: "Each author writes in their own voice, within the editorial standards"
parent: none
covers: [O7]
after: [26]
assignee: ""
refined: false
hitl: true
risk: medium
---

# Each author writes in their own voice, within the editorial standards

## Description

Owner request (2026-10-07, ADR 0011). On a site with several authors — a small team or a company — every post should be checked and rewritten in its author's own tone and voice, while the shared editorial rules hold for everyone. Story 26 turned the site-wide field into **Editorial Standards**; this story adds the second layer: each author writes their voice on their own WordPress profile page, sees the editorial standards there read-only, and the agents receive both for that author's posts. Pro feature.

## Acceptance Criteria

1. **Author voice on the profile**
   **Given** a Pro site and a user who may run reviews
   **When** they open *Users → Profile* (or an admin opens their *Edit User* page)
   **Then** a "Your voice for Post Quality" box shows a textarea with the three starting points (Professional, Casual, Direct — editable, Custom visible), the editorial standards read-only, and which commands use the voice; Save keeps it
2. **Payload for the post's author**
   **Given** a post whose author has a voice and a site with editorial standards
   **When** any Review or Rewrite runs — manual by anyone, or scheduled
   **Then** the payload's `brand_voice` is the editorial standards followed by the author's voice, composed in PHP; the n8n template is unchanged
3. **No author voice**
   **Given** an author with an empty voice
   **When** a command runs on their post
   **Then** the payload sends the editorial standards alone — no site-wide default voice
4. **Plan and role**
   **Given** a free site, or a user who may not run reviews (Contributors)
   **When** the profile page renders
   **Then** the box is locked with the upgrade copy on a free site and absent for users without the capability; the payload ignores stored voices on a free site
5. **Lifecycle**
   **Given** a stored voice
   **When** the plugin is deactivated, then uninstalled
   **Then** the box disappears on deactivation, the meta stays; uninstall deletes it for every user

## Boundaries

- Must not change: the n8n template, the site-wide Settings page (the voice is never edited there), the stored `pqm_settings` keys.
- Out of scope: per-author website context; a voice per post; chat-tool triggers naming a voice (story 4).

## References

- decision — docs/adr/0011-editorial-standards-and-author-voice.md
- plan — _bmad-output/backlog/story-ai-prompts-context-ux-review-plan.md (story 26, the editorial layer and `pqm_resolved_voice()`)
- code — post-quality-manager/post-quality-manager.php, `pqm_n8n_payload()`, `pqm_voice_presets()`
- code — post-quality-manager/includes/class-pqm-features.php (`is_pro()`, `can()`), post-quality-manager/uninstall.php
- copy — docs/brand.md

## Notes

- Decision (owner, 2026-10-07): the post's author's voice applies, never the triggering user's — scheduled runs have no triggering user.
- Decision (owner, 2026-10-07): the voice lives on the WordPress profile page (`show_user_profile` / `edit_user_profile`), not on the plugin's Settings page; admins reach any author's voice through *Edit User*.
- Decision (owner, 2026-10-07): no fallback voice — editorial standards alone when the author wrote nothing.
- Decision (owner, 2026-10-07): Pro plan; site-wide fields stay free.
- Open question: user meta key and capability check for the box (`pqm_run_free_agents` is the natural floor); settle at refinement.
