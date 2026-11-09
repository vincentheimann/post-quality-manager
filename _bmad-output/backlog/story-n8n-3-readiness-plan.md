---
title: 'Story 111 — the manual setup imports the workflow from a file, for n8n 2 and n8n 3'
type: 'feature'
ticket: '111'
created: '2026-11-09'
status: 'built'
baseline_revision: '0991b56a4e20ca867859f05cf87015fc136b9609'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/_bmad-output/backlog/story-n8n-3-readiness.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** n8n 3 removes "Import from URL", and the n8n Workflow page's manual setup (Set up by hand) leads with it: an n8n 3 user meets a step their editor no longer offers.

**Approach:** Step 1 of the manual setup becomes the file import, which n8n 2.36+ ("Import", then "From file") and n8n 3 ("Import JSON") both offer; the copyable import link and everything that served only it leave the page. Public text, the release notes and memory follow, and memory records the answer to each n8n 3 rule read at 3.1.0. The story's four criteria and its Notes decisions are the acceptance.

## Boundaries & Constraints

**Always:** both builds (Free and Pro) render the same step 1; the Download button stays; every remaining copy row behaves as before ("Copied."); copy per `docs/brand.md`; the rules' answers dated, with the version read (`memory/lessons.md` 2026-10-16).

**Never:** change the workflow template, `GET /workflow` (it stays for links already copied), the guided setup or its n8n API calls; mention importing from a URL anywhere on the page or in its script strings; touch `memory/` lines another open PR edits (#269 edits AGENTS.md, story 109's plan and the end of `deferred-work.md`).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Step 1, Pro | n8n Workflow page, Set up by hand | download, then the workflow menu: "Import", then "From file" (n8n 2.36+) / "Import JSON" (n8n 3); the Download button | — |
| Step 1, Free | the Free build's page | the same step 1 | — |
| No link | either build | no import URL row, no `.pqm-import-url`, no "From URL" in the section or the page script's strings | — |
| Copy rows | any remaining row | "Copied." / the copy-failed line, as before | — |
| Route kept | `GET /workflow?token=…` | serves the template as before | as before |
| Public text | README, readme, release notes | no import link; the release notes say the link left and the file import replaces it | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php` -- step 1 of `render_workflow_file()` L1958-1975 (the "From URL" sentence L1965, the `.pqm-import-url` row L1969-1972 with its token note, the fallback L1973, the Download button L1974); the script string `'copied'` L209 (used only by that row); `workflow_import_url()` L1395-1402, whose one caller is L1970 (its docblock's GET /setup is stale: `setup-harness.php` L171).
- `post-quality-manager/admin/js/pqm-admin.js` -- L600-601 picks `i18n.copied` for a row inside `.pqm-import-url`, `i18n.copiedValue` otherwise.
- `post-quality-manager/includes/class-pqm-rest-api.php` -- the `GET /workflow` route stays; only its comments that call it the page's import link change.
- `tests/js/workflow-file-copy.test.js` -- the `copied` fixture L11, the `.pqm-import-url` row L41-42, the test L132 "the import URL row keeps its own line".
- `tests/harness/render-matrix-harness.php` -- step-1 rows L516-525 (labels "as 2.42.3 shows them"), Copy rows L561-581 (import URL first, `tok-35` counted twice), L611 (the fresh site's step 1), L1236 (label), L1253-1254 (the `copied` string).
- `tests/harness/free-build-harness.php` -- L617-618, the Free page's copy rows start with "Workflow import URL".
- `tests/harness/rest-reviews-harness.php` L256 -- the route's row; stays green unchanged.
- `README.md` L28 -- names the "Import from URL" link. `post-quality-manager/readme.txt` -- no mention (checked).
- `docs/release-notes/unreleased.md` -- one paragraph per change under "Upgrading from an earlier version".
- `memory/lifecycle.md` L11 (n8n row: "3.1.0 a GitHub pre-release, v3 rules → story 111"), `memory/n8n-provisioning.md` L35 (UI words verified against `en.json` at the tested tag).
- The n8n 3.1.0 facts and the 2.36 submenu fact: the story's Notes (read 2026-11-09).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- step 1: download, then "Import", then "From file" (n8n 3: "Import JSON"), the Download button; remove the import URL row, its token note, the fallback line, the `'copied'` string and `workflow_import_url()` -- the link and what served only it go.
- [x] `post-quality-manager/admin/js/pqm-admin.js` -- every copy row says `i18n.copiedValue` -- the import row's own line is gone.
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- the `GET /workflow` comments say it stays for links already copied (story 111) -- no behaviour change.
- [x] `tests/js/workflow-file-copy.test.js`, `tests/harness/render-matrix-harness.php`, `tests/harness/free-build-harness.php` -- drop the import-row expectations; a row asserting step 1 names "Import" → "From file" and "Import JSON" and that no "From URL" appears in the section or the script strings, on both builds (AC4).
- [x] `README.md`, `docs/release-notes/unreleased.md` -- no import link; the release-notes line.
- [x] `memory/lifecycle.md`, `memory/n8n-provisioning.md` -- the four rules' answers at 3.1.0 with the restoring settings (`N8N_SSRF_ALLOWED_IP_RANGES`, `N8N_SSRF_ALLOWED_HOSTNAMES`, `N8N_RUNNERS_TASK_TIMEOUT`), the `/activate` deprecation for `/lcm`, the 2.36 submenu, story 119 for the proof.

**Acceptance Criteria:** the story's criteria 1–4.

## Implementation Notes

- 2026-11-09, checkpoint 1: about 1,900 tokens against the 1,600 target; the owner kept the full plan and approved it ("Approve and continue"); status went ready-for-dev, then in-progress with the baseline. bmad-build ran through the Skill tool; every subagent runs on Opus (owner rule). The branch was rebuilt from main as `fix/111-n8n-3-manual-import` (the refinement commit cherry-picked as 0991b56): the first branch, `fix/111-n8n-3-readiness`, predates #267 and a force push is denied (precedent #154 → #156).
- 2026-11-09, implementation (Opus subagent): the six Execution tasks done, step 1 in the Design Notes' words. Beyond the Code Map, three stale mentions of the link were reworded so the copy and memory stay true: `includes/pqm-functions.php`'s `postquality_webhook_path()` docblock ("the import URL" → `GET /workflow`), `pqm-admin.js`'s two block comments, and three fragments of `memory/llm-options-architecture.md` (the forget-on-serve line, the focus refresh, what the page owns). The AC4 rows are in `render-matrix-harness.php` (Pro: the section, the fresh site's section, the localized data and `pqm-admin.js`) and `free-build-harness.php` (Free: the section and the Free tree's `class-pqm-admin.php` and `pqm-admin.js`); their pattern skips "Re-import link", the status block's other link, which a comment in `class-pqm-admin.php` names. The Verification grep also lists that comment and the vendored Freemius docblock `@param string $from URL`, both unrelated to the import link. The first `npm run test:unit` failed one test in `workflow-status.test.js` (untouched; its copy rows are not in that suite); it passed alone and in two full runs after (211/211).

- 2026-11-09, step 03 (coordinator): diff read against 0991b56 (13 files). Harness sweep 24/24 green (render-matrix 462, free-build 100, rest-reviews 79 with the `GET /workflow` row unchanged); Jest 211/211 in three full runs (the implementer's one failure in `tests/js/workflow-status.test.js`, a suite this build does not touch, did not reproduce: noted as a possible flaky test); the plan's grep finds only the route's comments, the release-notes line and the status block's unrelated "Re-import link" comment (L1683). Matrix audit: steps 1 Pro/Free and no link by the AC4 rows of `render-matrix-harness.php` and `free-build-harness.php`, copy rows by `workflow-file-copy.test.js` and the Copy-feedback row, route kept by `rest-reviews-harness.php`, public text by the grep; all ran and passed.

## Plan Change Log

## Review Triage Log

- 2026-11-09, pass 1 (lens: quick; review_loop_iteration 0). Verdicts: high 0, medium 0, low 5, false 0, maybe-false 0. Routes: patch 3 entries (F1+F2, F3, F4), reject 1 (F5).
  - F1 low → patch (entry A, with F2). `class-pqm-admin.php` L157-159, `enqueue_assets()`'s comment still names an "import-URL copy" control that the diff removed; the step-03 grep missed the hyphenated form. Harm: the next reader of the enqueue path looks for a control that no longer exists. Direct correction.
  - F2 low → patch (entry A). Same root cause, the stale-mention sweep: `ajax_workflow_status()`'s docblock (L2426-2430) still says "an import from URL" while its JS twin and the memory sentence were rewritten; the grep missed it across a line break. Direct correction.
  - F3 low → patch (entry B). `render-matrix-harness.php` L527-529: the check's label names "From file" and "Import JSON" while its body asserts only "Create credential" and the absence of older names; the label would report coverage the row does not have if the AC4 row changed. Direct correction (label matches body).
  - F4 low → patch (entry C). The same label and the L518 comment restate n8n UI words with versions instead of naming `memory/n8n-provisioning.md`, where the diff records them (`memory/lessons.md` 2026-10-16); pre-existing pattern ("as 2.42.3"), now repeated. Direct correction.
  - F5 low → reject. The AC4 rows scan `pqm-admin.js`, its localized data and the Free `class-pqm-admin.php`, not the app bundle's strings (`src/setup/`, `src/n8n-connection/`); a grep of `src/` finds no URL-import string, so no bad outcome today, and the fix adds a new scan.
  - Patches applied by the implementer (re-engaged): A — `enqueue_assets()`'s comment reads "(ping, copy rows)", `ajax_workflow_status()`'s docblock "a fetch of GET /workflow"; B and C — the render-matrix label says only what its body asserts and names `memory/n8n-provisioning.md`, the story 111 comment no longer restates n8n's words. Coordinator re-ran Verification: lint clean, Jest 211/211, build exit 0, harness sweep 24/24, `php -l` and `node --check` clean; the grep, widened to "import-url" and "import from", finds only the route's comments, the release-notes line and the unrelated "Re-import link" comment.

## Design Notes

Step 1 copy (brand.md, one sentence per action): "Download the workflow, then in %s open a new, empty workflow, open the workflow menu (the three dots at the top right) and choose "Import", then "From file" — in n8n 3, "Import JSON". Pick the file you downloaded." The "Before you import" warning and steps 2–4 stay as they are. Skipped: removing `GET /workflow` — the story's later decision.

## Verification

**Commands:**
- `npm run lint:js && npm run test:unit && npm run build` -- expected: green.
- `for f in tests/harness/*.php; do php -d sys_temp_dir="C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/15bc8415-d28a-4945-bbb0-af050c7ba7d6/scratchpad" "$f" || break; done` (the session scratchpad; never the default `%TEMP%`) -- expected: every harness green; `php -l` and `node --check` on each touched PHP and JS file. Temporary files of any kind go in that scratchpad.
- `grep -rn -i "from url\|import url\|import link" post-quality-manager/ README.md docs/release-notes/unreleased.md` -- expected: only the route's comments and the release-notes line that says the link left.

**Manual checks:**
- The owner walks Set up by hand on the showcase against a disposable n8n 2 (`docs/local-verification.md`): download, "Import" → "From file", the workflow lands; the n8n 3 wording rests on the source read at 3.1.0.
