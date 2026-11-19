---
id: 139
type: bug
title: "Plugin Check flags a slow meta query in the Free build"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# Plugin Check flags a slow meta query in the Free build

## Description

Plugin Check on the Free release zip reports one warning beyond the accepted list: `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` in `includes/class-pqm-activator.php` (the build's L240). The source's `phpcs:ignore` sits on the line above a one-line `get_users( [ 'meta_key' => … ] )` call; Freemius's re-print of the Free build splits that call over several lines, so the ignore no longer reaches the flagged line. Normal Plugin Check runs (WP-CLI and Tools → Plugin Check) show it; the directory form's submission scan did not report it. Reviewers and site owners who run Plugin Check should see only the accepted warnings.

## Reproduction

1. Local WordPress 6.6, Free 4.0.5 (the GitHub release zip, sha256 checked), Plugin Check 2.2.0 from the directory (story 38's T1 frame, 2026-11-19).
2. `wp plugin check post-quality-manager`, or Tools → Plugin Check.
3. Actual: 0 errors, 23 warnings; one of them is `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` at `includes/class-pqm-activator.php:240`. CI's Plugin Check job (on its own Free build, before Freemius's re-print) reports 0 errors and 22 warnings, without it.
4. Expected: the accepted warnings only, as CI reports.

## Cause Hypothesis

`PostQuality_Activator::migrate_user_meta()` (source around L215–216): `// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key` above `foreach ( get_users( [ 'meta_key' => $old, 'fields' => 'ids' ] ) as $user_id )`. Freemius re-prints every PHP file of the Free build; the array argument lands on its own lines, and a `phpcs:ignore` covers only the next line. This is bug 125's family (comments moved or dropped by the re-print); its fix (9c2120d) did not cover this call. CI cannot see it because it checks the build before the re-print.

## Acceptance Criteria

1. **The Free release zip shows only the accepted warnings**
   **Given** the Free build as Freemius re-prints it
   **When** Plugin Check runs on it
   **Then** `slow_db_query_meta_key` is not reported, and the warnings equal CI's list
2. **The migration is unchanged**
   **Given** a site with legacy `pqm_` user meta
   **When** the one-time upgrade runs
   **Then** the meta moves to `postquality_` as before (the prefix harness rows stay green)
3. **The guard survives the re-print**
   **Given** the suppression chosen
   **When** the line is re-printed over several lines
   **Then** it still covers the flagged line (for example a `phpcs:disable` / `phpcs:enable` pair around the call, or the query argument built on its own line), with a harness or check that fails on today's form
4. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the warning is not reported, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 3

## References

- parent — none
- source — `_bmad-output/backlog/story-wordpress-org-directory-listing-plan.md`, pass list rows P068 and P035 and the T1 entry (2026-11-19)
- related — bug 125 (`bug-submission-scan-flags-32-low-severity-errors-in-the-free-build.md`), commit 9c2120d
- logs — the T1 frame's results, session scratchpad (not committed): Plugin Check 2.2.0 output on the 4.0.5 Free zip; CI run 44330838788's Plugin Check job for the comparison

## Notes

- Decision (owner, 2026-11-19): ships with bug 138 in the same 4.0.x release; it joins story 38's `after`.
- Fact: the directory form's exact submission scan did not report it (story 38's T1 frame); normal Plugin Check runs do.
- Assumption: severity P3, risk low — a warning, not an error; the change touches one call's comment or layout.
