---
id: 65
type: story
title: "A site moving to a Freemius build, or from Free to Pro, gets a notice that matches what it needs"
parent: none
covers: [O2, O9]
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# A site moving to a Freemius build, or from Free to Pro, gets a notice that matches what it needs

## Description

The Freemius SDK shows its own notice, "We made a few tweaks to the plugin, Opt in to make "Post Quality Manager" better!", on any install it treats as an update rather than a new install, while it waits for an opt-in or a license. Two paths reach it: a site that ran a build without Freemius and updates to one with it, and a Free site that installs and activates Pro. On the owner's test site, after Free → Pro, the notice stayed after the opt-in and its link only opened the Dashboard, while Pro sat unlicensed. A paying customer who needs to activate a license is asked to opt in instead. This story walks the three upgrade paths on the showcase site with the Freemius sandbox, then makes the notice match what the site needs or removes it when nothing is left to do. An agent runs the walks; the owner confirms the opt-in email, buys the sandbox license and types its key (hitl).

## Acceptance Criteria

1. **The three upgrade paths are walked and recorded**
   **Given** the showcase site in each starting state, restored from its backup between paths: a build without Freemius updated to the current build; Free opted in, then Pro installed and activated; Free skipped, then Pro installed and activated
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
- walk — docs/local-verification.md (backup, builds, restore: the frame every path runs in)
- copy — _bmad-output/backlog/story-freemius-surfaces-carry-the-brand-copy.md (62: wording of the other SDK strings)

## Notes

- Filed 2026-10-14 (owner), from a notice seen on the owner's test site: Pro 3.5.1 active, opted in, no license; Free 3.6.0 installed and inactive. Its link opened the Dashboard with nothing to do.
- Finding (agent, 2026-10-14, SDK 2.13.4 source): the notice is the SDK's `connect_account` sticky notice, added only when the install is not new and the SDK is in activation mode. Activating the Pro build without a license puts the site in activation mode, so Free → Pro customers meet the opt-in wording. Its claim that the single-site opt-in (`setup_account()`) leaves the stored notice was wrong (agent, 2026-10-28, re-read in SDK 2.13.4 and 2.13.5, the same code): every opt-in goes through `setup_network_account()`, which removes `connect_account`. Why the notice outlived the owner's opt-in is unexplained; criterion 1's record is where it shows.
- Finding (agent, 2026-10-14; re-read 2026-10-28 in SDK 2.13.5): levers that leave `vendor/freemius` unmodified exist. The filter `sticky_message_connect_account` changes the text of a notice added after the plugin registers it (the SDK applies it inside `add_sticky()`, then stores the HTML), not of one already stored; the public `remove_sticky()` on `postquality_fs()` (the name since story 88) clears a stored one; the filter `connect_url` changes where the SDK's activation link points. Which one each path needs is the walk's output (criterion 1), not a choice made here.
- Finding (agent, 2026-10-28, read, not walked): a stored notice keeps the text and link it was added with. The link targets the SDK's menu page, `postquality-dashboard` since story 88 (`pqm-dashboard` before), so a notice a test build stored before story 88 points at a page that no longer exists. The SDK shows its opt-in or license screen on that page only while the site is in activation mode; otherwise the page is the plain Dashboard, which would explain where the owner's click landed.
- Decision (owner, 2026-10-14): lands before story 38; added to 38's `after`.
- Decision (owner, 2026-10-28; replaces the 2026-10-14 assumption that the owner walks criterion 1 by hand): an agent walks all three paths on the showcase site in the frame of `docs/local-verification.md`, WP-CLI for the state, a headless browser for each admin screen and notice link. The owner's steps: confirm the opt-in email, buy the sandbox license through the sandbox checkout, type its key on the SDK screen, and add the sandbox dev-mode constants to the showcase's `wp-config.php` (the restore puts the file back). The opt-in and the purchase use hello@postquality.com.
- Decision (owner, 2026-10-28, refinement approved): path 1 starts from the v3.3.0 release zip, the last build without the SDK; "the current build" is main's, the Free build from `bin/build-free.php` and the Pro build from `npm run zip` with its root folder renamed `post-quality-manager-premium/`, the name Freemius gives the Pro download (3.9.0 release artifact, read 2026-10-28), so installing Pro adds a second plugin as it does for a customer.
- Cleanup (owner rule 2026-10-20, backlog chore 81): what the walk creates in Freemius (the user for hello@postquality.com if it is new, the showcase's install, the sandbox license) is listed in the plan and removed in the Freemius dashboard when the walk ends; the owner's own user and production install are never touched.
- Risk low: admin notice text and its removal, visible at once, revert clean.
- Done (owner, 2026-10-28): merged in PR #201; before the merge the agent walked paths 1 to 3 and the variants 1b, 2b and 3b on the showcase site (plan Implementation Notes).
