---
id: 40
type: story
title: "The Workflow File section explains itself: ordered steps, copy buttons and links to where each value goes"
parent: none
covers: [UV4]
after: []
assignee: ""
refined: true
hitl: false
risk: low
---

# The Workflow File section explains itself: ordered steps, copy buttons and links to where each value goes

## Description

Owner request (2026-10-08). The Workflow File section of the n8n Workflow page lists the three credentials *above* the Download button — before the user has imported anything — shows the values a user must transcribe into n8n (the webhook token, the header names) as running text with no Copy button (the import URL is the only value that has one), and names no destination: nothing links to the user's n8n instance, to the profile page where an Application Password is created, or to where an Anthropic API key comes from. The guided setup dialog (story 36) carries the novice through first setup; this static section is what a returning user reads on a re-import (a v3 site re-imports from here) and what a novice falls back to. This story makes the section carry its reader to the right place: steps in the order the work happens, a Copy button on every transcribed value, and a link wherever an accurate one exists — never a guessed one.

## Acceptance Criteria

1. **Steps come in the order the user acts**
   **Given** the Workflow File section
   **When** it renders
   **Then** it reads as numbered steps in journey order — import the workflow (Download, or the import URL), create the three credentials, open each flagged node so n8n picks its credential (today's "Credentials … are not set" hint becomes this step), then save, activate and copy the production URL into the plugin — instead of the credentials list sitting above the Download button
2. **Every transcribed value has Copy**
   **Given** a value the user must enter in n8n — the webhook token, the header names `X-PQM-Token` and `x-api-key`, the two site-specific credential names
   **When** it is displayed
   **Then** it carries a Copy button with the same `aria-live` feedback the import URL row already has, and the token is a labeled value row, not part of a sentence
3. **Links only where they are accurate**
   **Given** the credential steps
   **When** the user reads them
   **Then** the Application Password sentence links to the signed-in user's own profile page (`profile.php`, Application Passwords section) and says the password must belong to a user who can edit posts; the Anthropic key sentence links to the Anthropic Console as the place a key comes from; when a webhook URL is saved, "in n8n" links to that instance's origin, and with no URL saved no n8n link is shown; n8n screens that cannot be addressed by URL keep exact menu-path words instead of a link (the story 36 rule)
4. **The novice is handed to the dialog first**
   **Given** a site whose setup is not complete
   **When** the section renders
   **Then** its first line recommends the "Set up the workflow" guided dialog and presents the section as the reference / re-import path; once setup is complete that line is gone
5. **Translated and announced**
   **Given** the reworked section
   **When** rendered on a translated site or read by a screen reader
   **Then** every new string is translatable with translator comments on parameterized ones, Copy feedback is `aria-live`, and link texts name their destination (no "click here")

## Boundaries

- Must not change: the setup dialog's steps (story 36), the workflow template and its credential names, the stored settings contract, the import URL contract.
- Out of scope: screenshots or illustrations of n8n (owner decision 2026-10-07, story 36); the round-trip test (story 2); masking or rotating the token (a story 22 finding if it comes).

## References

- code — post-quality-manager/admin/class-pqm-admin.php (the page render, `render_credentials_list()`, `render_import_url()`, `render_credentials_hint()`)
- code — post-quality-manager/admin/js/pqm-admin.js (the existing Copy binding by class); src/setup/steps.js (the dialog wording to stay consistent with)
- requirement — docs/user-paths.md, A4 (the friction wall) — hardens the converted UV4 path
- copy — docs/brand.md, Copy Rules
- adjacent backlog — story 36 (done: the dialog), story 22 (the generator review reads the same section), story 2 (round-trip test)

## Notes

- Decision (owner, 2026-10-08): created and refined in the same sitting from the owner's read of the live page; Given/When/Then written because the ticket has no epic.
- UX practices applied, the reviewable list: steps ordered by the user's journey, one action per step; copy-to-clipboard on every value a human would otherwise retype; deep links only to addressable destinations, exact menu words elsewhere; progressive disclosure — guided dialog first, reference section second; descriptive link text; `aria-live` feedback; translator comments.
- Assumption: the n8n instance origin is derived from the saved webhook URL (scheme + host); n8n Cloud and self-hosted both serve the editor at that origin. Confirm at build.
- Risk low (reason): copy and markup on one PHP-rendered section; `tests/harness/render-matrix-harness.php` asserts today's copy and updates with it.
- Dropped (owner, 2026-10-13): absorbed by `epic-n8n-provisioning` entry 2 (CAP-8, manual fallback); the acceptance criteria above are that entry's reference. Not built under this id.
