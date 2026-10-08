---
title: 'Tracer bullet: release PR and a CI-built plugin zip'
type: 'feature'
ticket: '1'
created: '2026-10-08'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'a631055242aee512e5a166e032ea8060c37cc115'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Nothing has ever been released. No merge produces a version, a CHANGELOG or an installable zip, so installing the plugin means copying a folder by hand.

**Approach:** release-please on Conventional Commits opens or updates one release PR on every push to `main`. That PR proposes **3.0.0** and bumps the plugin header `Version`, `PQM_VERSION`, `package.json` and `package-lock.json` together. Merging it creates the tag `v3.0.0` and a **draft** GitHub Release. In the same workflow, a packaging job builds `admin/build/` from the released commit and zips `post-quality-manager/` as `post-quality-manager-<version>.zip`, with that root folder. It attaches the zip, appends `docs/release-notes/<version>.md` to the release body when the file exists, and publishes the release. A manual dispatch runs only the packaging and uploads the zip as a workflow artifact: no tag and no release.

**Decision (owner, 2026-10-08):** the first CHANGELOG starts after `main` at `237b1b9f02bfc4145dc9bee9a126da87a9c8f95b` (`bootstrap-sha`). It lists the commits from this story up to the 3.0.0 cut, and story 15's release notes file tells the story of 3.0.0 itself.

## Boundaries & Constraints

**Always:** `GITHUB_TOKEN` only, with job-level `permissions`. `PQM_WORKFLOW_VERSION` is never bumped. Built assets never enter git. One zip for free and paid sites. The owner merges every release PR.

**Never:** No version check (entry 2), no Plugin Check (4.4), no `languages/` (4.6), no release-notes content (story 15). The real 3.0.0 cut is entry 8: nobody merges the release PR this story opens. No new npm or Composer dependency.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Release PR | this story merged to `main` | one release PR "release 3.0.0": header `Version`, `PQM_VERSION`, `package.json`, `package-lock.json` (root + `packages[""]`) at 3.0.0, a `CHANGELOG.md` listing the commits after the bootstrap point; `PQM_WORKFLOW_VERSION` and `Requires at least` / `Requires PHP` untouched | — |
| Manual dispatch | Actions → Release → Run workflow on any branch | an artifact holding `post-quality-manager-3.0.0.zip`; the zip's single root folder is `post-quality-manager/` with `admin/build/index.js` + `index.asset.php` inside; no tag, no release | the build fails → the job fails, no artifact |
| Zip contents | the built tree | only the plugin subfolder: no `src/`, `tests/`, `node_modules/`, `_bmad*`, `docs/`, `memory/` | — |
| Release merged (entry 8) | release PR merged | tag `v3.0.0`, the draft gets the zip and, when `docs/release-notes/3.0.0.md` exists, its text appended to the body, then it is published | packaging fails → the release stays a draft with the tag; re-run the job |

</frozen-after-approval>

## Code Map

- `.github/workflows/release.yml` (new) -- two jobs:
  - `release-please` (`on: push` to `main` only) runs `googleapis/release-please-action@v5`, which bundles release-please ^17.6, where `force-tag-creation` exists since 17.2. Permissions: `contents: write`, `issues: write`, `pull-requests: write`. Outputs: `release_created`, `tag_name`.
  - `package` (`needs: release-please`, `if: always()` and either a dispatch or `release_created == 'true'`) checks out the tag on a release and the dispatched ref on a dispatch. Then: Node 22 `npm ci`, `npm run build`, the asset assertion copied from `ci.yml:37-40`, `V=$(node -p "require('./package.json').version")`, and `zip -rq "post-quality-manager-$V.zip" post-quality-manager` from the repo root. It uploads the zip with `actions/upload-artifact@v4` every time. On a release only, it also runs `gh release upload`, appends the notes file to the body read by `gh release view --json body`, then `gh release edit --notes-file … --draft=false`.
- `release-please-config.json` (new) -- top level:
  - `bootstrap-sha`: `237b1b9f02bfc4145dc9bee9a126da87a9c8f95b` (owner's decision in the Intent)
  - `include-component-in-tag: false`, so the tag is `v3.0.0`, not `post-quality-manager-v3.0.0`
  - `draft: true` and `force-tag-creation: true`. Without the latter, a draft has no tag, release-please cannot find the previous release, and the next CHANGELOG pulls the whole history.

  Package `"."`: `release-type: node` updates `package.json`, `package-lock.json` and `CHANGELOG.md` (`src/strategies/node.ts`), plus `release-as: "3.0.0"` and `extra-files: [{ type: generic, path: post-quality-manager/post-quality-manager.php }]`.
- `.release-please-manifest.json` (new) -- `{}`. No release exists yet; `release-as` sets the first version.
- `post-quality-manager/post-quality-manager.php:6,20` -- put a block annotation around the header `Version` line: ` * x-release-please-start-version` before it, ` * x-release-please-end` after. Put an inline `// x-release-please-version` on the `PQM_VERSION` line. An inline annotation on the header line would end up inside the header value that WordPress reads, so the header needs the block. The generic updater replaces the first semver on each annotated line (`src/updaters/generic.ts` `VERSION_REGEX`).
- `.claude/skills/release/SKILL.md` -- rewrite the checklist for the automated flow. Steps: review the release PR, check that the CHANGELOG reflects the merged commits, check that the release notes file exists (story 15), merge, then watch the `package` job publish and check the zip. Add one line: remove `release-as` after 3.0.0.
- `README.md:101` -- "releases are GitHub Releases cut by release-please: merging its release PR tags the version and publishes a CI-built zip (`/release` checks it)".
- `.github/workflows/ci.yml:1-8` -- header comment: `release.yml` builds the released commit itself, so the `admin-build` artifact is for inspection only.
- `memory/delivery-pipeline.md` -- record the built facts: the files, annotations, draft + force-tag, `release-as` removal owed by entry 8, and the dispatch artifact.

## Tasks & Acceptance

**Execution:**
- [x] `release-please-config.json`, `.release-please-manifest.json` -- config as in the Code Map.
- [x] `post-quality-manager/post-quality-manager.php` -- the two annotations. No other change.
- [x] `.github/workflows/release.yml` -- the two jobs.
- [x] `.claude/skills/release/SKILL.md`, `README.md`, `.github/workflows/ci.yml` -- docs aligned.
- [x] `memory/delivery-pipeline.md` -- facts.
- [ ] Commit as `feat(release): …`. release-please opens a PR only when a releasable commit (feat/fix) follows the bootstrap point, and that is this story's own.

**Acceptance Criteria:**
- Given the header after the edit, when WordPress reads it (`get_file_data` regex `^[ \t\/*#@]*Version:(.*)$`), then `Version` is exactly `3.0.0` and `php -l` is clean.
- Given the branch pushed, when the owner runs `release.yml` by manual dispatch on it, then the matrix rows Manual dispatch and Zip contents hold, and the zip installs and activates on a clean WordPress 6.6 on Local with `WP_DEBUG` on and no notices, showing version 3.0.0.

## Implementation Notes

- Built by one implementation subagent. The commit step stays open under the house rule: the owner commits, as `feat(release): …`.
- Local proof: WordPress's header regex reads `Version` = `3.0.0`, release-please 17.6's own generic updater, run in the scratchpad with 3.1.0, changed exactly the two annotated lines, and the zip's single root is `post-quality-manager/`, holding `admin/build/`. The matrix rows Release PR, Manual dispatch and Release merged can only run on GitHub. They are covered by the owner's checks, the post-push dry run and entry 8.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick):** high 0 · medium 1 · low 1 · false 0 · maybe-false 1

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | The `release-please` job lacks `issues: write` | medium | patch | The action README lists contents, issues and pull-requests write. release-please labels its PR `autorelease: pending` and finds it by that label after the merge, so a refused label could stop the release from ever being created. Added. |
| 2 | No harness despite the I/O matrix (AGENTS.md rule) | low | patch | Added `release-header-harness.php`: header parse, annotation placement, `PQM_WORKFLOW_VERSION` unannotated. The equality of all versions stays with entry 2. |
| 3 | Re-running a failed `package` job may hit an artifact-name conflict | maybe-false (medium if true) | defer | upload-artifact issue #323 suggests a re-run deletes the earlier artifact. To settle it: one re-run of a failed dispatch. Logged in deferred-work.md. |

## Design Notes

- **Why one workflow, not a `release`-event workflow:** events made with `GITHUB_TOKEN` start no workflow, and GitHub never fires `release: created` for drafts. So the `package` job has to run in the same workflow, gated on the action's `release_created` output. The ticket's "on a draft release" is met by this job.
- **Artifact shape:** GitHub wraps every artifact in its own zip, so the owner unzips once to get `post-quality-manager-3.0.0.zip`, which is the upload file.
- Skipped: a `concurrency` group for release-please. Two quick merges re-run it idempotently; add one if a duplicate PR ever appears.

## Verification

**Commands:**
- `php -l post-quality-manager/post-quality-manager.php` and all `tests/harness/*.php` -- expected: clean, green.
- `php -r` reading the header via WordPress's own regex -- expected: `Version` = `3.0.0`, no annotation text.
- `node -e` parsing both release-please JSON files; `npx --yes @action-validator/cli` is not installed, so read `release.yml` against the action README instead -- expected: valid JSON, outputs used as documented.
- Local packaging dry run: `npm run build`, then the job's zip command, then `unzip -l` -- expected: one root folder, `admin/build/` present, no repo-root files.
- After the owner approves a push of the branch: `npx release-please release-pr --dry-run --repo-url=vincentheimann/post-quality-manager --target-branch=feat/4.1-release-tracer --token=$(gh auth token)` -- expected: proposes 3.0.0 and lists the four version files plus `CHANGELOG.md`.

**Manual checks (owner):**
- Repo Settings → Actions → General → Workflow permissions: allow GitHub Actions to create and approve pull requests.
- Dispatch the workflow on the branch, download the artifact, then install and activate the zip on a clean WordPress 6.6 site on Local (`WP_DEBUG` on) and check that it shows 3.0.0.
- After merge: the release PR "release 3.0.0" is open. Leave it open until entry 8.
