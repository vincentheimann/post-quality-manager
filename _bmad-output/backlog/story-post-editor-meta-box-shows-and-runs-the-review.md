---
id: 109
type: story
title: "A Post Quality meta box on the post edit screen shows the post's review and runs a new one"
parent: none
covers: [UV1]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# A Post Quality meta box on the post edit screen shows the post's review and runs a new one

## Description

A writer edits a post in the post editor, but the post's review lives on another screen (Post Reviews). To act on a suggestion, they switch screens, find the post, read the edits, then go back to the editor. This is the insight-to-action gap that `docs/user-paths.md` ranks first (B3–B4, UV row 1). This story puts the review where the writing happens: a "Post Quality" meta box under the content, in the block editor and in the Classic Editor, like the boxes SEO plugins show there. This first slice lets the writer see the latest review and run a new one. Later slices add the other commands and the insertion of Takeaways and Sources into the open editor (Notes).

## Acceptance Criteria

1. **The box shows the latest review**
   **Given** a post with at least one review, and a user who may view reviews
   **When** the post's edit screen opens, in the block editor or the Classic Editor
   **Then** a "Post Quality" meta box under the content shows the score with its band, the review date, the top suggested edits, and a link to the full review on Post Reviews
2. **A post without a review says so**
   **Given** a post that was never reviewed, or a new post never saved
   **When** its edit screen opens
   **Then** the box says the post has no review yet and offers the review action (criterion 4 when there is something to save first)
3. **A new review runs from the box**
   **Given** a user who may run Review, within their monthly quota
   **When** they click **Review**
   **Then** the run starts, its progress shows in the box, and the new result replaces the old one without a reload; a failed run shows the plugin's existing error sentence, and a reached quota shows the existing quota note instead of the button
4. **Unsaved changes are saved first**
   **Given** a post with unsaved changes
   **When** the box renders, and the user clicks the review action
   **Then** the action reads **Save and review**; it saves the post, then reviews the saved content; when the save fails, no review starts and the editor's own save error stays the message
5. **The box follows the roles**
   **Given** a user without `postquality_view_reviews`
   **When** they edit a post
   **Then** there is no box; a user who may view reviews but not run Review sees the box without the action
6. **Tests cover the box**
   **Given** the harness sweep and the unit tests
   **When** they run
   **Then** a harness row checks the box's registration and its capability gate, and a unit test covers Save and review (save succeeds, save fails)

## Boundaries

- Must not change: the Post Reviews page and its detail panel, the contracts of the REST routes the box reuses (`/reviews/<post_id>`, `/trigger-review`), the workflow template, the Free/Pro split (Review is free).
- Out of scope (later slices, Notes): the other commands, Insert into Post from the editor, Rewrite, a panel in the editor's sidebar.

## References

- user paths — docs/user-paths.md (B3 Act on a post, B4 Verify improvement; EBM row 1)
- decision — docs/adr/0005-wordpress-components-for-app-screens.md (the admin UI on `@wordpress/components`)
- source — memory/admin-ui-rebuild.md (the kit in `src/kit/`, `useSingleRun`, polling); memory/freemium-and-roles.md (the five capabilities, the author quota)
- code — src/post-reviews/detail-panel.js and result-view.js (what the full review shows), src/kit/ (score badge, status badge, progress pills, quota note, `use-single-run.js`, `use-polling.js`)
- copy — docs/brand.md

## Notes

- Filed 2026-11-04 at the owner's request: "on each post, a meta box (like Yoast or another plugin provider) … a view and actions for the post directly bound within the post edit page", with the best WordPress and lean UX practices in mind.
- Decision (owner, 2026-11-04): the box sits **under the content** (a classic meta box), chosen over a panel in the editor's sidebar. The agent had said that a registered meta box keeps the block editor's canvas out of its iframe; to verify against the current WordPress release at refinement and record in the plan.
- Decision (owner, 2026-11-04): the first slice is **see + review**: the latest review, its top edits, Review with live progress, a link to the full review. The other commands come in later slices.
- Decision (owner, 2026-11-04): with unsaved changes, the action reads **Save and review**: save, then review what the writer sees.
- Decision (owner, 2026-11-04): built in the **next sprint**, before the first public release ("a strong plugin at launch").
- Later slices, to file at refinement: (a) the other commands the plan and role allow (Takeaways; Pro: Sources, SEO, Audit, Rewrite) with their results in the box; (b) Insert Takeaways and Sources into the open editor as blocks, undoable, instead of the server-side insert that would race the writer's unsaved edits; (c) Rewrite, which needs its own design for unsaved edits; (d) after a review, the box offers the next step of B4 (before/after score).
- Practices to apply (agent, for refinement): the box is registered on `add_meta_boxes` only for the post types the plugin reviews, and only for users with the capability; it renders a React root with the kit's components (no second UI kit); scripts load only on the post edit screens; it uses the editor's data store to know about unsaved changes and to save; progress is announced to screen readers; one primary action; no notice outside the box; the box's collapse and Screen Options visibility stay WordPress's own.
- Open question: whether a draft or pending post can be reviewed today (how n8n reads the post's content for a status other than published).
- Open question: how **Save and review** works in the Classic Editor, where saving reloads the page (for example, the review starts after the reload, or the box asks the writer to save first there).
- Open question: which post types get the box (those the plugin reviews today, to confirm in code).
