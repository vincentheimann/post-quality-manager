---
id: 65
type: story
title: "A site moving to a Freemius build, or from Free to Pro, gets a notice that matches what it needs"
parent: none
covers: [O2, O9]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# A site moving to a Freemius build, or from Free to Pro, gets a notice that matches what it needs

## Description

The Freemius SDK shows its own notice, "We made a few tweaks to the plugin, Opt in to make "Post Quality Manager" better!", on any install it treats as an update rather than a new install, while it waits for an opt-in or a license. Two paths reach it: a site that ran a build without Freemius and updates to one with it, and a Free site that installs and activates Pro. On the owner's test site, after Free → Pro, the notice stayed after the opt-in and its link only opened the Dashboard, while Pro sat unlicensed. A paying customer who needs to activate a license is asked to opt in instead. This story walks the three upgrade paths on Local with the Freemius sandbox, then makes the notice match what the site needs or removes it when nothing is left to do. The walks need the owner (sandbox checkout, license key, opt-in email).

## Acceptance Criteria

1. **The three upgrade paths are walked and recorded**
   **Given** a Local site in each starting state: a build without Freemius updated to the current build; Free opted in, then Pro installed and activated; Free skipped, then Pro installed and activated
   **When** an Administrator loads WP Admin and follows every Freemius notice shown
   **Then** the plan records, per path, the notice text, where its link lands, and the opt-in and license state afterwards
2. **An unlicensed Pro site is asked for its license**
   **Given** Pro active without a license on a site where Free ran before
   **When** an Administrator loads WP Admin
   **Then** the notice asks for the Pro license, not an opt-in, and its link opens the license activation screen
3. **No opt-in prompt once there is nothing to opt in to**
   **Given** a site that has opted in, skipped, or activated a license
   **When** an Administrator loads any WP Admin page
   **Then** no notice asks it to opt in, including one stored before that choice, without the user dismissing it
4. **The notice's link always leads to the action it names**
   **Given** any notice this story leaves in place
   **When** an Administrator clicks its link
   **Then** the screen that opens offers that action (opt in, skip, or enter the license), never the plain Dashboard
5. **A path the walk proves fine stays as it is**
   **Given** a path where criterion 1's record shows the SDK's notice and link already match the site's state
   **When** this story is done
   **Then** the plugin changes nothing for that path, and the plan says so with the record as evidence
6. **Existing sites are told**
   **Given** a change to what an existing site sees
   **When** this story merges
   **Then** `docs/release-notes/unreleased.md` carries one line on it

## Boundaries

- Must not change: what is sent to Freemius and when (nothing before the opt-in choice, ADR 0013 D8); the opt-in screen at first activation; the vendored SDK, which stays unmodified.
- Out of scope: the brand voice of the other Freemius screens and emails (story 62); the Upgrade links on the Account page (bug 59); dual-activation logic between the two builds (story 54).

## References

- parent — none
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8 opt-in)
- decision — docs/adr/0014-two-builds-from-one-source.md (two builds, Free → Pro by installing the Pro zip)
- data — docs/distribution.md, row Opt-in screen
- copy — docs/brand.md
- licensing — _bmad-output/backlog/story-real-license-unlocks-pro.md (44: the sandbox, and its open Local pass)
- copy — _bmad-output/backlog/story-freemius-surfaces-carry-the-brand-copy.md (62: wording of the other SDK strings)

## Notes

- Filed 2026-10-14 (owner), from a notice seen on the owner's test site: Pro 3.5.1 active, opted in, no license; Free 3.6.0 installed and inactive. Its link opened the Dashboard with nothing to do.
- Finding (agent, 2026-10-14, SDK 2.13.4 source): the notice is the SDK's `connect_account` sticky notice, added only when the install is not new and the SDK is in activation mode. Activating the Pro build without a license puts the site in activation mode, so Free → Pro customers meet the opt-in wording. The single-site opt-in path (`setup_account()`) does not remove the stored notice, so it can outlive the opt-in, which matches what the owner saw.
- Finding (agent, 2026-10-14): levers that leave `vendor/freemius` unmodified exist: the filter `sticky_message_connect_account` changes the text, and the public `remove_sticky()` on `pqm_fs()` clears it. Which one each path needs is the walk's output (criterion 1), not a choice made here.
- Decision (owner, 2026-10-14): lands before story 38; added to 38's `after`.
- Assumption: criterion 1 runs on Local by hand (the owner, sandbox license); if story 9's WP-CLI environment is ready first, an agent can replay paths 1 and 3 from it.
- Risk low: admin notice text and its removal, visible at once, revert clean.
