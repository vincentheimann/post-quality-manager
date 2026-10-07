---
id: 36
type: story
title: "A guided setup dialog walks a novice through connecting n8n, step by step"
parent: none
covers: [UV4]
after: [35]
assignee: ""
refined: true
hitl: true
risk: medium
---

# A guided setup dialog walks a novice through connecting n8n, step by step

## Description

Owner request (2026-10-07). The n8n Workflow page offers a four-step checklist, but a blogger who has never opened n8n does not know where the import menu is, what a credential is, which node takes which one, or what "production URL" means; they stall at the friction wall (user paths, A4). This story replaces the checklist with a "Set up the workflow" button that opens a dialog guiding the user through every step in order, from getting an n8n instance to the first review landing in the plugin. Each step says exactly what to click in n8n and what to copy, offers a Copy button for every value, and is marked done; the dialog remembers where the user stopped. The plugin verifies the steps it can see (the connection, the first result); the others the user ticks off. The person's step (hitl): the owner walks the whole dialog on a real n8n Cloud instance and confirms every instruction matches what n8n shows.

## Acceptance Criteria

1. **One entry point until setup is done**
   **Given** a user who may manage the plugin's settings, on a site whose setup is not complete
   **When** they open any plugin page
   **Then** a notice offers "Set up the workflow", the n8n Workflow page shows the same button, the old inline checklist is gone, and the button opens the dialog; users without that right see neither
2. **Every step is spelled out and resumable**
   **Given** the dialog is open
   **When** the user moves through it
   **Then** the steps come in this order, each with the exact n8n menu items and fields named, a Copy button on every value to paste into n8n, and a Done mark: get an n8n instance (Cloud or self-hosted, with the reachability prerequisite); import the workflow (by URL or by file); create the three credentials; link each credential to its node; save, activate and copy the production webhook URL; paste the URL here; run the first review
   **And** closing the dialog, reloading or returning another day reopens it at the first unfinished step
3. **Credentials come with their exact fields and their node**
   **Given** the credentials step
   **When** the user reads it
   **Then** each of the three credentials shows its n8n type, its field names and values (the token with Copy), and the node it belongs to
   **And** for the WordPress one, a button creates an Application Password for the signed-in user, named for this plugin, shown once with Copy; where WordPress does not allow Application Passwords (no HTTPS, no local environment), the step says so and what to do instead
4. **The connection step proves what it can**
   **Given** the paste-the-URL step
   **When** the user saves a production URL
   **Then** the URL is saved and pinged in place, and success says what the ping proved: this site reaches n8n and the webhook credential is right; a test URL is refused with the warning
5. **The first review closes the setup**
   **Given** the first-review step with a connected site
   **When** the user picks a published post and runs a review from the dialog
   **Then** the dialog waits and shows the score when the result arrives, or the failure with its next step when n8n reports one; a result marks the setup complete, the notice disappears, and a last step offers the Blog Profile and scheduled reviews
6. **Setup can be redone without losing anything**
   **Given** a site whose setup is complete
   **When** the user opens the dialog again from the n8n Workflow page (for example to re-import a newer workflow version)
   **Then** every step is available, the stored webhook URL, token and settings stay as they are, and completing it again changes nothing else

## Boundaries

- Must not change: the stored settings contract and how the Settings form saves; the result callback and the workflow template; who may run reviews; the quota.
- Out of scope: screenshots or illustrations of n8n (owner, 2026-10-07; imagery waits for story 10); a round-trip ping (story 2: the first review already proves the return path here); creating n8n credentials from WordPress.

## References

- requirement — docs/user-paths.md, UV4 "Guided activation", stages A3–A5 and "Notes for the future setup wizard (Path A)"
- decision — docs/adr/0005-wordpress-components-for-app-screens.md
- copy — docs/brand.md, Copy Rules and UX Writing Examples
- adjacent backlog — story 35 (three credentials, test-URL warning, the ping proves the webhook credential), story 2 (round-trip test), story 22 (generator review), story 10 (identity and imagery)

## Notes

- Decision (owner, 2026-10-07): a dialog, not a page.
- Decision (owner, 2026-10-07): no screenshots; exact menu and field names in text.
- Decision (owner, 2026-10-07): the dialog creates the WordPress Application Password.
- Decision (owner, 2026-10-07): the dialog replaces the inline checklist; one setup path.
- Decision (owner, 2026-10-07): the dialog is a WordPress-components Modal (ADR 0005, lesson of 2026-10-05) mounted as a small React island on the existing n8n Workflow page; the rest of that page stays PHP.
- Decision (owner, 2026-10-07): progress is stored once per site, not per user, since setup is a site task that one administrator may finish for another; it is removed on uninstall.
- Decision (owner, 2026-10-07): the Application Password belongs to the signed-in user who clicks the button; it is the account n8n will edit posts with, so that user must be allowed to edit posts, which the step states.
- Decision (owner, 2026-10-07): n8n's menus and labels are named as of the n8n version checked at build, with one line telling the user that n8n's layout may differ; the owner's walk-through on n8n Cloud is the check.
- Decision (owner, 2026-10-07): the welcome notice (user paths, wizard note 1) is the plugin's existing admin notice, not a redirect on activation.
- Risk medium (reason): the dialog creates a credential and writes settings; a mistake is visible at once and a revert is clean, but the Application Password path is auth-adjacent and needs a harness case for the "not allowed" branch.
