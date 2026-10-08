# Agent Lessons Log

_Updated by the agent after any user correction. Reviewed at the start of each session and loaded into every `bmad-build` run as a persistent fact (`_bmad/custom/bmad-build.toml`). Lived in `tasks/` until 2026-10-01 (ADR 0004)._

---

<!-- The agent appends entries here as the project progresses. Format:

## [YYYY-MM-DD] — Short description of the mistake
**What happened:** ...
**Root cause:** ...
**Rule going forward:** ...

-->

## [2026-10-08] — Reporting CI green from workflow runs instead of the PR's checks
**What happened:** Story 4.4 made Plugin Check non-blocking with `continue-on-error: true` on the job. The agent read `gh run list`, saw every workflow run conclude `success`, and told the owner PR #77 was all green. The owner saw both Plugin Check checks failing on the PR.
**Root cause:** A job-level `continue-on-error` only stops the workflow run from failing; the job's check run still concludes `failure`, and that is what the PR shows. The agent checked a different layer from the one the owner looks at.
**Rule going forward:** Report a PR's CI state from `gh pr checks <n> --repo …` (the check runs the owner sees), never from workflow-run conclusions alone. To make a CI finding non-blocking, put `continue-on-error` on the step that reports it, not on the job.

## [2026-10-08] — Proposing a repo-settings change before verifying it
**What happened:** To stop release-please counting each merged PR twice, the agent recommended a GitHub setting ("Default message") that was already the cause. It then asked to PATCH the repo's merge-commit settings to a combination the UI does not offer and the API docs neither allow nor forbid, without having read how release-please parses commits. The owner asked whether it had thought the command through. Reading release-please's source confirmed the mechanism, and showed that an option already enabled (rebase merging) solved the problem without any experiment.
**Root cause:** Answering from a remembered mental model of GitHub and release-please instead of checking the current setting and the tool's source. Also narrowing the options to the two first framed (squash or merge commit) instead of listing every merge method the repo already allows.
**Rule going forward:** Before proposing a change to an external system's settings (GitHub repo, Actions, n8n, hosting), read the current value through its API, confirm the mechanism in the tool's docs or source, and list every option that is already available. Never ask to apply a setting combination the documentation does not state as valid; say it is unverified and offer the documented alternative first.

## [2026-10-07] — Treating presets as text fillers instead of as the setting
**What happened:** Story 26 built the AI Prompts & Context presets twice as mechanics around an always-visible textarea — first "fill, then Undo", then "fill when empty, otherwise offer Replace / Keep". The owner rejected both: a user picking a profile should not have to reason about text boxes, drafts or undo. The model they want is progressive disclosure — the radio *is* the setting, a selected preset shows its text read-only and muted, and the textarea appears only under Custom.
**Root cause:** Optimizing the overwrite problem inside the existing control layout instead of asking which control layout removes the problem. Also over-reading the 2026-07-23 rule ("every preset stays editable") as "presets must fill an editable field".
**Rule going forward:** For preset-driven settings here: a preset is a choice, not a fill — resolved from code when used, shown read-only when selected; **Custom is the one editable path and stays visible**, starting from the stored custom text, never pre-filled. The 2026-07-23 rule is replaced by this one; its spirit — never remove the Custom escape hatch — stays. When a UX fix needs an undo, an offer or a confirmation, first ask whether a different control removes the action that needs them.

## [2026-10-07] — Naming a feature after the requester's context instead of the persona
**What happened:** Story 26 renamed the site-wide voice field "Corporate Standards" because the owner described the need as a corporate company. The owner corrected it: the plugin also serves solo bloggers and small teams (startups), for whom "Corporate" says the field is not for them. Renamed to "Editorial Standards" before commit.
**Root cause:** Taking the framing of one request ("as a corporate company…") as the product's audience, without checking the persona in `docs/user-paths.md` (solo professional blogger, any niche).
**Rule going forward:** Name features, labels and copy for the whole persona in `docs/user-paths.md`, never for the context of the person asking. When a request names an audience narrower than the persona, check whether the name and copy still read right for a solo blogger before writing them.

## [2026-10-05] — Recommending `window.confirm()` over a WordPress `Modal`
**What happened:** The story 5.5 plan offered the bulk confirmation as native `window.confirm()` or a `@wordpress/components` `Modal`, and recommended the native one because the minimal-code ladder's rung 4 lists the browser next to WordPress core. The owner chose `Modal` and said they prefer WordPress components by default.
**Root cause:** Reading rung 4 as "the browser is the cheaper of two equals". The React pages exist to render from one WordPress component source (ADR 0005), so a browser-native dialog is an off-system exception there, not the minimal option.
**Rule going forward:** In the React admin pages, dialogs, confirmations and similar UI use `@wordpress/components` by default. Offer a browser native only with a named reason, and ask first. The durable fact lives in `memory/admin-ui-rebuild.md`.

## [2026-10-05] — `intval` as a REST sanitize_callback fatals every request using the arg
**What happened:** A story 5.3 review patch swapped `absint` for `'intval'` on the `min_score`/`max_score` args of `GET /posts`. WP calls every `sanitize_callback` as `(value, request, param)`; PHP 8 internals with fixed arity throw `ArgumentCountError` on the third argument, so any score-band request returned 500 ("Could not load your posts"). The owner found it on the real site; the harnesses were green because they call route callbacks directly, bypassing WP's arg sanitizing.
**Root cause:** Treating a PHP internal like a WP userland helper (WP functions such as `absint`/`sanitize_key` silently ignore extra args; internals do not), and trusting harness coverage for a code path the harness never exercises.
**Rule going forward:** Never put a PHP internal (`intval`, `trim`, `strtolower`…) in a REST `sanitize_callback` — use a WP helper, a closure, or omit it and let the `type` schema sanitize. `rest-posts-harness.php` now calls every registered arg's `sanitize_callback` with WP's three arguments; keep stubs at WP's real arity so that check stays honest.

## [2026-07-24] — Dismissing ADR/skill as vehicles because the practice "didn't exist yet"
**What happened:** When placing the user-paths doc, the agent reasoned "the repo has no ADR practice, skills are workflows" and wired references only into AGENTS.md + memory. The user corrected: ADRs, skills, and living memory are practices that *should* be applied in this repo — the absence of a practice was the gap to fix, not a constraint to design around.
**Root cause:** Treating the current state of the workspace as fixed. The agentic workspace is itself a product the user actively invests in; "we don't do X here" is an invitation to propose adopting X, not a reason to route around it.
**Rule going forward:** When a decision reveals a missing practice (decision records, telemetry, a recurring workflow without a skill), surface it as an adoption proposal instead of silently choosing the existing structure. For knowledge placement use the triad: ADR = why, memory = what is true now, skill = recurring how.

## [2026-07-23] — Removing a user-facing "Custom" option during a UX redesign
**What happened:** A settings-UX plan proposed replacing the Blog Profile radios (which include "Custom") with preset-only one-shot buttons, dropping the explicit Custom option. The user rejected it: presets are useful, but the custom possibility is *more* important than the presets.
**Root cause:** Optimizing for control-model purity (action vs. state) over the user's actual priority — full ownership of the AI prompt inputs. Presets are onboarding aids; custom input is the product.
**Rule going forward:** In any preset-driven UI here, every preset must remain fully editable AND an explicit "Custom" path must stay visible. Never remove an escape hatch to simplify a control; simplify around it. *(Replaced 2026-10-07, see the entry above: presets are read-only choices, Custom is the one editable path — the escape hatch stays.)*

## [2026-07-23] — confirm() dialog on a pre-fill that only takes effect on Save
**What happened:** The profile/voice preset pre-fill asked "Apply this profile?" via confirm() before overwriting the textareas. The user flagged it as too intrusive: the settings form is not persisted until Save, so the overwrite is reversible by simply not saving.
**Root cause:** Treating an in-form field fill like a destructive action. Confirmation dialogs are for irreversible operations; a pre-fill inside an unsaved form is not one.
**Rule going forward:** Never gate in-form, not-yet-persisted changes behind confirm(). Reserve confirmation for actions with immediate side effects (AJAX writes, deletions, resets that persist). The Save button is the real commit point.

## [2026-03-27] — Hardcoding niche dimensions in AI Prompts
**What happened:** We initially hardcoded "Neuroscience" and "Marketing" as scoring dimensions in the n8n AI workflow, making the plugin highly specific to one niche. We had to do a massive refactoring to replace them with universally applicable pillars.
**Root cause:** Overfitting the prompt to the specific use-case of the current website instead of thinking about the plugin as a distributable, agnostic WordPress tool.
**Rule going forward:** Always build AI prompts and data schemas using universally accepted content pillars (Structure, Readability, Engagement, Credibility). Any niche-specific grading should be handled dynamically via user settings (like the Brand Voice preset), not hardcoded in the core prompts.
