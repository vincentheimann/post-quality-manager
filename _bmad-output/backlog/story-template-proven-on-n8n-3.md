---
id: 119
type: story
title: "The workflow template is proven on n8n 3's first stable release"
parent: none
covers: [UV4, UV5]
after: [111]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The workflow template is proven on n8n 3's first stable release

## Description

Story 111 read n8n 3's rules at the pre-release 3.1.0 and found nothing in the template or the n8n API calls that breaks. That reading is proven only when the guided setup, **Update workflow** and a command run against n8n 3's first stable release. n8n 3 runs only as a Docker image, so the disposable n8n of chore 105 (installed from npm) cannot run it, and the environment is chosen with the owner when this story starts (hitl).

## Acceptance Criteria

1. **The template works on n8n 3**
   **Given** n8n 3's first stable release, with its defaults
   **When** the guided setup provisions the workflow, **Update workflow** runs, and one Review runs on a published post
   **Then** each succeeds, and the review is stored
   **And** `memory/lifecycle.md` and `memory/n8n-provisioning.md` name the n8n version proven
2. **A difference from 3.1.0 becomes its own ticket**
   **Given** a default, an API answer, or an editor label the manual steps quote, that differs from what story 111 recorded at 3.1.0
   **When** the proof meets it
   **Then** it is filed as its own ticket, and the records say what differed

## Boundaries

- Must not change: the template, unless the proof finds a break (then with a version bump and a release-notes line, ADR 0017); the owner's production site, which no walk touches.
- Out of scope: what story 111 already settled (the manual path, the recorded answers).

## References

- prerequisite — _bmad-output/backlog/story-n8n-3-readiness.md (story 111: the rules read at 3.1.0, the decisions)
- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md
- walk — docs/local-verification.md (A disposable n8n)
- record — memory/lifecycle.md, memory/n8n-provisioning.md

## Notes

- Fact: split from story 111's third criterion on 2026-11-09 (owner decision), because no stable n8n 3 exists yet (3.0.0, 3.0.1 and 3.1.0 are GitHub pre-releases) and n8n 3 ships only as a Docker image.
- Unknown: the environment for the proof — Docker on the owner's PC (chore 105 kept Docker and WSL off it), or the owner's own n8n once it updates to 3. Settled with the owner before the build.
- Open question: whether the stable release keeps 3.1.0's defaults (SSRF protection off, the task runner at 60 seconds).
- Open question: whether the guided setup's probe, which calls `/activate` and `PATCH /credentials` with an unknown id, still reads n8n 3's answers as "the route exists".
- Open question: how long the template's Code nodes run against the 60-second task timeout.
- Handoff (bug 120, 2026-11-10): the manual path's step 1 places the workflow menu "right after the workflow's name, at the top left", read from n8n 3.1.0 and 3.2.0 pre-release source. The proof also checks that position in n8n 3's running editor, not only the labels the steps quote; a moved menu is filed like a changed label.
