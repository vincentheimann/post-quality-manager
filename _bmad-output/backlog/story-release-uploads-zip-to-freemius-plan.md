---
title: 'A release uploads its zip to Freemius and brings back the Free and Pro builds'
type: 'feature'
ticket: 58
created: '2026-10-13'
status: done
baseline_revision: 'b904761'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: []
review_loop_iteration: 0
context:
- '{project-root}/docs/adr/0014-two-builds-from-one-source.md'
- '{project-root}/docs/distribution.md'
- '{project-root}/.claude/skills/release/SKILL.md'
- '{project-root}/memory/delivery-pipeline.md'
- '{project-root}/_bmad-output/backlog/story-free-build-contains-no-pro-code-plan.md'
- '{project-root}/_bmad/custom/minimal-code-ladder.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Every release ends with two owner steps that an agent may not take (`/release` 7–8): upload the CI zip in Freemius → Deployment, download the Free build Freemius generated and check it. Story 38's SVN deploy needs that Free build as a workflow artifact of the same run, and story 54's one-off `diff -r` against the local strip (`bin/build-free.php`) is the only thing that proves CI's Free-build check matches what Freemius ships; today nobody re-runs it.

**Approach:** The `package` job of `release.yml` gains two steps after the publish step, both under the same `release_created` guard. The first calls Freemius's product-scoped REST API with `curl` and a bearer token from a GitHub secret: it logs the zip's sha256, looks the version up in the product's deployments, uploads the zip as a new deployment only when the version is absent and sets its release mode to `pending`, then downloads the Free and Pro builds of that deployment as `post-quality-manager-<version>-free.zip` and `-pro.zip`. The second step unzips the Free build and checks it the way `/release` step 8 does (no `__premium_only` outside `vendor/`, header `Version` = the released version, `templates/` holds only the Free template) and `diff -r`s it against `bin/build-free.php` run on the tree the job just zipped, tolerating only what story 54's HITL diff recorded as Freemius's own rewrites. Both zips are uploaded as workflow artifacts. The means is settled from Freemius's OpenAPI spec (read 2026-10-13, `https://api.freemius.com/v1/openapi.json`): bearer-token auth is product scope only, and `GET/POST /products/{id}/tags.json`, `PUT …/tags/{tag_id}.json` (`release_mode`: `released`|`pending`|`beta`) and `GET …/tags/{tag_id}.zip?is_premium=` are all product-scoped under it. No SDK, no third-party action, nothing to pin beyond what the runner image ships (`curl`, `jq`, `unzip`, `php`). The "Deploy on Freemius" action (`buttonizer/freemius-deploy`) is not used: its source (read 2026-10-13) confirms the ticket's reading — developer-scope keys, a `git clone` of the PHP SDK at image build time, no license file, and an "already deployed" check that compares with the newest deployment only.

## Boundaries & Constraints

**Always:** release-please, the version check, the build, the CI zip, its attachment to the GitHub Release, the pre-release flag and the publish step stay as they are; the new steps run after publishing, so a Freemius failure leaves a published release with its zip (AC1). Release mode is set to `pending` and nothing else (Boundaries: `released` and `beta` stay the owner's). Only a release reaches Freemius: the steps carry `if: needs.release-please.outputs.release_created == 'true'`, which a manual dispatch never satisfies (AC4). Pre-release test builds deploy too (owner decision D1, 2026-10-13). The token comes from the `FREEMIUS_API_TOKEN` secret only; the script never prints a request header or runs with `set -x`; GitHub masks the secret in the log (AC5). No new action, no container, no dependency: `curl`, `jq`, `unzip`, `php` from `ubuntu-latest` (AC5: nothing new to pin; the runner image is the one every job already runs on). The artifacts are named `post-quality-manager-<version>-free.zip` and `-pro.zip`, never the CI zip's name (AC2). The `free-build` CI job and `bin/build-free.php` are unchanged.

**Never:** No SVN deploy, no switch of the GitHub Release asset to the Free build (story 38). No `released`/`beta`, no `limit`/`percentage_limit` (staged rollout), no deletion of a deployment. No sandbox or dry-run path that sends anything outside a release (AC4 forbids it; the first run is the next real release, owner decision D3). No developer-scope key, no secret or product secret key in the repository. No retry that could create a second deployment: the version lookup runs before every upload, and the upload is attempted once per run.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Release, version new on Freemius | release PR merged, `package` published the release, no deployment with this `version` | sha256 of the zip logged; `POST …/tags.json` (multipart `file`) → deployment `id`; `PUT …/tags/{id}.json` `{"release_mode":"pending"}`; Free and Pro zips downloaded; checks pass; two artifacts uploaded; log ends "Freemius deployment {id}, version {v}, release mode pending" | — |
| Release, version already on Freemius (re-run of `package`, or an earlier manual upload) | a deployment with this `version` exists | no `POST`, no `PUT`; log says "Version {v} is already on Freemius as deployment {id}: that first upload is what customers and SVN get; this run's zip may differ byte for byte"; fetch and checks as above | — |
| Release, the version exists and the owner already set it Released | `release_mode: released` on the existing deployment | left as is (the step never writes release mode on an existing deployment); fetch and checks as above | — |
| Manual dispatch (packaging check) | `workflow_dispatch` | both steps skipped (`release_created` is empty); the CI zip artifact as today | — |
| Any CI run | `ci.yml` | untouched; nothing calls Freemius | — |
| Secret missing or empty | `FREEMIUS_API_TOKEN` unset | step fails first thing: "FREEMIUS_API_TOKEN secret is not set (Freemius → product Settings → API & Keys)"; release untouched | exit 1 |
| Token rejected | Freemius answers 401 | `curl --fail-with-body` prints Freemius's error JSON (never the request), step fails; release untouched | exit 1 |
| Upload refused | Freemius answers 4xx on the `POST` (e.g. readme missing, uninstall.php present, bad zip) | the error body is printed, step fails before any `PUT` or download; nothing to clean up (no deployment was created) | exit 1 |
| No Free plan on the product | deployment object has `has_free: false` | step fails: "Freemius generated no Free build: the product needs a Free plan (Plans)"; the Pro zip is still downloaded and uploaded as an artifact for the record | exit 1 |
| Free build not ready right after the upload | `GET …/tags/{id}.zip?is_premium=false` answers 404 or an empty body | up to 5 attempts, 10 s apart, then fail naming the last status | exit 1 |
| Free build check, leak | a line with `__premium_only` outside `vendor/` in the unzipped Free build | fail, printing `file:line` (`grep -rn`) | exit 1 |
| Free build check, version | header `Version:` ≠ the released version | fail, printing both | exit 1 |
| Free build check, templates | `templates/` holds anything but `n8n-workflow-free.json` | fail, listing the folder | exit 1 |
| Free build check, diff | `diff -r` of the Freemius Free tree against `build/free/post-quality-manager` (from `php bin/build-free.php build/free` on the job's own tree) shows a difference outside the tolerated set | fail, printing the diff (`-q` first for the file list, then the full diff) | exit 1 |
| Pro build | `GET …/tags/{id}.zip?is_premium=true` | saved as `-pro.zip`; its root folder name logged from `unzip -l`, expected `post-quality-manager-premium/` (story 54 Q4); not diffed | — |
| Artifacts | both zips present | `actions/upload-artifact@v4`, names `post-quality-manager-<v>-free.zip` and `post-quality-manager-<v>-pro.zip`, `if-no-files-found: error`, `overwrite: true` (re-runs) | — |
| More than 50 deployments on the product | `GET …/tags.json?count=50` (the API maximum) | the lookup pages with `offset` until a page is shorter than 50 (a loop of a few lines; ceiling noted in a comment) | — |

</frozen-after-approval>

## Decisions (owner, 2026-10-13: D1–D3 as recommended)

- **D1 — Pre-release test builds deploy too.** Every release deploys as `pending`, which customers never see, so the pipeline is exercised before launch. Alternative: gate on `prerelease` in `release-please-config.json`, untested until launch day.
- **D2 — The diff against the local strip runs on every release and fails on any difference** beyond what story 54's HITL diff records as Freemius's own rewrites. Alternative: `/release` step 8's three checks only. *Amended 2026-10-13 after that diff (owner: "go ahead with best practices"):* Freemius re-prints every PHP file, so a text diff can never match; PHP is compared as code by a new `bin/compare-php-trees.php` (token streams; whitespace, comments, string escaping, trailing commas, parentheses and alternative control syntax ignored), everything else byte for byte, and the stripper and the source take over Freemius's four other rewrites, so there is no tolerance list. Alternative rejected: dropping the content comparison (a stripping difference inside a file would reach a reviewer first).
- **D3 — First run is the next real release.** A Freemius failure leaves the published release untouched and a re-run is safe (AC3), so a first failure costs one re-run of the `package` job.

## Code Map

- `.github/workflows/release.yml`, job `package`, after "Attach zip, append release notes, publish" (about 60 lines in total):
  - step "Deploy to Freemius (unreleased) and fetch its builds" — `if: needs.release-please.outputs.release_created == 'true'`; env `FREEMIUS_API_TOKEN: ${{ secrets.FREEMIUS_API_TOKEN }}`, `PRODUCT_ID: 41144`, `V`, `ZIP` from `steps.zip.outputs`; `set -euo pipefail`; a two-line `api()` wrapper around `curl -sS --fail-with-body -H "Authorization: Bearer $FREEMIUS_API_TOKEN"`; `sha256sum "$ZIP"`; the version lookup (`jq -r --arg v "$V" '.tags[] | select(.version == $v) | .id'`, paged by `offset`); `POST` + `PUT` only when the lookup is empty; `has_free` read from the deployment object; the two downloads (Free with the bounded retry); the Pro root folder logged; output `id`.
  - step "Check the Free build Freemius generated" — same guard; `unzip -q` into `$RUNNER_TEMP/freemius-free`; the three `/release` step 8 checks; `php bin/build-free.php build/free` (the tree is still built from the zip step); `diff -r` against it with the tolerated set (D2); fails naming the file.
  - step "Upload the Freemius builds" — `actions/upload-artifact@v4` with a two-line `path`, `if-no-files-found: error`, `overwrite: true`.
  - the header comment of the file gains two sentences (what the steps do, the secret).
- `docs/distribution.md` § Release order — the paragraph and the table say what is automatic: Upload row → "automatic, `release.yml` package job, after the GitHub Release is published; release mode pending"; Free build download row → "artifacts `post-quality-manager-<v>-free.zip` / `-pro.zip` of the release run (90-day retention), checked as `/release` step 8 and diffed against `bin/build-free.php`"; Automation endpoints row → built, CHECK dropped, the endpoints as confirmed in the OpenAPI spec; a new Secrets row: `FREEMIUS_API_TOKEN` (product Settings → API & Keys; regenerating the product's secret key invalidates it, then the secret is replaced), owner; and the remaining owner step (Released).
- `.claude/skills/release/SKILL.md` steps 7–9 — 7: the run deploys the zip and the artifacts come back; what to read in the run log; 8: the checks run in the job; on a red step, read the log, never fix a Freemius build by hand; 9 unchanged in substance (SVN = story 38, Released = owner).
- `memory/delivery-pipeline.md` — one paragraph "Freemius deployment (story 58)"; `memory/freemium-and-roles.md` Free-build bullet gets one clause.
- Ticket 58 Notes (built, the means, the first run); ticket 38 Notes (consumes `post-quality-manager-<v>-free.zip` from the release run; adds 58 to its `after`).
- `bin/compare-php-trees.php` (new, about 150 lines, PHP 8.0, no dependency): every non-vendor `.php` of two trees reduced to a normalised token stream and compared; prints each file's first difference with both line numbers, exit 1 on any; vendor/ and non-PHP are left to `diff -r`.
- `bin/build-free.php`: leaves out `vendor/freemius/README.md` and `composer.json`, appends `, freemius` to the readme's Contributors (what Freemius writes). `post-quality-manager/post-quality-manager.php`: `'is_live' => true` stated, `'has_premium_version'` dropped (the SDK's default is `has_paid_plans`, true) — the two keys as Freemius's Free build has them, no-ops at runtime.
- `tests/harness/free-build-harness.php`: the readme and SDK-file rows; a `compare-php-trees` row (the Free tree against itself; a pair written in braces vs alternative syntax with parentheses, trailing commas, comments and the two string quotings → identical; one changed key → exit 1 naming the file and both lines; a file on one side only → exit 1; vendor/ ignored).
- No other harness: the remaining logic is shell inside a workflow and the tree it checks is Freemius's output. No ADR: this implements ADR 0014's release order. No release-notes line: nothing an installed site meets.
- Skipped: a reusable shell script under `bin/` for the checks — one caller, inline in the step. Skipped: diffing the Pro zip against the upload — Freemius documents the premium package as identical to the upload; the root folder name is logged for story 54 Q4 and that is all.

## Implementation sequence

1. **The steps in `release.yml`.** Gate: `bash -n` on the extracted scripts; the check step's script run locally against story 54's unzipped Freemius Free build and the local strip (the same trees the HITL diff uses), passing, and once with a planted `__premium_only` line, failing and naming the file; the YAML parsed once (any available parser; failing all, a careful read). The tolerated-diff set is filled from story 54's HITL result before the PR is opened; until then the step holds an empty list and a comment naming this plan.
2. **Records.** distribution.md, the release skill, memory, ticket 58 and 38 Notes.
3. **HITL (owner):** create the `FREEMIUS_API_TOKEN` repository secret from the product's Settings → API & Keys before the next release PR is merged; merge the next release PR; read the `package` job log (sha256, deployment id, "release mode pending"); open Freemius → Deployment and see the version listed unreleased with both builds; download the two artifacts once. Then re-run the `package` job of that run: the log must say the version is already on Freemius and no second deployment may appear (AC3). Only then is the story done.

## Verification

- `bash -n` on each step script; the check script against the story 54 trees locally (exit 0), and once with a planted `__premium_only` line (exit 1 naming the file).
- `gh pr checks <n> --repo vincentheimann/post-quality-manager` green (CI does not run this workflow; the PR's checks cover lint and the harnesses, which this story does not touch).
- The first release after the merge: run log, Freemius → Deployment, the two artifacts, then the re-run (sequence step 3).

## Unverified assumptions, settled on the first run

- A new deployment created by `POST …/tags.json` starts as `pending` (the SDK-based action sets it explicitly; the step does too, so either way holds).
- The Free zip is downloadable as soon as the `POST` returns (the bounded retry covers a delay).
- `GET …/tags.json` lists deployments newest first and the `version` field is the plain plugin version (`3.5.2`, no `v`); the lookup compares strings exactly.
- The product has a Free plan (ID 71338, confirmed on the owner's Plans screen 2026-10-13), so Freemius generates the Free build. The deployment object's `has_free` is `true` once that build was generated; if the field means something else, the check moves to "the Free zip downloads with a 200 and holds a plugin".
- Freemius's Pro zip has the root folder `post-quality-manager-premium/` (story 54 plan, Q4).
- `curl` on `ubuntu-latest` supports `--fail-with-body` (7.76+; the image ships 8.x).
- Freemius's processor leaves `'is_live' => true` in place when the source already states it (it added the key to 3.5.1's Free build, which lacked it) and does not re-add `'has_premium_version'` (it removed it from 3.5.1's). If the first run shows otherwise, the stripper mimics those two edits instead and the source line is reverted.
- A re-run of the `package` job rebuilds `admin/build/` from the same tag and lockfile; the diff assumes that build is byte-identical to the first run's (webpack output is deterministic for the same inputs). If a re-run fails only on `admin/build/`, that assumption broke: the fix is an `--exclude` for it on re-runs, decided then.
- The tolerated diff set from story 54's HITL is small and expressible as `diff -r --exclude`/`-I` arguments; if it needs more, a tiny PHP normaliser is the fallback and the plan is amended.

## Plan Change Log

- 2026-10-13: plan written at the checkpoint; means settled from the OpenAPI spec (bearer token, product scope) instead of the ticket's candidate action; D1–D3 answered by the owner.
- 2026-10-13, slice 1: the spec has no `GET` for a single deployment, so the deployment object (for `has_free`, `release_mode`) comes from the list lookup or the `PUT` response. The bounded download retry covers both builds (one helper). The Pro artifact uploads with `always()` once the Pro zip is downloaded (the matrix row "No Free plan" keeps it for the record); the Free artifact uploads only after its checks pass, so story 38 never reads an unchecked build. The header `Version` is read with `sed`, not `grep -P` (locale-dependent). Tested locally with a stub `curl` playing Freemius and a checksum-verified jq 1.7.1 in the scratchpad: new version over two list pages (one POST, one PUT, both zips), existing version on page 2 (no POST/PUT), secret missing, token rejected (Freemius's error body printed), no Free plan (Pro kept, exit 1), Free build never ready (5 tries, exit 1); the check step against the 3.5.1 local strip zipped as a stand-in (exit 0) and with a planted `__premium_only`, a wrong `Version`, an extra Pro template and a wrong root folder (exit 1 each, named). `TOLERATED` is empty until story 54's diff.
- 2026-10-13, slice 1b (after story 54's HITL diff, D2 amended): `bin/compare-php-trees.php`, the stripper's two mimicked rewrites, the init-key alignment in the main file, four harness rows; the check step compares PHP through the comparer and the rest with two `diff -rq` calls (`-x '*.php'`, then `vendor/`). Verified against Freemius's real Free build of 3.5.1: the check script exits 0 ("PHP identical: 10 files", nothing else differs). Bug found by the harness pair and fixed: double-quoted literals were decoded with C escapes (`"\y"` is `\y` in PHP).
- 2026-10-13, slice 2: records — `docs/distribution.md` § Release order, `/release` steps 7–9, `memory/delivery-pipeline.md`, `memory/freemium-and-roles.md`, ticket 58 and 38 Notes, story 58 in ticket 38's `after`.
- 2026-10-14, first run (release 3.7.0, run 38572105307): deploy, unreleased mode and both downloads worked; the Free check failed on `readme.txt` alone (PHP identical, 11 files). Freemius's 3.7.0 Free build (owner's download) keeps `Contributors: postquality`: the `freemius` it added on 3.5.1 came with the dashboard upload, not with the API one. Fix on `fix/freemius-readme-contributors`: the stripper no longer rewrites the readme, the check ignores the `Contributors` line (`diff -I`, GNU diffutils honours it under `-q`/`-u`) and prints non-PHP differences with `diff -ru` instead of `-rq`; replayed against the owner's 3.7.0 download (exit 0) and with a changed `Tags` line (exit 1, lines shown). AC3 (the re-run) waits for the next release: re-running 3.7.0's job checks out the tag and would fail again.
- 2026-10-14, second run (release 3.8.0, run 38584695308): the readme fix held (no non-PHP difference); deployment 162266 uploaded, release mode pending, Pro root `post-quality-manager-premium`. The Free check failed on one PHP line: Freemius prints a variable property `$node->credentials->$type` (entry 6.4) as `->{ $type }`, which the token comparer reads as different tokens. Fix on `fix/freemius-dynamic-property`: the source writes `->{$type}`, Freemius's form (the precedent of the SDK init keys); replayed with `bin/compare-php-trees.php` against that printed form (identical) and against the old line (reproduces the CI difference). It is the only variable property or variable variable outside `vendor/`. AC3 still waits for a release whose check passes.
- 2026-11-02, third run (release 3.10.0, run 41484767680): deployment 169782 uploaded, release mode pending, Pro root `post-quality-manager-premium`; the Pro artifact's PHP equals the v3.10.0 source (Freemius re-prints only the Free build). The Free check failed on one cosmetic token: Freemius drops a close tag directly followed by an open tag, the first such pair in Free code (`render_workflow_body()`, story 67). Fixed by `plan-compare-empty-tag-pair.md` (PR #219): the comparer reads such a pair as the `;` it stands for. The Free artifact of 3.10.0 was never uploaded; 3.10.0 stays pending.
- 2026-11-02, fourth run (release 3.10.1, run 41491659473): all green. Deployment 169805, pending; Free check "PHP identical: 11 files"; both artifacts uploaded. AC3: the agent re-ran the `package` job (attempt 2, owner's word). The lookup did not find 3.10.1, the job posted again, Freemius answered `400 duplicate_plugin_version` and created nothing. The re-run re-attached a rebuilt zip to the release (same files and bytes inside, new sha256). The assumption above that `GET …/tags.json` lists the deployments does not hold: the owner's reads on 2026-11-03 returned none of the product's deployments, all pending, with or without `fields`. Fixed by `plan-freemius-latest-deployment-lookup.md`: the lookup reads `tags/latest.json?type=all`, and AC3 covers the newest release (owner, 2026-11-03). AC3's next try is the re-run of the release that ships the fix.
- 2026-11-03, fifth run (release 3.10.2, run 41603959044): deployment 169992 uploaded, release mode pending, Pro root `post-quality-manager-premium`, Free check "PHP identical: 11 files", both artifacts. AC3: the agent re-ran the `package` job (attempt 2, owner's word): "Version 3.10.2 is already on Freemius as deployment 169992 (pending)", no POST and no PUT, the Free check and both artifacts again. The rebuilt CI zip replaced the release asset (sha256 d70466cc… → e4ab80b1…, as AC3's note expects). The owner saw 3.10.2 listed once, pending, in Freemius → Deployment. Every criterion met: done.
