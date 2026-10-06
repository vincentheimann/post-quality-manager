---
name: insert-into-post
description: Insert into Post (story 23, built 2026-10-05, reworked to patterns 2026-10-06). The plugin writes the latest Takeaways / Sources items into the post via POST /insert-block, as an inline copy of a user pattern the site owner picks (or a plain list). Covers the per-agent `pattern` setting and the `metadata.pqmInsert` marker.
metadata:
  type: project
---

Built 2026-10-05 (story 23); the owner swapped the typed block-name field for a pattern picker on 2026-10-06. ADR [0008](../docs/adr/0008-plugin-writes-agent-output-into-posts.md) says why the plugin does this and n8n does not.

- **Insert path:** `POST /insert-block` (`post_id`, `command` = `takeaways` | `sources`). Its `permission_callback` is `PQM_REST_API::check_can_insert()`: `edit_post` on that post, then `PQM_Features::why_not()`, whose refusal goes through the shared `gate_error()` helper (same 403 copy as `/trigger-review`). The route calls `PQM_Blocks::insert()` (`includes/class-pqm-blocks.php`), which:
  - reads the command's newest row from `PQM_DB::get_reviews_for_post( $id, 50 )`: `rewritten_items` for Takeaways, `rewritten_sources` for Sources (`PQM_Blocks::ITEM_KEYS`);
  - answers 409 "Run Takeaways first, then insert." when that row has no items (processing, failed, or empty);
  - resolves the copy: the stored pattern's `parse_blocks()` when the site is Pro and the ID is in `PQM_Blocks::patterns()`, otherwise `[]` = plain list (free site, or the pattern was deleted, unpublished or lost its List: silent fallback);
  - runs `parse_blocks` → `place()` → `serialize_blocks`, then `wp_update_post( wp_slash( … ) )` with ID + content only.
  The post status is kept and WP saves a revision. There is no n8n call, no quota spend and no History row.
- **Patterns = user patterns:** `PQM_Blocks::patterns()` returns `[id => title]` for published `wp_block` posts (Appearance → Patterns → My patterns), by title, all of them (no cap: the list also gates the sanitizer and `insert()`), kept only when their content holds a `core/list` at any depth. It is the single source for the dropdown, the sanitizer and `insert()`. Theme- and plugin-registered patterns are not listed.
- **The copy (`PQM_Blocks::place()`, pure):** the pattern's top-level blocks (whitespace dropped), copied inline — never a synced `wp:block` reference, and the pattern itself is never edited. The first `core/list` (depth-first) gets the items as `core/list-item` children (`<li>` text through `esc_html`); its attrs and wrapper (`<ul>`/`<ol>`, classes) are kept. Everything else is copied untouched. Plain list = an empty `<ul class="wp-block-list">` filled the same way.
- **Marker:** every top-level block of the copy gets `attrs.metadata.pqmInsert` = `"takeaways"` / `"sources"`, in the block comment only, so the pattern's HTML and block validity are untouched.
- **Placement (top-level blocks only):**
  1. Blocks marked for the command: the run from the first marked block through the marked blocks and whitespace after it is replaced by the new copy; stray marked blocks elsewhere are removed.
  2. Otherwise, a hand-made copy at the edge (first non-whitespace blocks for Takeaways, last for Sources): unmarked blocks whose names match the copy's top-level names in order, the first one with the same non-empty `metadata.name` as the pattern's first block. That run is replaced. A plain list has no name, so a hand-made `core/list` is never replaced.
  3. Otherwise the copy is prepended (Takeaways) or appended (Sources) next to a `"\n\n"` whitespace block.
  The other command's marked blocks are never taken, so both agents can use the same pattern.
- **`pattern` setting:** `agent_prompts.takeaways.pattern` and `agent_prompts.sources.pattern`, default `0` (plain list). Only these two agents have the key.
  - Sanitized as `absint`, kept only when `isset( PQM_Blocks::patterns()[ $id ] )`, else `0`.
  - UI (story 24, 2026-10-06): the "Pattern to use" row in the two Overrides boxes (its description says only published patterns with a List block are listed): a `<select>` ("Plain list" + optgroup "Your patterns" from `patterns()`), an "Edit pattern" link (core's `post.php?post=ID&action=edit`, follows the selection, hidden at 0), "Manage patterns" (`edit.php?post_type=wp_block`), or a "Create a pattern" button when none qualifies — links and button on Pro sites only (a free site's select is locked and `insert()` ignores the pattern); one line saying where the items land. A stored ID that no longer qualifies renders as Plain list (same test as `insert()`). Reset to Defaults sets Plain list and re-syncs the link. A live front-end preview was built and removed the same day (owner decision): rendering through the theme's `wp_head()`/`wp_footer()` pulled third-party scripts into the Settings page.
  - The field sits in the Pro-locked Overrides section. On a free site, `insert()` ignores the stored value and inserts a plain list.
  - The value reaches n8n inside `agent_prompts`, and the template ignores it.
- **UI:** `src/post-reviews/insert-block.js` adds "Insert into Post" with a `Modal` confirmation, then an `apiFetch` POST, with the outcome shown via `onNotify` (the page toast; `useSingleRun` now also returns `addToast`). It appears in the Takeaways / Sources tab when:
  - the latest row is done and has items;
  - `editLink` is set;
  - the viewer can render the command and it is not plan-gated.
- **Known ceilings:** only top-level blocks are matched; the hand-made fallback needs the pattern's first block to have a block name (editor's Advanced panel). Editing a pattern later changes future inserts, not posts already inserted into.
- **Locked in by:** `tests/harness/insert-block-harness.php` (every matrix row with the owner's Group › Heading + List, Spacer pattern; core's serializer copied verbatim), sanitize-matrix Case 7 and the render-matrix "Pattern field" / "Pattern to use row (story 24)" rows.

Not done here: block-aware detection in the Takeaways prompt (with story 22's template fixes); the dead `apply` enum (story 14); theme/plugin-registered patterns (`WP_Block_Patterns_Registry`), when the owner asks.
