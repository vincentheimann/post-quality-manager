---
id: 125
type: bug
title: "The directory's submission scan flags 32 low-severity errors in the Free build"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
severity: P2
estimate: ""
---

# The directory's submission scan flags 32 low-severity errors in the Free build

## Description

The WordPress.org add-plugin form scans each uploaded zip with Plugin Check. On 4.0.0's Free build it reports 32 findings of type `ERROR_LOW_SEVERITY`: 22 strings with placeholders that carry no translator note, and 10 values printed on the Settings and n8n Workflow pages and in the workflow download that are not escaped where they are printed. They do not block the upload, but the form marks each one "ERROR*" for a reviewer's manual check, and the directory's translators would get those 22 strings without their notes. Expected: the scan finds nothing, in patch release 4.0.1, before story 38 submits; the owner's merges are the hitl steps in Notes.

## Reproduction

1. Story 38's clean Local site (WordPress 6.6, PHP 8.1.29), with the Free asset of release v4.0.0 (`post-quality-manager-4.0.0.zip`, sha256 `776cd175f513597c0282cdaaedd47a6a6b9f320c29710c3a28c1bc28d1b14c3b`) installed and active. Plugin Check 2.2.0 installed for the run only.
2. From the session scratchpad (a folder with no `post-quality-manager/` in it), through the local WP-CLI entry point, run the form's exact command:
   `wp plugin check --error-severity=7 --warning-severity=6 --include-low-severity-errors --exclude-checks=prefixing --categories=plugin_repo --format=json --slug=post-quality-manager <path>`
   where `<path>` is the installed plugin folder, `wp-content/plugins/post-quality-manager` under the site's root.
3. Actual (2026-11-11): 0 `ERROR` and 32 `ERROR_LOW_SEVERITY`:
   - 22 `WordPress.WP.I18n.MissingTranslatorsComment`: 8 in the admin class, 8 in the n8n client, 6 in the REST API class;
   - 10 `WordPress.Security.EscapeOutput.OutputNotEscaped` in the admin class, each on the echo of a value built earlier (Notes list them).
4. Expected: 0 `ERROR` and 0 `ERROR_LOW_SEVERITY`.
5. Per the upload handler's code (References), the form lists the same findings as "ERROR*" with "This may be a false-positive, and will be manually checked by a reviewer."

Neither CI's Plugin Check nor story 95's run shows them: both scanned a tree that keeps the source's comments, never Freemius's re-print. Run without a severity option, Plugin Check 2.2.0 would report these 32 as `ERROR`.

## Cause Hypothesis

The source already carries a translator note directly above each of the 22 calls and a `phpcs:ignore` on each of the 10 echoes, so the defect appears between the source and the Free build. Freemius generates the Free build by re-printing every PHP file. In 4.0.0's Free build, each multi-line expression sits on one line and the notes inside it, before a ternary branch or a concatenated operand, are gone; nine trailing `phpcs:ignore` comments moved to the line after their echo, where they no longer apply, and the tenth, inside a `printf` argument list, is gone. Two checks miss this by design: CI scans the tree the repository's Free-build script makes, which keeps every comment, and the release workflow compares Freemius's build with that tree as token streams with comments dropped.

## Acceptance Criteria

1. **The form's scan finds nothing in the Free build**
   **Given** the Free build Freemius generates for the release that carries the fix (4.0.1), installed on a Local site as in the reproduction
   **When** the form's exact command runs on it
   **Then** it reports 0 `ERROR` and 0 `ERROR_LOW_SEVERITY`, and the output is recorded in the plan
2. **What the pages print is unchanged, and escaped once, where it is printed**
   **Given** each of the ten outputs the scan named, on the Free and the Pro build, in every page state that prints them
   **When** the Settings and n8n Workflow pages render (the n8n Workflow body's refresh included) and the workflow file is downloaded
   **Then** each value is escaped at the point of output; the rendered markup equals 4.0.0's for the same state, nonces and relative times ("checked … ago") aside, with every link, button and `data-pqm-*` attribute intact and no entity shown as text (such as `&amp;amp;`); and the downloaded workflow file is byte for byte the one 4.0.0 serves for the same settings
3. **Every placeholder string keeps its translator note in the Free build**
   **Given** the 22 strings the scan named, and every other translatable string with a placeholder
   **When** the strings and their notes are extracted for translation from the source and from the fix's Free build
   **Then** each of these strings has a `translators:` note naming each placeholder, and every string both extractions hold carries the same note in both
4. **CI keeps passing**
   **Given** the fix's pull request
   **When** CI runs
   **Then** every check passes: both Plugin Check jobs, PHP lint, the harness sweep and the JS unit tests
5. **The same check shows the defect and the fix**
   **Given** the form's exact command
   **When** it runs on 4.0.0's Free asset
   **Then** it reports the 32 findings of the reproduction, and that output is recorded in the plan beside criterion 1's
6. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it runs on 4.0.0's Free asset with the latest Plugin Check release
   **Then** it reports no `ERROR_LOW_SEVERITY`, with the output and that version recorded in Notes — this supersedes 1 to 5

## Boundaries

- Must not change: what each page shows and does (copy, markup, links, the AJAX refresh of the n8n Workflow body), the downloaded workflow file, the text of any translatable string, the vendored Freemius SDK (`vendor/`, excluded from the scan), and the premium markers' rules (ADR 0014).
- The version is release-please's: the fix lands as a `fix:` commit, and 4.0.1 comes from its release PR, never a hand bump.

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md, Implementation Notes "F1, after the pause" (row 5, the form's scan) and Code Map (the form's command)
- blocks — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38, criterion 4 and Notes, decision of 2026-11-11)
- the form's command — _bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission-plan.md, "AC1 halt resolved", the submission scan bullet (L124, read in WordPress.org's source on 2026-10-29)
- the re-print — memory/delivery-pipeline.md, the Freemius deployment line
- the walk — docs/local-verification.md, § Other checks on the same site (Plugin Check run from the scratchpad)
- external — https://github.com/WordPress/wordpress.org/blob/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/jobs/class-plugin-scan.php (`run_plugin_check()`: only type `ERROR` fails the scan)
- external — https://github.com/WordPress/wordpress.org/blob/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/shortcodes/class-upload-handler.php (`check_plugin()`: the "*" label and the reviewer note)

## Notes

- Decision (owner, 2026-11-11): all 32 are fixed before story 38's submission and ship in patch release 4.0.1; story 38's pre-production pass then runs on 4.0.1.
- Decision (owner, 2026-11-11): escaping is late and correct. Escape at output with the function that fits the context; where markup is intended, `wp_kses` with an explicit allow-list; never escape again markup already built from `esc_html`/`esc_url` pieces. Translator notes take the form WordPress expects.
- Fact: WordPress's form is a `/* translators: … */` comment on the line directly above the line that holds the call, describing each placeholder. The source already has that form; the re-print drops it when the call sits inside a multi-line expression, so the note must survive the re-print (criterion 3).
- Fact (WordPress.org trunk, read 2026-11-11): only a record of type exactly `ERROR` fails the form's scan; the upload handler labels `ERROR_LOW_SEVERITY` records with a "*" and the reviewer note. The scan runs under a 45-second timeout.
- Fact (Plugin Check 2.2.0, `Plugin_Check_Command.php`, read 2026-11-11): the severity filter runs only when a severity option is given; without one, a low-severity result keeps the type `ERROR`.
- Fact (source lines, the plugin tree of v4.0.0, equal to 78c8bc8; Free build line in brackets, matched by content):
  - Escaping, `admin/class-pqm-admin.php`: L831 `$license_line` [643]; L1413 `self::render_workflow_body()` [1018]; L1708 `$guided_link` [1305]; L1713 `$primary` and `$secondary` [1321]; L1723 and L1728 `$by_hand_link` [1337, 1353]; L1956 `$n8n`, an argument of a `printf` [1649]; L1993 `$nodes_pick` [1714]; L2112 `$json`, the workflow file download [1814].
  - Translator notes, `admin/class-pqm-admin.php`: L1588 and L1590 [1192]; L1617 [1211]; L1625 and L1627 [1216]; L1675 [1256]; L1787 [1448]; L2057 [1758].
  - Translator notes, `includes/class-pqm-n8n.php`: L563 and L565 [594]; L573 and L575 [596]; L584 and L586 [599]; L602 and L604 [609].
  - Translator notes, `includes/class-pqm-rest-api.php`: L733 and L735 [682]; L776 and L778 [714]; L1885 and L1887 [1678].
- Fact (the cause's checks): CI's free-build job scans the tree `bin/build-free.php` makes; the release workflow's "Check the Free build Freemius generated" step compares it with Freemius's build through `bin/compare-php-trees.php`, which drops comments (`memory/delivery-pipeline.md`). The admin class keeps 23 of its 34 translator notes in 4.0.0's Free build: 8 lost to the re-print, 3 with the stripped Pro code.
- Fact: the full scan output (JSON per file) is in story 38's session scratchpad, `s38/findings/form-scan.txt`; it goes with that session.
- Assumption: severity P2. Users see no change and the upload is not blocked, but each finding goes to a reviewer's manual check and the 22 strings would reach the directory's translators without notes. Not P0: the source's comments say each echoed value is escaped piece by piece where it is built; that is not re-read here for all ten.
- Assumption: risk medium. The fix touches the admin class's rendering in ten places; an allow-list that misses an attribute, or a second escape, would change the markup in a way review can miss. Criterion 2 is the guard.
- Assumption: criteria 1 and 5 run on the Free release assets, their digests checked, on a Local site per `docs/local-verification.md` (Plugin Check installed for its run only, run from the scratchpad, removed by the restore). Story 38's pass repeats criterion 1 as its form-scan row.
- Assumption: the form runs the latest Plugin Check release (criterion 6).
- Open question: only a release run makes Freemius's Free build, so a fix that passes on the source and on the Free-build script's tree can still fail after the re-print, and that would show only after 4.0.1 is out. Whether the re-print can be checked before the release (a Freemius upload that is not released, for example) is unverified.
- Open question (not decided, for the owner): a check so this cannot come back. The gap is which tree is scanned, not the severity: CI never sees Freemius's re-print. The release workflow's "Check the Free build Freemius generated" step is the one place that holds it, so the form's command could run there. CI could also take the form's arguments: the pinned `wordpress/plugin-check-action` v1.1.9 has the inputs `error-severity`, `warning-severity`, `include-low-severity-errors`, `slug`, `categories` and `exclude-checks` (its `action.yml`), and its `src/main.ts` fails the job only on type `ERROR` unless `strict` is on (both read 2026-11-11), so `ERROR_LOW_SEVERITY` results would not fail CI without `strict`. Options: the release step, CI with `strict`, both, or a separate ticket.
- Open question: the workflow download (L2112) prints JSON, not HTML. Which output form satisfies the scan while the file stays byte for byte the same (criterion 2) is for the build to find.
- Handoff: `docs/local-verification.md`'s Plugin Check line runs without the form's arguments; story 126 changes that guide and leaves the line as it is unless the recurrence question above says otherwise.
- Records at done: `memory/delivery-pipeline.md`'s Freemius deployment line gains the two re-print behaviours seen here, dated 2026-11-11: a comment inside a multi-line expression is dropped, and a trailing comment moves to the line after its statement.
- hitl (owner): merge the fix's pull request, then 4.0.1's release PR. The agent then downloads 4.0.1's Free asset and runs criterion 1.
