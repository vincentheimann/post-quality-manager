---
title: 'Story 109 — a Post Quality meta box shows the post''s review and runs a new one'
type: 'feature'
ticket: '109'
created: '2026-11-09'
status: done
baseline_revision: '418d1071c82e3447fd71c4aa5b273f6d55eabb1b'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/story-post-editor-meta-box-shows-and-runs-the-review.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** A post's review lives on Post Reviews, so a writer leaves the editor to read the edits and to run a new review (user paths B3–B4, UV row 1).

**Approach:** A classic "Post Quality" meta box under the content of posts, in the block editor and the Classic Editor, renders a React root from a second build entry. It shows the latest Review with the kit's pieces and runs Review through the existing routes. `GET /posts` gains one optional `post_id` filter: the box reads its `viewer` block from it, and Post Reviews uses it for a link that shows only that post with its panel open. The story's eight criteria and its Notes decisions are the acceptance.

## Boundaries & Constraints

**Always:** posts only; the box and its script only for users with `postquality_view_reviews`; a run only on a saved published or pending post; gating, quota and connection read from `GET /posts`' `viewer`, never from a localized snapshot; the kit's components and copy (`docs/brand.md`); progress in a `role="status"` region; names per the prefix harness (handle `postquality-meta-box`, global `window.postqualityMetaBox`, box id `postquality-review`, root id `pqm-meta-box`, URL arg `postquality_post`); Post Reviews and the setup dialog render and behave as before apart from the link.

**Never:** change `GET /reviews/<post_id>` or `POST /trigger-review`, the template, the Free/Pro split, `counts` or the key order of `GET /posts`; import `@wordpress/editor` (read `core/editor` by its store name); a second way to start a run (no run on `save_post`); `wp_localize_script`; a JS constant named `*_ARG` (prefix harness row 8).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Result | published post, latest Review done | score + status badge, date, top edits, link to Post Reviews | — |
| Running on load | latest Review row `processing` | running note; polls; result replaces it | gives up after 3 min with `giveUpMessage` |
| Failed | latest Review row failed | the existing failure sentence | — |
| Never reviewed | published/pending, no Review row, can run | "not reviewed yet" + Review | — |
| Not reviewable | draft, auto-draft, private, future | says reviews run on published and pending posts; no action; past review still shown | — |
| Status change | block editor, draft published without reload | box offers Review | — |
| Save and review | block editor, dirty | `savePost()`; run only if the save succeeded and nothing is dirty | save fails or skipped → no run, editor's message stays |
| Classic dirty | `wp.autosave.server.postChanged()` true | no run; asks to save changes first | — |
| Refused start | 429, 502, 200 `success:false` | server's message in the box | — |
| Not connected | `viewer.connected === false` | role-aware not-connected notice instead of the action | — |
| Quota | user with a quota | "X of Y left" | — |
| Roles/type | no view cap; view without run; page or CPT | no box/script; box without action; no box | — |
| Link | `postquality-posts&postquality_post=N` | list narrowed to N, panel open on Review, "Show all posts" clears | unlisted N → empty state with "Show all posts" |
| REST filter | `GET /posts` `post_id` 0/absent vs N | as today vs `p.ID = %d` in count and items queries | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` -- `list_posts_args()` L813-860 (add `post_id`, `type` only), `list_posts_callback()` L918-1007 (clamps args; `viewer` L992-1004, independent of items).
- `post-quality-manager/includes/class-pqm-db.php` -- `get_all_posts_with_scores()` L239-341; `$where_clauses` L256 feed both count and items queries.
- `post-quality-manager/admin/class-pqm-admin.php` -- `enqueue_app()` L224-270 (asset.php, translations, inline `window.postqualityCommandLabels`, `wp-components` + `style-index.css` rtl replace); `enqueue_assets()` L141-145 returns early without `postquality-`.
- `post-quality-manager/post-quality-manager.php` -- requires L101-109; `is_admin()` hook block L171-181; `PostQuality_Admin` is static.
- `src/post-reviews/detail-panel.js` -- unexported `rowState` L62, `reviewData` L78, `RunningNote` L88, `FailedNote` L104, `ResultMeta` L149, done block L254-271 (meta, pillars, "Top edits"); `ReviewTab` L227 keeps Open in Editor, Rewrite, Run button.
- `src/post-reviews/result-view.js` -- `PillarMeters`, `isReviewShape`, `PILLARS`, `pillarScore` L44-78, used by `ObjectView` L133/L148.
- `src/post-reviews-table.js` -- `NotConnectedNotice` L285-323 (role-aware); URL state L64-119 (writer keeps foreign args); `restPath` L127-134; `hasActiveFilters` L136; `openPostId` L340; empty state L684-715.
- `src/setup/first-review.js` -- polling L160-224 (`reviewsPath`, `POLL_INTERVAL`, `GIVE_UP_AFTER`, start from the `processing` row's `reviewed_at`); pinned by `tests/js/setup-first-review.test.js` (8 tests).
- `src/kit/runs.js` -- `requestRun(postId, command)` → `{ok, message}`, never rejects; `isFailedRow`, `isConnected`, `giveUpMessage`, `reviewsPath`, `POSTS_PATH`. `src/kit/quota-note.js` `QuotaNote({viewer})`.
- `src/post-reviews/style.css` L291-392 -- unscoped result classes the box needs.
- `package.json` scripts, `bin/zip.js` L53-58, `.github/workflows/ci.yml` L114-117, `release.yml` L79-81 -- check `index.js` + `index.asset.php` only. `@wordpress/scripts` 37.1.0 takes entry file args joined to `--webpack-src-dir`.
- `tests/harness/rest-posts-harness.php` -- rows via `list_with()` L341 + `prepared_with()`; sanitize loop L172-188; key order L229-230. `tests/harness/voice-harness.php` -- smallest shape; recorders in `setup-harness.php` L734-751, `render-matrix-harness.php` L1241-1271.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php`, `includes/class-pqm-db.php` -- optional `post_id` (`absint`, 0 = none) → prepared `p.ID = %d` -- one filter for the box and the link.
- [x] `src/kit/review-summary.js`, `src/kit/not-connected-notice.js`, `src/kit/use-review-wait.js` (new) -- move the pieces above, `PillarMeters`/`isReviewShape`, `NotConnectedNotice` and first-review's wait; `detail-panel.js`, `result-view.js`, `post-reviews-table.js`, `setup/first-review.js` import them unchanged in output; move their CSS so both entries get it -- one source, existing tests stay green.
- [x] `src/meta-box/index.js`, `meta-box.js`, `editor.js` (new) -- mount on `#pqm-meta-box`; `editor.js` wraps `core/editor` (saved status, dirty, `savePost` + `didPostSaveRequestFail`) and Classic's `postChanged()`; the box fetches `GET /posts?post_id` + `/reviews/<id>`.
- [x] `post-quality-manager/admin/class-pqm-meta-box.php` (new), `admin/class-pqm-admin.php`, `post-quality-manager.php` -- `PostQuality_Meta_Box`: `add_meta_boxes_post` + cap; `admin_enqueue_scripts` on `post.php`/`post-new.php` for posts + cap; shared command-labels helper; inline `window.postqualityMetaBox` `{postId, status, isBlockEditor, reviewsUrl}`.
- [x] `src/post-reviews-table.js` -- `POST_PARAM = 'postquality_post'`: initial `openPostId`, `post_id` in `restPath`, counted by `hasActiveFilters`, "Show all posts" clears it (`replaceState`).
- [x] `package.json`, `bin/zip.js`, `.github/workflows/ci.yml`, `release.yml` -- `index.js meta-box.js` entries; check `meta-box.js` + `meta-box.asset.php`.
- [x] `tests/harness/meta-box-harness.php` (new), `tests/harness/rest-posts-harness.php` -- registration (posts, cap), enqueue gate, `post_id` rows.
- [x] `tests/js/meta-box.test.js` (new) -- matrix rows Save and review (succeeds, fails, still dirty), Classic dirty, not reviewable.
- [x] `docs/release-notes/unreleased.md`, `memory/admin-ui-rebuild.md` -- the new box (7.1 visual-revisions cost); the second entry, `post_id`, the WordPress meta box facts dated with versions.

**Acceptance Criteria:** the story's criteria 1–8.

## Implementation Notes

- 2026-11-09, checkpoint 1: the plan measures about 2,600 tokens against the 1,600 target; the owner kept the full plan ("Keep full plan") and approved it ("Approve and continue"). Mitigation, for the implementer: work in three stages in this order — (1) the `post_id` filter and the Post Reviews link with their harness rows, (2) the kit move with Post Reviews and the setup dialog unchanged, (3) the meta box with packaging, tests and records. After each stage, run its checks (lint, Jest, harness sweep, build as they apply), then stop and report; the coordinator reads the diff and says when to continue. bmad-build ran through the Skill tool; every subagent runs on Opus (owner rule).
- 2026-11-09, stage 1 (coordinator read the diff): `post_id` in `list_posts_args()` (type only) and `list_posts_callback()` (`absint`), a prepared `p.ID = %d` in `get_all_posts_with_scores()` feeding both queries; Post Reviews holds the link as `query.post` outside `DEFAULT_QUERY` (the URL says `postquality_post`, the route `post_id`), `writeQueryToUrl()` owns the arg, `openPostId` starts from it, `hasActiveFilters()` counts it; "Show all posts" is `clearFilters` (clears every filter and the post, page 1), shown above the table while narrowed and in the empty state; one `.pqm-show-all` rule; `rest-posts-harness.php` +10 rows (190 checks). Accepted by the coordinator: "Show all posts" clears all filters (the button's existing meaning); the open panel stays open after it; no explanatory sentence (the owner judges the look at the walk). Prefix-harness row 8 does not list `postquality_post` (it finds only `*_ARG` constants); the name carries the prefix. Lint, Jest (10 suites, 182), build and the sweep green.
- 2026-11-09, stage 2 (coordinator read the diff): `src/kit/review-summary.js` (`rowState`, `RunningNote`, `FailedNote`, `ResultMeta`, `PillarMeters`, `isReviewShape`, `PILLARS`; default `ReviewSummary({row})`, the Review tab's done block, identical markup), `src/kit/not-connected-notice.js` (unchanged markup; its relative `admin.php?page=postquality-workflow` link also resolves from `post.php`), `src/kit/use-review-wait.js` (`useReviewWait(postId, waiting, {onSettled, onGiveUp})`, first-review's effect moved as is: same start-from-`reviewed_at`, give-up, retry; handlers in a ref). `detail-panel.js`, `result-view.js`, `post-reviews-table.js` and `setup/first-review.js` import them; the 8 first-review tests pass unedited. The moved rules went to `src/kit/style.css` (only files named `style.css` reach `style-<entry>.css`); built CSS compared rule by rule, same rules. Risk carried to stage 3: `@wordpress/scripts` names a shared style chunk after its first chunk, so the kit's rules may land only in `style-index.css`; the meta box's enqueue follows what the build emits.
- 2026-11-09, stage 3 (coordinator read the diff): `admin/class-pqm-meta-box.php` (`PostQuality_Meta_Box`: `register()` on `add_meta_boxes_post` behind `postquality_view_reviews`, box `postquality-review` "Post Quality" normal/default; `render()` prints `#pqm-meta-box` through the now-public `PostQuality_Admin::render_app_root()`; `loads_on($hook)` split out for the harness; `enqueue()` adds `postquality-meta-box`, translations, the shared `add_command_labels()`, inline `window.postqualityMetaBox` `{postId, status, isBlockEditor, reviewsUrl}`, and the styles). CSS settled from the build: `style-meta-box.css` holds only `src/meta-box/style.css`; the kit's rules ship in `style-index.css`, so the box also enqueues it through the extracted `enqueue_app_style()` (every selector there starts with `.pqm`/`#pqm`). `src/meta-box/` (`index.js`, `editor.js` reading `core/editor` by name, `meta-box.js`, `style.css`); the button is `secondary` beside the editor's own Update. `package.json` builds `index.js meta-box=meta-box/index.js`; `bin/zip.js`, `ci.yml`, `release.yml` check `meta-box.js` and `meta-box.asset.php` (deps `wp-data`, no `wp-editor`). Beyond the plan's file list: `tests/harness/release-header-harness.php` row 4 now expects one `wp_set_script_translations()` per entry; the handle stays a string literal (prefix row 6 and that row read literals only); `outcomeToast()` in `src/kit/runs.js` is exported so the box announces a finished run to screen readers with the existing sentence. Records: `docs/release-notes/unreleased.md` (the box and the 7.1 visual-revisions cost), `memory/admin-ui-rebuild.md` + its `MEMORY.md` line (second entry, CSS, kit move, `post_id`, the WordPress facts read 2026-11-09 at 6.6.0 and 7.1.4). `npm run zip` refuses an uncommitted tree; a tar of the folder held both entries.
- 2026-11-09, matrix test audit (step 03): rows Result, Running on load (and give-up), Failed, Never reviewed, Refused start, Not connected, Quota, view without run, and the Post Reviews link had no test; the implementer was sent back to add them (plus a test isolating the failed-save check).
- 2026-11-09, audit fixed: `tests/js/meta-box.test.js` 22 tests (14 new: result, running on load and its give-up, failed ×2, never reviewed, refused start ×3, not connected ×2, quota, view without run, the isolated failed save), new `tests/js/post-reviews-table.test.js` 4 tests (narrowed fetch and open panel, Show all posts drops the arg, no arg, unlisted post). No source change. Mutation check by the implementer (four deliberate breaks each failed its test, sources restored byte for byte). Coordinator's own run: Jest 12 suites / 208 tests, the two files 26; harness sweep 24/24 green (`meta-box-harness` 43, `rest-posts-harness` 190); `php -l` clean.
- 2026-11-09, the showcase walk (hitl manual check, after #267 merged as e85d0df; owner's choice "Walk 109 now"), run by an Opus agent per `docs/local-verification.md`: the showcase on WordPress 7.1.4, Pro build (`npm run zip` of clean main) then Free build, Playwright 1.65.0 with headless Edge and a WP-CLI-minted session, the webhook listener on 127.0.0.1, completions sent as n8n's `POST /save-review` would through `rest_do_request()`. All 13 checks passed: the result (badge, score, date, pillars, three top edits, Re-run Review, Open full review; no quota note for the administrator); the 7.1 Meta Boxes pane closed and reopened with the same React node kept (the open question of the story's Notes: answered); a run from the box (one `review` POST with `X-PQM-Token`, the running note alone in the status region, the result replacing it without a reload 1.9 s after the callback, the outcome read to screen readers); Save and review (save 200, the meta-box POST, then the trigger; status kept); "Not reviewed yet."; a draft (the sentence, no action, no link) published without a reload (Run Review and the link appear); the Post Reviews link (one post, Review tab, "Show all posts" back to 27 posts and the arg dropped); the Classic Editor plugin installed for the walk ("Save your changes first, then run the review." with nothing sent, then one POST after Update), removed after; roles (contributor no box and no script, author the box, a page no box); the Free build (result and a run); no console or page errors; `debug.log` only WP-CLI's lines; Plugin Check on the Pro build 0 errors, 20 warnings, the same as chore 103. Restore, then step 11: "plugin list: same", "tables, reviews, users, log: same", "plugin files, wp-config.php: same"; the listener (PID 16120) stopped by its PID, port 8765 free; the walk's scratch and zip deleted, 17 screenshots kept for the owner. Seen, not this story's: in 7.1 the Meta Boxes bar's own resize grip takes a click aimed at its centre; Post Reviews' table rows stop short of the table border at 1280 px too (pre-existing, the deferred entry of story 86's captures).
- Released 2026-11-09 in **3.13.0** (#268, release commit 31e3fd6; pre-release). Release run 42647414122 green. Zip `post-quality-manager-3.13.0.zip` sha256 `ebe9cec5…5964` (the release asset's digest and Freemius's upload): one root folder, 259 files, `admin/build/` with `index.js`, `meta-box.js`, `meta-box.asset.php` and `style-index.css`. Uploaded to Freemius as **deployment 172263**, release mode pending, Pro build root `post-quality-manager-premium`; the Free build check said "PHP identical: 13 files."; the -free and -pro artifacts were uploaded. Header: `Version` 3.13.0, `Requires at least` 6.6, `Requires PHP` 8.1 (8.0 in 3.12.4), workflow template unchanged (version 10). Release check 2026-11-09: steps 1–5 and 8 green; step 7's Freemius upload green, and its PHP read done afterwards on the Deployment page: "Min PHP version" 8.1 for 3.13.0 (8.0 for 3.12.4), read by the owner through Claude in Chrome on 2026-11-09; from the release after 3.13.1 the job prints it; step 6's clean WordPress 6.6 install is story 38's; step 9 not applicable yet.

## Plan Change Log

## Review Triage Log

- 2026-11-09, pass 1 (lens: quick; review_loop_iteration 0). Verdicts: high 0, medium 3, low 2, false 0, maybe-false 0. Routes: patch 2 entries (findings 1+2 grouped, 5), defer 1 (3), reject 1 (4).
  - F1 medium → patch (entry A, with F2). The box reads `viewer` once at mount (`src/meta-box/meta-box.js` load effect, deps `[postId]`) and `run()` never refetches; Post Reviews refetches after an accepted start (`src/kit/use-single-run.js` L93-100). Verified: after a run the quota note keeps the old count until a reload, against AC3.
  - F2 medium → patch (entry A). Same root cause: after `requestRun()` resolves ok only `phase` changes, `row` keeps the pre-run row. Verified at `meta-box.js`: (a) `state === 'done'` keeps the old `ReviewSummary` under the running note, while the Review tab shows only the running note for a `processing` newest row (AC1's "the state Post Reviews' Review tab shows it"); (b) on a never-reviewed post, `onGiveUp` sets only `phase`, so `state === 'none' && reviewable && phase !== 'waiting'` shows "Not reviewed yet." and Run Review beside the give-up message.
  - F3 low → defer. AGENTS.md L8 and "Where things are" do not name `src/meta-box/`, `admin/class-pqm-meta-box.php` or the second entry; harm: the next agent changing the kit or the enqueue path does not learn the box exists from the rules file. The fix edits an agent-context file → defer (deferred-work entry), hand edit on the owner's word as in earlier stories.
  - F4 low → reject. `render()` prints the root when `index.asset.php` exists, `enqueue()` needs `meta-box.asset.php`: a build without the second entry shows an empty box. Reached only in a development checkout (CI and the zip build both entries); the fix adds a branch.
  - F5 medium → patch (entry B). The kit's rules reach the box and the app pages only because webpack names the shared style chunk after its first chunk (`index`); no check fails if that changes, and Post Reviews and the Dashboard would lose the kit's styles silently. Verified: `ci.yml` L115-120 tests only that four files exist. Smallest fix: one assertion in the build job that `style-index.css` holds a kit selector.
  - Patches applied by the implementer (re-engaged): entry A — `meta-box.js` reloads `GET /posts?post_id` and `GET /reviews/<id>` after an accepted start (a `reloads` counter in the load effect's deps; a failed reload after a start keeps the box as it is), `ReviewSummary` gated on `phase !== 'waiting'`, "Not reviewed yet." only when `phase === 'idle'`; 3 new tests (quota 14 → 13 of 20 after a start, a past result hidden while running, a never-reviewed post's give-up without "Not reviewed yet."; the button then reads "Re-run Review", as Post Reviews labels it). Entry B — `ci.yml`'s "Assert built assets exist" step greps `pqm-pillars` in `style-index.css`. F3 written to `deferred-work.md`. Coordinator re-ran Verification: lint clean; Jest 12 suites / 211 tests; build green with `meta-box.asset.php` deps `react-jsx-runtime, wp-api-fetch, wp-components, wp-data, wp-element, wp-i18n, wp-url` (no `wp-editor`); the four file checks and the `pqm-pillars` grep hold; harness sweep 24/24 green. `npm run zip` runs after the commit.

## Design Notes

Box states, top to bottom: result (or running / failed) → quota note → one primary action (Review / Save and review, or the not-connected notice, or the not-reviewable sentence) → "Open full review" link. Copy (brand.md): "Not reviewed yet.", "Reviews run on published and pending posts.", "Save your changes first, then run the review.", "Save and review", "Open full review". The box title is "Post Quality" (short form). Skipped: a sidebar panel, other commands, draft review — the story's later slices.

## Verification

**Commands:**
- `npm run lint:js && npm run test:unit && npm run build` -- expected: green; `admin/build/` holds `index.*`, `meta-box.js`, `meta-box.asset.php` (deps include `wp-data`, not `wp-editor`) and the CSS the box needs.
- `for f in tests/harness/*.php; do php -d sys_temp_dir="C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/15bc8415-d28a-4945-bbb0-af050c7ba7d6/scratchpad" "$f" || break; done` (the session scratchpad; never the default `%TEMP%`) -- expected: every harness green; `php -l` on each touched PHP file. Temporary files of any kind go in that scratchpad.
- `npm run zip` -- expected: the zip carries both entries.

**Manual checks:**
- Showcase walk (`docs/local-verification.md`; backup, Pro build, restore): the box in the block editor (7.1: pane open/closed, root survives) and with the Classic Editor plugin enabled for the walk, every matrix row reachable on Local (webhook capture); Post Reviews link; Plugin Check from the scratch folder. The owner judges the look.
