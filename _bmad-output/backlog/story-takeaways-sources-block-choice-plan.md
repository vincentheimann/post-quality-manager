---
title: 'Takeaways and Sources insert into the post through a pattern the site owner picks'
type: 'feature'
ticket: '23'
created: '2026-10-05'
status: 'done'
baseline_revision: '160bd62948832ab83fde22d5f3b9412bf8ee145e'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context:
  - '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Takeaways and Sources only *suggest* items (`rewritten_items`, `rewritten_sources`). Nothing puts them into the post, and nothing lets a site present them in its own design. The site owner designs that presentation as a **pattern** in Appearance → Patterns, for example a styled Group with a "Key Takeaways" heading, a List and a Spacer.

**Approach:** Each of the Takeaways and Sources boxes in Settings → Agent-Specific Overrides gets a **Pattern** dropdown: "Plain list" (the default) plus the site's own patterns that contain a List. An **Insert into Post** action in the Post Reviews detail panel's Takeaways and Sources tabs copies the chosen pattern, puts the latest stored items into its first List, and writes the copy into the post with WordPress's own block parser and serializer. Takeaways go first and Sources last. A re-insert replaces the earlier copy.

## Decisions

- **Owner, 2026-10-06:** the Pattern dropdown *replaces* the typed block-name field (a custom block goes inside a pattern). Both Takeaways and Sources get one.
- **Owner, 2026-10-05:** plan kept whole.
- **Delegated to best practice:**
  - **Insert lives in the plugin, not n8n.** No AI call, no template change, no quota spend, and a person confirms what lands in the post. Post status is kept and the previous version stays in Revisions.
  - **Patterns = user patterns.** These are published `wp_block` posts (what Appearance → Patterns → My patterns stores) whose content holds a `core/list` at any depth. The setting stores the pattern's ID; `0` means plain list.
  - **Always an inline copy, synced or not.** A synced reference (`wp:block {"ref"}`) shows the same items on every post, and pattern overrides support only a fixed number of named list items. Editing the pattern later changes future inserts, not posts already inserted into.
  - **The first `core/list` in the pattern receives the items.** Its own items are dropped; its attributes and wrapper markup are kept. Everything else in the pattern is copied untouched.
  - **Marker = `metadata.pqmInsert`** (`"takeaways"` / `"sources"`) on every top-level block of an inserted copy. It goes in the block's comment attributes, not its HTML, so the pattern's markup and block validity are untouched.
  - **Earlier hand-made copies are recognised:** the first-root signature fallback below, so posts where the owner inserted the pattern by hand don't get a second copy.
  - **Tier follows the section.** The setting sits in the Pro-locked Overrides, so free sites insert Takeaways as a plain list. Insert is gated like running its agent: Takeaways free, Sources Pro.
  - **Confirm first** with a `@wordpress/components` `Modal`.
  - **A missing pattern falls back to a plain list.** That covers a pattern that was deleted, unpublished or lost its List; the insert still succeeds.

## Boundaries & Constraints

**Always:** `permission_callback` checks `edit_post` on that post, then `PQM_Features::why_not( $command )`. Item text is escaped (`esc_html`). Blocks are built as parsed-block arrays and written via `serialize_blocks()` + `wp_update_post()`. Only top-level blocks are matched. Replacing an earlier copy:
1. Remove every top-level block whose `metadata.pqmInsert` equals the command, and put the new copy where the first one was.
2. If no block carries the marker, use the first-root signature fallback: at the edge (first non-whitespace blocks for Takeaways, last for Sources), find a run of unmarked blocks whose block names match the copy's top-level names in order, where the first one has the same non-empty `metadata.name` as the pattern's first root (e.g. "Key Takeaways"). Replace that run.
3. Otherwise insert at the edge.

All copy goes through i18n and follows brand.md.

**Never:** No n8n template change and no `PQM_WORKFLOW_VERSION` bump. No quota consumption, no History row, no change to `/trigger-review` or the dead `apply` enum (story 14 owns that). No new dependency. Never insert a synced `wp:block` reference. Never edit the pattern itself. No typed block-name field.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Plain list, new | 4 items, pattern `0`, post has none | `wp:list` with `metadata.pqmInsert:"takeaways"` and 4 `wp:list-item` children, prepended | — |
| Re-insert | marked block mid-content | replaced in place; nothing else changes | — |
| Pattern, new | owner's pattern (Group › Heading + List of 4 sample items, then Spacer); 3 items | Group (attrs + HTML unchanged except `metadata.pqmInsert`), Heading untouched, List holds the 3 items only, Spacer marked; both prepended in pattern order | — |
| Pattern re-insert | earlier marked Group + Spacer at top | both replaced by one new copy; no duplicate Spacer | — |
| Setting changed | marked plain list in post, now a pattern | the list is replaced by the pattern copy | — |
| Hand-made copy | post starts with unmarked Group `metadata.name` "Key Takeaways" + Spacer | that run replaced by the new copy | — |
| Same names, mid-post | unmarked "Key Takeaways" Group not at the edge | left alone; the copy is prepended | — |
| Sources | Sources pattern, post already has the Takeaways copy | copy appended last; Takeaways copy untouched | — |
| HTML in item | `<b>x</b> & y` | escaped text in the `<li>` | — |
| No items | no latest row, or empty items | nothing written | 409 "Run Takeaways first, then insert." |
| Pattern gone | stored ID is deleted, unpublished, or has no List | plain list inserted | silent fallback |
| Free site | stored pattern ID set | plain list | — |
| No edit rights / Sources on free | — | — | 403 as before |
| Bad setting | ID of a page, a list-less pattern, or `abc` | stored `0` | sanitizer fallback |
| Settings list | 3 user patterns, 1 without a List, 1 draft | dropdown: Plain list + the 1 published pattern with a List, by title | — |

</frozen-after-approval>

## Code Map

KEEP from the first build (reuse as is): the `POST /insert-block` route, `check_can_insert()`, `gate_error()`, `insert_block_callback()`, `PQM_Blocks::latest_items()` / `label()` / `refill()` / `edge_index()`, the whole JS flow (`insert-block.js`, `detail-panel.js`, `addToast` plumbing), the harness's copied `serialize_block*` core functions and the route/permission/409 checks. Rework only what follows.

- `post-quality-manager/post-quality-manager.php` -- `pqm_default_settings()`: `'block' => 'core/list'` becomes `'pattern' => 0` on takeaways/sources.
- `post-quality-manager/includes/class-pqm-blocks.php` -- add `patterns(): array` (`[id => title]`): `get_posts( post_type wp_block, publish, orderby title, numberposts -1 )`, kept when `has_list( parse_blocks( content ) )` (recursive). This is the single source for the dropdown, the sanitizer and `insert()`. Rewrite `place( array $blocks, array $items, array $copy, string $command ): array`: `$copy` is the pattern's parsed top-level blocks with null-name whitespace dropped, or `[]` for plain list. Fill the first list depth-first via `refill()`, stamp `attrs.metadata.pqmInsert` on each top-level block, then match per Boundaries. `insert()` resolves the copy (Pro and `isset( patterns()[ $id ] )`, otherwise plain). Delete `wrap()`, the className marker and the registry/dynamic logic. Update the docblock.
- `post-quality-manager/admin/class-pqm-admin.php` -- sanitizer: `pattern` = `absint`, kept only when `isset( PQM_Blocks::patterns()[ $id ] )`, else `0`. Render: replace the text input + `<datalist>` with a `<select>` "Pattern": "Plain list" (0) + `patterns()`. The description names Appearance → Patterns and the first List; when `patterns()` is empty, it says how to create one.
- `post-quality-manager/admin/js/pqm-admin.js` -- Reset to Defaults sets the Pattern select to `0`.
- `src/post-reviews/insert-block.js` -- modal copy: "…into the post at the top/end (using the pattern chosen in Settings)…" stays generic; no data change.
- `tests/harness/insert-block-harness.php` -- replace the custom-block cases with this matrix. The owner's pattern from the ticket conversation is the fixture (Group › Heading + List, Spacer). Stub `get_posts` / `get_post`. `sanitize-matrix-harness.php` / `render-matrix-harness.php`: `block` cases become `pattern` cases.
- `memory/insert-into-post.md`, `docs/adr/0008-…md`, `README.md`, ticket file -- block-name wording becomes pattern wording.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- default `pattern` -- setting source of truth
- [x] `post-quality-manager/includes/class-pqm-blocks.php` -- `patterns()`, new `place()`, `insert()` resolution, docblock -- the pattern copy
- [x] `post-quality-manager/admin/class-pqm-admin.php`, `admin/js/pqm-admin.js` -- sanitize + select + reset -- the picker
- [x] `src/post-reviews/insert-block.js` -- copy check -- the trigger
- [x] `tests/harness/*` -- every matrix row; drop obsolete custom-block cases -- DoD
- [x] `memory/`, `docs/adr/0008`, `README.md`, ticket file -- wording -- DoD

**Acceptance Criteria:**
- Given a Pro site with the owner's "Key Takeaways" pattern, when they pick it for Takeaways, save and reload, then the dropdown shows it selected.
- Given a done Takeaways run, when the user confirms Insert into Post, then the post shows the styled group with the heading and the new items, the block editor raises no invalid-content warning, and a revision exists.
- Given a free site, when a user views the Overrides, then the Pattern dropdown is locked like the rest of the section and Takeaways still inserts as a plain list.

## Implementation Notes

- Built by the step-03 subagent 2026-10-05. Beyond the plan: the Settings JS's Reset to Defaults also resets the Block field. `insert()` ignores a stored custom block on a free site (AC3 after a Pro→free downgrade). The Insert button also requires that the agent isn't role-hidden. README got a feature bullet and an `/insert-block` row. ADR 0008 was written by hand following the `adr` skill.
- Core's `serialize_block*` functions are copied into the harness from the Local site's WordPress 7.1.2, at real behavior.
- Pattern rework built 2026-10-06 (loop 1). `place()` drops `edge_index()`: the hand-made fallback reads the non-whitespace indexes directly, and whitespace detection moved to `is_gap()`. `refill()` now takes the inner blocks (list items) instead of one list. The marked match also takes the whitespace between the earlier copy's own blocks (an editor save separates Group and Spacer), so re-inserts don't pile up blank lines. Stray marked blocks elsewhere are removed, per "remove every marked block". The `pqm-takeaways` / `pqm-sources` className marker is gone; posts that got it from the uncommitted first build on Local get a new copy instead of a replacement (remove the old one by hand in the manual check).

## Plan Change Log

- **2026-10-06, owner intent renegotiation (loop 1).** Trigger: the owner meant a *pattern* from Appearance → Patterns (pasted: a styled Group › Heading "Key Takeaways" + List, then a Spacer), not a block type. Amended: the frozen block (Intent, Decisions, Boundaries, Matrix), Code Map, Tasks, Design Notes, Verification. Known-bad state avoided: a typed block-name field that can't express a designed presentation, and the inner-blocks limit on custom blocks. Code is reworked in place, not reverted (uncommitted; the KEEP list in the Code Map survives). KEEP: route + permission + gate helper, `latest_items()`/`label()`/`refill()`/`edge_index()`, the JS Insert flow, the harness's real core serializer and route checks, the review-1 fixes (marker isolation between commands, translatable labels, string/number item filter).

## Review Triage Log

**Pass 1 (quick, 2026-10-05):** high 0 · medium 2 · low 2 · false 1 · maybe-false 0. All four real findings routed to patch.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | `PQM_Blocks::place()` edge reuse ignores the other command's marker, so with both agents on the same custom block Sources overwrites Takeaways (and back) | medium | patch | Traced: `marked_index()` misses `pqm-sources` and `edge_index(last)` returns the lone `acme/box` marked `pqm-takeaways`, which `refill()` then fills while keeping its attrs. Fix: the edge match skips blocks with any `pqm-*` marker, plus a harness case. |
| 2 | Custom-block mapping works only for blocks that accept inner blocks; a stock `create-block` scaffold gets an invalid block or drops the list, and neither the comment nor the settings copy says so | medium | patch | The scaffold's static save is `<p {...useBlockProps.save()}>` with no InnerBlocks, a limit the frozen mapping decision implies. Fix: state the ceiling in the docblock and the Block field description. The real-editor check stays in manual verification. |
| 3 | 409 and success copy use `ucfirst( $command )`, so the label never translates | low | patch | Two new strings, and the fix is a direct correction (translatable labels). `gate_error()` keeps its pre-existing pattern. |
| 4 | JS `insertItems()` counts null/objects that the server drops, so the modal count can be wrong or the action can hit a 409 | low | patch | `String( null ).trim()` is "null"; `latest_items()` maps non-scalars to ''. A one-line direct correction. |
| 5 | AC2 "post opens in the block editor" is unmet because there's no editor link from the Takeaways/Sources tabs | false | reject | AC2 describes the manual verification. Open in Editor is in the Review tab of the same panel, and the plan asks for no navigation. |

Patches 1–4 applied by the implementer (2026-10-05). The server item filter was also narrowed to string/number to match the JS exactly. Re-verified by the orchestrator: every harness passes (insert-block 51/51), `php -l` and `node --check` are clean, and the build and lint are clean.

**Pass 2 (quick, after the pattern rework, 2026-10-06):** high 0 · medium 1 · low 3 · false 2 · maybe-false 0. Three routed to patch, one low rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | A synced `wp:block` ref nested in the owner's pattern is copied into the post, against "Never insert a synced reference" | false | reject | The rule targets the chosen pattern itself, which per-post items can't live in. A nested ref is the owner's own design, and WordPress copies it the same way when inserting an unsynced pattern by hand. `fill()` can't reach a List inside a ref (refs have no inner blocks), so no items are ever shared. |
| 2 | The 100-pattern cap applies before the List filter and also gates `insert()` and the sanitizer, so a valid pattern past 100 titles is silently dropped | low | patch | Real by reading `patterns()`. Direct correction: `numberposts => -1`, and the ceiling is removed from the docs. |
| 3 | A block editor tab open on the post overwrites the insert on its next save; the modal doesn't warn | medium | patch | Real: `wp_update_post()` doesn't touch the open editor's state, and "Open in Editor" sits in the same panel. Fix: one modal sentence. Skipped: `wp_check_post_lock()` refusal, which only catches *other* users; add it when multi-author editing is reported. |
| 4 | Moving the gate copy into `gate_error()` breaks "no change to `/trigger-review`" | false | reject | Behaviour is identical (the harness asserts the same `WP_Error`). The extraction is sanctioned by the KEEP list and the first plan's Code Map. |
| 5 | DoD: `docs/user-paths.md` B3 not updated though Insert addresses its UV | low | patch | AGENTS.md DoD line. Fix: add one pending-validation note in the existing style. |
| 6 | After a Pro→free downgrade the modal says "using the pattern chosen in Settings" but a plain list is inserted | low | reject | Only after a downgrade, and the locked Overrides note already says the overrides need Pro. The fix needs a new `viewer` field, more than a direct correction. |

Pass-2 patches 2, 3 and 5 applied by the implementer (2026-10-06). Re-verified by the orchestrator: every harness passes (insert-block 66/66), `php -l` and `node --check` are clean, the build and lint are clean, and the frozen block is unchanged.

Manual check validated by the owner on the Local site (2026-10-06).

## Design Notes

The owner's pattern after an insert of 2 items (abridged):

```
<!-- wp:group {"metadata":{"name":"Key Takeaways","pqmInsert":"takeaways"},…} --><div class="wp-block-group …">
  <!-- wp:heading {…} --><h2 …>Key Takeaways</h2><!-- /wp:heading -->
  <!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Item 1</li>… </ul><!-- /wp:list -->
</div><!-- /wp:group -->
<!-- wp:spacer {"height":"60px","metadata":{"pqmInsert":"takeaways"}} -->…<!-- /wp:spacer -->
```

`metadata` is a block-support attribute (an object, serialized in the comment only). WordPress's own pattern code adds keys to it (`patternName`, `categories`), so an extra key survives the editor. Confirm this in the manual check.

Known ceilings (one comment in code): only top-level blocks are matched. The hand-made fallback needs the pattern's first block to have a block name (set in the editor's Advanced panel, or the pattern title on single-block inserts).

Skipped: theme- and plugin-registered patterns (`WP_Block_Patterns_Registry`), to add when the owner asks. Block-aware detection in the Takeaways prompt, to add with story 22.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors
- `for f in tests/harness/*.php; do php "$f" || echo "FAIL $f"; done` -- expected: all pass
- `npm run build && npm run lint:js` -- expected: clean

**Manual checks (if no CLI):**
- On the Local site (WP 7.1.2), with the owner's "Key Takeaways" pattern: pick it, insert Takeaways twice (one copy, no duplicate spacer), insert Sources (appended), open the post in the editor (no invalid-block warning; `pqmInsert` still in the code editor after an editor save), check Revisions.
