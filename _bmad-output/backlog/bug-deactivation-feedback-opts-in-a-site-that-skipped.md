---
id: 90
type: bug
title: "Deactivation feedback sends the admin's name and email from a site that skipped the opt-in"
parent: none
covers: [O9]
after: [89]
assignee: ""
refined: true
hitl: true
risk: medium
severity: P2
---

# Deactivation feedback sends the admin's name and email from a site that skipped the opt-in

## Description

An admin who skipped the Freemius opt-in, then deactivates the plugin and answers the feedback form without ticking "Anonymous feedback" (unticked by default), is opted in when the plugin is deleted: the SDK sends their first name, last name and email with the site's details and the reason. The privacy policy says the author receives nothing from a site unless its admin opts in or buys Pro, and WordPress.org guideline 7 asks for explicit consent before any outside contact. The form says nothing about sending a name or an email.

## Reproduction

Read in the vendored SDK 2.13.4 code (2026-10-24), re-read in 2.13.5 on main 02c9151 (2026-10-26): the same path; not yet run on a site.

1. A WordPress site with the Free build; at activation, click Skip on the Freemius opt-in.
2. Plugins → Deactivate. The feedback form opens. Pick a reason, type a line, leave "Anonymous feedback" unticked, and deactivate.
3. Delete the plugin.
4. Expected: nothing reaches Freemius, or only the anonymous reason. Actual, per the code: `Freemius::_uninstall_plugin_event()` finds a site that is not registered and a stored reason with `is_anonymous` false, and calls `opt_in( false, false, false, false, true )`. With `$is_uninstall` true, `get_opt_in_params()` fills `user_firstname`, `user_lastname` and `user_email` from the current WordPress user and sends them with the reason.
5. With "Anonymous feedback" ticked, the SDK sends only the reason, up to 128 characters of text and an md5 site id (`uninstall.json`), which the privacy text does not mention either.

## Cause Hypothesis

The SDK treats a non-anonymous deactivation answer as consent to opt in at uninstall. The plugin sets neither filter that changes this (`show_deactivation_feedback_form`, `default_to_anonymous_feedback`), and `docs/policies/privacy.md` was written from the opt-in screen alone.

## Acceptance Criteria

1. **Nothing leaves a site that skipped the opt-in without the admin knowing**
   **Given** a site that skipped the opt-in
   **When** the admin deactivates, answers the feedback form either way, and deletes the plugin
   **Then** either no name or email reaches Freemius, or the form says plainly what is sent before the admin submits, and `docs/policies/privacy.md` describes exactly what each choice sends
2. **Tests cover the condition found and fixed**
   **Given** the harness sweep
   **When** it runs
   **Then** a row fails on the current behaviour and passes on the fix, for both the ticked and the unticked box
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the Local sandbox with the Free build and the Freemius dashboard is read afterwards
   **Then** no user record or name appears for that site, with the evidence recorded in Notes — this supersedes 1 and 2

## References

- source — post-quality-manager/vendor/freemius/includes/class-freemius.php (`_uninstall_plugin_event()`, `opt_in()`, `get_opt_in_params()`); templates/forms/deactivation/form.php (the "Anonymous feedback" box)
- policy — docs/policies/privacy.md (the short version and "Optional usage data")
- found by — _bmad-output/backlog/spike-funnel-measurement-without-tracking-findings.md (finding 8)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (guideline 7)

## Notes

- Found 2026-10-24 by spike 64's research, confirmed by the session in the code; not yet run on a site.
- The person's part: the walk needs the Local sandbox running and a look at the Freemius dashboard afterwards.
- Open question: the fix may be a plugin filter, a copy change, a privacy text change, or a mix. The builder proposes it at the plan checkpoint; the owner chooses.
- Walked 2026-10-27 (agent, owner's request; showcase, Free build of main 8502d84, WP-CLI with a `--require` logger in the session scratchpad): path A (a skipped site) passed. The filter answered false and the form was empty; a deactivation and deletion as user 1 sent nothing; a planted pre-fix non-anonymous answer was stored at uninstall priority 8 and gone at 10, nothing sent; with the answer put back after the hook (the control), the SDK tried `POST wp.freemius.com/action/service/user/install/` with the admin's email (blocked by the logger): the bug's path. The owner's read-only dashboard check the same day: no user for either test address, no install for the showcase, no deactivation or uninstall.
- Walked 2026-10-28 (agent, owner hitl for the opt-in email; showcase, main 8502d84's Free build, opted in with hello@postquality.com, the confirmation clicked by the owner): plan 1's last check passed, the deactivation form shows on an opted-in site. Path C ("Communication" opted out on the Plugins screen, after answering the form while sharing): the site is not registered, no form at the next deactivation; at deletion as user 1 the stored answer was there at priority 8 and gone at 10, and Freemius received no request at all. Path B ("Diagnostic Info" opted out): registered, tracking off, no form; the answer gone at 10, and Freemius received one `PUT /v1/installs/<id>.json` with only `is_uninstalled: true` and `uid`, no reason, no name, no email. Deletion removed every table, option, user meta and capability of the plugin (the SDK keeps its own record of the site). The requests were logged, not blocked. Every path of both plans is walked; the bug is ready for the owner's done.
