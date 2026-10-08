# Deferred work — initiative post-quality-manager

Work consciously postponed during the initiative, with the decision that deferred it. Pick these up as backlog tickets when their trigger arrives.

## Real Freemius licensing integration (deferred 2026-10-02, story 3)

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
- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-refactor-sweep-plan.md`
  summary: `AGENTS.md:8` (bmad:context block) states that Settings and the n8n Workflow page use jQuery; they use vanilla JS, so the line should read "Settings and n8n Workflow use vanilla JS".
  evidence: Introduced by story 5.8's planned AGENTS.md floor edit (wording from the plan's Code Map). `post-quality-manager/admin/js/pqm-admin.js` is a vanilla IIFE booting on DOMContentLoaded, enqueued with no dependencies (`class-pqm-admin.php:145-148`); no `jquery` anywhere in the plugin. Deferred by the triage rule for agent-context file edits; a one-word owner-approved fix.
- source_plan: `_bmad-output/backlog/story-pattern-picker-explains-itself-plan.md`
  summary: The Agent-Specific Overrides section has no capability gate of its own: it renders for anyone who can open the Settings page (pqm_manage_settings or pqm_manage_agents), while the design (CAP-3) treats it as the agents' section.
  evidence: render_settings() gates the admin sections on pqm_manage_settings only; settings_page_capability() admits either cap; the sanitizer saves agent_prompts on plan alone. Surfaced by the story-24 review (a now-removed preview endpoint required pqm_manage_agents and would have 403'd a settings-only role). Pre-existing; the shipped role map never hits it. Settle by gating the section on pqm_manage_agents.
- source_plan: `_bmad-output/backlog/bug-run-error-missed-when-n8n-unset-or-unreachable-plan.md`
  summary: When n8n is unreachable (502 `pqm_n8n_error`) or answers non-2xx, the failed-start message tells every user to "Check your Webhook URL in Settings" / "Check that the workflow is active in n8n", which Authors and Editors without `pqm_manage_settings` cannot do; ticket 32's AC1 asks for a next step the clicking user can take.
  evidence: Ticket 32 quick review. Pre-existing copy in `trigger_review_callback()` (the `pqm_n8n_error` branch) and `requestRun()` in `src/kit/runs.js`; the approved plan made only the `pqm_not_configured` copy role-aware and its frozen Never list forbids other `/trigger-review` copy changes. Fix candidate: the same `current_user_can( 'pqm_manage_settings' )` split, with "ask an administrator" for others (server) and a `viewer.can_manage_settings` branch in `requestRun()`'s non-2xx copy (client). Owner call: fold into ticket 32 or a follow-up.

- source_plan: `C:/Git/Post Quality/post-quality-manager/_bmad-output/backlog/story-guided-setup-dialog-plan.md`
  summary: AGENTS.md still says Settings and n8n Workflow use vanilla JS only; since story 36 the n8n Workflow page also loads the React bundle for the setup dialog island.
  evidence: Story 36 quick review (2026-10-07). AGENTS.md is the `bmad-project-context` managed block, so the fix goes through that skill (refresh), not a build edit; memory/admin-ui-rebuild.md already records the island pattern.
- source_plan: `_bmad-output/backlog/story-ai-prompts-context-ux-review-plan.md`
  summary: The AGENTS.md managed block still states the replaced rule "every preset must stay editable and an explicit 'Custom' path visible (memory/lessons.md 2026-07-23)" while story 26 made presets read-only choices with Custom as the one editable path.
  evidence: Story 26 quick review. AGENTS.md wins over `_bmad/custom/minimal-code-ladder.md` (already updated), so the stale line governs the next build until `bmad-project-context` refreshes the block; agent-context files are not edited inside a build.
- source_plan: `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-tracer-bullet-release-pr-and-a-ci-built-plugin-zip-plan.md`
  summary: Re-running a failed `package` job in `release.yml` may fail at "Upload zip artifact" because an artifact with the same name already exists in the run, which would block the documented draft-release recovery (medium if true, unverified).
  evidence: Story 4.1 quick review. upload-artifact v4 defaults to `overwrite: false` and fails on an existing name. Its issue #323 reports that a re-run deletes the earlier attempt's artifact, which suggests no conflict, but the docs don't settle it. To settle it: re-run a failed dispatch of the job once. If it conflicts, set `overwrite: true` on that step.
