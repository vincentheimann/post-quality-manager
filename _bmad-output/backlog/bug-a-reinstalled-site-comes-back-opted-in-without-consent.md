---
id: 135
type: bug
title: "A site that opted out of Freemius comes back opted in after a reinstall, without being asked"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: high
severity: P1
estimate: ""
---

# A site that opted out of Freemius comes back opted in after a reinstall, without being asked

## Description

A site owner who opted in to Freemius's usage data and later opted out of "Communication" (their name and email) expects the opt-out to hold. After they delete the plugin and install the same version again, the plugin activates as opted in and sharing usage data, with no opt-in screen and no consent click, and Freemius's record of the site still ends at their opt-out. The privacy policy says the opt-in is off until they say yes and can be changed with Opt In or Opt Out; WordPress.org's guideline 7 forbids tracking a site without its consent. Expected: after a reinstall, or a deactivation and reactivation, a site that opted out stays opted out until its administrator opts in again.

## Reproduction

1. Story 38's clean Local site (WordPress 6.6, PHP 8.1.29), the Free build of release v4.0.3 (Freemius SDK 2.13.5), a must-use request logger in place (story 38's Free opt-in frame, 2026-11-17).
2. Activate the plugin and opt in on Freemius's screen ("Never miss an important update" → "Allow & Continue"), then confirm the email Freemius sends.
3. On the Plugins screen, under the plugin: "Opt Out" → in the dialog, "Opt Out" beside COMMUNICATION → confirm "Opt Out". The SDK reads not registered and anonymous; the row reads "Opt In | Deactivate".
4. Deactivate the plugin (no feedback form shows), then delete it as an administrator (no request reaches Freemius).
5. Install the same 4.0.3 zip again and activate it from the Plugins screen.
6. Actual: no opt-in screen; the plugin's Dashboard opens; the Plugins row reads "Opt Out | Deactivate" and the "Account" menu item is back; the SDK reads `is_registered` true, `is_anonymous` false, `is_tracking_allowed` true, and the plugin's `shares_usage_data()` is true. The Freemius dashboard shows no new opt-in event for the site: its last consent event is the "Communication" opt-out (`install.user.opt_out`). Expected: the site stays opted out (or the opt-in screen shows again), and nothing is shared until the administrator opts in.

Also seen in the same frame: after a "Diagnostic Info" opt-out and a deletion, the reinstall came back registered with tracking still off and no opt-in screen. Not walked: a plain deactivate and reactivate of the same version, without deleting.

Evidence: story 38's plan, Implementation Notes "The Free opt-in frame (2026-11-17 …)", and its stage results in that session's scratchpad (not committed).

## Cause Hypothesis

Two SDK behaviours combine (SDK 2.13.5, read 2026-11-17). Deleting the plugin leaves the SDK's own record of the site in the `fs_accounts` option (`_uninstall_plugin_event()` deletes nothing, `class-freemius.php` L9773; `memory/freemium-and-roles.md`). On activation, `_activate_plugin_event_hook()` calls `reset_anonymous_mode()` when the same plugin version is activated again (L7487–7513, the "Reset skipped click cache" block), and with the kept site record `is_registered()` reads true again, so the SDK treats the site as opted in. The plugin itself writes no consent state; it reads the SDK's (`PostQuality_License::shares_usage_data()`). Whether a plain deactivate and reactivate takes the same path (the hook runs on every activation) is to be confirmed; whether the reset also applies to a site that only skipped the opt-in is to be checked (the block resets the "skipped" flag, which would only bring the opt-in screen back).

## Acceptance Criteria

1. **An opt-out survives a reinstall**
   **Given** a site that opted in and then opted out of Communication, of Diagnostic Info, or of both, on the Plugins screen
   **When** the same version is deleted and installed again, or deactivated and activated again
   **Then** the site's sharing state is the one the administrator last chose, and nothing beyond what that choice allows reaches Freemius before the administrator opts in again
2. **A skipped opt-in stays skipped or asks again**
   **Given** a site that skipped the opt-in
   **When** the same version is reactivated, or deleted and reinstalled
   **Then** it either stays skipped or shows the opt-in screen again; it never becomes opted in without the administrator's click
3. **Opting in again still works**
   **Given** a site from criterion 1 or 2
   **When** its administrator opts in again (the opt-in screen, or the Plugins row's "Opt In")
   **Then** the site is opted in and sharing, as on a first opt-in
4. **Tests cover the condition found and fixed**
   **Given** the harness suite
   **When** it runs
   **Then** a test that fails on the defect and passes on the fix covers the reinstall after a Communication opt-out, and one covers each other path the fix touches; the Free build check and the Free build harness still pass
5. **Walked before submission**
   **Given** the fix in a 4.0.x release
   **When** story 38's opt-in frame steps 2–6 are walked again on WordPress 6.6, with the owner's consents and a read-only Freemius dashboard read
   **Then** the reinstall comes back opted out and the dashboard shows no data for the site after the opt-out until a new opt-in
6. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the site stays opted out, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 5

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md, Implementation Notes "The Free opt-in frame (2026-11-17 …)" (the order of the frame, the booleans, the dashboard's events and times)
- facts — memory/freemium-and-roles.md ("What a deletion leaves on the site", "A reinstall comes back with the earlier consent", "Freemius dialogs at a glance")
- the promise — docs/policies/privacy.md (the "Optional usage data" paragraph)
- related — _bmad-output/backlog/story-privacy-policy-says-what-deletion-leaves-of-the-freemius-sdk.md (story 127: one sentence on what deletion leaves of Freemius's record)
- earlier consent bug — _bmad-output/backlog/bug-deactivation-feedback-opts-in-a-site-that-skipped.md (bug 90: the deactivation answer sent from a site that had not consented)
- licensing decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md
- blocks — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38; owner, 2026-11-17: fixed before submission)

## Notes

- Decision (owner, 2026-11-17): a privacy bug that blocks story 38's submission; it joins story 38's `after`.
- Fact: the vendored SDK stays unmodified (AGENTS.md, ADR 0014's build markers rely on it); a fix lives in the plugin's own code, uses the SDK's public hooks and filters, or goes to Freemius upstream, or a newer SDK release.
- Fact: the plugin already leaves the SDK's `fs_accounts` alone at deletion on purpose, and the SDK's own clean-up (`delete_account_event()`) sends `DELETE` to Freemius's API (owner, 2026-11-11; `memory/freemium-and-roles.md`).
- Open question: which data reaches Freemius in the reset state before the administrator acts (the next install sync, its timing, its fields), and whether the user's name and email are among them. Walk it with the request logger before choosing a fix; it decides the severity, which the owner may change.
- Open question: whether a plain deactivate and reactivate takes the same path (criterion 1's second case).
- Open question: the fix's direction: a plugin-side guard on activation that keeps the last choice, clearing the SDK's site record at deletion (which has the `DELETE` cost above), reporting it to Freemius, or a combination; the plan settles it with the owner.
- Assumption: hitl true. The owner gives every consent and reads the Freemius dashboard, as in story 38's opt-in frame (criterion 5).
- Assumption: severity P1 (proposed): a consent the administrator withdrew is re-armed without asking, against the privacy policy and the directory's guideline 7; there is no workaround a site owner would know. Risk high: consent state, a vendored SDK, and data that leaves the site; the check outside the ticket's own criteria is the owner's read of the Freemius dashboard (criterion 5).
- Handoff: story 127 adds a policy sentence about what deletion leaves; if this bug's fix changes what deletion leaves, story 127's sentence follows it.
