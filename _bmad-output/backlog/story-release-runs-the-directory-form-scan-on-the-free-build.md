---
id: 131
type: story
title: "A release stops before it publishes when the directory form's scan finds anything in Freemius's Free build"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
estimate: ""
---

# A release stops before it publishes when the directory form's scan finds anything in Freemius's Free build

## Description

Freemius generates the Free build by re-printing every PHP file, and the WordPress.org add-plugin form scans that build, not the source. CI's Plugin Check reads a tree that keeps every comment, so it never sees what the re-print loses: 4.0.0 shipped with 32 low-severity findings the form showed and CI did not (bug 125). After this story, the release job runs the form's exact scan on the Free build Freemius generated, in the step that already checks that build, and a finding of either severity leaves the release a draft with no zip. The owner merges the release PR that proves it (hitl).

## Acceptance Criteria

1. **A finding stops the release**
   **Given** a release whose Free build from Freemius holds a finding the form's scan reports, of type `ERROR` or `ERROR_LOW_SEVERITY`
   **When** the release job runs
   **Then** the job fails before its publish step, the release stays a draft without the zip, and the log names each finding's file, line and code
2. **A clean build publishes as today**
   **Given** a release whose Free build from Freemius the form's scan finds nothing in
   **When** the release job runs
   **Then** the scan passes and the release publishes with the Free build, as it does today
3. **The scan is the form's**
   **Given** the release job
   **When** the scan runs
   **Then** it reads the Free build Freemius generated, with the form's exact arguments, and its log prints the Plugin Check version it ran
4. **The same check shows the defect and the fix**
   **Given** the scan as the release job runs it
   **When** it runs on 4.0.0's Free asset and on the Free asset of the release after bug 125
   **Then** it fails on 4.0.0's with the 32 findings bug 125 records, and passes on the later one

## Boundaries

- Must not change: the step's comparison of Freemius's Free build with the repository's Free-build script; the publish-last order and what a red step leaves (a draft, its tag, no zip); the Freemius upload and its re-run guard; CI's Plugin Check jobs and their arguments.
- Out of scope: CI's `strict` input (decision below); the Plugin Check line of the local verification guide (story 126).

## References

- found by — _bmad-output/backlog/bug-submission-scan-flags-32-low-severity-errors-in-the-free-build-plan.md, Boundaries (the owner's decision on recurrence) and Design Notes (Skipped)
- defect — _bmad-output/backlog/bug-submission-scan-flags-32-low-severity-errors-in-the-free-build.md, Reproduction (the form's command) and Notes (the open question on recurrence)
- the release job — memory/delivery-pipeline.md, the Freemius deployment line (publish last, the Free build check, the comments in the re-print)
- the form's command — _bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission-plan.md, "AC1 halt resolved", the submission scan bullet

## Notes

- Decision (owner, 2026-11-16): the recurrence guard for bug 125 is the form's exact command in the release job's "Check the Free build Freemius generated" step, filed as this ticket; bug 125 adds a harness that models the re-print on the source, and only the release job sees the real re-print.
- Decision (owner, 2026-11-16, bug 125): no CI `strict`: CI scans the source tree, which keeps every comment, so it would not see the re-print.
- Fact (bug 125, 2026-11-11): only a record of type exactly `ERROR` fails the form's scan; it lists `ERROR_LOW_SEVERITY` records for a reviewer's manual check. This story fails the release on both.
- Fact (bug 125, 2026-11-16): Plugin Check 2.2.0 was the latest release on that day; the form runs the latest.
- Open question: how the release job runs Plugin Check on a build: the `wordpress/plugin-check-action` CI already pins (its inputs mirror the form's arguments, and it fails only on `ERROR` unless `strict` is on, per bug 125's Notes), or WP-CLI on a WordPress the job sets up. The choice must fail on `ERROR_LOW_SEVERITY` too.
- Open question: whether the job pins the Plugin Check version, or takes the latest as the form does and so may fail a release on a check Plugin Check added since.
- Assumption: the scan fits in the job's time without a limit of its own; the form gives its scan 45 seconds.
- Finding (story 38's findings batch, owner 2026-11-23: folded here): main's push runs of `ci.yml` skip both Plugin Check jobs (they run on pull requests only), so main has no Plugin Check output of its own; story 38's P035 compared the site with the release PR's run instead (T1, 2026-11-19).
