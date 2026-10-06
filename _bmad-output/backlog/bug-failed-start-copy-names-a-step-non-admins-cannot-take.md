---
id: 33
type: bug
title: "When n8n is unreachable or answers an error, non-admins are told to fix Settings or n8n"
parent: none
covers: ["UV5"]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P3
estimate: ""
---

# When n8n is unreachable or answers an error, non-admins are told to fix Settings or n8n

## Description

An Author or Editor clicks a Run button while n8n is down, or while n8n refuses the request. The failed-start notice (bug 32) appears where they clicked, but its next step is "Check your Webhook URL in Settings" or "Check that the workflow is active in n8n". Those users cannot open Settings or n8n. The notice should name the reason and a step they can take: tell an administrator. Administrators keep the current steps.

## Reproduction

1. A site with the n8n webhook URL set. Sign in as an Author (`pqm_run_free_agents`, no `pqm_manage_settings`).
2. Point the webhook URL at a host that does not answer, for example `https://127.0.0.1:9/webhook`. Post Reviews → Details → **Run Review**.
3. Observed (bug 32 review, 2026-10-06): after about 5 seconds the notice reads "Could not connect to n8n (cURL error 7: …). Check your Webhook URL in Settings." The Author has no Settings page.
4. Point the URL at an n8n whose workflow is inactive. Run Review again. Observed: "Review could not start — n8n answered HTTP 404. Check that the workflow is active in n8n, then try again." The Author has no n8n access.
5. Expected in both cases: the reason, then "Ask an administrator …" for users without `pqm_manage_settings`; the current steps for administrators. Same on the Dashboard queue and in the bulk "Review selected" report.

## Cause Hypothesis

The two messages predate the role split. The "could not connect" message is built on the server, and the "n8n answered HTTP …" message exists twice, on the server and in the shared client run helper, which is the copy the pages show. None of the three reads the viewer's role, although the server knows it and the post list already tells the client whether the viewer can manage settings.

## Acceptance Criteria

1. **The next step matches what the user can do**
   **Given** the webhook URL points at a host that does not answer, or at an n8n that answers anything but 2xx
   **When** a user who cannot manage settings starts a run from Post Reviews (single, detail panel, bulk) or the Dashboard
   **Then** the failed-start message names the reason (connection failed, or the HTTP status n8n answered) and tells them to ask an administrator; it never names Settings or n8n as their step
2. **Administrators keep their steps**
   **Given** the same two conditions
   **When** a user who can manage settings starts a run
   **Then** the message keeps naming the reason and the step they can take (the webhook URL, the workflow being active)
3. **One wording per outcome**
   **Given** the server and the client each describe the same outcome
   **When** a run fails to start
   **Then** every surface shows the same text for that outcome and role
4. **Tests cover the condition found and fixed**
   **Given** the harnesses and `npm run lint:js`
   **When** they run
   **Then** the unreachable and the non-2xx trigger responses are covered for both roles, and the response shape of the trigger route is unchanged
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code as an Author
   **Then** the messages already name a step the Author can take, with the evidence recorded in Notes — this supersedes 1–4

## References

- parent — none
- origin — `_bmad-output/initiative-post-quality-manager/deferred-work.md`, the entry whose source_plan is bug 32's plan
- bug 32 — `_bmad-output/backlog/bug-run-error-missed-when-n8n-unset-or-unreachable.md`, Acceptance Criteria 1 (the "next step" part this ticket finishes)
- copy rules — `docs/brand.md`, "Copy Rules" (errors gentle, with an actionable solution)
- user paths — `docs/user-paths.md`, UV5

## Notes

- Filed 2026-10-06 from the deferred-work entry of bug 32's review. Bug 32's approved plan allowed only the "not connected" text to change, so these two paths were left as they were.
- Decision (bug 32's approved plan, Approach, 2026-10-06): "not connected" copy is role-aware with `pqm_manage_settings` as the split; this ticket extends the same split, it does not introduce a new one.
- Risk medium: the run helper that builds the client message serves Post Reviews, the Dashboard and the bulk report, and a harness asserts the exact non-2xx text. Severity P3: the reason still shows and the user can ask an administrator without being told to.
- Assumption: the bulk "Review selected" report, which prints the run helper's message per post, needs no separate wording; it inherits the role-aware text.
- Open question: whether the connection-test message on the n8n Workflow page ("Could not connect to n8n: …") should be aligned too. It is admin-only, so it is out of this ticket unless the owner wants one wording everywhere.
