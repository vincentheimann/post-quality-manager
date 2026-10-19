---
id: 79
type: story
title: "A Dashboard block lets any user send feedback or a bug report to hello@postquality.com with the facts prefilled"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# A Dashboard block lets any user send feedback or a bug report to hello@postquality.com with the facts prefilled

## Description

Owner request (2026-10-19): the plugin has no way for a user to tell us what works, what does not, or what broke, and the support promise of ADR 0013 (hello@postquality.com, an answer within two business days) is written on the site only. This story adds a feedback block to the Dashboard page: the user picks feedback or a bug, writes their message, and one button opens their mail client with the address, a subject and the message prefilled, followed by an environment block the user sees and can edit before sending (plugin version and build, WordPress and PHP versions, the workflow version last seen). Nothing leaves the site without the user's own send: no tracking, no request to an external service, no mail sent by the server. The block is quiet, dismissible per user and never shown as an admin notice, within the WordPress.org guidelines and `docs/brand.md`.

## Acceptance Criteria

1. **The block is on the Dashboard, for everyone who sees it**
   **Given** a user who can open the Dashboard
   **When** the page loads
   **Then** a feedback block sits below the dashboard's content, collapsed to one line and a button, and reads the same on the Free and Pro builds
2. **Two kinds, one message**
   **Given** the open block
   **When** the user chooses feedback or a bug and writes a message
   **Then** the button reads "Send by email" and stays disabled until the message has text
3. **The mail client opens prefilled**
   **Given** a message and a kind
   **When** the user presses the button
   **Then** their mail client opens a new mail to hello@postquality.com whose subject names the plugin and the kind, and whose body holds the message followed by a short environment block with the plugin version and build, the WordPress and PHP versions and the workflow version last seen; the site's URL and the user's name or email are never included unless the user types them
4. **The facts are visible before sending**
   **Given** the open block
   **When** the user reads it
   **Then** the environment block is shown as it will be sent, with a sentence saying it is included so we can reproduce a problem, and a Copy control copies the whole mail text for a user whose browser opens no mail client
5. **A bug knows its other channels**
   **Given** the bug kind chosen
   **When** the block is read
   **Then** it links to the public issue tracker for bugs that may be shared, and says a security problem goes by email only, never in a public issue
6. **Dismiss means dismissed**
   **Given** a user who dismisses the block
   **When** they return to the Dashboard
   **Then** the block stays dismissed for that user until they reopen it from a one-line link, and no other page shows it
7. **Nothing leaves the site by itself**
   **Given** any use of the block
   **When** the page's network activity is read
   **Then** the only request the block makes is the one that stores the user's dismissal

## Boundaries

- Must not change: the dashboard's existing cards and tiles; the Freemius opt-in and account surfaces; any admin notice.
- Out of scope: sending mail from the server (`wp_mail`); a feedback inbox, ticketing or analytics; the Freemius contact form (turned off since story 44); a WordPress.org support-forum link (story 38, when the listing exists); the copy pass of every surface (story 60).

## References

- decision — `docs/adr/0013-pro-sold-through-freemius-before-public-release.md`, D9 (support promise, the mailbox)
- data sheet — `docs/distribution.md`, Support contact; Private vulnerability reporting
- public channels — `_bmad-output/backlog/story-public-repository-welcomes-issues-and-security-reports.md` (story 48: issue templates, `SECURITY.md`)
- nudges — `_bmad-output/backlog/story-in-plugin-nudges-free-to-pro.md` (story 61: one primary action, a reason, a way to dismiss, no repeated notices)
- no tracking — `_bmad-output/backlog/spike-funnel-measurement-without-tracking.md` (spike 64)
- copy — `docs/brand.md`, Copy Rules
- design — none; the block follows the Dashboard's existing cards (`@wordpress/components`); offer `bmad-ux` at refinement if the owner wants a mock

## Notes

- Filed 2026-10-19 at the owner's request ("a feedback block within the Dashboard page, contact by email at hello@postquality.com, a form that prefills the email, best practices; the goal is to receive feedback and/or bugs"). Not ranked by `/prioritize`.
- Assumption: a `mailto:` link is the mechanism (the address, subject and body are URL-encoded; long bodies are cut by some clients, so the environment block stays short and the Copy control covers the rest). Server-side mail was declined in the draft: deliverability depends on the site's host, and it would send site facts without the user seeing them.
- Assumption: the environment block carries the plugin version and build (Free / Pro), the WordPress and PHP versions, the workflow version last seen, and the active theme's name; never the site URL, the user's identity, keys, tokens or settings. The issue template of story 48 asks for the same facts, so one list serves both.
- Assumption: the dismissal is a per-user meta key, written through the existing REST namespace; the block reopens from a one-line "Send feedback" link in the Dashboard's footer.
- Assumption: visible to every role that can open the Dashboard (`pqm_view_reviews`); a non-administrator's bug report is as welcome as an administrator's.
- Open question (owner, at refinement): should the Pro build also link the Freemius account page's support, or stay on the one mailbox? Draft: one mailbox, so every report lands in the same place.
- Open question (owner, at refinement): the public issue tracker link exists only once the repository is public (entry 4.8); until then AC5 shows the email path alone.
