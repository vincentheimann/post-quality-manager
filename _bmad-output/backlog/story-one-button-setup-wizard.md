---
id: 55
type: story
title: "One button sets up the whole plugin: the setup wizard continues past n8n into profile, standards and schedule"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
---

# One button sets up the whole plugin: the setup wizard continues past n8n into profile, standards and schedule

## Description

Owner request (2026-10-12). The guided setup dialog (story 36) carries a novice from nothing to the first review — n8n, credentials, connection, one review — and then only points at the Blog Profile and scheduled reviews. The configuration that makes the system theirs still happens alone on the Settings page, right when motivation is highest (user paths A5→A6, wizard note 4). This story grows the same one button into a setup wizard for the whole plugin: after the first review, the wizard walks the user through the Blog Profile, the Editorial Standards and scheduled reviews, each prefilled and skippable — at first install through the welcome notice, or triggered again any time after. The goal is the one story 36 set: one guided path from install to a working, configured system.

## Acceptance Criteria

1. **Still one entry point**
   **Given** a site whose setup is not complete
   **When** a user who may manage the plugin's settings opens any plugin page
   **Then** the existing welcome notice and the existing setup button open the wizard — no second button, no second notice — and users without that right see neither

2. **The wizard continues past the first review**
   **Given** the wizard's first review has landed, or the site already has one
   **When** the user moves on
   **Then** the wizard offers one step each for the Blog Profile, the Editorial Standards and scheduled reviews, in that order, each showing the current choice, each with its one-line reason why it matters, each skippable, each marked done — replacing the closing links story 36 left there

3. **A choice lands where Settings would put it**
   **Given** a configuration step
   **When** the user picks a value and moves on
   **Then** the stored settings change exactly as a Settings-page save of that field would change them, every other setting stays as it was, and the Settings page shows the choice afterwards

4. **Skipping everything leaves a working site**
   **Given** a user who skips all configuration steps
   **When** the wizard closes
   **Then** the site behaves as before the wizard opened — defaults in place, and setup still counts as complete from the first review, as today

5. **Triggered after, nothing moves silently**
   **Given** a site whose setup is complete
   **When** the user reopens the wizard and walks any step without changing a choice
   **Then** the stored settings, the webhook URL and the token are exactly as before

6. **Resumable, configuration steps included**
   **Given** a user who closes the wizard mid-configuration
   **When** they return, even another day
   **Then** the wizard reopens at the first unfinished step, the new steps counted like the story 36 ones

## Boundaries

- Must not change: the n8n connection steps and what each verifies (story 36); the stored settings contract and how the Settings form saves; what counts as setup complete (the first review — configuration stays optional); who may run reviews; the quota.
- Out of scope: provisioning n8n through its API (a possible later ticket); the Roles tab (story 42 owns it); the AI model picker; usage limits; a pull-back digest (UV3); screenshots or images (owner decision 2026-10-07, story 36).

## References

- adjacent backlog — _bmad-output/backlog/story-guided-setup-dialog.md (done: the dialog this grows), _bmad-output/backlog/story-workflow-file-novice-path.md (story 40: the static reference section stays the fallback)
- requirement — docs/user-paths.md, A5–A6 and "Notes for the future setup wizard (Path A)", note (4)
- decision — docs/adr/0005-wordpress-components-for-app-screens.md
- copy — docs/brand.md, Copy Rules and UX Writing Examples

## Notes

- Decision (owner, 2026-10-12): confirmed — "the one-button setup for n8n" is the story 36 dialog; this ticket grows that same button and dialog into the whole-plugin wizard rather than adding a second wizard beside it. API provisioning stays out of scope here and lands in the ADR 0015 epic afterwards.
- Assumption: the configuration steps are Blog Profile, Editorial Standards and scheduled reviews — the three the story 36 dialog already points at. Roles and the AI model stay on the Settings page.
- Decision (owner-delegated, 2026-10-12): the schedule step may enable scheduled reviews in one click with the default knobs (option a), for a pro site and a `pqm_manage_agents` user; the step names the knobs it sets and links to Automation for tuning.
- Decision (owner, 2026-10-12): this story builds before the n8n provisioning epic (ADR 0015) — both touch the setup wizard files; the epic reworks the connection steps on top of this story's result.
- Risk medium (reason): the wizard writes settings outside the Settings form, where a wrong merge can reset neighbouring fields — the shape of the stale-post wipe bug; a mistake there slips past a quick look.
