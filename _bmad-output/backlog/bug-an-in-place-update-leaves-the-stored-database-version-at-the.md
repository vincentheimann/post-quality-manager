---
id: 149
type: bug
title: "An in-place update leaves the stored database version at the old release"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P3
estimate: ""
---

# An in-place update leaves the stored database version at the old release

## Description

After a site updates the plugin in place from 3.9.0, `postquality_db_version` still reads "3.9.0", although the names migrated and the table works. Only activation writes the version, and an update through WordPress's upgrader does not activate. A later schema step that reads this option would think the database is older than it is.

## Reproduction

1. Local WordPress 6.6, the 3.9.0 full build active with data (story 38's licensed sitting, stage A: 2026-11-19 on 4.0.6, 2026-11-23 on 4.0.7).
2. Update in place to Free 4.0.7 (`wp plugin install <zip> --force`, WordPress's replace path), then load an admin page.
3. Actual: every stored name moved to `postquality_` and the reviews table holds its rows, but `postquality_db_version` reads "3.9.0".
4. Expected: the option reads the running version's database version after the first load.

## Cause Hypothesis

Only `create_table()`, run on activation, writes `postquality_db_version`; the one-time upgrade on `init` (`migrate_stored_names()`) moves the names but does not check or write the database version.

## Acceptance Criteria

1. **An update records the database version**
   **Given** a site updated in place from an older release
   **When** the first request after the update runs
   **Then** the database version is checked, any table change is applied, and the option holds the current version
2. **Tests cover the condition found and fixed**
   **Given** the harnesses
   **When** they run
   **Then** a test that fails on the defect covers an update from 3.9.0 without activation
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the option already holds the current version, with the evidence recorded in Notes — this supersedes 1 and 2

## References

- parent — none
- source — story 38's plan, pass row P161 and Implementation Notes (the licensed sitting)
- related — story 91 (stored names move to the `postquality_` prefix)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P3 — nothing reads the stale value today; a later schema step would. Risk medium — an option later schema steps may read, on every site's first request after an update.
