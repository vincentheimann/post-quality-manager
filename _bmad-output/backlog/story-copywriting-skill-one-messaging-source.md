---
id: 60
type: story
title: "A copywriting skill writes every public surface from one messaging source"
parent: none
covers: [UV7, O9]
after: [53]
assignee: ""
refined: false
hitl: true
risk: low
---

# A copywriting skill writes every public surface from one messaging source

## Description

The plugin's words live on many surfaces: postquality.com, the GitHub repository (README, release notes, the About text), the WordPress.org listing (readme.txt), Freemius (plan names and feature bullets, the checkout, the opt-in screen, the emails Freemius sends) and the plugin itself (admin copy, the welcome notice, the setup guide, the locked-section and Get Pro copy). Each was written in its own story, and nothing keeps them saying the same thing. This story adds a copywriting skill under `.claude/skills/`, the recurring "how" of the knowledge triad (ADR 0001, ADR 0004): it takes `docs/brand.md`, the messaging file of story 53 and the persona of `docs/user-paths.md` as its canon, knows the rules of each surface, and writes or reviews one named surface at a time. Its first deliverable is a content inventory of every public surface, the file that owns it and its current text, which is also how drift is caught later. The owner reviews the skill's first outputs (hitl).

## Acceptance Criteria

1. **The inventory names every surface**
   **Given** the plugin, the site, the repository, the listing and Freemius as they are
   **When** the inventory is read
   **Then** it lists each public surface with the file or dashboard field that owns its text, the story that last wrote it, and the constraints that apply to it (length limits, forbidden names, translation, directory guidelines), and the owner confirms nothing is missing
2. **One canon, cited**
   **Given** the skill
   **When** it is invoked for a surface
   **Then** it reads `docs/brand.md` (Copy Rules, Naming), the messaging file of story 53 and the persona of `docs/user-paths.md` before writing, and refuses to invent a figure, a claim or a name those sources do not carry
3. **Per-surface rules hold**
   **Given** a surface from the inventory
   **When** the skill writes for it
   **Then** the text follows that surface's constraints (WordPress.org readme conventions and trademark rules for the listing; the Freemius field lengths; translatable, labeled strings with translator comments for admin copy; the pricing and policy wording of ADR 0013 and `docs/policies/`), and names the persona, never one requester's context (lessons 2026-10-07)
4. **Review, not only writing**
   **Given** an existing text on any surface
   **When** the skill is asked to review it
   **Then** it reports each sentence that breaks the Copy Rules, the naming rules or the messaging, with the rewrite and the reason, and leaves the decision to the owner
5. **Documented like the other skills**
   **Given** `AGENTS.md`'s skill list and the ADR 0004 record
   **When** the skill lands
   **Then** both name it and when to use it, through the managed context refresh
6. **Owner-verified first run**
   **Given** the skill
   **When** the owner picks one surface for a first run (hitl)
   **Then** the output is reviewed, accepted or corrected, and the corrections are written back into the skill or the canon, not left in chat

## Boundaries

- Must not change: `docs/brand.md`'s decided rules (story 10) and the messaging values (story 53), which the skill reads and never edits on its own; the policy texts in `docs/policies/`.
- Out of scope: writing the surfaces themselves (stories 38, 41, 49, 57, 61, 62 use the skill); visuals (story 41); translations; a tracker for copy changes.

## References

- parent — none
- canon — docs/brand.md (Copy Rules, Naming); _bmad-output/backlog/story-value-messaging-kpis.md (story 53: the messaging file this reads)
- persona — docs/user-paths.md (the solo professional blogger, any niche)
- consumers — _bmad-output/backlog/story-wordpress-org-directory-listing.md (38); _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (41); _bmad-output/backlog/story-launch-content-demo-cost-guidance-announcement.md (49); _bmad-output/backlog/story-site-content-style-checkout-best-practices.md (57)
- practice — docs/adr/0004-bmad-method-for-delivery.md (skills as the recurring how); docs/adr/0007-minimal-code-ladder-and-agent-tooling.md (tooling decisions)
- data — docs/distribution.md (the Freemius and WordPress.org fields this must know)

## Notes

- Filed 2026-10-13 (owner request: one agent that writes the content published on the website, the repository, Freemius and the plugin pages).
- Decision (owner, 2026-10-13): story 53 first; the skill writes from its numbers, not from a feature list.
- Assumption: the skill is a prompt-and-checklist skill like `/prioritize` and `/release`, with no code; the inventory is a markdown file under `docs/`.
- Open question: whether the inventory records each surface's current text verbatim (a drift check by diff) or only its location and constraints; the owner decides at refinement.
- Risk low: prose and one new skill folder; a bad text is visible and reversible.
