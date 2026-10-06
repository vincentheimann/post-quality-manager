---
id: 32
type: bug
title: "Clicking Review with n8n unset or unreachable shows no error the user notices"
parent: none
covers: ["UV5"]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
estimate: ""
---

# Clicking Review with n8n unset or unreachable shows no error the user notices

## Description

When the n8n webhook URL is not set, or n8n cannot be reached, a user who clicks **Review** (or another Run button) on Post Reviews sees no error they notice. The run silently does not start. The user should be told clearly, in place, that the review could not start and what to do, and should know before clicking when no workflow is connected.

## Reproduction

1. Settings → n8n Connection: clear the webhook URL and save (or point it at a host that doesn't answer).
2. Post Reviews → open a post's detail panel → **Run Review**.
3. Observed (owner, 2026-10-06): no visible error.
4. Expected: a clear, persistent message in the place the user clicked, e.g. "n8n is not connected yet. Ask an administrator to set the webhook URL in Settings.", or the n8n connection error. Before clicking, the page signals that no workflow is connected.

Code trace (2026-10-06): the server answers correctly. An unset URL gets 400 `pqm_not_configured`, an unreachable n8n gets 502 `pqm_n8n_error` after the 5 s `wp_remote_post` timeout, and an n8n non-2xx gets 200 `success:false`. `requestRun()` turns each into `{ok:false, message}`, and `useSingleRun` shows it with `addToast(…, 'warning')`.

## Cause Hypothesis

The error reaches the screen but is easy to miss:
- Single and detail-panel runs report failure only as a `SnackbarList` toast (`src/kit/toasts.js`). It disappears after 5 s, is fixed at the bottom right away from the panel, and looks the same as a success toast. For an unreachable n8n it appears only after the 5 s timeout.
- Post Reviews has no "not connected" signal. `GET /posts` `viewer` carries no `connected` field, `canRender()` checks permissions only, and the PHP admin banner shows to `pqm_manage_settings` holders only, so Editors and Authors get no warning at all. The Dashboard already hides its Review button behind a ConnectCard when `connected` is false.
- Bulk **Review selected** uses a persistent inline `Notice` and is likely fine. Check it.

Not ruled out without a live check: a non-JSON response (proxy/HTML) making `err.message` empty, or a URL that answers 2xx (the user would then see "requested" and a much later give-up message).

## Acceptance Criteria

1. **A failed start is reported where the user clicked, and stays**
   **Given** the webhook URL is unset, or n8n is unreachable, or n8n answers non-2xx
   **When** any user clicks a Run button on Post Reviews (single, detail panel, bulk) or the Dashboard
   **Then** a message naming the reason and the next step stays visible next to the clicked control until dismissed or the next run, styled as an error/warning per `docs/brand.md`, and the button shows it is working while the request is pending
2. **Not connected is visible before the click**
   **Given** no webhook URL is set
   **When** a user who can run agents opens Post Reviews
   **Then** the page says no workflow is connected (with the Settings link for `pqm_manage_settings` holders, "ask an administrator" for others), and the Run buttons are disabled or explain why
3. **Tests cover the condition found and fixed**
   **Given** the harnesses and `npm run lint:js`
   **When** they run
   **Then** the `connected` signal on the REST side is covered (e.g. `GET /posts` `viewer`), and each failure response maps to its user message
4. **Or: no change is needed, with proof**
   **Given** the reproduction on the real site
   **When** it is run on the current code
   **Then** a noticeable error already appears, with the evidence (screenshot, Network response) recorded in Notes. This supersedes 1–3

## References

- parent — none
- server — `post-quality-manager/includes/class-pqm-rest-api.php` `trigger_review_callback()` (not configured, n8n error, non-2xx branches), `GET /posts` `viewer`, `GET /dashboard` `connected`
- client — `src/kit/runs.js` `requestRun()` / `canRender()`, `src/kit/use-single-run.js`, `src/kit/toasts.js`, `src/post-reviews/bulk-bar.js`, `src/dashboard.js` (ConnectCard)
- admin banner — `post-quality-manager/admin/class-pqm-admin.php` (not-connected notice, `pqm_manage_settings` only)

## Notes

- Filed 2026-10-06 from the owner's report while re-importing the v3 workflow.
- Assumption: the owner's case was the detail-panel Run button. Confirm at refine, with the Network response of `POST /trigger-review`.
- Open question: when not connected, should the Run buttons be disabled or stay clickable and explain on click? Settle at refine.
