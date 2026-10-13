---
id: 58
type: story
title: "A release uploads its zip to Freemius and brings back the Free and Pro builds"
parent: none
covers: [O9]
after: [54]
assignee: ""
refined: false
hitl: true
risk: medium
---

# A release uploads its zip to Freemius and brings back the Free and Pro builds

## Description

Today the owner uploads each release zip to Freemius by hand and checks the Free build Freemius generates (ADR 0014, `docs/distribution.md` § Release order, `/release` steps 7–8). This story makes the Release workflow do both: after the GitHub Release is published, its zip is deployed to Freemius as a new, unreleased version, and the Free and Pro builds Freemius generates come back as workflow artifacts, checked and ready for story 38's SVN deploy. Releasing the version to licensed sites stays an owner step.

## Acceptance Criteria

1. **A release deploys its zip to Freemius**
   **Given** a release PR merged and `release.yml`'s `package` job having attached `post-quality-manager-<version>.zip` and published the GitHub Release
   **When** the job continues
   **Then** that same file is uploaded to Freemius product 41144 as version `<version>` in release mode `pending` (not released to customers), and the step logs its sha256 so it can be compared with the release asset
   **And** a failed Freemius step shows as a failed step on the run and leaves the published GitHub Release and its zip as they were
2. **The generated builds come back, checked**
   **Given** the upload succeeded
   **When** the step ends
   **Then** Freemius's builds are uploaded as workflow artifacts `post-quality-manager-<version>-free.zip` and `post-quality-manager-<version>-pro.zip`, never under the CI zip's name
   **And** the job fails, naming the file, unless the Free build holds no `__premium_only` outside `vendor/`, its header `Version` is `<version>`, and `templates/n8n-workflow-free.json` is its only n8n template (the checks of `/release` step 8)
3. **A re-run is safe**
   **Given** `<version>` already exists on Freemius (a re-run of the job, or an earlier manual upload)
   **When** the job runs again
   **Then** no second version is created, the existing version's builds are fetched and checked as in criterion 2, and the log says the Freemius version is the first upload (a re-run rebuilds the CI zip, so its bytes can differ from what Freemius holds; the Freemius copy is the one customers and SVN get)
4. **Only a release reaches Freemius**
   **Given** a manual dispatch of the Release workflow (the packaging check) or any CI run
   **When** it runs
   **Then** nothing is sent to Freemius
5. **Credentials and third-party code are controlled**
   **Given** the Freemius credentials the step needs
   **When** the workflow runs
   **Then** they come from GitHub secrets only and never appear in the repository, the job log or an artifact
   **And** every piece of third-party code the step runs is pinned: actions and fetched source by commit SHA or tag the refinement names, container images by digest
6. **The release order says what is automatic**
   **Given** `docs/distribution.md` § Release order and `/release` steps 7–9
   **When** a release is cut
   **Then** they describe the automatic upload, fetch and checks, the secrets the owner maintains and where each key comes from, and the remaining owner step (set the release mode to Released in Freemius)

## Boundaries

- Must not change: release-please's flow, the CI-built zip attached to the GitHub Release, the pre-release flag, the `free-build` CI job.
- Never sets release mode `released` or `beta`: releasing to licensed sites stays the owner's (story 44: release plans are off until its last step).
- No SVN deploy and no switch of the GitHub Release asset to the Free build: both are story 38's (see Notes).

## References

- decision — docs/adr/0014-two-builds-from-one-source.md (release order: Freemius first, its Free build to SVN)
- data sheet — docs/distribution.md § Release order and § Licensing provider: Freemius (product 41144, API endpoints)
- skill — .claude/skills/release/SKILL.md steps 7–9
- code — .github/workflows/release.yml (`package` job)
- prerequisite — _bmad-output/backlog/story-free-build-contains-no-pro-code.md (the markers this story's checks look for)
- consumer — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC7, SVN deploy)
- external — https://github.com/marketplace/actions/deploy-on-freemius (`buttonizer/freemius-deploy`)
- external — https://github.com/Freemius/freemius-php-sdk

## Notes

- Decision (owner, 2026-10-12): automate the Freemius deployment from the Release workflow; the owner proposed the "Deploy on Freemius" GitHub Action as the means. The means is settled at refinement (open question below).
- Read at filing (2026-10-12, repository `buttonizer/freemius-deploy`). Settle each at refinement, from the action's source:
  - Latest tag v0.1.3 = `dcbf3a1a0217c8235657b66ac86704437f87d5ee`; the default branch is ahead of it (`4a76779`, a fix of the Debian apt sources the same day), so the tag is not the latest code.
  - It is a Docker action. At v0.1.3 the image is `php:7.4-cli` (end of life), on the default branch `php:8.3-cli`; neither is pinned by digest. It clones `Freemius/freemius-php-sdk` at its default branch on every run, unpinned. Pinning the action's SHA pins neither.
  - It runs in developer scope: `DEV_ID`, `PUBLIC_KEY` and `SECRET_KEY` are the developer's keys (account-wide), not the product public key in `docs/distribution.md`; plus `PLUGIN_SLUG`, `PLUGIN_ID`.
  - The repository has no license file, so it can be run but not forked or copied into this repository.
  - It writes outputs with the deprecated `::set-output` command.
  - It reads `file_name` from the repository root and downloads the Pro build to that same file name, overwriting the CI zip; hence criterion 1's step order (after publishing) and criterion 2's artifact names.
  - Its "already uploaded" check compares `version` with the latest Freemius tag only (criterion 3), while its README says it fails when the version exists. Settle which one holds.
- Open question: the means. (a) The action as published cannot meet criterion 5 (unpinned SDK clone and image) and holds account-wide keys. (b) A step of about 40 lines in `release.yml` calling the Freemius API, either through the PHP SDK pinned to a tag (it signs with key pairs; the plugin scope uses the product keys from the product's Settings → Keys) or directly with a product-scoped bearer token (Settings → API & Keys, as `docs/distribution.md` names it). CHECK both against Freemius's API reference. (b) keeps the credentials product-scoped and every dependency pinned; (a) is less code to own. If the owner wants (a), criterion 5 must be loosened explicitly.
- Open question: whether pre-release test builds (every release until entry 4.8) go to Freemius as `pending` versions, or only from the launch on; with the second answer, criterion 1 runs only when `prerelease` is gone from `release-please-config.json`.
- Open question: whether criterion 2 also runs story 54's `diff -r` (Freemius's Free build against `bin/build-free.php`) on every release, turning that one-off HITL check into a standing one.
- Assumption: `readme.txt` exists in the plugin before this story runs, since Freemius rejects a zip without it (found 2026-10-12 during story 54's HITL step; how it is added is decided with the owner there).
- Assumption: story 38 consumes `post-quality-manager-<version>-free.zip` from the same release run (a run started by `GITHUB_TOKEN` starts no other workflow, so its SVN job lives in `release.yml` or reads this run's artifact), adds this story to its `after` when refined, and switches the GitHub Release asset to the Free build at the first directory release (ADR 0014).
- hitl: the owner creates the GitHub secrets for the chosen means (developer keys: Freemius → My Profile; product keys: the product's Settings → Keys; bearer token: Settings → API & Keys) and checks the first deployed version in Freemius → Deployment.
