---
id: 109
type: story
title: "A Post Quality meta box on the post edit screen shows the post's review and runs a new one"
parent: none
covers: [UV1]
after: []
assignee: ""
refined: true
hitl: true
risk: medium
---

# A Post Quality meta box on the post edit screen shows the post's review and runs a new one

## Description

A writer edits a post in the post editor, but the post's review lives on another screen (Post Reviews). To act on a suggestion, they switch screens, find the post, read the edits, then go back to the editor. This is the insight-to-action gap that `docs/user-paths.md` ranks first (B3–B4, UV row 1). This story puts the review where the writing happens: a "Post Quality" meta box under the content of a post, in the block editor and in the Classic Editor, like the boxes SEO plugins show there. This first slice shows the latest review and runs a new one; later slices add the other commands, the insertion of Takeaways and Sources into the open editor, and the review of drafts (Notes). The owner walks the box in both editors on the showcase site and judges its look and feel (hitl).

## Acceptance Criteria

1. **The box shows the post's latest review**
   **Given** a post with at least one Review run, and a user with `postquality_view_reviews`
   **When** the post's edit screen opens, in the block editor or the Classic Editor
   **Then** a "Post Quality" box under the content shows the latest Review in the state Post Reviews' Review tab shows it: the score with its status badge, the review date and the top edits; a run still in progress; or a failed run with the plugin's existing failure sentence
   **And** while the post is published or pending, the box links to this post's review on Post Reviews (criterion 7)
2. **The box offers Review only where a run is accepted**
   **Given** a draft, a new post, or a post of any status other than published or pending
   **When** its edit screen opens
   **Then** the box says that reviews run on published and pending posts, and offers no action; a published or pending post that was never reviewed says it has no review yet and offers Review
   **And** in the block editor, a post that becomes published or pending without a reload makes the box offer Review
3. **A review runs from the box**
   **Given** a published or pending post with no unsaved changes, and a user who may run Review
   **When** they start Review from the box
   **Then** the run starts, its progress shows in the box and is announced to screen readers, and the result replaces the previous one without a reload; a run that fails, or whose result does not arrive in the time Post Reviews waits, shows the message Post Reviews shows for it
   **And** a start the server refuses (monthly quota reached, n8n unreachable or refusing) shows the server's message in the box; a user with a monthly quota sees how many runs are left; on a site not connected to n8n the box shows the not-connected notice instead of the action, as Post Reviews does
4. **Block editor: unsaved changes are saved first**
   **Given** a published or pending post with unsaved changes in the block editor
   **When** the user chooses the box's action
   **Then** the action, labelled **Save and review**, saves the post, keeping its status, and starts the review only once the save succeeded and no unsaved changes remain
   **And** when the save fails or does not run, no review starts and the editor's own save message stays the message
5. **Classic Editor: unsaved changes wait for a save**
   **Given** a published or pending post whose title, content or excerpt has unsaved changes in the Classic Editor
   **When** the user starts Review from the box
   **Then** no run starts, and the box asks them to save their changes first
6. **The box follows the roles and the post type**
   **Given** a user without `postquality_view_reviews`, or the edit screen of any post type other than posts
   **When** the edit screen opens
   **Then** there is no box
   **And** a user who may view reviews but not run Review sees the box without its action
7. **The link opens the post's review on Post Reviews**
   **Given** the box's link to the full review
   **When** the user follows it
   **Then** Post Reviews opens showing only that post, with its detail panel open on its Review tab, and a control that shows all posts again
   **And** Post Reviews opened without that link behaves as before
8. **Tests cover the box**
   **Given** the harness sweep and the unit tests
   **When** they run
   **Then** a harness row checks the box's registration (posts only) and its capability gate, and unit tests cover Save and review (the save succeeds, the save fails) and the Classic Editor's refusal while changes are unsaved

## Boundaries

- Must not change: what Post Reviews and its detail panel show and do, apart from the link of criterion 7; the contracts of the REST routes the box reuses (`GET /reviews/<post_id>`, `POST /trigger-review`), including which post statuses a run accepts; the workflow template; the Free/Pro split (Review is free; the box runs no paid command); how the editor saves, apart from the box's Save and review.
- Out of scope (later slices, Notes): the other commands, Insert into Post from the editor, Rewrite, the review of drafts, a panel in the editor's sidebar.

## References

- user paths — docs/user-paths.md (B3 Act on a post, B4 Verify improvement; EBM row 1)
- design — _bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md (D2 one render source, D5 status badges, D6 polling with a live region; the box reuses these)
- decision — docs/adr/0005-wordpress-components-for-app-screens.md (the admin UI on `@wordpress/components`)
- source — memory/admin-ui-rebuild.md (the component kit, polling); memory/freemium-and-roles.md (the five capabilities, the author quota)
- copy — docs/brand.md
- external — https://make.wordpress.org/core/2024/10/18/post-editor-iframing-with-meta-boxes-in-wordpress-6-7/
- external — https://make.wordpress.org/core/2026/08/03/iframed-editor-changes-in-wordpress-7-1/
- external — https://make.wordpress.org/core/2018/11/07/meta-box-compatibility-flags/

## Notes

- Fact: filed 2026-11-04 at the owner's request: "on each post, a meta box (like Yoast or another plugin provider) … a view and actions for the post directly bound within the post edit page", with the best WordPress and lean UX practices in mind.
- Decision (owner, 2026-11-04): the box sits **under the content** (a classic meta box), chosen over a panel in the editor's sidebar.
- Decision (owner, 2026-11-04): the first slice is **see + review**: the latest review, its top edits, Review with live progress, a link to the full review. The other commands come in later slices.
- Decision (owner, 2026-11-04): with unsaved changes, the action reads **Save and review**: save, then review what the writer sees. Narrowed to the block editor on 2026-11-09 (below).
- Decision (owner, 2026-11-04): built in the **next sprint**, before the first public release ("a strong plugin at launch"). `/prioritize` 2026-11-09 picked it.
- Fact (WordPress source, read 2026-11-09 at wordpress-develop 6.6.0 and 7.1.4 with their bundled Gutenberg): the claim of 2026-11-04, "a registered meta box keeps the block editor's canvas out of its iframe", holds on 6.6 only. Since 6.7 a meta box no longer decides it; on 7.0 only the blocks in the post do; on 7.1 the canvas is always iframed. The meta box itself is never inside the iframe: the block editor shows it in its own area under the canvas, a resizable pane on 7.1 (open state and height kept per user) and a plain area on 6.6.
- Fact (same read): on 7.1.4 any active meta box turns off the block editor's visual revisions (revisions open on the classic `revision.php` screen), and, where a plugin enables real-time collaboration, a box without the Gutenberg plugin's `__rtc_compatible` flag turns collaboration off; core 7.1.4 sets no collaboration flag in the files read. A meta box also keeps the editor's Save button enabled.
- Decision (owner, 2026-11-09): keep the classic meta box under the content, with the 7.1 cost above. Most sites already carry an SEO plugin's meta box, which costs the same.
- Fact (same read): `savePost()` of `core/editor` resolves after the post and the meta boxes are saved, never rejects, and does nothing while another save runs; `didPostSaveRequestFail()` tells a failed post save (a failed meta box POST is swallowed). `isEditedPostDirty()` tells unsaved changes. A save keeps the post's status. The meta box form is POSTed to `post.php` after each save, so `save_post` fires twice, and fields inside the box are read by `edit_post()`. In the Classic Editor the `core/editor` store does not exist; `wp.autosave.server.postChanged()` compares the title, content and excerpt. `add_meta_box()` is unchanged from 6.6 to 7.1; `context` `normal` puts the box under the content in both editors; at `default` priority it sits below any `high`-priority box and, in the Classic Editor, below the core boxes a user shows (Excerpt, Discussion, Slug, Author, Revisions); neither compatibility flag applies. A script for both editors loads on `admin_enqueue_scripts` for `post.php` and `post-new.php`.
- Decision (owner, 2026-11-09): a run accepts only published and pending posts, as today. On a draft, a new post or any other status, the box says so and offers no action. Reviewing drafts is a later slice (e).
- Decision (owner, 2026-11-09): Post Reviews gains one link parameter that opens a post's detail panel; the box's link uses it, and nothing else on the page changes.
- Decision (owner, 2026-11-09, at the `validate.md` check): a detail panel is a row of the list page on screen, so the link narrows the list to that one post with its panel open, and a control shows all posts again; jumping to the page that holds the post was declined (it depends on the sort).
- Decision (owner, 2026-11-09, at the `validate.md` check): no design document for the box. It follows the Review tab's layout and components (the design in References), and the owner judges its look at the walk on the showcase site.
- Decision (owner, 2026-11-09): in the Classic Editor, where saving reloads the page, the box asks the writer to update the post first; **Save and review** is the block editor's. One way to start a run, the same as Post Reviews.
- Decision (owner, 2026-11-09, proposed by the agent from the code): the box shows on posts only; Post Reviews' list, its counts and the schedule cover posts only.
- Decision (owner, 2026-11-09, proposed by the agent from the code): the quota works as on Post Reviews (the runs-left note, the server's refusal message). The filing draft's "the quota note instead of the button" described a behaviour no page has.
- Fact (code, read 2026-11-09): `GET /reviews/<post_id>` returns every command's rows, newest first, running rows included; the run route refuses statuses other than published and pending with a 404, and checks neither the post type nor `edit_post`. Post Reviews polls `GET /posts` and its `in_flight` field; the setup dialog's first-review step polls `GET /reviews/<post_id>`, the precedent for a single post. The React pages learn the user's capabilities and quota only from `GET /posts`' `viewer` block, so the box needs its own; the prefix harness requires every `wp_localize_script` object to be read by `admin/js/pqm-admin.js`. The Review tab, failure and running notes live unexported in `src/post-reviews/detail-panel.js`; moving them into `src/kit/` keeps within the Boundaries when Post Reviews shows the same. One `wp-scripts` entry today; packaging and CI check only `index.js` and `index.asset.php`.
- Fact (code, read 2026-11-09): `tests/harness/prefix-harness.php` row 8 reads every JS `const …_ARG = '…'` as the setup dialog's open argument and checks it equals PHP's `SETUP_QUERY_ARG`, so a second such constant breaks the row; the new link parameter carries the `postquality_` prefix (AGENTS.md, query args). While n8n is not connected, Post Reviews disables its Run buttons and shows a notice (ticket 32); a run that gets no result in 3 minutes ends with the kit's give-up message.
- Open question (for the build): where the box reads the user's capabilities, quota and connection state. `GET /reviews/<post_id>` stays as it is (Boundaries), and `memory/admin-ui-rebuild.md` says gating and quota come from REST, never from a localized snapshot.
- Fact (Definition of Done): existing sites meet a new box on every post edit screen and, on WordPress 7.1, lose the block editor's visual revisions while it is shown; the PR adds a line to `docs/release-notes/unreleased.md`.
- Assumption (agent, 2026-11-09): the build applies these practices: the box is registered on `add_meta_boxes` for posts and only for users with the capability; it renders a React root with the kit's components (no second UI kit); scripts load only on the post edit screens; it uses the editor's data store to know about unsaved changes and to save; progress is announced to screen readers; one primary action; no notice outside the box; the box's collapse and Screen Options visibility stay WordPress's own.
- Decision (owner, 2026-11-09): the later slices stay listed here and are filed after this slice lands: (a) the other commands the plan and role allow (Takeaways; Pro: Sources, SEO, Audit, Rewrite) with their results in the box; (b) Insert Takeaways and Sources into the open editor as blocks, undoable, instead of the server-side insert that would race the writer's unsaved edits; (c) Rewrite, which needs its own design for unsaved edits; (d) after a review, the box offers the next step of B4 (before/after score); (e) the review of drafts (the run route, the list and the quota).
- Fact: refined 2026-11-09, owner approved: eight criteria and the 2026-11-09 decisions above. The `validate.md` check ran the same day before execution (a fresh agent): its fixes applied, its suggestions on criteria 2–5 adopted, its two asks answered by the owner above.
- Open question (for the build): whether the box's React root survives the block editor moving the meta box area in and out of its pane (7.1); the walk checks it.
