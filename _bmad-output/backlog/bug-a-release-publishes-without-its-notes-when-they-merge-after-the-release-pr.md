---
id: 136
type: bug
title: "A release publishes without its notes when they merge after the release PR"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
estimate: ""
---

# A release publishes without its notes when they merge after the release PR

## Description

The release notes for a version reach main through their own `docs:` PR. When that PR merges after the release PR, the GitHub Release is published with the changelog only, and the site owner reading it gets no "what changed, what to do". This happened on 4.0.3 and 4.0.4; both times the notes were added by hand afterwards. The release should carry its notes whatever order the two PRs merge in, and should never go out without them.

## Reproduction

1. The release PR for 4.0.4 (#28) merged at 15:53:59 UTC on 2026-11-17; the notes PR (#29, `docs/release-notes/4.0.4.md`) merged at 15:54:16, 17 s later.
2. The Release workflow ran on the release commit (run 44119587264). Its step "Attach the Free build, append release notes, publish" started at 15:55:20, when main already held the notes file.
3. Actual: the release was published with the changelog only; the step logged nothing about the missing notes. Expected: the release body ends with the text of `docs/release-notes/4.0.4.md`.
4. Same on 4.0.3: release PR #20 merged at 11:36:48, notes PR #21 at 11:37:02; published without notes.

## Cause Hypothesis

The publish step reads the notes file from the checkout of the tag, the release commit, which holds only what merged before the release PR. A notes file merged after it is on main but not in that tree, and a missing file is skipped without a message, so the step publishes without notes.

## Acceptance Criteria

1. **Notes merged after the release PR still reach the release**
   **Given** a release whose notes file is not in the tagged tree but is on main when the release is published
   **When** the Release workflow publishes the release
   **Then** the release body is the changelog followed by that file's text, as when the file is in the tag
2. **Notes in the tag keep working as today**
   **Given** a release whose notes file is in the tagged tree
   **When** the Release workflow publishes the release
   **Then** the release body is the changelog followed by the tag's file, unchanged from today
3. **No release goes out without notes**
   **Given** a release whose notes file is neither in the tagged tree nor on main when the release is published
   **When** the Release workflow reaches the publish step
   **Then** the release stays a draft, the run fails, and its message names the missing file and says to re-run the failed job once the notes are on main
4. **A re-run after the notes merge publishes once**
   **Given** the failed run from criterion 3, and the notes file merged to main afterwards
   **When** the failed job is re-run
   **Then** the release is published with its notes, and no step that already succeeded is repeated with a different outcome (one Freemius deployment for the version, one Free build asset)
5. **Tests cover the condition found and fixed**
   **Given** the checks CI runs on the release pipeline
   **When** they run
   **Then** a check that fails on the defect and passes on the fix covers each of the three cases above (notes in the tag, on main only, nowhere)
6. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current workflow
   **Then** the release already carries notes merged after the release PR, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 5

## References

- parent — none
- process — `memory/delivery-pipeline.md`, the release-notes bullet (notes accumulate in `docs/release-notes/unreleased.md` and move to `<version>.md` before a release)
- skill — `.claude/skills/release/SKILL.md`, step 3 (stop before the merge when the notes file is missing) and step 5 (the publish)
- records — `_bmad-output/backlog/story-wordpress-org-directory-listing-plan.md`, Implementation Notes, the 4.0.3 entry (notes appended by `gh release edit` after the publish)
- logs — https://github.com/vincentheimann/post-quality-manager/actions/runs/44119587264, job "Package plugin zip", step "Attach the Free build, append release notes, publish"

## Notes

- Decision (owner, 2026-11-18): the publish step takes the notes from the tag, else from main; with neither, the release stays a draft and the step fails (fail closed). Chosen over a check on the release PR (release-please's PRs trigger no CI here, `GITHUB_TOKEN` only) and over committing the notes into release-please's PR (it rewrites its branch on every push to main).
- Fact: with this rule, a release with no notes file cannot be published. 4.0.1 and 4.0.2 had none; `/release` step 3 already asks for one for every version.
- Handoff: `/release` steps 3 and 5 describe the publish step; their wording follows the change in the same PR.
- Open question: whether re-running the failed job repeats a step with side effects (the Freemius upload, the asset upload); the plan reads the job and settles it before criterion 4.
- Open question: how criteria 3 and 4 are shown without a real release (for example a dispatch of the job against a test tag); the happy path shows at the next release under `/release`.
- Assumption: severity P2 — releases ship impaired (no notes for site owners), and a workaround exists (append the notes by hand with `gh release edit`).
- Assumption: risk medium — the release pipeline; a mistake shows only at the next release and can block or misfire a publish.
