---
title: 'The Free build passes the directory form scan with no low-severity error'
type: 'bugfix'
ticket: 125
created: '2026-11-16'
status: 'built'
baseline_revision: '6c8384961d2209f1aed605cabc0c09f6f462c14c'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/backlog/bug-submission-scan-flags-32-low-severity-errors-in-the-free-build.md'
  - '{project-root}/docs/local-verification.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Freemius re-prints every PHP file of the Free build. The re-print drops comments that sit inside an expression and moves a trailing comment to the next line. So 4.0.0's Free build loses 22 translator notes and 10 `phpcs:ignore` lines, and the directory form's Plugin Check scan reports 32 `ERROR_LOW_SEVERITY` findings.

**Approach:** Put every translator note where the re-print keeps it: at statement level directly above the line of the call, or directly before a call that is itself an argument. Escape each of the ten outputs late, at the echo, with the function that fits it. Add a harness that fails when a note or an ignore sits where the re-print drops it.

## Boundaries & Constraints

**Always:**
- Owner decisions (2026-11-11): escape late and correctly. Use `wp_kses` with an explicit allow-list where markup is intended. Never escape again markup already built from `esc_*` pieces. Translator notes take WordPress's `/* translators: … */` form, one per string.
- Rendered markup, the AJAX `status_html`, and the downloaded workflow file stay as in 4.0.0, with nonces and relative times aside (ticket criterion 2).
- The text of every translatable string stays the same.
- The ADR 0014 premium markers' rules still hold: no `else` beside an `is__premium_only()` block, no assignment from a premium call outside it.
- The fix lands as a `fix:` commit. Version 4.0.1 comes from release-please.
- Decision (owner, 2026-11-16): to keep this from coming back, this PR adds the new harness, and a separate backlog ticket, filed through `bmad-ticket`, runs the form's exact command in `release.yml`'s "Check the Free build Freemius generated" step. The plan is kept whole at about 2,800 tokens (owner, 2026-11-16).

**Never:** a change to `vendor/` or to CI's Plugin Check arguments; a new Composer or npm dependency; re-encoding the workflow JSON; a `phpcs:ignore` anywhere except the workflow download (Design Notes).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Ternary text | Each of the 22 calls (admin 1588/1590, 1617, 1625/1627, 1675, 1787, 2057; n8n 563–604; REST 733/735, 776/778, 1885/1887) | The same string is chosen as before, with its note directly above the line of the `__()` call at statement level | — |
| Workflow body | n8n Workflow page load and its AJAX refresh, in every status state | The page echoes the body directly; `render_workflow_body()` still returns the same string to the AJAX and REST callers | — |
| Built markup | `$primary`, `$secondary`, `$guided_link` and `$by_hand_link` in every state, `$nodes_pick` on Free and Pro | `wp_kses` with an allow-list per value returns the input unchanged. `data-pqm-*`, `&quot;` and `&#038;` are kept, with no double entity | A missing attribute in an allow-list shows as a markup diff in the Local check |
| Links in text | `$license_line` (Free, Pro active, Pro expired) and `$n8n` (with and without a URL) | Inline `printf` or `esc_html_e`, with the same output | — |
| Download | Workflow download, Free and Pro | Bytes identical to 4.0.0 for the same settings | — |
| Regression | A note placed after `?`, `:` or `.`, or a `phpcs:ignore` trailing a statement, in the three files | The new harness fails and names the file and line | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php`. The 10 escapes:
  - L831 `$license_line`;
  - L1413 `echo self::render_workflow_body()`; the AJAX callers are at 2412, 2419 and 2440;
  - L1708 `$guided_link`;
  - L1713 `$primary`/`$secondary`, built by the closures at 1635–1643 and by 1655 and 1673;
  - L1723 and L1728 `$by_hand_link` (1688);
  - L1956 `$n8n`, the last `printf` argument (built at 1920);
  - L1993 `$nodes_pick` (1928; the Pro variant at 1938, inside a premium block);
  - L2112 `$json`.

  The 8 translator sites are at 1588/1590, 1617, 1625/1627, 1675, 1787 and 2057. Reuse the shape of the Anthropic Console `printf` (around L2003): it already passes the form's scan on the Free build.
- `post-quality-manager/includes/class-pqm-n8n.php` L563–604: four `sprintf( $adopting ? __() : __(), … )`.
- `post-quality-manager/includes/class-pqm-rest-api.php` L733/735 (inside `new WP_Error`), L776/778 (nested in an outer ternary) and L1885/1887.
- `post-quality-manager/includes/class-pqm-rest-api.php` 1583, 1750 and 1907, and `admin/js/pqm-admin.js` L367: consumers of `render_workflow_body()`'s string. They do not change.
- Harness rows that match exact markup or source text and may need their expected strings re-checked (never weakened):
  - `tests/harness/render-matrix-harness.php` 153–202, 515, 606, 664–674 and 701–721;
  - `tests/harness/free-build-harness.php` 349–632 (Free-tree source text, among them 383 and 615);
  - `n8n-client-harness.php` 313/319, `n8n-adoption-harness.php` 129 and `n8n-managed-harness.php` 199.
- `tests/harness/lib/wp-stubs.php` L97: `esc_*` and `wp_kses` are identity stubs, so the harness cannot prove criterion 2; the Local check does.
- Re-print rules (source compared with 4.0.0's Free build, 2026-11-16):
  - Kept: a comment on its own line before a statement, and a comment directly before a call argument.
  - Lost: a comment before a ternary `?` or `:` branch, before a concatenation operand, before an array item, or after the last argument.
  - Moved: a trailing `// …` after `;` goes to the next line.
- WPCS 3.4.1, the version Plugin Check 2.1.0 bundles:
  - `I18nSniff` takes the nearest previous comment, which passes when it sits on the line above the call's line.
  - `wp_kses*` and `wp_json_encode` count as escaping.
  - A `phpcs:ignore` alone on its line ignores the next line.

## Tasks & Acceptance

**Execution:**
- [x] Local site (the owner starts it): run the form's exact command (ticket Reproduction) on 4.0.0's Free asset, first with Plugin Check 2.2.0, then with the latest release. If the latest release reports no `ERROR_LOW_SEVERITY`, stop and record criterion 6 in the ticket's Notes. Otherwise record criterion 5's output here.
- [x] Before any edit, capture on the same site, for each state in the matrix, the HTML of the n8n Workflow body and the Settings license line, plus the downloaded workflow file (Free and Pro trees), with `wp eval` calling the render functions. This is the baseline for criterion 2.
- [x] `post-quality-manager/includes/class-pqm-n8n.php`, `post-quality-manager/includes/class-pqm-rest-api.php` and `post-quality-manager/admin/class-pqm-admin.php`: turn each ternary into `if`/`else` that assigns a format string under a statement-level note, then a `sprintf` on its own line. For L1675, put the `sprintf` in its own statement.
- [x] `post-quality-manager/admin/class-pqm-admin.php`: make the ten escapes per Design Notes.
- [x] `tests/harness/reprint-comments-harness.php` (new): tokenize the plugin's PHP files outside `vendor/`. It fails when either of these holds:
  - a gettext call whose string holds a placeholder has no `translators:` comment on the line above that also sits where the re-print keeps it (the token before the comment is `;`, `{`, `}` or the open tag, or the call directly follows the comment as an argument);
  - a `phpcs:ignore` shares its line with code.

  Also update the expected strings of existing rows only where source text moved.
- [x] `_bmad-output/backlog/`: file the release-step scan ticket through `bmad-ticket`, following the owner's decision on recurrence. The harness only models Freemius's printer; the release job is the only place that sees the real re-print.
- [x] After the edits, render the same states on the Local site and `diff` them against the baseline, normalising nonces and relative times. Remove Plugin Check and the scratch files per `docs/local-verification.md`.

**Acceptance Criteria:**
- Given this branch, when the form's command runs on the tree that `bin/build-free.php` makes and the new harness runs, then there are 0 findings of either severity and the harness passes.
- Given the PR, when CI runs, then `gh pr checks` shows every check passing (ticket criterion 4).
- Given 4.0.1 is released (hitl: the owner merges this PR, then the release PR), when the form's command runs on 4.0.1's Free asset, then there are 0 `ERROR` and 0 `ERROR_LOW_SEVERITY`, recorded here beside criterion 5's output (ticket criterion 1).

## Implementation Notes

- Environment (orchestrator, 2026-11-16):
  - The Local site is **WordPress66**. The owner started it, and it is clean: WordPress 6.6, PHP 8.1.29, no plugin, `AUTOMATIC_UPDATER_DISABLED` already set.
  - The session scratchpad is `C:/Users/vhe/AppData/Local/Temp/claude/c6257ab00552099f/475b482a-2619-489e-8c62-a683cc8811cb/scratchpad/s125/`. Its contents:
    - `scripts/walk-lib.sh`, story 38's walk frame, with `SP` and `T` repointed to this folder: `source` it from `s125/` to get `lwp` (WP-CLI through `bin/local-wp.py`), `lwpd` (for `plugin install`, which downloads), `walk_backup`, `walk_restore` and `walk_check`;
    - `free400/post-quality-manager-4.0.0.zip`, the v4.0.0 Free asset (sha256 776cd175…3c3b, checked).
  - Back the site up before installing anything, and restore it at the end. Run `wp core version` at the start and the end. Never run `cron event run --all`, and never print `wp-config.php` (`memory/lessons.md`, 2026-11-11 and 2026-11-03).
  - The Pro tree for the criterion-2 baseline is the repository's own `post-quality-manager/` at the baseline revision. The Free tree is `php bin/build-free.php <dir>`.
- Implementer (2026-11-16, Opus subagent standing in for `bmad-build`'s dispatch):
  - **Walk.** `wp core version` 6.6 at the start and the end. Backup with `walk_backup`, restore with `walk_restore`; `walk_check` printed the three "same" lines. Plugin Check and the plugin are gone with the restore; the walk folder (dump, `wp-config.php` copy, builds, captures) is deleted. No cron event was run.
  - **Criterion 5 (the form's command on 4.0.0's Free asset, sha256 checked).** Plugin Check 2.2.0: 0 `ERROR`, 32 `ERROR_LOW_SEVERITY`, the reproduction's 32. Free-build lines, `E` = `WordPress.Security.EscapeOutput.OutputNotEscaped`, `T` = `WordPress.WP.I18n.MissingTranslatorsComment`:
    - `admin/class-pqm-admin.php`: 643E 1018E 1192T 1192T 1211T 1216T 1216T 1256T 1305E 1321E 1321E 1337E 1353E 1448T 1649E 1714E 1758T 1814E;
    - `includes/class-pqm-n8n.php`: 594T 594T 596T 596T 599T 599T 609T 609T;
    - `includes/class-pqm-rest-api.php`: 682T 682T 714T 714T 1678T 1678T.
  - **Criterion 6 does not hold.** `plugin update plugin-check` answered "Plugin already updated": 2.2.0 is the latest release on 2026-11-16, and its run is the one above. The fix went ahead.
  - **Criterion 2 baseline and diff.** `wp --user=1 eval-file` rendered 27 n8n Workflow states (page load through `render_workflow()`, and `render_workflow_body()` with an outcome line as the AJAX and REST replies call it) and the Settings page in three plan states (Free, Pro by the `postquality_is_pro` filter, lapsed by `postquality_license_lapsed`), and wrote `postquality_workflow_json()`, the string `download_workflow()` echoes. Nonces and "checked … ago" normalised. The states cover all five page states, every fix and secondary (test, update, re-import, Open Post Reviews, Finish setting up), the managed, adopted and hand-made maps, the Free variant, the guided and by-hand links, and the n8n link with and without an address. 4.0.0's asset and the baseline `build-free.php` tree rendered the same 57 files. After the edits, the Free tree and the Pro tree each rendered the same 57 files as their baseline (`diff -r` empty), through WordPress's real `wp_kses()`; the workflow file's sha256 is unchanged on both (Free `ac4d2187…51cb3`, Pro `c4767158…d8395`).
  - **The form's command on the fixed `build-free.php` tree:** `[]`, 0 findings. That tree keeps every comment, so the result proves the escapes (none of the ten rests on an ignore any more); the notes' survival in Freemius's re-print is modelled by the new harness and proven only by criterion 1 on 4.0.1.
  - **Ternaries.** Where both branches take the same arguments (n8n ×4, REST ×3, admin `$how`), each branch assigns a `$format` under its note, then one `sprintf`. Where they differ (admin `outdated`, `$version`, `when()`, `render_copy_value()`), each branch builds its own string under its note: a shared format would feed the one-placeholder string the wrong argument. The REST trigger's reply builds `$message` before the response.
  - **Escapes, as Design Notes say.** One deviation in form, not in effect: `$primary` and `$secondary` share an allow-list, so one `wp_kses()` takes the whole actions line (the two values and the space between them), which keeps a ternary out of the echo.
  - **More `phpcs:ignore` lines moved.** The harness's rule fails on any ignore sharing a line with code in the three files (matrix, Regression row), so the six others there moved to their own line above the code, unchanged: the Settings page's `$_GET['settings-updated']`, the n8n client's two `$wpdb->query()` calls, the REST class's two `$post` overrides and its `the_content` filter. No ignore was added; the workflow download's own-line ignore is the only new placement the plan asked for. The fifteen trailing ignores in `class-pqm-activator.php`, `class-pqm-db.php` and `pqm-functions.php` stay: those files are outside the matrix row's scope, and the form's scan reports nothing there (their sniffs are warnings or outside its checks).
  - **Harness scope.** Rule 1 (translator notes) reads every plugin PHP file outside `vendor/` and `admin/build/`; rule 2 (`phpcs:ignore`) the three files. Its 26 self-test rows feed each lost and kept pattern; on the baseline source it named exactly the 22 notes and the 10 echoes of the reproduction (plus the six ignores above), and it passes on the fix. A note under a `case` label or an `if (…):` label counts as statement-level (4.0.0's build kept those).
  - **Harness rows.** No existing row matched moved source text: the sweep passed unchanged.
  - **Records.** `memory/delivery-pipeline.md`'s Freemius deployment line gains the re-print's comment rules (bug 125, seen 2026-11-11, rules read 2026-11-16). Story 131 (`story-release-runs-the-directory-form-scan-on-the-free-build.md`) is the release-step scan, written by hand per `bmad-ticket`'s ticket reference (standalone, numbered criteria, `refined: false` for the owner's refine).
  - **Correction after review (2026-11-16):** the trailing ignores in `class-pqm-activator.php`, `class-pqm-db.php` and `pqm-functions.php` numbered 17, not fifteen, and they moved too. Each now sits, text unchanged, on its own line directly above the line where its sniff reports. With PHPCS 3.13.6 and the WPCS that the showcase's Plugin Check bundles, the sniffs named by the six files' ignores, plus `EscapeOutput` and `I18n`, report the same 19 findings before and after (`DirectDatabaseQuery` in `class-pqm-db.php`, which no ignore covers), and nothing in the admin class. The harness's rule 2 now reads every file, and the three-file scope with its self-test row is gone. The import sentence in `render_workflow_file()` is one `printf` again: the note sits before its `esc_html__()`, and the link-or-plain value is a ternary of two escaped strings.
  - **Not done here:** criterion 4 (CI on the pull request) and criterion 1 (4.0.1's asset) wait for the commit, the PR and the owner's merges. `npm run test:unit` was not run: no JS changed.

## Plan Change Log

## Review Triage Log

**Pass 1 (Quick, 2026-11-16):** 7 findings. Verdicts: 0 high, 1 medium, 3 low, 2 false, 0 maybe-false; 1 is a non-defect counted with the false ones. Routes: 3 patch, 2 defer, 2 rejected.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | Rule 2 of the harness (a `phpcs:ignore` sharing a line with code) runs only on the three files | medium | patch | Confirmed: running the harness with rule 2 on every file names 17 trailing ignores in `class-pqm-activator.php`, `class-pqm-db.php` and `pqm-functions.php`. A future trailing `EscapeOutput` ignore in any other file (the meta box prints HTML) would pass the harness and show only in the form's scan after the release. The owner chose this harness as the recurrence guard. The Implementation Notes' "fifteen" is wrong. Fix: rule 2 runs on every file, and the 17 ignores move to their own line |
| 2 | The six moved ignores break the "Never: a `phpcs:ignore` anywhere except the workflow download" line | false | reject | Read literally, that line would also forbid the existing ignores in other files, which the plan never touches. The only reading consistent with the rest of the plan is "no new ignore as the fix for an escape". The six are existing ignores moved, their text unchanged, and the count of ignores is the same |
| 3 | Leftover alignment spaces after `$post` (`class-pqm-rest-api.php` around L444) | low | patch | Confirmed: the padding lined up with `$original_post` on the line above, and the comment line now between them leaves it lining up with nothing. The fix is a direct correction |
| 4 | The import sentence is written twice in `render_workflow_file()` (one copy per `$n8n_url` branch) | low | patch | Confirmed: the two copies are identical today, but a later copy edit must touch both or they become two msgids. One `printf` whose argument is a ternary, with both branches escaped, passes `EscapeOutput`: WPCS skips a ternary's condition and checks each branch |
| 5 | The bug ticket's Notes still list the recurrence question as open | low | defer | Confirmed, but a build never writes to a ticket file (step 01). The decision goes into the ticket's records at the done step, with story 131 named |
| 6 | Story 131 was written by hand, not through `bmad-ticket` | low | defer | Confirmed: the file parses (`tickets.py`) and is `refined: false`. Its refinement through `bmad-ticket` runs that skill's validation, so nothing is lost before then |
| 7 | Ticket criteria 1 and 4 are not met yet | false | reject | Not a defect: the plan's acceptance criteria say both wait for the PR's CI and for 4.0.1's Free asset (hitl), and the plan is not `done` |

## Design Notes

How each of the ten outputs is escaped:

| Output | Treatment |
|---|---|
| `$license_line`, `$n8n` | Inline `printf( /* translators */ esc_html__( … ), '<a href="' . esc_url( … ) . '">' . esc_html__( … ) . '</a>' )`; the plain branch uses `esc_html_e` |
| Workflow body | Split into `print_workflow_body()`, which echoes, and `render_workflow_body()`, which wraps it in `ob_start()` and `ob_get_clean()`. The page calls the printer. No allow-list is needed, and the AJAX output stays the same string |
| `$primary`, `$secondary`, `$guided_link`, `$by_hand_link`, `$nodes_pick` | `wp_kses( $x, <allow-list> )`, one private constant per allow-list, listing only the tags and attributes the value holds |
| `$json` | The workflow file's body, served as `application/json`. HTML escaping would corrupt it and re-encoding changes its bytes, so a `// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON file body, not HTML` goes on its own line above `echo $json;`. The re-print keeps it |

The `wp_kses` allow-lists:

| Value | Allowed tags and attributes |
|---|---|
| `$primary`, `$secondary` | `button` (`type`, `class`, `data-step`, `data-pqm-post`, `data-pqm-confirm`, `data-pqm-busy`), `a` (`class`, `href`) |
| `$guided_link` | `p`, `button` (`type`, `class`, `data-step`) |
| `$by_hand_link` | `p`, `a` (`href`, `class`, `data-pqm-post`, `data-pqm-body`, `data-pqm-focus`) |
| `$nodes_pick` | `strong` |

Skipped:
- The form's command in `release.yml`'s Free-build check: it gets its own backlog ticket (owner, 2026-11-16).
- CI `strict`: CI scans the source tree, which keeps every comment, so it would not see the re-print.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every harness passes, the new one included.
- `php -l` on the three PHP files and the new harness -- expected: clean.
- The form's command (ticket Reproduction) through `wp plugin check` on the Local site, on the `bin/build-free.php` tree -- expected: `[]`, or 0 findings.

**Manual checks (if no CLI):**
- The rendered-markup diff on the Local site against the baseline is empty, apart from nonces and relative times.
