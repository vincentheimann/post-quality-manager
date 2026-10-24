---
id: 61
type: story
title: "The plugin's upgrade and engagement moments nudge an interested user without nagging"
parent: none
covers: [O9]
after: [59, 60]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The plugin's upgrade and engagement moments nudge an interested user without nagging

## Description

The site gathers users; the plugin is where their interest shows. Today the Free build carries one "Get Pro" link in Settings and the locked-section notes, written one story at a time. This story reviews the moments where a Free user meets Pro, or where a user of either build could stall, and gives each one copy and a next step written by the copywriting skill (story 60) with conversion best practices: a nudge where the value is visible, one primary action, a reason, and a way to dismiss it. It stays inside the WordPress.org guidelines (no persistent or repeated notices, no notice outside the plugin's own screens) and `docs/brand.md` (no accusatory or patronizing copy). The owner approves the list of moments before any is built (hitl).

Candidate moments, to keep or drop at refinement: the welcome notice and the end of the setup guide; the first review result (the aha moment, A5); a locked Pro section or a paid command on the Free build; the Dashboard's empty and idle states; the month a Free author reaches the quota; the Pro license lapse; the Account page.

## Acceptance Criteria

1. **Every nudge is placed where its value shows**
   **Given** the approved list of moments
   **When** a user meets one
   **Then** the copy names what Pro, or the next step, gives them at that point, offers one primary action that works (story 59 for the checkout), and nothing else competes with it on the screen
2. **Dismissible, once**
   **Given** a nudge the user dismisses or acts on
   **When** they return to that screen
   **Then** it does not show again for that user on that site; a nudge never repeats on a schedule or appears on screens the plugin does not own
3. **Directory and brand rules hold**
   **Given** the Free build
   **When** its screens are reviewed against the WordPress.org guidelines on notices and upsells and against `docs/brand.md`
   **Then** no finding remains; the "Pro" word count on the Free Settings page stays within what story 54's harness asserts, or the harness is updated with the reason
4. **Both builds, every role**
   **Given** a user without `pqm_manage_settings`
   **When** they meet a moment that needs an Administrator (a license, a setting)
   **Then** the copy tells them to ask an administrator, never names a step they cannot take (ticket 32's rule)
5. **Owner-verified**
   **Given** the built moments
   **When** the owner walks them on the environment they name (hitl)
   **Then** each reads as intended and the walk is recorded in the plan with that environment

## Boundaries

- Must not change: the feature gate and the role map (ADR 0003, CAP-6); what Free and Pro contain; the Settings sanitizer; the setup guide's steps.
- Out of scope: the public site (story 57), the Freemius surfaces (story 62), emails (story 63), measuring the nudges (spike 64), any dark pattern (fake urgency, hidden dismiss, pre-checked choices).

## References

- parent — none
- copy — docs/brand.md (Copy Rules); the copywriting skill of story 60
- gate — memory/freemium-and-roles.md (hidden vs locked, the one Get Pro link, the Free-build "Pro" count check)
- journey — docs/user-paths.md (A5 the aha moment, A6 habit formation, B2 triage)
- checkout — _bmad-output/backlog/bug-upgrade-links-lead-nowhere.md (59)
- rule — _bmad-output/backlog/bug-failed-start-copy-names-a-step-non-admins-cannot-take.md (ticket 32: next steps per role)

## Notes

- Filed 2026-10-13 (owner: "gather users and nudge those with interest").
- Risk medium: notices and dismissal state are shared code across screens and roles, and a wrong notice on the Free build can fail WordPress.org review (story 38). Check outside the criteria: the Plugin Check run in CI and story 38's submission review.
- Assumption: dismissal is stored per user in user meta, like WordPress's own pointers; no new option in `pqm_settings`.
- Open question: whether the first-review moment nudges toward Pro at all, or only toward the next review; the owner decides from the persona.
- Input (2026-10-24, spike 64, ADR 0016): the moments are read as total upgrades per period in Freemius, before and after; no per-moment figure (no coupon per entry point, nothing sent from the plugin).
