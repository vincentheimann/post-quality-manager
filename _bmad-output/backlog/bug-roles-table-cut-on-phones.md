---
id: 108
type: bug
title: "On a phone, the Roles tab's table is wider than the screen and cuts its last columns"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# On a phone, the Roles tab's table is wider than the screen and cuts its last columns

## Description

On a phone, Settings → Roles shows the table "What each role can do" wider than the screen: **Manage agents** is cut in half and **Manage settings** is off the screen. The hint "Without this, the role does not see Dashboard or Post Reviews." under the Subscriber row wraps into a narrow column, one or two words a line, and makes the row tall.

## Reproduction

1. The showcase, plugin 3.10.2 Pro build with a license, 2026-11-03 (story 100's capture walk).
2. Settings → Roles at a 400 px phone viewport.
3. Actual: the table's right edge runs past the screen (the site's `assets/img/feature-roles-phone.png`, story 100). Whether the page scrolls sideways to reach the last column is to check.
4. Expected: every column reachable on a phone, by a layout that fits or a table that scrolls inside its own frame.

## Cause Hypothesis

`.pqm-roles-table` (`admin/css/pqm-admin.css` L57) is a `widefat` table with six columns and no narrow-screen rule; the inline hint (`.pqm-role-hint`, L59) widens its cell.

## Acceptance Criteria

1. **Every column is reachable on a phone**
   **Given** the Roles tab on Pro
   **When** it is shown at 360 px
   **Then** each role's five checkboxes can be read and changed without the page itself scrolling sideways
2. **Tests cover the condition found and fixed**

## References

- found by — _bmad-output/backlog/story-site-features-page-marks-pro.md (story 100's capture walk)
- source — `admin/class-pqm-admin.php` (the Roles tab, `pqm-roles-table`), `admin/css/pqm-admin.css` L57-L59

## Notes

- Fact (2026-11-03): the site's Features page keeps the capture as the plugin renders it; after the fix, `feature-roles-phone` can be recaptured (owner's choice).
