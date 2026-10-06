# 0008. The plugin, not n8n, writes Takeaways and Sources into posts

- Status: accepted
- Date: 2026-10-05

## Context

Story 23 asked for Takeaways and Sources to land in the post in a presentation the site owner picks. The owner designs it as a pattern in Appearance → Patterns (2026-10-06: e.g. a styled Group with a heading, a List and a Spacer), replacing an earlier typed block-name field. Until then nothing wrote them into the post: both agents only suggest items (`rewritten_items`, `rewritten_sources`), and the `apply` command was dead (the template routes it to Error Fallback). Two real alternatives for the write step: let n8n build and save the block (as Rewrite does, unattended, setting the post to `pending`), or let the plugin build it from the stored result when a person asks. The presentation had its own choice: insert a synced pattern reference (`wp:block {"ref"}`) or an inline copy. A synced reference shows the same items on every post, and pattern overrides support only a fixed number of named list items. The owner delegated these to best practice (2026-10-05, 2026-10-06).

## Decision

The plugin writes agent output into posts: `POST /insert-block` copies the chosen pattern inline, puts the latest stored items into its first List, and saves the post with WordPress's own block parser and serializer and `wp_update_post()`. A person confirms it in Post Reviews first.

- No AI call, no workflow template change, no quota, no History row. The post status stays as it is, and WP revisions keep the previous version.
- Same gate as running the agent (Takeaways free, Sources Pro), plus `edit_post` on that post.
- Per-agent `pattern` setting: a published user pattern (`wp_block`) that holds a List, or `0` for a plain list (the default and the fallback when the pattern is gone). Always an inline copy, never a synced reference; the pattern itself is never edited. A custom block goes inside a pattern.
- Matching: `metadata.pqmInsert` on every top-level block of the copy, in the block comment only, so the pattern's markup stays valid. A hand-made copy at the edge is recognised by the pattern's block names and its first block's name. Takeaways go first, Sources last, and a re-insert replaces the earlier copy.

## Consequences

- Easier: the output is predictable and reviewed by a person, and it works with any imported workflow version. Later "apply" features (SEO meta, rewritten sections) can follow the same plugin-side pattern.
- Harder: the plugin edits parsed block trees. Only top-level blocks are matched, and editing a pattern later changes future inserts, not posts already inserted into. Theme- and plugin-registered patterns are not listed yet.
- The `apply` enum stays dead here. Story 14 owns it.
