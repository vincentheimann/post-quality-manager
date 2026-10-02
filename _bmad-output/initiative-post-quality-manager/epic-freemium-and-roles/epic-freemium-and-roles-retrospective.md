---
epic: epic-freemium-and-roles
date: 2026-10-02
verdict: accepted-with-open-items
criteria: declared
headless: false
---

# Retrospective — epic-freemium-and-roles

## Epic summary

**Epic:** "Features follow plan and role through one gate" (covers initiative O1, O2; requirement source spec-freemium-and-roles CAP-1…CAP-5). All 8 tickets `done`, none `built`-only, `pending_tickets` empty:

| Ref | Title | Status | Range (baseline → next) |
|---|---|---|---|
| 1.1 | Feature gate tracer | done | `bea1c98..e28c0bf` |
| 1.2 | Role capabilities replace binary checks | done | `e28c0bf..a07bb90` |
| 1.4 | Author quota | done | `a07bb90..cca8766` |
| 1.5 | Settings sections follow role and plan | done | `cca8766..fb97112` |
| 1.6 | Cron under the gate | done | `fb97112..d09642d` |
| 1.3 | License provider behind is_pro() | done | `d09642d..a6e9deb` |
| 1.7 | Refactor sweep | done | `a6e9deb..c10f683` (cut at the last epic commit, inferred) |
| 1.8 | Manual pass of the epic's Done when | done | no baseline — HITL; evidence is the ticked checklist + result in its story file |

**Evidence inventory:** epic file with declared Done when 1–4; spec + access-matrix companion; 7 build plans with baselines, Implementation Notes, Review Triage Logs and Verification results; `git_evidence.py` run per range (commits + per-file churn); 6 CLI harnesses (caps 173, cron-gate 24, license 21, quota 32, render-matrix 38, sanitize-matrix 86); owner's 1.8 walkthrough result (2026-10-02, all green). Epic-wide churn concentrates where the design says it should: `class-pqm-features.php` +255 net (the gate), `class-pqm-admin.php` +181, `class-pqm-rest-api.php` +71; harness code (+1,086 net across six files) exceeds plugin code added.

**Missing evidence / narrowed scope:** (1) Session logs exist only for stories 1.6, 1.3, 1.7, 1.8 (this session); stories 1.1–1.5 were built in earlier sessions whose logs are not available — process-lesson analysis for them reads only what the plans recorded. (2) No previous epic retrospective exists (first epic under BMad, ADR 0004) — follow-through section is n/a. (3) The 1.3 and 1.7 ranges contain non-epic commits (ticket-7 UX review work, two backlog tickets); identified by subject and excluded from epic attribution. (4) Runtime behavior was exercised by the owner on the real site (1.8), not by the agent — no WP install in this environment (backlog ticket 9 exists for that).

## Findings

### Aggregate view — architecture delta (inline, full scope)

Before the epic: permission logic was scattered binary checks (`edit_posts`/`manage_options`) in `class-pqm-rest-api.php` and `class-pqm-admin.php`. After: one intentional hub — `PQM_Features` referenced from REST (6 call sites), admin (10), activator (3), cron (1), uninstall (1) — and `PQM_License` reachable only through `PQM_Features::is_pro()` (grep 2026-10-02, 25 occurrences / 7 files). No cycles (`PQM_License` reads only the global settings helper). This is the structure spec CAP-1/CAP-2 prescribed, not accidental coupling. **Disposition: clean — no action.**

### Aggregate view — spec-to-implementation reconciliation (subagent, full scope)

1. **CAP-2 weakened to a stub** — `PQM_License::is_pro()` = any non-empty key (`class-pqm-license.php:25-27`), no Freemius/validation. Recorded in the class docblock, `deferred-work.md` (2026-10-02), epic Notes, `memory/freemium-and-roles.md` — but **no ADR records the stub decision**; ADR 0003 still reads as if Freemius ships with the epic. *Disposition: accept the deviation (owner decision 2026-10-02), fix the record — see action item A1.*
2. **CAP-1 "no trigger bypasses the gate" reinterpreted** — `score`/`apply*` pass via the free-tier fallback (`class-pqm-features.php:104`), `ping` posts to the webhook behind `pqm_manage_settings` only (`class-pqm-admin.php:1338-1358`), cron uses the plan half alone (`class-pqm-cron.php:74`). All recorded in memory/plans ("outside the gate by design"), none in the spec. *Disposition: accepted deviations; reconcile the spec — action item A2.*
3. **CAP-4 quota exemption is capability-keyed** — `is_quota_subject()` = lacks `pqm_run_paid_agents` (`class-pqm-features.php:153-155`): free-site Editors are exempt though plan-blocked; `0 = unlimited` and "non-registry commands consume quota" are added behavior named by plans/memory, not the spec. *Disposition: accepted (owner 2026-10-01); reconcile — A2.*
4. **CAP-3 as-built extends the matrix** — `pqm_view_reviews` (no matrix row), Editor Settings-page access via `pqm_manage_agents`, `/save-review` WP-auth path on `pqm_run_free_agents`. Epic Done-when 3 names the Editor behavior; the matrix was never updated. *Disposition: reconcile — A2.*
5. **Added, recorded only in code/memory:** `pqm_is_pro` dev-override filter, `CAPS_VERSION` upgrade re-registration. *Disposition: accepted, already in memory; no action.*

### Aggregate view — duplication map (subagent, full scope)

1. **Six harnesses each re-declare their stub world** with semantic drift: 3 distinct `current_user_can()` implementations, 4 `apply_filters()` variants, 4 different `pqm_get_settings()` default arrays, `WP_Role` full stub vs empty shell, runner/exit/FAIL-label/globals-name drift (sources: caps-harness.php:24-41 vs cron:34-54 vs sanitize:14-78 et al.). Partly deliberate (each harness tests a different layer), but the drift class will grow with every new harness. *Disposition: defer — action item A3 (shared stub bootstrap when the next harness lands; not worth churning six green files now).*
2. **PHP↔JS double-render** — effective-model resolution exists 3× (`pqm_effective_model()`, admin.php:139-141 inline, js `effectiveDefault()`), quota note rendered in PHP (admin.php:561-566) and JS (js:850-867) with the English string duplicated, hide-for-role applied server-side and client-side. *Disposition: accepted — this is exactly finding F2 of the owner-approved UX review; ADR 0005's one-render-source rebuild removes the class. No separate action.*
3. **Upgrade copy in 4 wordings** (admin.php:156, :899, :1007, rest-api.php:380); 4× repeated `pqm_manage_settings` + "Insufficient permissions" AJAX guard (admin.php:1287-1394). *Disposition: defer to the rebuild epic (copy centralizes with the render source).*

### Aggregate view — god-class / size (subagent, inspected)

- `admin/class-pqm-admin.php`: **1,504 lines, 20 methods** (epic +181 net). Growth landed inside existing banner sections — structured accretion, not chaos — but `render_settings()` is ~388 lines of mixed PHP/HTML and the file now carries menu + assets + settings + three pages + AJAX + helpers. *Disposition: defer — the ADR 0005 rebuild strips the two app pages and the JS bridge out of it; record in the rebuild spec (A4). No split now.*
- `includes/class-pqm-features.php`: 255 lines, 14 short methods, two responsibilities (caps/gate + quota) under clear section banners, `why_not()` the single decision point. *Disposition: accept — healthy; a `PQM_Quota` split is not yet earning its file.*

### Aggregate view — pattern divergence (subagent, full scope)

- WP-spaced style in `includes/` vs no-space style in admin + harnesses — each file follows its own local convention. *Accept.*
- Harness architecture split (4 require class files; render/sanitize bootstrap the whole plugin) — matches what each layer tests. *Accept; fold normalization of the drift items (error_reporting, exit expression, FAIL label, sanitize's non-`tw` globals) into A3.*
- **i18n gaps**: JS-built labels hardcoded English ('Re-run Review' js:496, 'Regenerate Rewrite' js:580, 'Open in Editor' js:495) — known F3 class, dies with the rebuild (*accepted*); **`rest-api.php` success `message` sprintf untranslated** — a user-visible toast string, one-line fix (*fix-now candidate — A5*).
- `license_key` trimmed while adjacent `webhook_token` is not; Yoda conditions in REST only. *Accept (cosmetic, local conventions).*

### Lens — verification gap (bmad-review, pre-verified, full scope)

Context: verification = six CLI harnesses + the owner's manual leg; no CI/PHPUnit/JS tests. The lens also confirmed what is genuinely solid at the boundaries (license↔sanitizer same-save, license↔gate, gate-before-quota, quota↔webhook 2xx coupling, cron↔plan incl. quota neutrality, role×plan×command matrix).

1. **Permission callbacks untested** — `check_can_trigger()` (rest-api.php:85), `check_view_reviews()` (:75), the WP-auth half of `check_webhook_token()`: the epic swapped every route gate to `pqm_*` caps and no harness executes them (quota-harness calls `trigger_review_callback()` directly). A typo'd cap blocks Authors from free Review — or admits cap-less users — with the suite green. Also: the AJAX `quota_remaining ?? null` passthrough is unpinned. *Disposition: fix-now coverage — A6.*
2. **`PQM_Activator::maybe_upgrade()` untested** (activator.php:33-37 + init wiring): the one mechanism granting caps to zip-upgraded installs runs in no harness; an inverted comparison bricks every upgraded site's UI/API, suite green. *Disposition: fix-now coverage — A6.*
3. **Free-save `agent_prompts` smuggle unpinned** (admin.php:326): no sanitize-matrix case posts `agent_prompts` on a free-site save; a plausible "posted-else-stored" refactor would let a free-site Editor set Pro overrides and all cases stay green (`license_key` and `cron_batch_size` have exactly this smuggle case; `agent_prompts` doesn't). *Disposition: fix-now coverage — A6.*
4. **`render_posts()` / `render_dashboard()` gating uncovered** — render-matrix exercises only `render_settings()`; `$can_review`, the quota-note span, the pro-gated cron nudge and the banner gate run in no harness. *Disposition: coverage — A6.*
5. **Broken verification** — the "option save uses settings_page_capability" checks in render-matrix are `file_get_contents` + regex over source with `add_filter` stubbed to a no-op: the wiring is asserted as text, never executed. A regression that keeps the line but stops it running fails every non-admin save while the suite stays green. *Disposition: fix-now — replace with a recording `add_filter` stub — A6.*

Noted, not findings: the at-quota 429-before-404 ordering (cosmetic); "stored cron settings keep running on a free site" is a recorded, deliberately pinned decision.

### Lens — adversarial (bmad-review; 16 raw findings, triaged by the orchestrator against plans and recorded decisions)

- **(a1) Cron keeps running on free/lapsed sites and the Admin cannot disable it there** (cron.php:28-76 × the locked fieldset + carry rule): the *keeps-running* half is a recorded owner decision (epic note 2026-10-01); the *no-UI-to-disable* half is new and real — the Enable checkbox is inside the disabled fieldset. *Disposition: accepted decision + proposed improvement → A7 (owner choice: exempt the Enable toggle from the lock, or plan-gate the cron feature).*
- **(a2) "Story 1.6's gate guards a state that can't occur"** — false as to intent: `cron_command` is pinned today, but the 1.6 plan's frozen Intent names exactly the two futures it pre-wires (license lapse once backlog UV6 opens the command choice). *Disposition: false (recorded rationale); becomes true debt only if UV6 is dropped.*
- **(a3) Unknown commands fail open to free tier; AJAX/GET bypass the REST enum** (features.php free fallback × admin.php AJAX/GET building the request directly): pre-epic behavior deliberately preserved (story 1.1 Never), but the paid-leak-by-registry-drift scenario is unrecorded. *Disposition: defer → A8 (enforce the command whitelist at the chokepoint / registry ⊇ enum harness assert).*
- **(a4) `score`/`apply*` tier never explicitly decided** — quota consumption *was* decided (story 4 frozen Always); the tier side (free-site `apply` of a paid rewrite; 20/20 user stranded before applying) is unrecorded. *Disposition: defer → A8 (explicit registry entries or a documented decision).*
- **(a5) Non-atomic quota RMW** — previously rejected with cause at story 4's plan checkpoint and review ("harmless at human scale"); carried. *Disposition: carried rejection.*
- **(a6) Existing installs silently gain `author_quota = 20` on update** (defaults merge): the default is a spec decision, the silent flip on upgrade is not. *Disposition: surface to owner → A9 (release-notes/migration note; optionally default 0 for pre-existing options).*
- **(a7) `CAPS_VERSION` re-grant undoes role-editor removals and flips explicit denies** — the re-grant-on-reactivation is recorded as standard WP behavior (story 1.2); the explicit-deny flip on *upgrade* is an unrecorded nuance. *Disposition: record in memory; low → folded into A9's release-note thinking.*
- **(a8) Service-account/custom-role n8n credential users lose `/post-score` on upgrade** (cap swap; caps granted only to the three default roles): real, silent-403 migration risk. *Disposition: → A9 (release notes: "the n8n credential user must hold `pqm_view_reviews`").*
- **(a9) Paid sections gated by page access only** — a remapped `pqm_manage_settings`-only user can edit Overrides/cron although the README matrix assigns them `pqm_manage_agents`; consequence of 1.5's dynamic-capability review fix. *Disposition: defer → A10 (align code or README; owner picks which is right).*
- **(a10) README capability claims wrong** ("granted to Administrator/Editor/Author" for all five caps). *Disposition: fix-now docs → A10.*
- **(a11) `defaultModel` fallback duplicated inline** — duplication-map item 2; dies with the rebuild. *Disposition: accepted (ADR 0005).*
- **(a12) AJAX sends a success toast when n8n answers non-2xx** (admin.php ajax wrapper checks only `is_wp_error`, saves a processing row, `wp_send_json_success`): pre-existing gap the epic brushed against (quota passthrough in the same block) without closing. *Disposition: defer → bug ticket A11 (real user-facing defect).*
- **(a13) View-only remapped users see a quota counter they can never spend** (`is_quota_subject()` checks only `pqm_run_paid_agents`). *Disposition: defer to the rebuild spec (quota display logic).* 
- **(a14) Refusal/quota copy nits** — `ucfirst($command)` raw labels; quota-exceeded copy prints the limit twice (`%1$d of %1$d`), wrong when an admin lowers the limit mid-month. *Disposition: fix-now micro-copy → folded into A5.*
- **(a15) Multisite never considered** — `pqm_monthly_usage` is network-global user meta against a per-site setting; uninstall deletes network-wide. *Disposition: document single-site support → A10; revisit if multisite ever becomes a target.*
- **(a16) No CI runs the harnesses** — partially false (AGENTS.md documents the run-all loop as a session duty), but no automation exists. *Disposition: → A12 (GitHub Actions workflow: `php -l` + all harnesses).*

### Lens — edge-case hunter (bmad-review; 9 findings, triaged; overlap with adversarial noted as signal)

Overlapping (consolidated above): cron on free sites (a1), unknown-command default-allow + AJAX enum bypass (a3), quota RMW race (a5, carried rejection), service-account upgrade 403s (a8), deny/removal re-grant (a7), AJAX success-on-failure (a12). New, verified at source by the orchestrator 2026-10-02:

- **(e8) Pro-branch sanitize wipes stored settings on a section-less POST** (`class-pqm-admin.php:290-302+`): a pro-site save whose POST carries no cron/overrides keys (stale free-view form after another tab entered a key; direct options.php POST) recomputes `cron_enabled = !empty(absent) = false` and rebuilds `agent_prompts` to `[]` — stored per-agent overrides silently wiped. The free branch carries; the pro branch does not. *Disposition: defer → bug ticket (A11).* 
- **(e6) Uninstall never strips per-user `pqm_*` grants** (`WP_User::add_cap` is a supported remap path per the quota docblock; `remove_capabilities()` walks roles only). *Disposition: defer → bug ticket (A11).*
- **(e9) PHP quota note lacks the `$can_review` guard its JS twin has** (`class-pqm-admin.php:559-567` renders on `$quota !== null` alone) — a view-only remapped user sees actions they can never spend (sharpens a13). *Disposition: defer → rebuild spec note (A4) or micro-fix with A5.*
- Lens deletion-pass note: the removed JS early-returns were traced — no regression; the `edit_posts`/`manage_options` migration left no stale gates (grep: comments only).

## Behavior verification

Runtime behavior was exercised **by the owner on the real site** (story 1.8 walkthrough, 2026-10-02, recorded in its story file): free-site refusals in UI/REST/AJAX with no n8n execution; key entry unlocking all six commands end to end with a quiet template handshake; Author quota visible/consumed, Editor save containment, Contributor lockout; role-editor remap live in both directions. The agent exercised no runtime itself (no WP install in this environment — backlog ticket 9 exists to close that); deterministic behavior is pinned by the six harnesses (374 checks green at retro time). Phase 3 team discussion: skipped (opt-in, not requested).

## Previous-retro follow-through

No previous retrospective exists; this is the first epic completed under the BMad Method (ADR 0004, 2026-10-01). Nothing to follow through.

## Action items

All proposed, not applied — the owner decides what executes. Owner = vincentheimann unless the item is agent-executable on approval.

- **A1 — Record the CAP-2 stub decision as an ADR** *(agent drafts, owner accepts)*: ADR 0003 still reads as if Freemius ships; the stub decision lives only in code/memory/deferred-work. One short ADR ("license provider ships as a stub until Freemius").
- **A2 — Spec reconciliation** *(agent via `bmad-spec` update, owner approves)*: fold the accepted deviations into spec-freemium-and-roles + access-matrix — CAP-1 fallback/`ping`/cron-plan-half semantics, CAP-4 capability-keyed exemption + 0-unlimited + apply-consumes, CAP-3 `pqm_view_reviews` row, `/save-review` WP-auth cap, Editor Settings-page access.
- **A3 — Shared harness stub bootstrap** *(agent, when the next harness lands)*: extract the WP-stub world; normalize drift (error_reporting, exit expr, FAIL label, sanitize's globals naming).
- **A4 — Rebuild-spec inputs** *(agent, due in the queued rebuild `bmad-spec`)*: carry in the admin.php decomposition, the PHP↔JS single-render fixes (quota note, hidden rule, effective model), upgrade-copy centralization, quota-display logic (e9/a13).
- **A5 — Copy/i18n micro-fixes** *(agent, one small story)*: translate the REST success `message`; shared command→label map (kills `ucfirst`); quota-exceeded copy prints used vs limit.
- **A6 — Harness coverage pack** *(agent, one small story)*: permission callbacks (`check_can_trigger`/`check_view_reviews`/WP-auth half), `maybe_upgrade()` matrix, free-save `agent_prompts` smuggle case, `render_posts`/`render_dashboard` captures, recording `add_filter` stub replacing the regex check.
- **A7 — Free-site cron off-switch** *(owner decision, then small fix)*: exempt the Enable toggle from the locked fieldset, or plan-gate the cron feature itself — today a lapsed site keeps consuming scheduled runs with no UI to stop it.
- **A8 — Command whitelist + `score`/`apply` tier decision** *(owner decides tier; agent implements)*: enforce the enum at the chokepoint (AJAX/GET bypass it today; unknown commands fail open to free), add explicit registry entries or a recorded decision for `score`/`apply*`, plus a registry ⊇ enum harness assert.
- **A9 — Release-notes/migration pack for the next release** *(owner + agent)*: quota default 20 lands on existing installs silently (consider default 0 for pre-existing options); the n8n credential user must hold `pqm_view_reviews` (silent 403s otherwise); CAPS re-grant undoes role-editor removals and flips explicit denies on upgrade; optional migration grant for `edit_posts` roles. Joins the Freemius release-notes item already in deferred-work.md.
- **A10 — Docs truth pass** *(agent; owner picks code-vs-docs on the paid-sections capability)*: README capability→default-roles table (current claim wrong for 3 of 5 caps); align paid-sections access (`pqm_manage_settings`-only user can edit them, README says `pqm_manage_agents`); state single-site support (multisite quota meta is network-global).
- **A11 — Three bug tickets to backlog** *(agent files them)*: AJAX/GET report success + record a processing row when n8n answers non-2xx (verified admin.php:1409-1420); pro-branch sanitize wipes cron/overrides on a section-less POST (verified :290-302); uninstall leaves per-user `pqm_*` grants.
- **A12 — CI** *(agent)*: GitHub Actions workflow running `php -l` + the full harness sweep on push/PR — today the 374 checks run only when a session remembers to.

Process lessons (what would prevent the next ones): the strongest findings all sit at ticket boundaries or in migration paths — the spec never carried an "upgrade path" section (A9's class would have been caught at spec time); and verification debt concentrated where AGENTS.md assigns checks to the manual leg (A6's class) — the Local/WP-CLI environment (backlog 9) and CI (A12) are the structural fixes.

## Acceptance verdict

**accepted-with-open-items** (criteria: declared). All 8 tickets done (`pending_tickets` empty). The four declared Done-when lines are demonstrably met: owner's 1.8 walkthrough on the real site (2026-10-02, all green, recorded in the story file) plus the harness matrix (374 checks) and the lens confirmations of the boundary behaviors. No finding blocks a Done-when line; the open items are the tracked A1–A12 (spec/docs reconciliation, migration notes, coverage debt, three edge-path bugs). Machine verdict rendered interactively; owner confirmation of the verdict closes the epic via `bmad-ticket`.

## Open questions

1. A7 — which way does the owner want free-site cron handled (off-switch vs plan-gating the feature)?
2. A8 — what tier do `score`/`apply*` get (free, paid-following-their-agent, or quota-only as today)?
3. A9 — keep quota default 20 for existing installs, or default 0 when the stored option predates the key?
4. A10 — paid Settings sections: align code to the README (`pqm_manage_agents` required) or README to the code (either cap)?
