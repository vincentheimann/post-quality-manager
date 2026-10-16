---
id: 77
type: story
title: "Every n8n fact the plugin repeats has one source: names, headers, the connected state, the post-ping tail"
parent: none
covers: [UV4]
after: [71, 74]
assignee: ""
refined: false
hitl: false
risk: low
---

# Every n8n fact the plugin repeats has one source: names, headers, the connected state, the post-ping tail

## Description

The provisioning epic was built in twelve sessions, and the retrospective's duplication map shows the same facts spelled in several places: the three credential names in the template, the client and the page; the webhook header name in five places beside the function headed "single source of truth"; the origin parsed twice; the "key connected" test in five spellings across PHP and JavaScript; the 2xx check inlined seven times beside its helper; the post-ping record-and-render tail five times in the REST routes; the webhook URL composed on the server and again in the dialog; one method literal where the rest use constants. Two comments state opposite facts about n8n's save semantics, and two docblocks promise "never logged" where the plugin can only promise it never logs. This story is the second refactor sweep: cleanup only, behaviour unchanged, every harness count equal, and the agent guidelines updated to name what the epic added.

## Acceptance Criteria

1. **One source each**
   **Given** the duplication map's items 1 to 5, 7 and 9 and the pattern item 2
   **When** the sweep is done
   **Then** each fact is defined once and read everywhere else: the header name, the credential names (pinned by the template guard), the origin, the connected predicate (one server-side answer the islands read), the 2xx check, the post-ping tail, the provisioning reply carrying the saved webhook URL, the route method constant
2. **Comments and docblocks say what is true**
   **Given** the two comments on n8n's save semantics and the two "never logged" docblocks
   **When** the sweep is done
   **Then** the comments point at the memory finding (n8n republishes a PUT workflow, seen 2026-10-16) and the docblocks say the plugin never logs the keys itself
3. **Guidelines name the epic's additions**
   **Given** the agent guidelines' sections on where things are, conventions and options
   **When** the sweep is done
   **Then** they name the n8n client, the three n8n options, the page script's REST call and the in-callback sanitizing convention for new routes
4. **Nothing moved**
   **Given** every harness, the Jest suite and the lint
   **When** they run before and after
   **Then** they pass with equal counts, and no route, reply key, error code, HTTP status, option or n8n call changed

## Boundaries

- Must not change: any behaviour; the harness source regexes that read the client file (move helpers outside their spans, as the first sweep did).
- Does not split the client into several classes (declined in the first sweep; reopen only if the harness regexes can go).

## References

- retrospective — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/epic-n8n-provisioning-retrospective.md, Action items A11 and A12 (duplication map 1–5, 7, 9; pattern divergence 1, 2, 6; adversarial 9, 11; architecture delta 2, 3)
- first sweep — _bmad-output/initiative-post-quality-manager/epic-n8n-provisioning/story-refactor-sweep-plan.md (what it kept and why)
- memory — memory/n8n-provisioning.md (the PUT-republishes finding)
- guidelines — AGENTS.md (the managed context block: hand edits are noted, the project-context skill re-verifies)

## Notes

- Filed 2026-10-16 from the epic's retrospective. After stories 71 and 74, which change the gates and the connected predicate this sweep unifies.
