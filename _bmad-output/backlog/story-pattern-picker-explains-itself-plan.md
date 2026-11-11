---
title: 'Pattern to use: edit links, empty-state CTA, listing rule stated'
type: 'feature'
ticket: '24'
created: '2026-10-06'
status: 'done'
baseline_revision: '5024e9ecaa1d9467a2b2baaeb776f555e0cce1da'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 2
context:
  - '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The story-23 Pattern picker is a bare `<select>` labelled "Pattern" at the bottom of the agent box. The owner picks a title blind, cannot see the result, cannot reach the pattern to edit it, and an empty site gets only a sentence of instructions.

**Approach:** Rename the row **Pattern to use** (owner, 2026-10-06) and make it self-explaining: the select (Plain list + "Your patterns"), an **Edit pattern** link for the chosen pattern, a **Manage patterns** link, a **Create a pattern** button when none qualifies, and one line saying where the items land and that only published patterns containing a List block are listed. No preview (owner, 2026-10-06, see Decisions).

## Decisions (delegated to best practice, 2026-10-06)

- **Owner, 2026-10-06 — no preview.** The first build previewed the pattern through a front-end URL (`template_redirect`, theme `wp_head`/`wp_footer`); on the owner's site that loaded third-party scripts (a Buy Me a Coffee widget) inside Settings. Removed entirely: no endpoint, no iframe, no sample items. The Edit pattern link is the way to see the design.
- **Native select**, not a card gallery. Same model as core's Template dropdown.
- **Links go to the native screens:** `post.php?post=ID&action=edit`, `edit.php?post_type=wp_block`, `post-new.php?post_type=wp_block`.
- **Row stays last** in the box: the four LLM knobs keep the same order in all six boxes; agent-specific extras follow.

## Boundaries & Constraints

**Always:** The links point at core's own screens (`post.php?post=ID&action=edit`, `edit.php?post_type=wp_block`, `post-new.php?post_type=wp_block`). A stored pattern ID that no longer qualifies renders as Plain list with the Edit link hidden. All copy through i18n, per brand.md.

**Never:** No change to `insert()`, the REST route, the sanitizer or the stored setting. No React, no `@wordpress/block-editor` in Settings. No preview, no front-end hook, no thumbnails. No new dependency. No change to the n8n template.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Render, patterns exist | 2 eligible patterns, Takeaways set to 31 | label "Pattern to use"; select with Plain list + optgroup "Your patterns"; "Edit pattern" href ends `post=31&action=edit`; "Manage patterns" → `edit.php?post_type=wp_block` | — |
| Render, none eligible | no published pattern with a List | select has Plain list only; "Create a pattern" button → `post-new.php?post_type=wp_block`; no "Edit pattern" | — |
| Render, plain list chosen | `pattern` 0 | "Edit pattern" hidden (`pqm-hidden`) | — |
| Render, stored ID gone | stored 31, only 32 eligible | Plain list selected; "Edit pattern" hidden | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` L754-774 -- the row: `<th>` label "Pattern to use"; a `.pqm-pattern-row` flex line with the select (`<optgroup label="Your patterns">`), `<a class="pqm-pattern-edit">` to core's `post.php?post=ID&action=edit` (hidden when 0), `<a>` Manage patterns (`edit.php?post_type=wp_block`), and, when `$patterns` is empty, `<a class="button">` Create a pattern (`post-new.php?post_type=wp_block`). `$pattern_id` resolves to 0 when absent from `patterns()`. One `<p class="description">`: Takeaways "Goes at the top of the post. The items fill the pattern's first List block, so only your published patterns that contain a List block appear here." / Sources the same with "end".
- `post-quality-manager/admin/js/pqm-admin.js` -- `initPatternLinks()`: on select change rewrite `post=\d+` in the Edit link and toggle `pqm-hidden` at 0; Reset to Defaults dispatches `change` on the select (L96). Registered in BOOT.
- `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-pattern-row` (flex, gap 10px, wrap, align center).
- `tests/harness/render-matrix-harness.php` L141+ -- render rows: label, optgroup, links' hrefs, Create button in the empty state, Edit link hidden at 0 and for a stored ID that is gone.
- `memory/insert-into-post.md` -- the picker's row described (no preview).
- Removed in loop 1 (owner): the front-end preview endpoint, hook, iframe, JS sync, CSS and harness cases.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- the Pattern to use row -- the picker
- [x] `post-quality-manager/admin/js/pqm-admin.js`, `admin/css/pqm-admin.css` -- Edit-link sync + row layout -- the behaviour
- [x] `tests/harness/render-matrix-harness.php` -- every render row of the matrix -- DoD
- [x] `memory/insert-into-post.md` -- row described -- DoD
- [x] loop 1: preview removed everywhere (class-pqm-blocks.php, post-quality-manager.php, admin render, JS, CSS, both harnesses, memory) -- owner decision

**Acceptance Criteria:**
- Given the owner's "Key Takeaways" pattern, when they open the Takeaways box, then the row reads "Pattern to use", lists the pattern under "Your patterns", and Edit pattern opens it in the block editor.
- Given no eligible pattern, when they open the box, then the select offers Plain list only and a Create a pattern button opens the new-pattern screen.
- Given a free site, when a user opens the box, then the row is locked like the rest of the section.

## Implementation Notes

- **Edit link:** rendered as core's `post.php?post=ID&action=edit` (the frozen matrix row and `get_edit_post_link()` format) rather than the Code Map's `data-base="…post.php?action=edit&post="`; the JS rewrites `post=\d+` in the existing `href` instead of carrying a second base attribute. One URL source, same shape before and after a change.
- **Lazy `src`:** `sync()` sets the iframe `src` only while the box is open (`details.open`); the `toggle` listener runs `sync()` on the first open only (`!frame.src`), so re-opening a box does not reload the preview. Reset to Defaults now dispatches `change` on the pattern select.
- **Template whitespace:** the document template uses blank lines after `wp_head()` / `do_blocks()` / `wp_footer()` because `?>` swallows the following newline, and the closing `<?php` sits at column 0 so no indentation leaks after `</html>`.
- **Free site:** the row sits inside the disabled Overrides fieldset (select inert, as before); links, `<details>` and the iframe are not form controls, so Edit / Manage / Create and the preview keep working, dimmed by the section's `opacity: .55`.
- **Memory:** `memory/insert-into-post.md` UI bullet rewritten for the new row (one bullet, longer than the planned "one line" because it is the canonical description of the picker); `memory/MEMORY.md` index line extended.

## Plan Change Log

- **2026-10-06, owner renegotiation (loop 1).** Trigger: the owner saw the preview iframe load a Buy Me a Coffee script — the front-end render runs the theme's `wp_head`/`wp_footer` hooks, exactly the "Known ceiling" the first plan accepted. Amended: Intent, Decisions, Boundaries and the matrix drop the preview; the stored-ID-gone row (review pass 1 #1) is now in the matrix. KEEP: label, optgroup, links, Create button, description line, stale-ID resolution, the review-1 nonce fix is moot (endpoint gone).
- 2026-10-06 (build, after loop 1): removal executed — `class-pqm-blocks.php`, `post-quality-manager.php` and `insert-block-harness.php` are back to the baseline revision; the admin row keeps everything in KEEP; the JS is `initPatternLinks()` (Edit-link sync only, Reset dispatches `change`); CSS keeps `.pqm-pattern-row`; render-matrix asserts no `<iframe>`. The Implementation Notes entries about the preview are historical.

- 2026-10-06 (build): Edit link carries no `data-base`; the JS rewrites the rendered `href` (see Implementation Notes). `preview_url()` takes a nullable pattern instead of "`preview_url( $key, 0 )` without the pattern arg".
- **2026-10-06, owner copy change (loop 2).** The owner's new pattern was missing from the list because it had no List block, and nothing said why. Label "Insert into Post" → "Pattern to use"; the description now states the listing rule. Frozen label/description wording updated by the owner's instruction.

## Review Triage Log

- 2026-10-06 (coordinator review): (1) a stored pattern ID that no longer qualifies showed a visible "Edit pattern" link to the stale post while the select fell back to Plain list → `$pattern_id` now resolves to 0 when `!isset($patterns[$id])`, the same test as `insert()` / `preview_document()`; render-matrix row "stored pattern gone" added. (2) `?_wpnonce[]=x` raised "Array to string conversion" before the 403 → the nonce is cast only when `is_string()`, else empty; insert-block row "nonce as an array" added (error handler turns a warning into a failure).

**Pass 1 (quick, 2026-10-06):** high 0 · medium 1 · low 3 · false 0 · maybe-false 0. Two patch entries, one defer.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | "Edit pattern" stays visible for a stored ID that no longer qualifies, linking to a stale post while the select shows Plain list | medium | patch | Real: `$pattern_id` is read raw from the setting and only `0` hides the link; the sanitizer validates at save time only. Fix: resolve to 0 when absent from `patterns()`. |
| 2 | The picker renders for `pqm_manage_settings`-only users but the preview gate needs `pqm_manage_agents` | low | defer | Real only under a custom role remap (shipped map: admin has both, editor has agents). Root cause is pre-existing: the Overrides section has no capability gate of its own (`render_settings()` L461+). The frozen clause names `pqm_manage_agents`, so the section's gating is the thing to settle, not the preview's. |
| 3 | `?_wpnonce[]=x` emits an "Array to string conversion" warning before the 403 | low | patch | Real on PHP 8; the URL is public. Direct correction: `is_string()` before the cast. |
| 4 | No harness row for a stored ID absent from the pattern list | low | patch | Grouped with #1 (same root cause); the row is added with the fix. |

Patches 1 (+4) and 3 applied by the implementer (2026-10-06). Re-verified by the orchestrator: every harness passes (insert-block 90/90, render-matrix 62/62), `php -l` and `node --check` clean, template untouched, frozen block unchanged.

**Pass 2 (quick, final diff after loops 1–2, 2026-10-06):** high 0 · medium 1 · low 4 · false 0 · maybe-false 0. Two patch entries; two rejected.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | On a free site the Edit / Manage links and the Create button stay clickable inside the locked section, sending free users to build a pattern `insert()` won't use | medium | patch | Real: a disabled fieldset only inerts form controls, and `insert()` ignores the pattern unless Pro. Fix: render them only when `$is_pro`, plus a harness check. |
| 2 | The Edit link skips core's `edit_post` check that `get_edit_post_link()` applies, so a remapped Author sees a link to a "not allowed" screen | low | reject | Real only under a custom remap granting `pqm_manage_agents` to a role without `edit_others_posts`. Unlikely in everyday use, and the fix adds a guard. |
| 3 | `memory/MEMORY.md` index line says "Insert into Post" picker | low | patch | Direct correction. |
| 4 | `memory/insert-into-post.md` cites a harness section that was renamed | low | patch | Direct correction. Grouped with #3 (stale wording after loop 2). |
| 5 | Plan bookkeeping: an unticked loop-1 task, preview-era counts and notes | low | reject | The fix edits this build's plan (rejected by rule). The Change Log already marks the preview notes historical; the loop-1 task is ticked when the plan is marked built. |

Pass-2 patches 1, 3 and 4 applied by the implementer (2026-10-06). Re-verified by the orchestrator: all 9 harnesses exit 0 (render-matrix 59/59, insert-block 66/66), `php -l` and `node --check` clean.

## Design Notes

Skipped: per-pattern thumbnails and a card gallery — add if the owner's pattern count grows. A preview was built and removed (loop 1): a front-end render pulls in whatever the theme and other plugins hook into `wp_head`/`wp_footer`; an in-admin render would lack the theme's styles. If a preview comes back, it needs the editor's own iframe (`@wordpress/block-editor` `BlockPreview`), a React Settings page first.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean
- `for f in tests/harness/*.php; do php "$f" || echo "FAIL $f"; done` -- expected: all pass

**Manual checks (if no CLI):**
- On the Local site: open Settings → Takeaways box: the row reads Pattern to use; a new pattern with a List block appears under Your patterns after a reload; Edit pattern opens it; with no eligible pattern the Create a pattern button opens the new-pattern screen.
