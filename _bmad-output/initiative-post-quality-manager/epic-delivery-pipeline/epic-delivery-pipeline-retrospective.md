---
epic: epic-delivery-pipeline
date: 2026-11-11
verdict: accepted-with-open-items
criteria: declared
headless: false
---

# Retrospective — epic delivery-pipeline

Run interactively on 2026-11-11 by the session model (Opus 5.5), with the BMad retrospective skill rendered by hand (sessions run from the workspace root). Three Opus subagents did the work:
- the evidence gathering and the aggregate views;
- the adversarial and edge-case lenses;
- the verification-gap lens.

The two lenses read the epic's delivery surface as it stands on `main` at b2294d3, weighting the boundaries between tickets. They did not read one diff: nine builds by separate sessions landed it over a month, beside other epics.

The orchestrator re-read in the tree every claim an action item relies on; those findings are marked "verified" below. The lenses' full reports stayed in the session scratchpad; this document is the record. Paths are relative to the repository root.

The owner asked for the retrospective ("Yes") at the end of a long session. The invitation for going-in concerns was not made, so no concern was weighted.

## Epic summary

- **Epic:** `epic-delivery-pipeline`, "Releases and the workflow template are built and checked by CI" (UV7; requirements CAP-1…CAP-7 in `spec-delivery-pipeline/spec-delivery-pipeline.md`).
- **Tickets:** all nine are `done`, with none at `built` and `pending_tickets` empty. In build order: 4.1 tracer bullet, 4.2 version and PHP-floor guard, 4.3 template guard, 4.4 Plugin Check reports, 4.5 Plugin Check blocks, 4.6 translations, 4.7 refactor sweep, 4.9 repository ready to go public, 4.8 first public release (4.0.0, 2026-11-11).
- **Ranges:** every plan's `baseline_revision` resolves on the rewritten history (chore 87). In order of the baselines' ancestry:

  | Ref | Range | Commits | Attributed to the epic | Review record |
  |---|---|---|---|---|
  | 4.1 | dca18cf..7b167c7 | 13 (1 merge) | 7 + merge d4dff03 | quick lens, 3 findings |
  | 4.6 | 7b167c7..e6b3ffa | 3 | 3 | self-review, 1 |
  | 4.2 | e6b3ffa..8e33fff | 4 | 4 (includes f24067f, unplanned) | self-review, 0 |
  | 4.3 | 8e33fff..32867ce | 3 | 3 | self-review, 1 |
  | 4.4 | 32867ce..d64b1af | 4 | 4 | self-review, 0 |
  | 4.5 | d64b1af..ca8e1a8 | 2 | 2 | **no Review Triage Log** |
  | 4.7 | ca8e1a8..c247a60 | 7 | 2 | quick lens, 4 |
  | 4.9 | c247a60..5ce165d recorded (291 commits), **cut to ..416764d** | 13 | 4 | quick lens, 6 |
  | 4.8 | 5ce165d..b2294d3 (inferred, HEAD) | 12 | 12 | quick lens, 8 |

  Across dca18cf..b2294d3 there are 339 commits, of which 41 non-merge commits and 1 merge are the epic's. Four of those are release-please commits, counted as the pipeline's output: 3.0.0, 3.1.0, 3.1.1 and 4.0.0. Releases 3.0.1 and 3.2.0 are left out.

  The epic's churn is 77 files, +2,879 −400. No binary revision falls in an epic range. The one merge, d4dff03, was measured: it brings in only its baseline's own change, with no conflict resolution.

  Commits were attributed by subject and by the files they touch, because other epics and backlog tickets landed on the same `main`. Ranges 4.1 (5 of 12 cut) and 4.7 (5 of 7 cut) lost unrelated commits.
- **Who built the release path:** mostly not this epic. Its share of `release.yml` is +156 −32 of +335 −53; story 58 (Freemius) and others wrote the rest. `bin/build-free.php`, `bin/compare-php-trees.php`, `bin/zip.js` and the free-build harness came from other tickets.
- **Evidence inventory:**
  - available: the epic file, the spec, every plan and the story file of 4.8;
  - read: the four PR records of 4.8 (#3–#6), release v4.0.0 and run ids from 2026-11-11, all read-only through `gh`;
  - missing:
    - session logs were not mined;
    - run ids from before 2026-11-11 are gone with the old repository and return 404 (for example run 37781135834);
    - Freemius's dashboard was read only through the owner's report.

## Findings

Each finding gives its source and its disposition: fix now, defer or accept.

### Aggregate view — spec to implementation (gathering subagent; A1 and CAP-5 verified)

| Item | Result | Evidence |
|---|---|---|
| CAP-1 | met | release.yml:39-52; release PR #4; Release run 43025586463 opened no PR for chore commits. The next `feat:` → 4.1.0 is not observed yet. |
| CAP-2 | met, with an owner-decided deviation | The asset is the Free build Freemius generates (`release.yml:259-270`, ADR 0014 amended 2026-10-28), not "the CI-built zip". The install check of 4.0.0 used WP-CLI, not the upload screen. |
| CAP-3 | met | Row 6 of `tests/harness/release-header-harness.php` (L106-111). The failure case was shown by hand once. |
| CAP-4 | met | `tests/harness/template-guard*` on the real templates (L146-233). One of eight seeds was proven in CI; there are no committed negative rows. |
| CAP-5 | superseded | Story 95 (e6098fe, "translations come from WordPress.org language packs") removed the `languages/` path that CAP-5 and Done when 3 describe. Row 4 now forbids it (harness L63-87). Runtime loading was proven once, on story 95's walk. |
| CAP-6 | met | ci.yml:105-119 (8.1 lint and sweep), Row 8 (8.1 since story 118, ADR 0018). |
| CAP-7 | partly | Plugin Check blocks on every PR (ci.yml:142-195), not on "every push" as the spec says. The seeded-error result was never recorded. |
| Done when 1 | met, as amended | See CAP-2 (A1 below). |
| Done when 2 | met | CAP-3, CAP-4, CAP-6 and CAP-7 rows. |
| Done when 3 | met, as amended | Superseded by story 95, an owner-approved change; the wording is stale. |
| Done when 4 | met | Run 43019155750 published v4.0.0 as Latest, not a pre-release, with the Free build as its one asset. The 4.0.0 zip installed and activated on a clean WordPress 6.6 (PHP 8.2.29) with `WP_DEBUG` on and no notice (plan 4.8, Implementation Notes). |

- **A1 · fix now (records):** "the CI-built zip" in Done when 1, the Outcome and CAP-2 no longer describes the asset, which since 4.0.0 is the Free build Freemius generates from the CI zip (`release.yml:259-270`, verified). The epic and spec were never amended.
- **A2 · accept, update the records:** "GITHUB_TOKEN only" (epic Boundaries L34, spec L50) stopped being true with story 58. The release path calls Freemius's API with `FREEMIUS_API_TOKEN` (`release.yml:110-189`).
- **A3 · fix now (one sentence):** a manual dispatch zips the full build, which asks for a license key on activation, not the Free build that ships (`release.yml:26-27`; `.claude/skills/release/SKILL.md:24` does not say so).
- **S1 · fix now (records):** the epic's Notes keep lines that later decisions answered:
  - L59, "Open: whether real licensing gates entry 8", was answered by L61 (spike 43 D1), and stories 44, 48 and bug 93 are done;
  - L64's rotation line does not say that the database password was kept, a risk the owner accepted (plan 4.8 L154-157).
- **S2 · recorded here:** the 4.8 criterion "a docs-only PR reports the four checks" is met. PR #6 (records only) passed all four (run 43025887721). The other later criterion, the next `feat:` proposing 4.1.0, stays open; `/release` step 1 reads it on that release PR.
- **S3 · fix now (low):** `docs/distribution.md` L145 and L156 are stale.

### Aggregate view — duplication, god files, patterns (gathering subagent)

- **D1 · defer (chore):** the build-and-check-assets sequence exists three times and has drifted: `ci.yml:60-86`, `release.yml:63-88` and `bin/zip.js:54-59`. d5725d2 added the `style-index.css` check to `ci.yml` only (F10 below). Entry 7 deferred a shared step "until a third copy appears" (plan 4.7 L73). That happened (f136b3a), and nothing was filed.
- **D2, D3 · accept:** duplicate steps inside `ci.yml`, and three ways of parsing the plugin header. The template placeholder list matches the code (checked, clean).
- **G1 · defer, before story 96:**
  - `release.yml` grew from 99 to 282 lines, one job with about 127 lines of inline bash that no test reaches;
  - story 96 (the WordPress.org deploy) adds to it, so its scripts should move into `bin/` with harness rows first.
- **G2 · fix the stale lines now, defer the split:**
  - `memory/delivery-pipeline.md` is 34.9 KB, with 11 lines over 1,000 characters (the longest is 5,344);
  - L13 and the start of L49 still say the release carries the CI zip.
- **G3 · accept:** `release-header-harness.php` holds 9 rows from 7 sources under a name that fits 4 of them.
- **P1 · accept, process lesson (L1):** proofs of the failure path are missing from the plans:
  - the 4.2 seeds never ran in CI;
  - the 4.5 seed result and the 4.5 and 4.6 manual checks were never recorded.

  Since 5e0ac3a a seed proof needs a PR.
- **P2 · accept:** review evidence is uneven, and 4.5 has no triage log.
- **P3 · accept:** some work ran outside the plans; the decision is recorded in the epic's Notes (L54):
  - f24067f, 4c0cc84 and 69ba979 have no plan;
  - 3.0.0 shipped against the 4.1 plan's "nobody merges" line (L31, L111) and the spec's story 15 gate (L48).
- **P4 · accept:** the harness checks workflow files by matching strings. Mixed action pinning follows ADR 0017 L32.

### Lens — verification gaps (subagent; VG-2 verified)

- **VG-2 · medium, fix now:** the records say release PRs get no CI, but on the fresh public repository the release PR got a CI run.
  - The claim stands in `ci.yml:9-13`, `.claude/skills/release/SKILL.md:13` (step 4) and `memory/delivery-pipeline.md` L8.
  - On PR #4, CI run 43018561352 is a `pull_request` run on the release-please branch. Its second attempt was started by the owner's account and finished green (verified with `gh`).
  - Nothing else runs Plugin Check on the release commit, so "a Plugin Check error never ships" depends on that unprompted approval.
- **VG-1 · medium:** CAP-5 and Done when 3 are covered by A1's reconciliation, through story 95. Entry 6's own walk has no recorded result, and the meta-box script's translations were never walked (F18). Accept the one-time runtime proof.
- **VG-3 · low, defer:** none of CAP-4's four failure cases has a committed negative test.
- **VG-4 · low, accept:** the failure cases of CAP-3 and CAP-6 were shown once, by hand. Both checks fail closed, as the lens's scratch seeds confirmed.
- **VG-5 · low, accept and amend the spec:** CAP-7's seeded-error run has no recorded result, and Plugin Check runs on PRs only.
- **VG-6 · low, fix now:** Row 9 looks only for the literal `paths-ignore`. A `paths:` filter passes 37/37 in a scratch copy, yet it would leave docs-only PRs' required checks pending.
- **VG-7 · low, fix now or defer:** nothing stops `release-as` or `last-release-sha` coming back.
- **VG-8 · low, accept:** the install check is a manual `/release` step at each release.
- **VG-9 · low, accept and record:** plans' expected harness counts are stale, and entry 9's AC2 grep now also hits `memory/lessons.md:82`.

### Lens — adversarial and edge cases (subagent; 19 findings, 16 on a boundary between tickets; F3, F5 and F6 verified)

- **F1 · medium, fix now:** every release guard runs after release-please has pushed the tag and the draft. A re-run uses the tag's code and the tag's workflow file, so re-running only fixes outside causes: a secret, the Free plan, an outage. Yet `.claude/skills/release/SKILL.md:14,16` promises "fix the cause and re-run" for any red step, zip content included (`release.yml:22-23`, `:70-71`).
  - Proposed: reword the skill.
  - With VG-2, `/release` step 4 becomes "approve the release PR's CI run and merge on green", so Plugin Check and the sweep run on the release commit before the tag.
- **F2 · medium, fix now:** when any version stays a draft, its notes file, its changelog section and its pending Freemius deployment are left behind (`release.yml:266`, `:273-276`). The skill (L17-18) covers only the `release-as` case.
- **F3 · medium, fix now (verified):** the `prerelease` key contradicts itself, and a suffixed `release-as` (an RC) would publish as Latest.
  - Row 9 forbids the key (`release-header-harness.php:170`), and that harness runs inside the release job (`release.yml:71`). Yet `.claude/skills/release/SKILL.md:22` says the publish step "still applies the key when set". Setting it would therefore leave the release a draft.
  - With no key, the publish step passes `--prerelease=false`.
- **F4 · medium, defer (harness row):** the four required check names match the job names today, but nothing in the repository guards them (ruleset 27026970 vs `ci.yml:57,95,143,165`). Two changes would leave a check pending forever:
  - a rename;
  - a `paths:` or `branches:` filter.
  
  With the admin bypass and the habit of merging release PRs past pending checks, nobody would notice.
- **F5 · medium, fix now (verified):** the release job has no `setup-php` step. It runs the harness, `bin/build-free.php` and `bin/compare-php-trees.php` on whatever PHP the runner image ships (`release.yml:71`, `:231`, `:237`; run 43019155750 ran on ubuntu-26.04), while CI pins 8.1 and 8.3.
- **F6 · medium, fix now (verified, records):** a site that installs the Free build from GitHub gets no update notice, security fixes included, until the WordPress.org listing.
  - Since 4.0.0 the GitHub zip is the Free build (ADR 0014:20).
  - Freemius's SDK starts its updater only on a premium install, or when the premium plugin is present (`post-quality-manager/vendor/freemius/includes/class-freemius.php:4653-4669`).
  - `README.md:50` and `SECURITY.md:5` do not say so.
- **F7 · low, fix now:** a missing `<version>.md`, or an `unreleased.md` that still holds lines, publishes with no warning (`release.yml:273-276`).
- **F8 · low, fix now (doc), guard deferred:**
  - `/release` step 1 does not list `readme.txt`'s Stable tag;
  - the readme changelog is hand-written and unguarded (`readme.txt:75-80`).
- **F9 · low, fix now (doc):** the "remove `release-as`" instructions are stale since 1a4bf5e (`.claude/skills/release/SKILL.md:17,22`).
- **F10 · low, fix now:** the release build does not check the shared kit CSS in `style-index.css`, which CI checks (`ci.yml:79-86` vs `release.yml:83-88`).
- **F11 · low, defer:** CI's PHP job builds nothing, so harness rows that need the built files always skip, in CI and at release (`ci.yml:94-140`, `meta-box-harness.php:140-141`).
- **F12 · low, fix now:** the manual fallback invites attaching the full build: the full-build artifact already carries the release asset's name (`release.yml:98-102` vs `:269-270`).
- **F13 · low, defer:** if the Freemius upload succeeds but the switch to pending fails, a re-run never retries the switch. It also overwrites the zip artifact of what Freemius got (`release.yml:155-160`, `:104-105`).
- **F14 · low, accept:** after a red Free check no Free artifact is kept, though the skill says both builds come back (`release.yml:247-254`).
- **F15 · low, accept:** the comparer ignores every parenthesis, so a precedence change compares as identical (`bin/compare-php-trees.php:148-150`). Freemius's re-print forces that choice.
- **F16 · low, fix now:** nothing checks that the version in the files equals the tag or the manifest (`release.yml:93`, `:263`; harness L106-111).
- **F17 · low, defer:** re-running after a publish, or after a hand-publish, appends the notes twice, and hand-publishing an old draft makes it Latest (`release.yml:271-282`).
- **F18 · low, defer:** the meta-box script's translations have no fixture (harness L86, L98-104; `tests/i18n/`).
- **F19 · low, defer:** two hardening gaps matter more now that the repository is public (`release.yml:43-52`, `:59-65`, `:77-78`):
  - the third-party release-please action is unpinned and holds write scope;
  - the write token stays in the checkout while `npm ci` runs install scripts.

The lens confirmed as clean:
- the four required checks match the job names exactly;
- the publish step is last, runs only when every step before it passed, and attaches the Free build;
- v4.0.0 is Latest with one asset, and its notes were appended once;
- the template version is tied to the plugin constant;
- the PHP 8.1 notice reached users in `4.0.0.md`.

One of the lens's "could not check" lines is wrong and was dropped: the WordPress 6.6 install check of 4.0.0 is not open. It passed and is recorded on `main` (ccd33c1).

## Behavior verification

- **Observed on the real system, 2026-11-11:**
  - release run 43019155750 was green on every step, with publish last, and v4.0.0 is Latest, not a pre-release, with one asset, the Free build;
  - the 4.0.0 zip installed and activated on a clean WordPress 6.6 with `WP_DEBUG` on, with no notice on seven pages;
  - Release run 43025586463 after #5 opened no release PR, as expected for chore commits;
  - PR #6 (records only) reported the four checks, all green (run 43025887721).
- **Run by the verification lens on b2294d3:**
  - the harness sweep on PHP 8.3.32 (24 harnesses) and on 8.1.29;
  - `php -l` on 18 files under 8.3 and 8.1;
  - release-header 37/37 and template-guard 84/84;
  - `npm run test:unit` 211/211 and `npm run lint:js`;
  - both workflows and the release-please files parse.
- **Scratch seeds:**
  - four template breakages each exit 1 and name their cause;
  - `package.json` at 4.0.1 fails Row 6;
  - a PHP 8.2 version fails Row 8;
  - a `paths:` filter passes Row 9 (VG-6).
- **Not exercised:**
  - a seeded CI run;
  - Plugin Check on this machine;
  - a release-please dry run;
  - `npm run build` and a packaging dry run, which write into the repository;
  - translations at runtime (story 95's walk is the last proof).

## Previous-retro follow-through

Nothing to follow through on. The previous epic in `tickets.py status` order, `epic-ui-rebuild`, has no retrospective file. This is not a "no outstanding items" record. The n8n-provisioning retrospective (2026-10-16) belongs to a later epic in that order.

## Action items

All of these are proposals. None was applied by this retrospective; the owner decides what runs.

| # | Action | From | Owner | Kind |
|---|---|---|---|---|
| R1 | Reconcile the epic and the spec with the as-built release path: <ul><li>the asset is the Free build Freemius generates from the CI zip (ADR 0014 amended);</li><li>translations come from WordPress.org language packs (story 95);</li><li>the Freemius API token joins `GITHUB_TOKEN`;</li><li>Plugin Check blocks on every PR;</li><li>the stale Notes lines (L59, L64).</li></ul> | A1, A2, S1, VG-1, VG-5 | agent drafts, owner approves | spec reconciliation |
| R2 | Release PR CI. In `/release` step 4 (`SKILL.md:13`), `ci.yml:9-13` and `memory/delivery-pipeline.md` L8, say that the release PR's CI run waits for approval: approve it and merge on green. Limit "fix the cause and re-run" to outside causes. Confirm the approval prompt on the next release PR. | VG-2, F1 | agent (records), owner (the approval at each release) | remediation, records |
| R3 | Release job fixes in one small ticket: <ul><li>`setup-php` 8.1 in the package job (F5);</li><li>the `style-index.css` check (F10);</li><li>the files' version equals the tag (F16);</li><li>the full-build artifact renamed so the fallback cannot attach it (F12);</li><li>a warning for a missing `<version>.md` or a non-empty `unreleased.md` (F7).</li></ul> | F5, F7, F10, F12, F16 | agent (`bmad-build`) | remediation |
| R4 | Row 9 and the `prerelease` contradiction: <ul><li>refuse `paths:` and `branches:` filters on `pull_request`;</li><li>refuse leftover `release-as` and `last-release-sha`;</li><li>pin the four required check names;</li><li>pick one rule for the `prerelease` key and make the skill and the harness agree.</li></ul> | F3, F4, F9, VG-6, VG-7 | agent (with R3) | remediation |
| R5 | Say in `README.md` and `SECURITY.md` (and on the site, if the owner wants) that a GitHub install of the Free build gets no update notice until the WordPress.org listing: watch the repository's releases. | F6 | agent drafts, owner approves | records |
| R6 | Records: <ul><li>`memory/delivery-pipeline.md` L13 and L49 (CI zip → Free build);</li><li>`docs/distribution.md` L145 and L156;</li><li>the manual dispatch builds the full build (`SKILL.md:24`);</li><li>a stuck draft of any version (F2);</li><li>step 1 lists the Stable tag (F8).</li></ul> | A3, G2, S3, F2, F8 | agent | records |
| R7 | Before story 96: one shared build-and-check-assets step (D1), and `release.yml`'s inline bash moved into `bin/` with harness rows (G1). File it as a chore that story 96 waits on. | D1, G1 | agent files, owner ranks | deferred |
| R8 | Hardening, deferred: <ul><li>F11 (harness rows that need the built files skip in CI);</li><li>F13 (Freemius pending switch);</li><li>F17 (notes appended twice after a hand-publish);</li><li>F18 (meta-box translations fixture);</li><li>F19 (pin release-please, drop the persisted token);</li><li>VG-3 (negative template rows);</li><li>the split of `memory/delivery-pipeline.md` (G2).</li></ul> | as listed | agent files, owner ranks | deferred |
| L1 | Process lesson: when a criterion reads "CI fails on X", the plan's Verification records the seed proof's run id and outcome. A record of CI behavior (such as "the release PR gets no CI") is re-read after the repository changes. The fresh public repository changed it unnoticed. | P1, VG-2..5, F1 | agent (`memory/lessons.md`) | process |

## Acceptance verdict

**accepted-with-open-items**, from declared criteria. This is the machine verdict; the owner has made no separate verdict decision.

- Every ticket is done, and `pending_tickets` is empty.
- Done when 2 and 4 are met in the evidence.
- Done when 1 and 3 are met as amended by later owner decisions:
  - ADR 0014 amended (2026-10-28) made the release asset the Free build;
  - story 95 moved translations to WordPress.org language packs.
  
  Their wording is stale (R1).
- No finding blocks: none is high, and none shows a release shipping broken. The open items are the medium findings VG-2, F1–F6 and A1, tracked in R1–R6.

## Open questions

1. R1: should the epic and spec wording follow the as-built path? The retrospective does not edit the spec.
2. R2: should `/release` step 4 become "approve the release PR's CI run and merge on green", in place of merging past the pending checks? On the fresh public repository the run waits for approval.
3. R5: say the no-update-notice limit only in the README and `SECURITY.md`, or on the site too, until the WordPress.org listing (story 38)?
4. R3, R4 and R7: one chore before story 96, or two?
