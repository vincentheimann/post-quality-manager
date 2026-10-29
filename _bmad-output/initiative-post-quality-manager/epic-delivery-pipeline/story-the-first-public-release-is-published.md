---
id: 8
type: story
title: "The first public release is published"
parent: epic-delivery-pipeline
covers: [CAP-1, CAP-2]
after: [7, 9]
refined: true
hitl: true
risk: medium
---

# The first public release is published

## Description

Post Quality Manager 4.0.0 is published as the first GitHub Release that is not a pre-release, from the fresh public repository chore 87 creates. Anyone can download it without signing in, it installs cleanly on WordPress 6.6, and the README and user paths send new users to it.

## Acceptance Criteria

Verify: GitHub Release v4.0.0 is reachable without signing in, shown as Latest and not as a pre-release, with `post-quality-manager-4.0.0.zip` (the Free build Freemius generated, checked by the release job) and the release notes in its body; that zip installs and activates with `WP_DEBUG` on and no notices on a clean WordPress 6.6 site showing 4.0.0; `main` refuses a force push and requires the four CI checks, a docs-only PR reports them and can be merged, and the owner merges a release PR past them as admin; private vulnerability reporting is on; the Dashboard feedback card links the public issue tracker; after `release-as` is removed, the next `feat:` merge opens a release PR proposing 4.1.0.

## References

- parent — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/epic-delivery-pipeline.md (Notes: the 4.0.0 decision, the gates, story 48's step)
- gate — _bmad-output/backlog/chore-history-names-the-new-project-name-at-go-live.md (chore 87: the fresh repository and the history rewrite)
- gate — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44, through bug 93)
- decision — docs/adr/0014-two-builds-from-one-source.md (release order, amended 2026-10-28: the Free build on GitHub from 4.0.0)
- data sheet — docs/distribution.md, launch plan row 0 (GitHub)
- skill — .claude/skills/release/SKILL.md (the public-launch paragraph: `release-as`, `prerelease`, `unreleased.md`)
- issue link — _bmad-output/backlog/story-dashboard-feedback-block.md (story 79: the link joins at this entry)

## Notes

- Decision (owner, 2026-10-08, confirmed 2026-10-28): the version is 4.0.0, set with `release-as` (epic Notes).
- Gates that `after` cannot express (epic Notes): story 44 done, through bug 93 (with the exception the epic's Notes record); chore 87 done. Chore 87's review (AC8) carries the open consent of the firm the portfolio names.
- Assumption (agent, 2026-10-28): the order is
  1. chore 87 on a fresh private repository: rewrite, the owner's review, first push, secrets, one CI run and one release-please run;
  2. the owner switches it to public, then story 48's step: branch protection, private vulnerability reporting, Discussions;
  3. the launch PR: `prerelease` removed, `release-as` 4.0.0, `docs/release-notes/unreleased.md` renamed `4.0.0.md`, README installation and `docs/user-paths.md` A3 point to GitHub Releases, the issue link in the feedback card, CI's `paths-ignore` removed, `release.yml` attaching the Free build as the release zip;
  4. the release PR, `/release`, the owner's merge; the release job publishes the GitHub Release and uploads 4.0.0 to Freemius as pending (setting it Released is `/release` step 9, the owner's);
  5. the install check on the clean WordPress 6.6 site the owner creates in Local (it can be the one story 38's pre-production pass uses);
  6. `release-as` removed.
- Fact (GitHub Docs, read 2026-10-28): standard GitHub-hosted runners are free on a public repository. A required check whose workflow `paths-ignore` skipped stays Pending and blocks the merge; GitHub's documented fix is "Avoid requiring workflows that can be skipped". Events made with `GITHUB_TOKEN` start no workflow, so release-please's PRs get no CI run and cannot report a required check.
- Decision (owner, 2026-10-28): `main` requires the four CI checks (Build admin app, PHP lint and harness sweep, Plugin Check, Free build Plugin Check) and blocks force pushes. The launch PR removes CI's `paths-ignore`, free on a public repository, so every PR reports the checks. "Do not allow bypassing" stays off: the owner merges release-please's PRs, which run no CI, past the pending checks as admin, and only those. No new secret. Read each setting's current value through the API before applying it (`memory/lessons.md` 2026-10-08).
- Decision (owner, 2026-10-28; ADR 0014 amended the same day): GitHub Release 4.0.0, and every release after it, carries the Free build Freemius generated as `post-quality-manager-<version>.zip`, attached only once the release job's Free build check passes. The full build asks for a license key at activation (story 65's walks), and before the listing GitHub is where a free user downloads the plugin. Story 38 no longer makes this switch. Open for the build plan: what the published release carries when the Freemius step fails, since today the release is published before the upload.
- Open question (for the build, not the owner): how release-please finds the previous release on the fresh repository, where chore 87 carries the tags but no GitHub Release objects. Read its source before the launch PR, so the 4.0.0 CHANGELOG does not list the whole history (`last-release-sha` in its config is the candidate).
- Open question: whether Discussions are on (story 48 left it to the owner).
- Refined 2026-10-28 (owner approved): pulled from the entry, the order, the four checks on `main`, the Free build on GitHub; the two build questions stay open for the plan.
- Answered 2026-10-29 (agent, read in the source; the facts are in `memory/delivery-pipeline.md`), for the launch PR's plan to adopt or overrule:
  - The previous release on the fresh repository: release-please finds no GitHub Release object there and falls back to the carried tags. It looks for `v<manifest version>` and stops the changelog at that tag's commit, so the manifest and the newest carried tag must agree. As a safeguard, the launch PR sets `last-release-sha` to that tag's full 40-character id on the rewritten history (a short id never matches) and removes it with `release-as` at step 6. `bootstrap-sha` is not needed. At least one `feat:` or `fix:` commit after the tag is needed, or no release PR opens. The 3.x `/commit/` links in `CHANGELOG.md` are chore 87 AC6's to rewrite.
  - A failed Freemius step: `release.yml` publishes the release, with the full build attached, before the Freemius step runs (L101 before L124). So today every failure from the upload to the Free build check leaves a published Latest release whose zip asks for a license key, and no Free build. Recommended: move the publish step after "Check the Free build Freemius generated" and attach the Free build there, so a failure leaves a draft. The cost is that publishing on GitHub then waits on Freemius, and a failed Free build check ships the next patch version instead. The other option is a separate `publish` job (`needs: package`) that downloads the Free artifact: least privilege, but more YAML.
  - Found on the way, today's behaviour: re-running the release job after a Freemius failure appends the release notes to the body a second time (L111 reads a body that already holds them). The recommended order fixes it.
