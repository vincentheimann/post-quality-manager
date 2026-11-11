---
id: 124
type: bug
title: "The setup guide's n8n errors point to a \"Workflow File section\" the page no longer has"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# The setup guide's n8n errors point to a "Workflow File section" the page no longer has

## Description

When the setup guide cannot set n8n up through its API, the error ends with "Or set up the workflow by hand with the Workflow File section." The n8n Workflow page has no section with that name: the manual setup lives in its **Set up by hand** section, which the guide's own **Set up by hand instead** button opens. The message should name the section the user can see.

## Reproduction

1. Any site on 4.0.0 (Free or Pro), setup not done. Open Post Quality → n8n Workflow and click **Set up n8n**.
2. In **Connect your n8n**, enter the address of an n8n whose public API is turned off (or any address that answers 404 on the API path) and an API key, then connect.
3. Actual: "n8n answered 404: the public API is turned off on this instance (N8N_PUBLIC_API_DISABLED), or this is not your n8n address. Check the address. Or set up the workflow by hand with the Workflow File section."
4. Expected: the last sentence names the **Set up by hand** section.

The same closing sentence follows every cause that offers the manual path: the API turned off, a key limited by scopes, a proxy or firewall, an unexpected status, and an n8n too old for the setup.

## Cause Hypothesis

The closing sentence was written when the manual setup's section was called Workflow File (n8n provisioning entry 2, 2026-10-14). Story 67's page redesign renamed the heading to Set up by hand, and the shared sentence and the tests that pin its text kept the old name.

## Acceptance Criteria

1. **The error names the section the page shows**
   **Given** a setup whose n8n check fails with any cause that offers the manual path
   **When** the guide shows the error
   **Then** its closing sentence names the Set up by hand section, and no admin text names a "Workflow File" section
2. **Tests cover the condition found and fixed**
   **Given** the harnesses and the JS unit tests
   **When** they run
   **Then** a check that fails on the old wording and passes on the fix covers the closing sentence, for every cause that carries it
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the message already names the Set up by hand section, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 and 2

## References

- parent — none
- source — `docs/release-notes/4.0.0.md` ("The n8n Workflow page shows one next step": Set up by hand behind a collapsed section)
- found by — the Fable 5.2 reread of the README at entry 4.8 (plan `_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-first-public-release-is-published-plan.md`, Implementation Notes)

## Notes

- Decision (owner, 2026-11-11): file it as a bug, to fix after the 4.0.0 release.
- Assumption: comments and test names that still say "Workflow File section" are renamed with the fix, so the next reader does not reintroduce the old name; they change no behaviour.
