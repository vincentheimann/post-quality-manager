---
type: epic
title: "Releases and the workflow template are built and checked by CI"
parent: initiative-post-quality-manager
covers: [UV7]
after: []
assignee: ""
risk: medium
---

# Releases and the workflow template are built and checked by CI

## Description

Nothing has ever been released: no tag, no GitHub Release, and `3.0.0` is declared in three places but installing the plugin means copying a folder by hand. CI exists (`.github/workflows/ci.yml`: JS lint and build, PHP 8.3 lint, the harness sweep) but nothing turns a merge into a release, nothing checks the n8n workflow template, the plugin versions or the PHP 8.0 floor, PHP translations do not load from the plugin folder, and the WordPress.org Plugin Check has never run. This epic makes a release a merge that produces a zip CI has checked, with release-please on Conventional Commits, and puts the directory's own check in CI ahead of the WordPress.org submission (backlog story 38). Incepted 2026-10-07 from `spec-delivery-pipeline` (CAP-1…CAP-7).

## Outcome

Cutting a release is merging one PR, and a broken template, out-of-step versions, a parse error on the declared PHP floor (8.1 since story 118, ADR 0018; 8.0 before) or a Plugin Check error never ship; the signal is the first public GitHub Release carrying a CI-built zip that installs and activates on a clean WordPress 6.6.

## Requirements

`spec-delivery-pipeline/spec-delivery-pipeline.md` (+ `brownfield.md`) owns the requirement ids CAP-1…CAP-7; all map to UV7.

## Done when

1. Merging to `main` opens or updates a release PR; merging it creates the tag and the GitHub Release with the CI-built zip attached, release notes from `docs/release-notes/<version>.md` included (CAP-1, CAP-2).
2. CI fails on a broken workflow template, on plugin versions out of step, on PHP syntax the declared floor (8.1 since story 118, ADR 0018; 8.0 before) cannot parse, and on a Plugin Check error (CAP-3, CAP-4, CAP-6, CAP-7).
3. Translations in the plugin's `languages/` folder load on PHP admin pages and on React pages (CAP-5).
4. The first public release is published on GitHub and its zip installs and activates, with `WP_DEBUG` on and no notices, on a clean WordPress 6.6 (production delivery).

## Boundaries

GitHub Actions with `GITHUB_TOKEN` only, the template validator, the version check, Plugin Check and the translation loading path. Not: WordPress.org packaging, `readme.txt`, trademark and asset work (backlog story 38); the in-dashboard update channel (parked; story 38 provides it); real Freemius licensing (deferred-work); writing translations or a `.pot`; a JS unit-test path (open deferred-work decision); the content of the release notes (backlog story 15).

- Touch point: `post-quality-manager/post-quality-manager.php` header lines — release-please annotates `Version`, entry 6 adds `Domain Path`; owner: entry 1, then entry 6.

## References

- spec — _bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md (+ brownfield.md), the requirement source CAP-1…CAP-7
- parent — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Requirement UV7
- skill — .claude/skills/release/SKILL.md, the manual steps CI replaces (entry 1 aligns it)
- template — post-quality-manager/templates/n8n-workflow.json, and tests/harness/template-failure-harness.php (bug 31) as the existing node-runner pattern
- ci — .github/workflows/ci.yml, the jobs the new workflows sit beside
- backlog — _bmad-output/backlog/story-upgrade-release-notes-pack.md (story 15, gates entry 8 in prose) and _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38, needs this epic)

## Notes

- Decision (owner, 2026-10-07): tracer bullet = entry 1 (release PR + CI-built zip via a manual run); entries 3 and 4 run beside it; 2 and 6 follow it; 5 (Plugin Check fixes) waits for 2, 4 and 6 because its fixes may touch any plugin file.
- Decision (owner, 2026-10-07): the refactor sweep (entry 7) comes before the 3.0.0 cut (entry 8) so the release ships the swept code — the one entry after the sweep is the cut itself.
- Decision (owner, 2026-10-07): backlog story 15 gates entry 8 (the owner merges the release PR only after `docs/release-notes/3.0.0.md` is committed); `tickets.py` cannot express a backlog → epic prerequisite, so this line and entry 8's description carry it. Story 15 is also worked after entry 1 — both edit `.claude/skills/release/SKILL.md`.
- Decision (owner, 2026-10-07): Plugin Check lands in two slices — entry 4 reports (non-blocking) and triages, entry 5 fixes every error and makes the check blocking; "split, never shrink".
- Decision (owner, 2026-10-07): first release = 3.0.0; release-please draft release published by the release workflow after the version check; release notes as a committed file; no empty `languages/` folder (spec memlog 2026-10-07).
- Decision (owner, 2026-10-08): 3.0.0 shipped early as a pre-release test build, and every release stays a pre-release until the public launch, so entry 8 becomes the first public release. Story 15 still gates it.
- Decision (owner, 2026-10-08, story 15 refinement): story 15's notes accumulate in `docs/release-notes/unreleased.md` and `/release` moves them to `docs/release-notes/<version>.md` before the owner merges the launch release PR.
- Decision (owner, 2026-10-08): the first public release is 4.0.0 (a major: the quota, the role rights and the workflow re-import change existing sites), set through `release-as` in entry 8. The repository goes public in entry 8, because a release on a private repository cannot be downloaded without signing in; entry 9 (LICENSE file, history secret scan, `_archives/turbo-drill/` removed) runs before it, after the sweep.
- Deferred: the update channel, `readme.txt`, `src/` in the zip vs a repository link, WordPress.org assets — backlog story 38.
- The 2026-07-23 local prototype of the template check was not committed; entry 3 recreates it.
- Open (owner, 2026-10-09, backlog spike 43 D1): whether real licensing (backlog story 44) gates entry 8. As built, 4.0.0 would ship with the stub license (any key unlocks Pro) and a License section asking for a key nobody can buy. Backlog story 48 (repository issue templates, security policy, branch protection) is best merged before entry 8 turns the repository public.
- Decision (owner, 2026-10-09, `/prioritize`): spike 43 runs first, then story 48, then entry 8. Real licensing (story 44) gates backlog story 38 (the directory listing), not entry 8, unless spike 43's D1 decides otherwise; a GitHub-only release reaches almost nobody, so the stub license is tolerated there and must be gone before the directory submission.
- Decision (owner, 2026-10-09, spike 43, ADR 0013): D1 = real licensing before the first public release. Backlog story 44 now gates entry 8, beside story 15; `tickets.py` cannot express a backlog → epic prerequisite, so this line carries it. Entry 8 therefore also waits on a Freemius account and a working test purchase; the owner inputs story 44 needs (seller, prices, mailbox, response time) were given the same day.
- Decision (owner, 2026-10-24, at story 66's PR #189): the git history is rewritten before entry 8 turns the repository public. Every mention of the old name (the owner's blog the prototype was built for, in every spelling and its short form) becomes the new project name; the rewrite waits for the go-live and runs once, as entry 8's last step before the switch. Backlog chore 87 carries it and gates entry 8; `tickets.py` cannot express that, as for story 15 above. The history question in full is in `memory/delivery-pipeline.md` (entry 4.9's line).
- Step added to entry 8 (story 48, 2026-10-09): right after the repository is switched to public, apply what the free plan refuses on a private repository — branch protection on `main` requiring the four CI checks (Build admin app, PHP lint and harness sweep, Plugin Check, Free build Plugin Check; job names since 2026-10-15) with force pushes blocked, private vulnerability reporting on, Discussions as the owner decides. Read each current value through the API first (`memory/lessons.md` 2026-10-08). CI is path-filtered since 2026-10-15 (`plan-ci-minutes.md`), and GitHub leaves a required check of a skipped workflow pending, so a docs-only PR would be blocked: require the checks only together with a way for skipped runs to report, or decide at 4.8.
- Decision (owner, 2026-11-11, chore 87 AC8): before the switch to public, the owner rotates the production site's database password and salts (and the Pro license key if wished): the public records of story 98's walk (2026-11-03) then describe a closed exposure. Since chore 87 the repository is the fresh one, holding the rewritten history; the old one is `vincentheimann/post-quality-manager-archive` (`memory/delivery-pipeline.md`).
- Decision (owner delegation, best practice, 2026-10-28): backlog bug 93 (a lapsed Pro license shows Free, with no renew link) fails story 44's AC2, and story 44 gates this entry (ADR 0013 D1). Bug 93 is built before entry 8, and story 44 is marked done after its walk. If bug 93 is the last open gate, entry 8 goes without it: plans are yearly with no trial (ADR 0013 D4), so no license can lapse within a year of the first sale, and the fix ships in the next release.
- Decision (owner delegation, best practice, 2026-10-28): the first public release stays 4.0.0 (the 2026-10-08 decision). A version number never goes backward: Freemius already holds 3.7.0–3.9.0 (3.10.0 next), the owner's site runs a 3.x build, and WordPress and Freemius offer an update only to a higher number. The major marks the first public, stable release and what existing sites go through on the way (stored names, roles, workflow re-import). Entry 8's launch PR sets it with `release-as`, on the fresh repository (backlog chore 87).
