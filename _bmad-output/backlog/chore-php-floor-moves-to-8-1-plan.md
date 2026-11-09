---
title: 'The plugin requires PHP 8.1, and the project''s checks run on it'
type: 'chore'
ticket: 118
created: '2026-11-09'
status: done
baseline_revision: '46ac40b4e2e054ee1318c8a800b7dacaf45048b0'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/docs/adr/0018-php-floor-follows-wordpress-share-rule.md'
  - '{project-root}/_bmad-output/backlog/chore-php-floor-moves-to-8-1.md'
  - '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** ADR 0018 sets the PHP floor at 8.1 and requires the floor to run the project's checks, but the plugin, CI, the records, `/lcm` and the website still say 8.0, and CI only lints on 8.0.

**Approach:** Change every current statement of the floor to 8.1, make CI lint and run the harness sweep on 8.1 and 8.3, rewrite `/lcm`'s PHP step to ADR 0018's share rule, give `/release` the Freemius PHP read after a floor change, and add one release-notes line; the website follows in its own draft PR.

## Boundaries & Constraints

**Always:** every acceptance criterion of ticket 118 holds; the CI job keeps its name "PHP lint and harness sweep" (the PR's check name); a failed 8.1 step still lets the 8.3 steps report (`!cancelled()`); dated records keep their words (ADRs, findings, plans, CHANGELOG, released notes, `brownfield.md`, `.memlog.md`, `build-brief.md`, `.working/`, the spec's L70 scope note, the epic's L15 context); each hand edit in a managed block is named in its header.

**Never:** change plugin PHP code or behaviour; touch `Requires at least` (6.6); change the Free-build job's or Plugin Check's PHP; read Freemius's value in this build; use 8.1 language features; edit with a shell heredoc (lessons 2026-10-24).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| CI green | this branch's plugin PHP and harnesses | 8.1 lint, 8.1 sweep, 8.3 lint, 8.3 sweep all pass | — |
| Newer syntax | a scratch file with 8.2-only syntax (`readonly class`), never committed | `php -l` on Local's 8.1.29 fails | proves the floor lint catches it |
| Floor guard | CI sets up a PHP below the header's `Requires PHP`, or not the floor | `release-header-harness.php` fails naming both values | — |
| `/lcm` share ≥ 5 % | 8.1 at 9.17 % (2026-11-09) | reading recorded; nothing filed, even past end of life | — |
| `/lcm` share < 5 % | the floor version below 5 % | a ticket moving the floor by ADR 0018's rule, citing it | — |
| `/release` | `Requires PHP` unchanged / changed since the last release | no read / Freemius's value read | missing or different → bug ticket |

</frozen-after-approval>

## Code Map

Lines as of 46ac40b; every one re-verified 2026-11-09.
- Value: `post-quality-manager/post-quality-manager.php` L10 (padded `Requires PHP:      8.0`), `post-quality-manager/readme.txt` L6.
- `.github/workflows/ci.yml`: L23–26 header comment ("harnesses may use newer PHP" goes), job `php` L130–162 (comments L133, L143); the sweep loop body stays.
- `tests/harness/release-header-harness.php` L40, L131–133: format and header = readme only; reuse `header_value()`.
- `AGENTS.md` L4 (`bmad:context` hand-edit list, #264's entry is the form), L8, L30.
- `README.md` L38; `docs/distribution.md` L14, L41; `memory/lifecycle.md` L10; `memory/delivery-pipeline.md` L31.
- `.claude/skills/lcm/SKILL.md` L15 (step 4); `.claude/skills/release/SKILL.md` L16 (step 7).
- `_bmad-output/initiative-post-quality-manager/`: `spec-delivery-pipeline/spec-delivery-pipeline.md` L36, `epic-delivery-pipeline/epic-delivery-pipeline.md` L19, L28, `ux-postquality-website/DESIGN.md` L518, `EXPERIENCE.md` L122, L125.
- `_bmad-output/backlog/story-wordpress-org-directory-listing.md` L84, L88.
- Website `C:/Git/Post Quality/postquality.com`: `docs.md` L25, `index.html` L84.
- Share source: `https://api.wordpress.org/stats/php/1.0/` (findings §2).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager.php`, `readme.txt` -- 8.0 → 8.1, header padding kept -- AC1.
- [x] `.github/workflows/ci.yml` -- setup 8.1, "Lint plugin PHP on 8.1", "Harness sweep on 8.1"; `!cancelled()` setup 8.3, lint, "Harness sweep on 8.3"; comments say the floor is 8.1 (ADR 0018) and harnesses run on it -- AC2.
- [x] `tests/harness/release-header-harness.php` -- one row: every `php-version` in `ci.yml` is at least the header's `Requires PHP`, and the header's value is among them -- keeps AC2 true on the next move.
- [x] `AGENTS.md` -- L8, L30; append "Hand edit 2026-11-09 (story 118, owner's brief): …; keep them on the next refresh." to L4 -- AC3.
- [x] `README.md`, `docs/distribution.md` (L14 source → ADR 0018, story 118), DESIGN.md, EXPERIENCE.md, CAP-6 (rule kept, "PHP 8.1 since story 118 (ADR 0018), 8.0 before"), epic L19/L28, story 38 L84 → a dated `Decision (story 118, 2026-11-09)` naming 8.1 and ADR 0018, L88 → PHP 8.1 -- AC3.
- [x] `memory/lifecycle.md` PHP row: in use 8.1 (story 118), source = the share endpoint (ADR 0018) + endoflife.date, latest seen: 8.0 3.36 %, 8.1 9.17 % (2026-11-09), Freemius read pending at the first release; `memory/delivery-pipeline.md` L31: 8.1 + 8.3 lint and sweep, drop the Plugin Check claim, ceiling = functions newer than 8.1 caught only on harnessed paths -- AC3, AC4.
- [x] `lcm/SKILL.md` step 4 -- read the share, record it with the date in `memory/lifecycle.md`, below 5 % → ticket moving the floor to the oldest version ≥ 5 % the checks run on, citing ADR 0018; end of life alone files nothing; other reasons need a new ADR -- AC4.
- [x] `release/SKILL.md` step 7 -- when `Requires PHP` changed since the previous release, read the PHP requirement Freemius records for the version; missing or different → a bug; how to read it is unsettled until the first such release, which writes the way into this step -- AC5.
- [x] `unreleased.md` -- one paragraph: needs PHP 8.1; a site on 8.0 keeps its installed version and gets no further updates; move the site to 8.1 or later to get them -- AC6.
- [x] Website `docs.md` L25 "PHP 8.1 or later", `index.html` L84 "PHP 8.1+" -- AC3.

**Acceptance Criteria:** ticket 118's AC1–AC6, plus: Given the repo and the site after the change, when they are grepped for `8\.0` beside PHP, then only dated records match.

## Implementation Notes

- Implementer subagent, 2026-11-09, from this plan alone; every change left uncommitted in both repositories, no push, no remote call.
- The acceptance grep found three current statements the Code Map did not list; changed under AC3: `memory/delivery-pipeline.md` L8 (CI's PHP versions and the `!cancelled()` sentence) and L41 (`bin/` lint), and `EXPERIENCE.md` L260 (the A2 journey's "PHP 8.0+").
- Left as dated records, beyond the Boundaries list: `PORTFOLIO.md` L244 (snapshot, "Last updated: 2026-10-20", refreshed by `/portfolio-update`); the UX review outputs `review-rubric.md` L19 and `validation-report.md` L219 / `.html` L273; story 95's ticket L50 and plan L28 (done); the site audit ticket `story-site-content-style-checkout-best-practices-audit.md` L22; story 4.2's title in the epic's `tickets.toml` L23 and its plan's file name; ADR 0017 L12. `docs/local-verification.md` L20 and story 9's ticket L46 say "PHP 8.x" for a dev Local site, not the floor: unchanged.
- `ci.yml`: only the 8.3 steps carry `!cancelled()`, so a failed 8.1 lint skips the 8.1 sweep while the 8.3 lint and sweep still report. The sweep steps print no `php -v`; the lint step before each prints it.
- Row 8 of `release-header-harness.php` reads every `php-version:` line (quoted or bare) of `ci.yml`'s `php` job only, from `  php:` to the next job key (review fix: the Free-build job's 8.3 no longer counts), and compares with `version_compare()`. Seeded by hand and reverted (diff checked): `'8.0'` → FAIL; `'8.2'` (floor absent) → FAIL; the php job at 8.2 with the Free-build job at `'8.0'` → FAIL listing only "8.2, 8.3", so the other job is out of the read.
- CAP-6 and the epic's Done when edited by hand (ticket Notes), not through `bmad-spec`'s update. Story 38's L84 Assumption became the Decision in place; its second sentence (how the site reaches 6.6) kept.
- `memory/lifecycle.md`: the "Last checked" cell stays 2026-11-03 (the last `/lcm` run); the 2026-11-09 share reading sits in the latest-seen cell, credited to spike 104, as the other rows credit their stories.
- Website: branch `chore/118-php-floor-8-1` created locally from `main` (a3241bb); with no fetch, whether that `main` is current with origin was not re-checked.
- The 8.3 harness run used the default temp folder (the three harnesses that write temp files remove them at shutdown); the 8.1.29 run used `-d sys_temp_dir=` the scratch folder, empty afterwards.
- Orchestrator, 2026-11-09 (step 3): the implementer ran on Opus (owner rule 2026-10-26). The 8.3 run's default temp folder breaks the owner's brief (temporary files in the scratchpad); `%TEMP%` was listed afterwards (read only): no `pqm-free-build-*`, `pqm-n8n-runner-*` or `pqm-n8n-compile-*` entry. Diffs staged in the session scratchpad, not the system temp folder, by the same brief. Matrix audit re-run by the orchestrator: 8.3.32 and 8.1.29 sweeps 23 passed, 0 failed, no PHP message (both with `sys_temp_dir` in the scratchpad); touched PHP files lint clean on both; a `readonly class` file fails `-l` on 8.1.29 ("unexpected token \"readonly\"") and passes on 8.3.32; Row 8 passes on the branch, and its rule fails for CI versions (8.0, 8.3, 8.3) and (8.2, 8.3, 8.3). Rows `/lcm` and `/release` are skill text, checked by reading step 4 of `lcm/SKILL.md` and step 7 of `release/SKILL.md`; no executable test exists for them. Row "CI green" is proven by the PR's checks (step 5).
- 2026-11-09, the orchestrator: draft PR #266 opened on 50e5244; website PR vincentheimann/postquality.com#16 (25c28b1) merged by the owner as f0f4407. `gh pr checks 266` (run 42613217158): Build admin app pass, PHP lint and harness sweep pass (42 s; its log sets up PHP 8.1.34 and 8.3.36, 22 harness summaries with 0 failed on each, 14 clean lints on each, row 8 "ci.yml's php job sets up PHP 8.1, 8.3"), Plugin Check pass, Free build Plugin Check pass. Criterion 2 met on CI; plan marked `done` with `tickets.py mark` (lands on merge), as chore 103 did on #262.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-11-09, Quick lens on Opus, review loop 0): 3 findings — high 0, medium 0, low 3, false 0, maybe-false 0; 3 patch.**

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | `tests/harness/release-header-harness.php` Row 8 collects every `php-version:` in `ci.yml`, the `free-build` job's included, so "the floor is among them" can hold through a job that runs no lint and no sweep | low | patch | Verified: `ci.yml` jobs `php` L126–173 (L139 `'8.1'`, L158 `'8.3'`) and `free-build` L196 (L206 `'8.3'`); the row sees (8.1, 8.3, 8.3). A later floor of 8.3 with the `php` job above it would pass, against the row's docblock and the plan's "keeps AC2 true on the next move". Fix: read the `php` job's block only, and name the job in the message. |
| 2 | `.claude/skills/lcm/SKILL.md` L14, step 3's "Note WordPress's recommended PHP on its requirements page." is left from ADR 0017's PHP trigger, which ADR 0018 replaced | low | patch | Verified: the sentence is the only use of WordPress's recommendation in the skill; step 4 now says any reason other than the share needs a new ADR, and the diff dropped "WordPress requirements" from the lifecycle row's source. An instruction with no use, met on every `/lcm` run. Fix: delete the sentence. |
| 3 | `memory/lifecycle.md` L10 records 8.0 3.36 % and 8.1 9.17 %, while the new step 4 asks for "the floor version's share and the next version's" | low | patch | Verified: after the move the floor is 8.1; findings §2 gives 8.2 at 22.633 %. The cell follows the plan's task wording, which conflicts with the skill text the same plan asked for; the smallest fix is the cell itself, so patch, not bad_plan. Fix: the cell records 8.1 9.17 % and 8.2 22.63 % (2026-11-09). |

Patches applied by the step-03 implementer (re-engaged, Opus). The orchestrator re-ran Verification after them: 8.3.32 and 8.1.29 sweeps 23 passed, 0 failed, no PHP message; touched PHP files lint clean on both; Row 8 "ci.yml's php job sets up PHP 8.1, 8.3 … none is below it" ok; `ci.yml` back at 8.1 / 8.3 / 8.3 after the implementer's seeds; every touched file LF; the 8.0 grep over both repositories matches only dated records and sentences about sites on 8.0.

## Verification

Paths: PHP 8.1 = `C:/Users/vhe/AppData/Roaming/Local/lightning-services/php-8.1.29+0/bin/win64/php.exe`; scratch folder (every temporary file, `sys_temp_dir` included) = `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/5b133cc6-de97-4830-ac57-959d2ad2bc84/scratchpad/impl`. Leave every change uncommitted in both repositories; no push, no remote call.

**Commands:**
- `php -l` (8.3) and Local's 8.1.29 `php -n -l` on every touched PHP file -- clean.
- Harness loop on 8.3 and on 8.1.29 (`-n -d sys_temp_dir=<scratchpad>`) -- 23 files, 0 failed.
- Scratch 8.2-syntax file through 8.1.29 `-l` -- fails; file deleted.
- The 8.0 grep over both repos -- only dated records.
- After push: `gh pr checks <n> --repo vincentheimann/post-quality-manager` -- all pass, the PHP log shows 8.1.x and 8.3.x.

Skipped: a showcase walk and a local Plugin Check -- header metadata only; CI's Plugin Check reads the header and readme on the PR.
