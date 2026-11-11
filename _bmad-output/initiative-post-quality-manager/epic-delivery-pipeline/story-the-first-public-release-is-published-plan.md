---
title: 'The launch PR: 4.0.0 is published as the first public release, with the Free build'
type: 'feature'
ticket: 8
created: '2026-11-11'
status: 'built'
baseline_revision: '5ce165de29e624a8bbca0f157ae0157dd1388ceb'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-first-public-release-is-published.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** 4.0.0 must be the first public GitHub Release: shown as Latest, not as a pre-release, with the Free build as its zip, and the docs and the Dashboard must send users to it. Today:
- the config marks every release a pre-release;
- the release job publishes the full build, which asks for a license key, before Freemius has built and checked the Free one;
- CI skips docs-only PRs, so a required check would wait forever;
- the feedback card has no public issue link.

**Approach:** One launch PR (step 3 of the story's order). It:
- sets the release-please config: `prerelease` removed, `release-as` 4.0.0, `last-release-sha` set to v3.13.3;
- moves `release.yml`'s publish step last, so it attaches the Free build;
- runs CI on every PR;
- adds an issue link to the feedback card's Bug kind;
- points the docs to GitHub Releases;
- renames `unreleased.md` to `4.0.0.md`;
- updates the records.

Its `feat:` commit is the releasable commit release-please needs: there is none since v3.13.3.

**Decisions:**
- Owner, 2026-10-08, confirmed 2026-10-28: the version is 4.0.0, set with `release-as`.
- Owner, 2026-10-28:
  - `main` requires the four CI checks;
  - CI's `paths-ignore` goes;
  - the owner merges release PRs past their pending checks as admin.
- Owner, 2026-10-28, ADR 0014 amended: the release carries the Free build Freemius generated, as `post-quality-manager-<version>.zip`, attached only once the job's Free build check passes.
- Owner, 2026-11-11: Discussions stay off.
- Adopted from the story's Notes (agent, 2026-10-29):
  - the publish step moves after the Free build check, so a failure leaves a draft;
  - `last-release-sha` is set as a safeguard, though the tag fallback worked on 2026-11-11.

## Boundaries & Constraints

**Always:**
- The release job's other steps keep their logic. Only the publish step moves, and it attaches the Free build under the release zip's name.
- LF line endings and Conventional Commits.
- The records change in the same PR (Definition of Done).

**Never:**
- merge, tag or push to `main`;
- change repository settings or branch protection (the owner's step after the switch);
- remove `release-as` (step 6, after the release);
- change the Freemius API calls;
- story 96's SVN deploy;
- `CHANGELOG.md` (release-please's file);
- bump versions by hand.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Release run, all green | the release PR merged | Freemius gets 4.0.0 as pending and the Free check passes. Then the release gets `post-quality-manager-4.0.0.zip` (Freemius's Free build) and the notes once, and is published as Latest, not as a pre-release | No error expected |
| Freemius or Free check red | upload, fetch or check fails | release stays a draft with its tag, no zip attached; artifacts as today | fix, re-run the job (nothing is uploaded twice); a red Free check ships the fix as the next patch |
| Publish step red | upload or edit fails | release stays a draft | re-run; `--clobber` replaces the zip, the draft body has no notes yet, so they are appended once |
| Manual dispatch | any ref | zip artifact only | no Freemius call, no release |
| Docs-only PR | touches only `memory/` | the four CI checks run and report | No error expected |
| Feedback card, Bug | user picks Bug | the security sentence as today, then a sentence linking GitHub's new-issue page, opening in a new tab | No error expected |
| Feedback card, Feedback | default kind | no security sentence, no link | No error expected |

</frozen-after-approval>

## Code Map

- `release-please-config.json`:
  - drop L5 `"prerelease": true`;
  - add `"release-as": "4.0.0"` to package `.`, where 3.0.0's sat (removed in 4c0cc84);
  - add `"last-release-sha": "6938f4c63100444d5b649a45fe31447aaeaae1a2"` at the top level. That is v3.13.3's commit; release-please 17.6.0 matches only the full id (`memory/delivery-pipeline.md` L62).
- `.github/workflows/release.yml`:
  - L1–22: rewrite the header comment (the new order).
  - L102–121: move the publish step after "Upload the Freemius Free build" (L263–270). There it copies `steps.freemius.outputs.free` to `$RUNNER_TEMP/release/$ZIP`, runs `gh release upload --clobber`, then the same notes and `gh release edit --draft=false --prerelease="$PRE"`. The `jq` line stays: a missing key reads as false.
  - L126–261: unchanged.
- `.github/workflows/ci.yml`:
  - L3–16 and L46–47: rewrite the comment.
  - L51–82: remove both `paths-ignore` lists. `push: branches: [main]` and `pull_request:` stay, and so do the jobs' `if:` (a push still runs only the build and PHP jobs).
- `src/dashboard/feedback.js`:
  - after L26: `export const ISSUES_URL = 'https://github.com/vincentheimann/post-quality-manager/issues/new/choose'`;
  - L285–292 (`pqm-feedback-security`): keep the string, then add "For anything else, you can also <a>open an issue on GitHub</a>." with `createInterpolateElement` and `<a target="_blank" rel="noreferrer" />`, as in `src/setup/steps.js` L369–383 (eslint comment included);
  - L1–11: the header comment names the link.
- `tests/js/dashboard-feedback.test.js` L269–282: extend the Bug row (link text, `href`, `target`); the Feedback kind has no link.
- `docs/release-notes/unreleased.md` → `git mv` to `4.0.0.md`, content unchanged.
- `README.md`:
  - L45, Installation step 1: download the zip from the [latest release](https://github.com/vincentheimann/post-quality-manager/releases/latest) and upload it under Plugins → Add New Plugin → Upload Plugin. It is the free version; Pro comes from postquality.com after checkout. A folder copied from the repository is the full build: it needs Build from source and asks for a license key at activation (ADR 0014).
  - L111: "publishes a CI-built zip" → "publishes the Free build Freemius generated from it".
- `docs/user-paths.md` L30: A3 = download the zip from the latest GitHub Release, upload it, activate.
- `.claude/skills/release/SKILL.md`:
  - L8 and steps 5–9: the new order. Step 7 runs before publishing, and a red step leaves a draft.
  - Step 3: drop the pre-release branch.
  - L22: becomes history plus the `release-as` rule.
- `AGENTS.md` L19 (managed block): the pre-release clause becomes "public since 4.0.0, the zip is the Free build", with a hand-edit note in the L4 header.
- `docs/distribution.md` L125: same as AGENTS.md.
- `memory/delivery-pipeline.md` and `memory/MEMORY.md`:
  - L14: the rename done;
  - L24: `prerelease` removed;
  - L43, L62: the new order;
  - L60: Discussions off (owner, 2026-11-11);
  - L18 (MEMORY.md): the description.
- Unchanged:
  - `readme.txt`, which already has `= 4.0.0 =` (story 95);
  - `PORTFOLIO.md` (its own refresh);
  - the story file (`bmad-ticket`'s).

## Tasks & Acceptance

**Execution:**
- [x] `release-please-config.json` -- the three keys -- the version, the public flag, a changelog that starts after v3.13.3
- [x] `.github/workflows/release.yml` -- move and change the publish step, rewrite the header -- a failure leaves a draft, and GitHub gets the Free build
- [x] `.github/workflows/ci.yml` -- drop `paths-ignore`, rewrite the comment -- every PR reports the four checks
- [x] `src/dashboard/feedback.js`, `tests/js/dashboard-feedback.test.js` -- the issue link and its Jest rows -- story 79 AC5's deferred link
- [x] `docs/release-notes/4.0.0.md`, `README.md`, `docs/user-paths.md`, `docs/distribution.md` -- rename and point to GitHub Releases -- new users find the zip
- [x] `.claude/skills/release/SKILL.md`, `AGENTS.md`, `memory/delivery-pipeline.md`, `memory/MEMORY.md` -- the new order and public releases -- the Definition of Done
- [x] At step 05, after the review, not during implementation: three commits on `feat/8-first-public-release`, then push and open a draft PR:
  - `feat(dashboard): link the public issue tracker from the feedback card`
  - `ci(release): publish 4.0.0 as the first public release, with the Free build`
  - `docs(release): point installs to GitHub Releases and record the launch order`

**Acceptance Criteria:**
- Given the branch, when the Verification commands run, then all pass, and the PR's four checks are green (`gh pr checks`).
- Given the launch PR merged, when the Release workflow runs on `main`, then release-please opens a release PR proposing 4.0.0, whose changelog section lists only commits after v3.13.3.
- Given the release PR merged (order step 4), when the job ends, then v4.0.0 is published as Latest, not as a pre-release. Its zip is Freemius's Free build, and `/release` checks it on a clean WordPress 6.6 site.

## Implementation Notes

- 2026-11-11, planning:
  - The session runs from the workspace root, so `bmad-build` was rendered by hand (route `full`) and its step files followed (workspace CLAUDE.md rule).
  - The branch guard found `main`. `feat/8-first-public-release` was derived from the intent and created at the baseline revision; rename it at the checkpoint if wanted.
- Checkpoint, owner, 2026-11-11: "Keep full plan" (about 2,900 tokens against the 1,600 guide: one goal, long because the Code Map names each record line), then "Approve and continue".
- Found before planning (2026-11-11 pre-public scan, chat): the agent's commits on the fresh repository (#1, #2) carried the owner's personal address. The owner kept them (2026-11-11), and both clones now commit with the GitHub noreply address.
- 2026-11-11, implementation (Opus subagent, no commits):
  - Records beyond the Code Map, each made false by this PR: `memory/delivery-pipeline.md` L8 (CI's `paths-ignore` described as live; it now holds the required-check fact `ci.yml`'s comment points to); `docs/distribution.md` L103 ("after the GitHub Release is published"), L107 ("the published zip" → the CI zip), L123 (Discussions: the owner's 2026-11-11 answer replaces the open OWNER cell). `memory/delivery-pipeline.md` L43 also carries the publish-last facts (the `make_latest` default, the fallback).
  - Line numbers: `prerelease` sat on L6 of the config, not L5.
  - The publish step was run under `bash -e` (GitHub's default shell) from the YAML's own `run:` block, with a stub `gh` that keeps the draft's body and assets and a stub `jq` for `.prerelease // false` alone (no jq on this PC). Green: the Free zip uploaded as `post-quality-manager-4.0.0.zip` beside a same-named full zip in the workspace, `--clobber`, edit `--draft=false --prerelease=false`, no `--latest`, the body = changelog + blank line + `4.0.0.md` byte for byte; an edit failure then a re-run appends the notes once; no notes file leaves the body; `"prerelease": true` gives `--prerelease=true`; a missing Free zip fails before any `gh` call.

- 2026-11-11, after implementation (orchestrator):
  - Diff read in full against the baseline. The Bug-kind Jest row (211 of 211) and the publish-step script were re-run and are green.
  - `AGENTS.md` L30 gives `release.yml`'s new order (flagged by the implementer).
  - Owner, 2026-11-11, recorded in `memory/delivery-pipeline.md` (L54, L60) and `MEMORY.md`:
    - the repository is public since 2026-11-11;
    - the salts were rotated before the switch;
    - the database account's password could not be changed, and the owner accepted that risk ("Accept, go public as is").
  - Left for release day (order step 6's records PR): `docs/distribution.md` L144 (the launch-plan row's state) and L122 (`main`'s protection, once the owner applies it).

- 2026-11-11, after the review, owner's request ("Don't forget to update the README; I also would like a reread with the Fable 5.2 model of the README"):
  - The README refresh moved into this PR, since it shares Installation's lines with it.
  - A Fable 5.2 subagent reread it (read only; the owner's model choice, an exception to the all-Opus rule). Its 16 findings were checked against the code before they were applied: Free and Pro up front, the setup guide's real buttons (Set up n8n, Run your first review, Finish setting up), the Set up by hand section, the Pro-only Settings tabs, the schedule's real options, a Help section, and Contributions per `CONTRIBUTING.md`.
  - The same reread found the old section name "Workflow File" in `docs/release-notes/4.0.0.md` (L11, L19) and `readme.txt` L30; both now say Set up by hand.
  - Left for a separate bug: the n8n error message `class-pqm-n8n.php` L462 still says "the Workflow File section" (asserted by three harnesses and one Jest file).
  - The issue chooser's dead WordPress.org entry (review finding 7) is removed here too.

- 2026-11-11, after the merges (orchestrator):
  - The owner merged #3 at 12:13 UTC and the release PR #4 at 12:18 UTC; `/release` steps 1 to 8 ran after the merge.
  - Versions read 4.0.0 everywhere; the changelog starts after 3.13.3 with the one `feat` line; `4.0.0.md` is in place.
  - Release run 43019155750 was green on every step, publish last, and v4.0.0 is Latest, not a pre-release, with one asset: the Free build.
  - The asset checks: root `post-quality-manager/`, `admin/build/` inside, the Free template only, Version 4.0.0, no premium marker.
  - Freemius has deployment 172983 pending, with Requires PHP 8.1 and the Pro root `post-quality-manager-premium`.
  - Order step 6 (`release-as` and `last-release-sha` removed) is on branch `chore/after-4-0-0`.
  - Install check (order step 5), 2026-11-11, on the owner's clean Local site "WordPress66" (WordPress 6.6, PHP 8.2.29, no other plugin):
    - `WP_DEBUG` and `WP_DEBUG_LOG` were turned on for the check;
    - the released zip (sha256 `776cd175…`) was installed and activated through WP-CLI; the Plugins screen shows Version 4.0.0;
    - Freemius's opt-in was skipped as a user would;
    - the plugin's Dashboard, Post Reviews, n8n Workflow and Settings pages, plus the WordPress dashboard, Plugins and New Post screens, all answered 200 with no PHP notice in the pages or the log (the one log entry was the agent's own WP-CLI error, made before the page loads);
    - Settings shows the Free tabs only (Connection, AI, Usage & Limits), and the n8n Workflow page reads "Not connected to n8n yet." with **Set up n8n**.
    The pages were loaded through a WP-CLI session for user `agent`, because the given password was refused; that session was removed afterwards, `wp-config.php` was restored as found, and the plugin stays installed.
  - Still open before `done`:
    - the owner sets Released in Freemius (`/release` step 9);
    - the two criteria only later merges can show: a docs-only PR reports the four checks, and the next `feat:` proposes 4.1.0.

## Plan Change Log

## Review Triage Log

**Pass 1, Quick lens (Opus), 2026-11-11.** 8 findings. Verdicts: high 0, medium 3, low 5, false 0, maybe-false 0. Routes: 6 patched (through the implementer), 1 rejected, 1 deferred. After the patches the orchestrator re-ran:
- lint;
- Jest, 211 of 211;
- the 24 harnesses, 0 failures, `release-header-harness.php` 37 of 37;
- the YAML parse and `git diff --check`.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | A red Free check at 4.0.0 cannot "ship the fix as the next patch" while `release-as` is set: the next release PR proposes 4.0.0 again, and `4.0.0.md` never reaches a published release | medium | patch | `release-as` overrides the computed version; the draft keeps tag v4.0.0 (`force-tag-creation`); `NOTES` is built from the version (`release.yml`). Patched: `/release` step 8 and `memory/delivery-pipeline.md` L24 say the fix PR removes `release-as` and `last-release-sha` and renames the notes file |
| 2 | Definition of Done: no committed harness covers the release and CI matrix rows (only a scratchpad run) | medium | patch | DoD: "extend one, or add one, whenever the plan carries an I/O & Edge-Case Matrix". Patched: Row 9 of `release-header-harness.php`. It checks for no `paths-ignore` and no `prerelease` key, and that the publish step comes after the Free check and takes `steps.freemius.outputs.free`. The implementer showed it fails on the baseline's files. Routed patch, not bad_plan: a test-only addition that changes no implementation code |
| 3 | `memory/delivery-pipeline.md` L20 still describes the publish-first order | low | patch | L20 contradicted L43. Reworded to the new order |
| 4 | `docs/user-paths.md` L19 says "no public repository" | low | patch | The repository has been public since 2026-11-11. Reworded |
| 5 | `docs/distribution.md` L144 says "the release PR drops `prerelease`" | low | patch | The launch PR drops it. Wording fixed; the row's State stays for release day |
| 6 | README Installation links `releases/latest`, which is 404 until 4.0.0 is published | low | reject | Exposed only between the launch PR's merge and the release's publication, both merged by the owner in one sitting. A failed run has the by-hand fallback (`/release` step 5). The fix would split the README change into a post-release PR, more than a direct correction |
| 7 | `issues/new/choose` lists "Support for the free plugin", which points to a WordPress.org forum that does not exist before story 38 | medium | defer | Pre-existing (`.github/ISSUE_TEMPLATE/config.yml`, story 48), exposed by the switch to public, not by this diff. Fixed in this PR's README commit, which removes the entry; story 38 re-adds it with the forum (`deferred-work.md`) |
| 8 | `/release` steps 1 and 4 do not say that release PRs run no CI and are merged past the pending required checks | low | patch | Owner decision 2026-10-28; an agent reading `gh pr checks` would report a false blocker. Patched: one sentence in step 4 |

Also in the patch round, at the orchestrator's request (records, not findings), the owner's step 5 of 2026-11-11, read back through the API:
- two rulesets on `main`:
  - "main: no force push": deletion and force pushes blocked, no bypass;
  - "main: CI checks": the four checks, with a bypass for the admin role;
- no classic protection;
- private vulnerability reporting, secret scanning and push protection on.

They went into `docs/distribution.md` L117, L122, L124 and `memory/delivery-pipeline.md` L60.

## Design Notes

- **Why the step moves instead of a new `publish` job:** moving the step is the smallest change. A separate job would need the Free artifact downloaded again and more YAML (the story's Notes weighed both). The cost: GitHub now waits on Freemius, and a red Free check means the next patch version.
- **The publish script runs only at a real release,** and a release run uses the workflow file of its own merge. So before the PR, the script runs locally under `bash` with a stub `gh` and a fake Free zip. If the step still fails at 4.0.0, the draft can be published by hand: attach the Free build artifact under the zip's name and untick draft. The release skill names that fallback.
- **Latest without a flag:** GitHub's "Update a release" says `make_latest` "Defaults to true for newly published releases", and prereleases cannot be Latest (docs read 2026-11-11). So `gh release edit` needs no `--latest`.
- **The link goes to `issues/new/choose`,** which offers the bug and feature templates and the security policy link (story 48).
- **What the release run exposes once public (recorded, not changed):** the run's artifacts (the full zip, Freemius's Pro and Free builds) are kept 90 days and can be downloaded by any signed-in GitHub user. The same code is public in the repository, so nothing new is exposed.

## Verification

**Commands:**
- `npm run lint:js` and `npm run test:unit` -- expected: pass, with the new feedback rows.
- `for f in tests/harness/*-harness.php; do php "$f" || echo "FAIL $f"; done` (with `-d sys_temp_dir=<scratchpad>`) -- expected: no FAIL.
- Parse both workflow files as YAML, and `bash -n` each changed `run:` block -- expected: valid.
- The moved publish script under `bash`, with a stub `gh` that logs its arguments and a fake `-free.zip` -- expected: it uploads `post-quality-manager-4.0.0.zip` and edits with `--draft=false --prerelease=false`.
- `npm run build` -- expected: `admin/build/` written, with the new string in `index.js`.

**Manual checks (if no CLI):**
- After the merge: the release PR proposes 4.0.0 and its changelog starts after v3.13.3. After the release: `/release` steps 5–9, and the install check on the owner's clean WordPress 6.6 Local site (order step 5).
