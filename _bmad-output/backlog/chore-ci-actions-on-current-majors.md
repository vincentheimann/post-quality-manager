---
id: 102
type: story
title: "CI and the release workflow run on the current majors of their GitHub Actions"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# CI and the release workflow run on the current majors of their GitHub Actions

## Description

Both workflows use `actions/checkout`, `actions/setup-node`, `actions/upload-artifact` and `actions/download-artifact` at v4. Their current majors are v7, v7, v7 and v8. Every CI run now warns that Node.js 20 is deprecated and that `actions/checkout@v4` is forced onto Node.js 24. This chore moves each action to its current major before the forced run becomes a failure.

## Acceptance Criteria

1. **Each action is on a supported major**
   **Given** `ci.yml` and `release.yml`
   **When** they are read
   **Then** every action is on its current major, and the pinned `wordpress/plugin-check-action` stays pinned by SHA
2. **The runs stay green and quiet**
   **Given** a pull request and a manual dispatch of the Release workflow (the packaging check)
   **When** they run
   **Then** every job passes as before, artifacts keep their names, and no Node.js deprecation warning remains
3. **The release path is unchanged**
   **Given** the next release
   **When** its package job runs
   **Then** the zip, the GitHub Release, the Freemius upload and both artifacts behave as in 3.10.2's run

## Boundaries

- Must not change: what the jobs build, check or publish; the artifact names other steps and story 38 read.
- Out of scope: Dependabot (ADR 0017 allows it later without a new ADR).

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the build and CI rule)
- record — memory/lifecycle.md
- source — memory/delivery-pipeline.md (what each workflow does)

## Notes

- Fact (`/lcm`, 2026-11-03): latest releases are checkout v7.0.1, setup-node v7.1.0, upload-artifact v7.0.2 and download-artifact v8.0.2. setup-php 2.40.0 (v2), release-please-action v5.0.0 and plugin-check-action v1.1.9 are current. The annotation on main's CI: "Node.js 20 is deprecated … actions/checkout@v4 … forced to run on Node.js 24". The `ubuntu-latest` move to Ubuntu 26 (from 2026-10-19) needed no change.
- Open question: each major's breaking changes (the artifact actions changed upload semantics between majors before); read them when the chore starts.
