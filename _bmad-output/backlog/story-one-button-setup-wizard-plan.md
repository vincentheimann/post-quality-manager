---
title: 'One button sets up the whole plugin: the setup wizard continues past n8n into profile, standards and schedule'
type: 'feature'
ticket: '55'
created: '2026-10-12'
status: ready-for-dev
route: 'full'
route_source: 'auto'
review: ''
review_source: ''
lenses_ran: []
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/memory/admin-ui-rebuild.md']
---
<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The setup dialog (story 36) ends at the first review and only links to two Settings anchors for the Blog Profile and scheduled reviews; the configuration that makes the system the blogger's own happens alone on the Settings page, right at the motivation peak (user paths A5→A6, wizard note 4).

**Approach:** Three new steps after the first review — Blog Profile, Editorial Standards, scheduled reviews — inside the same dialog, same button, same welcome notice, same per-site progress option. Each step shows the stored choice, follows the Settings control model (a preset is a read-only choice, Custom is the one editable path), saves its fields alone through a whitelisted merge-save, and can be skipped without writing anything. The FINISH panel keeps only "Open Post Reviews"; its Blog Profile and scheduled-reviews links become these steps.

## Boundaries & Constraints

**Always:** UI from `@wordpress/components` + `@wordpress/i18n`; copy per docs/brand.md. All setup routes stay `pqm_manage_settings`. Settings writes are a single-key(-set) merge-save that sanitizes in place and leaves every other `pqm_settings` key byte-identical; unknown keys are refused. Cron fields are written only for a user with `pqm_manage_agents` on a pro site — the wizard never widens what `sanitize_settings()` would allow that user. Setup completion stays the first review; the config steps never gate it. Existing step keys, the progress-option shape and the n8n steps stay as they are (additive change). Skipping is the default path and leaves defaults in place.

**Never:** A second wizard, button or notice. Changing the n8n connection steps, `sanitize_settings()`, the Settings form, or what counts as complete. Pre-filling the Custom textarea from a preset. Roles, AI model, author voice (story 37), usage limits, n8n API provisioning, screenshots.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Continue past review | first review landed (or already complete) | Profile, Standards, Schedule steps follow, each prefilled with the stored choice, each with its one-line why | — |
| Pick a preset | profile step, preset chosen | `blog_profile` saved alone, preset text shown read-only, step done; Settings page shows it | save failure named, step not done |
| Choose Custom | profile or standards step | textarea starts from the stored custom text, never from a preset | — |
| Skip a step | any config step | Nothing written, step marked done, wizard moves on | — |
| Skip everything | all config steps skipped | `pqm_settings` unchanged; completion as today | — |
| Re-run, no change | complete site, steps walked without picking | settings, webhook URL and token byte-identical | — |
| Schedule, pro + manage_agents | schedule step | per Open Question 1 (one-click enable vs handover) | — |
| Schedule, free site | schedule step | says scheduled reviews are Pro, links; no write | — |
| Schedule, no `pqm_manage_agents` | manager without agents cap | step explains and links to Automation; REST refuses cron keys | 403 |
| Unknown settings key | `POST /setup/settings` with a stray key | refused, nothing stored | 400 |
| Non-manager | any route | 403; no button, no notice | 403 |
| Resume mid-config | close, return later | dialog reopens at first unfinished step, config steps counted | — |
| Legacy complete site | reopen "Setup guide" | config steps open and prefilled, nothing auto-written | — |

</frozen-after-approval>

## Code Map

- `src/setup/steps.js:513-559` -- `STEPS = [{key, title, tickable, Body}]`, current order ends `connect`, `review`; append `profile`, `standards`, `schedule`. `NextSteps` L562-608: drop the `#pqm-context-settings` and `#pqm-cron-settings` links, keep Open Post Reviews.
- `src/setup/index.js` -- `resumeAt()` L42-48 (first unfinished step / FINISH when complete — must count the new steps even when `complete` is true on re-run: resume logic reads steps first), `save({step,done})` L91-94 + 270-272, button L149-158, completion wiring L252-258; `?pqm_setup=1` auto-open.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- routes L108-134 (`check_manage_settings`), `SETUP_STEPS` L931 / `SETUP_TICKABLE` L934 (add the three keys — they are tickable), `setup_state()` L951-985 (add current `blog_profile`, `website_context`, `brand_voice_preset`, `brand_voice`, cron knobs, `can_manage_agents`, `is_pro`), `update_setup_callback` L996-1016, `connect` L1027-1062. New `POST /setup/settings`: whitelisted keys, per-key sanitize in place, merge-save; cron keys additionally gated.
- `post-quality-manager/admin/class-pqm-admin.php:1493-1512` -- `save_webhook_url()` is the merge-save pattern (`get_option` → set keys → `update_option`, which triggers the cron reschedule via `on_settings_updated`); `blog_profiles()` L270-302 (the presets, with their texts).
- `post-quality-manager/post-quality-manager.php` -- defaults L134-168, `pqm_voice_presets()` L322, resolvers `pqm_resolved_voice()`/`pqm_resolved_context()` L362-383, `PQM_SETUP_OPTION` + `pqm_setup_complete()` L555-571 (untouched).
- `post-quality-manager/includes/class-pqm-cron.php:34-68` -- consumes `cron_enabled`, `cron_frequency`, `cron_batch_size`, `cron_min_score`, `cron_max_age_days`, `cron_command`; reschedule is automatic on option update.
- `sanitize_settings()` (`class-pqm-admin.php:335-426`) is the capability reference: profile/voice keys are the `pqm_manage_settings` section; cron is `pqm_manage_agents` + pro (free-site off-switch exception L421-424). The new route mirrors these gates, it does not call the sanitizer.
- `tests/harness/setup-harness.php` -- route registration, permissions, state fields, whitelist, connect, uninstall; extend. `tests/js/setup-connect.test.js` -- island-mount pattern for a new test.
- Docs: `docs/release-notes/unreleased.md` (one note: the guide now offers these steps), `docs/user-paths.md` A6.

## Tasks & Acceptance

**Execution:**
- [ ] `post-quality-manager/includes/class-pqm-rest-api.php` -- three new step keys; `setup_state()` carries the current values and the two capability flags; `POST /setup/settings` with key whitelist, in-place sanitize, merge-save, cron gate (`pqm_manage_agents` + pro) -- criteria land where Settings would put them; matrix 403/400 rows.
- [ ] `src/setup/steps.js` (+ new step bodies, `src/setup/index.js` resume) -- the three steps with prefill, read-only preset text, Custom textarea, Skip; FINISH panel trimmed; resume counts them -- ticket criteria 2, 4, 6.
- [ ] `tests/harness/setup-harness.php` -- rows: whitelist refusal, merge isolation (untouched keys byte-identical), cron cap/pro refusal, new state fields.
- [ ] `tests/js/` -- one test: Skip writes nothing and advances; prefill shows the stored choice (browser-only rows).
- [ ] `docs/release-notes/unreleased.md`, `docs/user-paths.md` -- the upgrade note; A6 gains the wizard's offer.

**Acceptance Criteria:**
- Given a configured site, when the wizard is rerun and every step walked without a pick, then `get_option('pqm_settings')` is byte-identical before and after.
- Given a pick in any config step, when the Settings page loads afterwards, then it shows that choice, and `npm run build`, `npm run lint:js`, `npm run test:unit` and every harness pass.

## Implementation Notes

## Plan Change Log

## Review Triage Log

## Design Notes

- One new route (`/setup/settings`), not one per field, mirroring `/setup/connect`: one home for "the wizard writes settings". It never calls `sanitize_settings()` (the carry rule assumes a full form post); it sanitizes its few keys in place, like `save_webhook_url()`.
- Step values ride `GET /setup` — no second fetch, and the dialog stays one island.
- Skipped: a settings-change confirmation (the step's Save is the commit point); migrating progress for sites that finished the story 36 wizard — the new steps simply read as open when the guide is reopened, and completion never depended on them.

## Open Questions

1. **Schedule step behavior** — may it enable scheduled reviews in one click?
   - (a) One-click enable with the stored/default knobs (daily, batch 5, min score 60, 30 days, review) for a pro site + `pqm_manage_agents` user; the step names the knobs it sets and links to Automation for tuning. Means: strongest A6 conversion, one new cron write surface (gated as above).
   - (b) Hand over only: the step explains why schedules matter and links to the Automation tab. Means: zero new cron write surface, weaker conversion; the step is copy + a link.
   (The ticket's Notes carry the same question; answering it here settles both.)

   **Decision (owner-delegated, 2026-10-12): (a)** — one-click enable with the named default knobs, pro + `pqm_manage_agents` only; the step names what it sets and links to Automation.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass, new rows included.
- `php -l` on each touched PHP file; `npm run lint:js`; `npm run test:unit`; `npm run build` -- expected: clean, no new dependency in `index.asset.php`.

**Manual checks:**
- On Local: finish a fresh setup, walk the three steps (pick, skip, custom), confirm the Settings page mirrors each pick and a no-change walk leaves settings untouched; free site shows the Pro wording on the schedule step.
