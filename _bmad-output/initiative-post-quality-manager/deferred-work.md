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
