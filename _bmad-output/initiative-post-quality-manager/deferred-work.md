# Deferred work — initiative post-quality-manager

Work consciously postponed during the initiative, with the decision that deferred it. Pick these up as backlog tickets when their trigger arrives.

## Real Freemius licensing integration (deferred 2026-10-02, story 3)

ticket: `_bmad-output/backlog/story-real-license-unlocks-pro.md` (story 44, filed 2026-10-09; the provider choice is re-confirmed by spike 43 first).

Story 3 shipped the license provider as a **stub**: `PQM_License::is_pro()` treats any non-empty `license_key` in `pqm_settings` as pro — owner decision of 2026-10-02 ("nothing to protect until a paid product exists; third parties at the end of the epic", ticket note of 2026-10-01). Still to do when the paid product becomes real:

- **Freemius SDK integration**, confined behind `PQM_License` — only that class's internals change; `PQM_Features::is_pro()`, the `pqm_is_pro` filter contract, gate callers, `why_not()`/`plan_allows()` all stay as they are.
- **Owner creates the Freemius product** (plans, pricing, license issuance) — plugin-side work cannot start before this exists.
- **Release notes / migration path** for installs the licensing flip turns free: any site that demoed pro via the stub (or the mu-plugin filter) and has no real license reverts to the free tier — the release notes must say so, and stored cron/override settings must keep surviving the flip (they do: sanitizer carry rules, story 5/6).

Trigger: end of `epic-freemium-and-roles`, when third-party distribution starts.

- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-status-and-copy-component-kit-plan.md`
  summary: The stored `error` review status (written by `save_review_callback` when n8n posts `error_message`) has no StatusBadge entry, so a post whose last review failed shows the raw, untranslated word "error" on the neutral badge instead of a gentle, actionable failure state.
  evidence: Pre-existing — legacy `format_status()` rendered the same raw value; the mockup deliberately omits error states and the spec does not name one. Needs an owner decision on the failed-review badge (icon, label, where the message surfaces) — natural home is story 5.4 (detail panel), where the error text becomes visible.

- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-post-reviews-list-filters-sorting-plan.md`
  summary: The stored `error` review status has no place in the Post Reviews filter/sort contract — `status=error` is ignored, errored posts fold into `counts.all` without a chip, and the severity sort ranks them with never-reviewed posts.
  evidence: Pre-existing inventory gap (the route and the kit assume four stored statuses; `save_review_callback` also stores `error`). Same owner decision as the 5.2 badge deferral: define the failed-review state (badge, chip or not, severity slot) once, then story 5.4 renders it and the route gains `error` in `LIST_STATUSES`/`STATUS_SEVERITY`/counts.

- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md`
  summary: The n8n template's failure paths (Error Fallback, agent JSON-parse errors, rewrite save failure) post `status: ''` with `result: {}` instead of `status: 'error'` + `error_message`, so a failed run is stored as "never analyzed" and the kit's `error` badge never lights up.
  evidence: Verified in `templates/n8n-workflow.json` (POST Result to WordPress body, ~line 598–604, sends no `error_message`); `save_review_callback` only wraps `{error}` when the status is `error` or `error_message` is present. Owner decision 2026-10-05 (story 5.4 OQ-1): keep the save-review contract and chips unchanged in the rebuild; fix the template in its own backlog ticket (template-version bump + handshake), after which the list shows "Could not complete" without further plugin work.
  ticket: `_bmad-output/backlog/bug-failed-n8n-run-stored-as-never-analyzed.md` (bug 31, filed 2026-10-06)

- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-bulk-review-selected-plan.md`
  summary: The React pages have no automated check for their I/O matrix rows (no JS test path), and `wp-scripts lint-js` crashes on an `@typescript-eslint` / `ts-api-utils` load error, so story code ships unlinted and UI matrix rows rely on the manual site pass alone.
  evidence: Story 5.5 quick review. Pre-existing since 5.1: stories 5.1–5.4 covered UI matrix rows manually, and 5.4 recorded the same lint crash. `@wordpress/scripts` already ships `test-unit-js` (Jest), so the minimal-code ladder's "installed dependency" rung could cover pure logic such as the bulk queue without a new dependency. Needs a decision on adopting it, and a lint-tooling fix.
- source_plan: `_bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-dashboard-queue-tiles-sparkline-plan.md`
  summary: Post Reviews score sort puts never-reviewed posts (NULL score) first in ascending order, so the Dashboard's Average score tile ("sorted by score, lowest first") opens on Not analyzed posts instead of the weakest scored ones.
  evidence: `get_all_posts_with_scores()` maps `orderby=score` to `latest_score` with no NULL handling; MySQL sorts NULL first ascending. Pre-existing since story 5.3; surfaced by the 5.6 quick review. Fix candidate: `latest_score IS NULL, latest_score {dir}` (NULLs last both ways) — a Post Reviews sort-semantics change, so owner call.
- source_plan: `_bmad-output/backlog/story-pattern-picker-explains-itself-plan.md`
  summary: The Agent-Specific Overrides section has no capability gate of its own: it renders for anyone who can open the Settings page (pqm_manage_settings or pqm_manage_agents), while the design (CAP-3) treats it as the agents' section.
  evidence: render_settings() gates the admin sections on pqm_manage_settings only; settings_page_capability() admits either cap; the sanitizer saves agent_prompts on plan alone. Surfaced by the story-24 review (a now-removed preview endpoint required pqm_manage_agents and would have 403'd a settings-only role). Pre-existing; the shipped role map never hits it. Settle by gating the section on pqm_manage_agents.
- source_plan: `_bmad-output/backlog/bug-run-error-missed-when-n8n-unset-or-unreachable-plan.md`
  summary: When n8n is unreachable (502 `pqm_n8n_error`) or answers non-2xx, the failed-start message tells every user to "Check your Webhook URL in Settings" / "Check that the workflow is active in n8n", which Authors and Editors without `pqm_manage_settings` cannot do; ticket 32's AC1 asks for a next step the clicking user can take.
  evidence: Ticket 32 quick review. Pre-existing copy in `trigger_review_callback()` (the `pqm_n8n_error` branch) and `requestRun()` in `src/kit/runs.js`; the approved plan made only the `pqm_not_configured` copy role-aware and its frozen Never list forbids other `/trigger-review` copy changes. Fix candidate: the same `current_user_can( 'pqm_manage_settings' )` split, with "ask an administrator" for others (server) and a `viewer.can_manage_settings` branch in `requestRun()`'s non-2xx copy (client). Owner call: fold into ticket 32 or a follow-up.
- source_plan: `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-tracer-bullet-an-administrator-connects-an-n8n-instance-by-k-plan.md`
  summary: The connect probe's route checks (`POST /workflows/<unknown>/activate`, `PATCH /credentials/<unknown>`) send no body and no Content-Type; if n8n's OpenAPI validator answers such a PATCH with 415, a healthy instance fails with cause `unexpected` (high if true, unverified).
  evidence: Entry 6.1 quick review. `PQM_N8n::route_exists()` accepts only 400/404 in n8n's error shape. Settled by the owner's HITL probe on n8n 2.42.3: record the real answer of each route probe; if a 415 appears, send `{}` with `Content-Type: application/json` or accept a JSON 415 as "exists", and update the harness's `ROUTE_OK` fixture.
  resolved: 2026-10-13, HITL probe on 2.42.3 — a body-less PATCH does answer 415; the probes now send `{}`, a JSON 415 counts as "exists", and the generic `not found` is the missing-route signal (branch `fix/n8n-probe-shapes`).
- source_plan: `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-tracer-bullet-an-administrator-connects-an-n8n-instance-by-k-plan.md`
  summary: The managed context block in `AGENTS.md` says the n8n Workflow page mounts one React island (the setup dialog) and lists the React sources without `src/n8n-connection/`.
  evidence: Entry 6.1 added the n8n Instance island (`pqm-n8n-connection-root`). The block is managed by `bmad-project-context`; refresh or record it there rather than by hand.
  resolved: 2026-10-16, entry 11 (D1) — the two sentences inside the managed block are hand-edited: two islands named, `n8n-connection/` in the React sources list; the project-context skill's next audit or refresh re-verifies the block (branch `feat/n8n-refactor-sweep`).
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-workflow-template-carries-a-per-site-webhook-path-plan.md`
  summary: A manual re-import onto a webhook path that an active workflow already holds (a Pro site replacing its v6 Free workflow, or any re-import after a future version bump) cannot be activated in n8n until the old workflow is deactivated, and no copy says so.
  evidence: Entry 6.3 triage. n8n refuses to activate a second workflow on a registered production webhook path; with the per-site path, the re-imported workflow takes the same path as the one it replaces. Pre-existing with the static `pqm-execute` path. Belongs in entry 6.2's manual steps ("deactivate the old workflow, then activate the new one"); entries 6.4 and 6.9 avoid it (create on a fresh instance, PUT in place).
  resolved: 2026-10-14, entry 6.2 — step 4 of the Workflow File section says a re-import over a workflow of version 6 or later is published only once the old one is unpublished (the arrow beside "Publish"; older n8n: the Active toggle).
- source_plan: `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md`
  summary: The header comment of `src/setup/steps.js` (line 7) says the credentials copy also lives in `PQM_Admin::render_credentials_list()`, which entry 6.2 replaced with `render_workflow_file()`.
  evidence: Entry 6.2 quick review. Comment-only drift; the entry's frozen Never forbids any change to `src/setup/`. Fix it in entry 6.6, which reworks the dialog's n8n steps and so edits that file.
  resolved: 2026-10-14, entry 6.6 — the header comment of `src/setup/steps.js` is rewritten for the two paths; the credentials copy lives only in the Workflow File section (branch `feat/n8n-setup-wizard`).
- source_plan: `_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-workflow-file-section-carries-a-novice-through-the-manua-plan.md`
  summary: The setup dialog's n8n steps in `src/setup/steps.js` use labels n8n 2.42.3 no longer shows ("Import from URL" / "Import from File", "Create" in the side menu then "Credential") and link credentials node by node without the order that keeps a hand-picked webhook credential from spreading to the Claude nodes.
  evidence: Entry 6.2, label check of 2026-10-14 against n8n's source at `n8n@2.42.3` (en.json: `menuActions.import` "Import" → `importFromUrl` "From URL" / `importFromFile` "From file"; `projects.header.create.credential` "Create credential"; `NodeCredentials.vue` + `assignCredentialToMatchingNodes()` spread a hand pick to every empty node of the type). The Workflow File section was corrected; the frozen Never of entry 6.2 forbids `src/setup/`. Entry 6.6 replaces these steps; until then, align the wording there (see `memory/n8n-provisioning.md`).
  resolved: 2026-10-14, entry 6.6 — the dialog's manual n8n steps are removed, not reworded: the wizard's Set up by hand step links to the Workflow File section, the one place for those steps (branch `feat/n8n-setup-wizard`).
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-plugin-provisions-n8n-on-the-server-side-plan.md`
  summary: A failed `POST /n8n/provision` records nothing (no setup map, no created ids, no Application Password uuid), so a retry is not refused and creates a second Application Password, same-name credentials and a second workflow; if `activate` times out while n8n did activate, every retry then fails at `activate` on the path the live orphan holds.
  evidence: Review of entry 4 (2026-10-14). `PQM_N8n::provision()` writes `pqm_n8n_setup` only after activation, and `step_failed()` returns without the ids created so far. The frozen intent leaves rollback to entry 5: its in-run rollback holds the ids in memory, but it must also deactivate and delete a workflow whose `activate` answered with a WP_Error (it may be active), and revoke the Application Password created before the first n8n call.
  resolved: 2026-10-14, entry 5 — `provision()` rolls back every failure after the first creation (deactivate, delete the workflow, then the credentials; the Application Password revoked) and records what it could not remove in `pqm_n8n_leftovers`, which the next run removes first (branch `feat/n8n-provisioning-rollback`).
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-a-failed-provisioning-leaves-nothing-behind-plan.md`
  summary: Entry 5's `unknown` field in `epic-n8n-provisioning/tickets.toml` says "the 409 and silent-delete behaviours are confirmed by the spike", but n8n 2.42.3's source shows a DELETE right after an unpublish answers 409 "still being unpublished" until the publication outbox poll (15 s by default), so the spike's "200 after deactivate" is timing-dependent.
  evidence: Entry 5 quick review, finding 4; `packages/cli/src/workflows/workflow.service.ts` `delete()` at `n8n@2.42.3` (read 2026-10-14, plan Design Notes). Not observed on a live instance yet; update the field through `bmad-ticket` once a forced failure has run on the owner's instance (entry 5 walk if the owner takes it, else entry 12).
  resolved: 2026-10-16, entry 12 — the forced failure ran on a real instance (activation refused with 409); the field now says what was observed (branch `feat/n8n-success-signal-walk`).
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-wizard-connects-and-provisions-n8n-or-hands-over-to-the-plan.md`
  summary: `docs/user-paths.md` A4's CV line still describes the setup dialog's seven manual steps (exact n8n menu names, a Copy button on every value, the Application Password created through core), which entry 6.6 replaced with connect by key, set up the workflow, first review.
  evidence: Entry 6.6 quick review, finding 7. The epic's decision (2026-10-13) gives user paths to entry 12, which records the success-signal walk; rewrite A4's CV/UV there.
  resolved: 2026-10-16, entry 12 — A4 rewritten for the key path (three values, no n8n UI visit, rollback, adoption, diagnosis, update, rotation; the manual path kept), the first review's landing on a fresh site kept as UV (branch `feat/n8n-success-signal-walk`).
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-the-wizard-connects-and-provisions-n8n-or-hands-over-to-the-plan.md`
  summary: The comment above the deterministic credential names in `PQM_N8n::provision()` (`class-pqm-n8n.php` ~L346) says the setup dialog carries them too; entry 6.6 removed the dialog's credential names.
  evidence: Entry 6.6 quick review, finding 8. Comment-only drift inside `provision()`, which entry 6.6's frozen Never excludes; fix in entry 11's refactor sweep.
  resolved: 2026-10-16, entry 11 — entry 9 moved the names into `credential_names()`; its docblock, which still named the dialog, now names the Workflow File section only (branch `feat/n8n-refactor-sweep`).
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-an-administrator-adopts-an-existing-manual-setup-plan.md`
  summary: `docs/policies/privacy.md` and `readme-external-services.md` do not say that, with an n8n API key stored, every test ping of the saved webhook URL also reads the workflow through the n8n API (entry 6.7's diagnosis, GET only); entry 6.8 added the adoption reads beside it.
  evidence: Entry 6.8 quick review, row 10. Pre-existing since PR #134: the policy lines name Connect, Check again, setup and now adoption, never the diagnosis. Fix with story 68's site sync or entry 12's records.
  resolved: 2026-10-16, entry 11 — both policy files say that a test ping with an n8n API key stored also reads the workflow at the webhook address through n8n's API, changing nothing (the readme's suggested privacy text lists the test too); the site copy follows through story 68 (branch `feat/n8n-refactor-sweep`).

- source_plan: `../backlog/story-n8n-one-source-each-plan.md`
  summary: The example production URL "https://your-n8n.example.com/webhook/<path>" is spelled in PHP (`class-pqm-admin.php` render_workflow_file) and in JS (`src/setup/steps.js`), two copies of one placeholder string.
  evidence: Story 77's review (finding 6) found it while grepping `/webhook/`; it predates the sweep and is a copy string, not one of the ticket's duplication-map items; story 67's copy pass against docs/brand.md is where one source for it belongs.

- source_plan: none
  summary: Story 78's second goal, the split of `tests/harness/n8n-api-harness.php` into four files by concern (client; provisioning and rollback; diagnosis and adoption; managed actions) with the constants, HTTP and `$wpdb` stubs and world builders in a shared include, builds second on top of the shared stub bootstrap.
  evidence: Owner decision 2026-10-21 at the story's multi-goal check: two builds, two PRs, the bootstrap first so the split is written against it; the four files each keep their own "key never leaves the server" scan (+3 checks accepted as the one count delta). Resolved by `_bmad-output/backlog/story-harness-stub-bootstrap-and-split-plan-2.md` (built 2026-10-21).

- source_plan: none
  summary: Story 2's second goal, the bounded wait on the wizard's first review — when nothing lands within a limit, a sentence naming the webhook URL and the site's reachability from n8n — is built after the round-trip ping as its own small build (independent of the template bump).
  evidence: Owner decision 2026-10-21 at story 2's multi-goal check: build (a) the round-trip ping first (template v7, the callback endpoint, the result copy, the wizard's connect step runs it); (b) the first-review wait is the deferred second goal. Resolved by `_bmad-output/backlog/story-full-round-trip-connection-test-plan-2.md` (built 2026-10-22).

- source_plan: `_bmad-output/backlog/story-site-navy-and-check-plan.md`
  summary: /docs/ still names n8n Cloud, has no `getting-n8n` or `cost` id for the home page's two links, and the interest links on /docs/ and /support/ carry no body or page phrase (brief D16).
  evidence: Build 1's quick review, finding 10 (2026-10-23): pre-existing text the Intent leaves to build 2; build 2's plan (`story-site-navy-and-check-plan-2.md`) covers the docs rewrite and anchors; /support/'s link is policy text, changed in `docs/policies/support.md` first if wanted.

- source_plan: `_bmad-output/initiative-post-quality-manager/plan-admin-copy-post-quality-name.md`
  summary: Bug 59's reproduction step still says "Open Content Quality → Account"; after the rename the menu is "Post Quality → Account".
  evidence: The admin menu label changed on 2026-10-23 (`admin/class-pqm-admin.php`); the build workflow does not write ticket files, so the bug's text is corrected at its next refinement or by bmad-ticket.
- source_plan: `_bmad-output/backlog/story-site-navy-and-check-plan-3.md`
  summary: Post Reviews detail tabs render "✓Review" and "✓Takeaways" with no space, because the '✓ ' span is a flex item of an inline-flex tab button and its trailing space collapses.
  evidence: Seen in story 86's capture `review.png` (2026-10-23); `src/post-reviews/detail-panel.js` L482–486. A margin on `.pqm-tab-done` (or a non-breaking space) fixes it.
- source_plan: `_bmad-output/backlog/story-site-navy-and-check-plan-3.md`
  summary: At a 1024 px window the Post Reviews table rows stop short of the table's right border, likely the hidden "Last reviewed" column in a fixed-layout table.
  evidence: Seen during story 86's captures on the showcase site (2026-10-23), capture `<session scratchpad>/capture/raw/review-page-1024.png`; not in any published frame.
- source_plan: `_bmad-output/backlog/story-site-navy-and-check-plan-3.md`
  summary: The setup guide's step 1 tells new users "Sign up for n8n Cloud at n8n.io", while the n8n Cloud path is untested and n8n Cloud's free trial has no public API, which the guide needs.
  evidence: `src/setup/steps.js` L162–166; docs/user-paths.md A4 ("The n8n Cloud path … is untested"); the site's honesty review C4 (2026-10-22) and the owner's launch gate (Cloud walk before Cloud is named). The site no longer names Cloud; the plugin still does.
