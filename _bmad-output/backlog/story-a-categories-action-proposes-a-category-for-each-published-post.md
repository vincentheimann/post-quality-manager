---
id: 152
type: story
title: "A Categories action proposes a category for each published post, and a person accepts each proposal before it is applied"
parent: none
covers: [O11]
after: []
assignee: ""
refined: false
hitl: false
risk: high
estimate: ""
---

# A Categories action proposes a category for each published post, and a person accepts each proposal before it is applied

## Description

Owner request (2026-11-27), a future new action, not planned yet. On a blog that has grown for years, most older posts sit in a broad category and in none of its more precise ones; assigning them one by one is too slow, so it does not happen. This story adds an action, Categories: an Administrator keeps, for each category in scope, the names that point to it with their variants (a product name written with or without spaces, in the singular, with "and" instead of "&"). The action reads each published post and proposes the matching category, with the names it found, where it found them and a short reason. A person accepts or rejects each proposal, so a post that only mentions a name in passing is not assigned. Only accepted proposals are applied. The date of the pass is kept, so a later pass reads only what was published or changed since, and reports posts whose category differs from the proposal without changing anything.

## Acceptance Criteria

1. **The names per category are the site's own**
   **Given** an Administrator on a site with categories
   **When** they choose the categories in scope and save, for each, its names and variants
   **Then** the action uses these lists only, and nothing in the agent's instructions names a niche, a product or a category (`memory/lessons.md` 2026-03-27)
2. **The pass proposes and changes nothing**
   **Given** saved lists and published posts in scope
   **When** the pass runs
   **Then** each post with a match has a proposal that shows the post, the proposed category, the names found and where (title, headings, text) and a short reason
   **And** no post's categories have changed
3. **Only accepted proposals are applied**
   **Given** a list of proposals
   **When** a user who may do so accepts some, rejects others and applies
   **Then** each accepted post gains the proposed category, each rejected or undecided post is unchanged, and each proposal records who decided and when
4. **Applying adds, never removes or moves**
   **Given** the site's state before applying
   **When** the accepted proposals are applied
   **Then** every post keeps the categories it had, the number of published posts and the number of posts in each category it already had are the same, and no post's address has changed
5. **A later pass reads what is new and only reports**
   **Given** a pass that ran and was dated
   **When** a later pass runs
   **Then** it reads only the posts published or changed since that date and lists those whose category differs from the proposal
   **And** it changes nothing
6. **Run failures are visible**
   **Given** a pass
   **When** n8n or the model does not answer for a post, or answers with no usable proposal
   **Then** that post is listed as not read with the reason, the other posts keep their proposals, and the pass can be run again for the posts not read
7. **Gated to the right plan and people**
   **Given** a site on a plan without this action, or a user without the capability it needs
   **When** they look for the action or call its REST routes
   **Then** it is not offered and the routes refuse the request (feature gate and capability checks)
8. **Tests**
   **Given** the harnesses and the JS tests
   **When** they run
   **Then** they cover the post selection (published only, the date of the last pass), the proposal-is-not-a-change rule, apply for accepted proposals only, the unchanged counts and addresses, the gate and capability refusals, and the failure path

## Boundaries

- Must not change: the six existing commands, their payloads and their results; a post's content, slug, tags, author and dates; the categories themselves (the action never creates, renames, moves or deletes one).
- Must not: assign a category without a person's acceptance; remove a category from a post; send drafts, private or password-protected posts; change anything in a later pass.
- Out of scope: proposing new categories or a new category tree; tags and custom taxonomies; redirects.

## References

- requirement — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, O11
- facts — memory/pivot-agnostic.md, memory/llm-options-architecture.md, memory/freemium-and-roles.md
- precedent — _bmad-output/backlog/story-image-prompt-agent.md (story 83: a new command added beside the existing ones)
- copy rules — docs/brand.md
- product decisions — docs/user-paths.md

## Notes

- Decision (owner, 2026-11-27): the idea fits the plugin's purpose and could become a new action in the future; filed as an idea, to be challenged and prioritized before it is planned.
- Decision (owner, 2026-11-27): nothing is applied without a person's acceptance; a later pass is a report only.
- Open question: names or meaning. Finding a name is not the same as a post being about it. What counts as "about": the name in the title or a heading, a minimum number of mentions, or the model's own judgement with a short reason. This sets how many proposals a person must review.
- Open question: one or several categories per post, and what the list shows for a post that matches none.
- Open question: which categories are in scope: any category, or only the children of a chosen parent (the case the request came from: a broad parent with precise children that hold few posts).
- Open question: whether existing tags feed the lists of names, and whether a tag match counts as a place where a name was found.
- Open question: how the pass runs. Every other agent runs one post per command through the n8n workflow (`memory/llm-options-architecture.md`); a pass over every published post is a bulk run. A new command means a template change, a new template version and an Update workflow or re-import for existing sites, and a line in `docs/release-notes/unreleased.md`. Cost, batching, and how each read counts against the usage quota belong to the same answer.
- Open question: where proposals, decisions and the date of the last pass are kept (with each post, or as a dated list of the posts read). A schema change needs the owner's yes first.
- Open question: the review screen: one list per category or one list with a filter, accept and reject in bulk, who may review (an existing capability or a new one). User-facing: offer `bmad-ux` before the build; without it the builder follows Post Reviews.
- Open question: a site whose permalinks contain the category. There, adding a category can change a post's address; the plan decides whether the action refuses to apply on such a site, warns, or proves the address is unchanged per post (criterion 4).
- Open question: whether the later pass is run on request only or can be scheduled.
- Assumption: a paid action, like every command beyond Review and Key Takeaways (O2).
- Assumption: larger than one session. When it is picked up, `bmad-spec` fixes the WHAT and `bmad-ticket` incepts it into stories; this ticket is then the epic's source.
- Assumption: risk high: applying writes category assignments to many posts at once, which a code revert does not undo. The check outside this ticket's own criteria: the owner compares the per-category and published counts and a sample of addresses before and after applying on the Local sandbox, and a database backup is taken before the first apply on a real site.
