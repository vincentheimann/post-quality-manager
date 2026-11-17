---
id: 134
type: story
title: "An author's voice is drafted from their published posts"
parent: none
covers: [O7]
after: [37]
assignee: ""
refined: false
hitl: false
risk: medium
estimate: ""
---

# An author's voice is drafted from their published posts

## Description

New feature (owner request, 2026-11-17). Story 37 gives each author a voice box on their WordPress profile, filled from a preset or written by hand. Writing your own voice down is hard; your published posts already show it. After this story, an author (or an admin on *Edit User*) can ask Post Quality to reconstruct their voice and tone from their own published posts: Claude reads a sample of them and returns a description of the voice, which the author reviews, edits and saves as their Custom voice. Nothing changes until they save. Pro, like the author voice itself (ADR 0011).

## Acceptance Criteria

1. **Draft from the author's own posts**
   **Given** a Pro site, and a user who may run reviews with published posts of their own
   **When** they ask for a draft from the voice box on their profile (or an admin does on their *Edit User* page)
   **Then** Claude receives a sample of that author's published posts only, never another author's or an unpublished post, and the box shows the returned description as a draft beside the current voice
2. **Save is the commit point**
   **Given** a draft is shown
   **When** the user edits it and saves the profile
   **Then** the draft is stored as the author's Custom voice and used by later Reviews and Rewrites of their posts
   **And** when they leave or discard instead, the stored voice is unchanged
3. **Too little to learn from**
   **Given** an author with fewer published posts than the minimum the plan sets
   **When** they look at the voice box
   **Then** the draft action is unavailable and says why, in one line
4. **Run failures are visible**
   **Given** a draft request
   **When** n8n or Claude does not answer, or answers with no usable description
   **Then** the box says the draft could not be made and why, keeps the current voice, and the request can be tried again
5. **Gated to Pro and to the right people**
   **Given** a site on the Free plan, or a user who may not edit that author's profile
   **When** they open the profile
   **Then** no draft action is offered, and the REST route refuses the request (feature gate and capability checks)
6. **Counts like an AI action**
   **Given** a draft request
   **When** it runs
   **Then** it is recorded as one AI action for the author's usage, as the other agents are
7. **Tests**
   **Given** the harnesses and the JS tests
   **When** they run
   **Then** they cover the post sample (author only, published only, the size limit), the gate and capability refusals, the draft-not-saved rule, and the failure path

## Boundaries

- Must not change: the Editorial Standards in Settings and their presets (story 26); how `brand_voice` is composed for Review and Rewrite (ADR 0011); the author voice box's existing presets, Custom field and save (story 37); every existing command and its payload.
- Must not: write the voice without the user's save; send posts by other authors, drafts, private or password-protected posts; store the posts' text anywhere new.
- Out of scope: reconstructing the site-wide Editorial Standards from the site's posts (owner, 2026-11-17: author voice only); a voice per post.

## References

- decision — docs/adr/0011-editorial-standards-and-author-voice.md (two voice layers; author voice per user on the profile, Pro)
- prerequisite — _bmad-output/backlog/story-author-voice-on-profile.md (story 37: the profile voice box this story adds a draft action to)
- facts — memory/voice-layers.md, memory/llm-options-architecture.md, memory/freemium-and-roles.md
- copy rules — docs/brand.md
- requirement — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, O7

## Notes

- Decision (owner, 2026-11-17): the reconstructed text fills the author's own voice only (story 37's layer), not the site-wide Editorial Standards.
- Decision (owner, 2026-11-17): it is a proposal the user reviews and saves; nothing is applied directly.
- Decision (owner, 2026-11-17): Pro.
- Open question: how the draft runs. A new command through the n8n workflow (a template change, a new template version and a re-import or Update workflow for existing sites, and a `docs/release-notes/unreleased.md` line) or another path; the plugin's agents all run through n8n today (`memory/llm-options-architecture.md`). The plan settles it with the owner.
- Open question: the sample: how many posts (most recent? highest scored?), how much of each (a size cap per post and in total, against the model's context and cost), and the minimum count for criterion 3.
- Open question: whether the draft reuses story 37's preset/Custom shape (it lands in Custom) and how the box shows draft versus current voice. User-facing: offer `bmad-ux` before the build; without it, the builder follows story 37's box.
- Open question: whether the run is synchronous (the profile page waits) or follows the agents' pattern (start, poll, callback through `/save-review`-like route).
- Assumption: the author voice needs story 37 first, so this story lists 37 in `after`.
- Assumption: risk medium: a new AI command with a payload of post text, a template change if it runs through n8n, and a gated REST route; a mistake can slip past review (the post sample's filter is the privacy-relevant part).
