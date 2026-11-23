---
id: 148
type: bug
title: "Deleting the Pro build and opening its pricing page write PHP warnings to debug.log"
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

# Deleting the Pro build and opening its pricing page write PHP warnings to debug.log

## Description

Two paths of the Pro build write PHP warnings to `debug.log`, both from the vendored Freemius SDK: uninstalling the Pro build with deactivation and deletion in one request, and opening Freemius's hidden pricing page on PHP 8.1. A site with debugging on should see no warning from the plugin's folder.

## Reproduction

1. Local WordPress 6.6, PHP 8.1.29, Pro 4.0.5 on the free plan, Free 4.0.5 installed and inactive (story 38's N4 stage C, 2026-11-19).
2. `wp plugin uninstall post-quality-manager-premium --deactivate` (deactivation and deletion in one request).
3. Actual: 4 PHP warnings in `debug.log` from the plugin's folder, in the vendored SDK (`class-freemius.php` around L8273, `fs-essential-functions.php` around L388). A deletion from the Plugins screen (two requests) was not run.
4. Pro 4.0.2 (the 2026-11-17 Pro walk): open `admin.php?page=postquality-dashboard-pricing` (kept registered by bug 59's `pricing_url` filter). Actual: the page has no title, and `debug.log` gets `PHP Deprecated: strip_tags(): Passing null` from core's `admin-header.php`.
5. Expected: no line from the plugin's folder in `debug.log` on either path.

## Cause Hypothesis

- Uninstall: the SDK's deactivation calls `remove_sdk_reference()` → `fs_fallback_to_newest_active_sdk()`; with no active SDK left it sets `$fs_active_plugins` to an empty object, and the uninstall in the same request reads it (`include_once` loads nothing new).
- Pricing page: the SDK registers it with an empty parent and no title, so core's header passes null to `strip_tags()`.
Both are SDK paths; the SDK stays unmodified (chore 89).

## Acceptance Criteria

1. **The Plugins-screen deletion is checked first**
   **Given** Pro on a site with debugging on
   **When** it is deactivated and then deleted from the Plugins screen
   **Then** the result (lines or none) is recorded in Notes
2. **No warning reaches debug.log, or the owner's choice is recorded**
   **Given** the paths in the reproduction
   **When** they run on the fixed build
   **Then** no line from the plugin's folder is written, or, as with bug 135, the owner chose an upstream report or accepted the lines, recorded with the reason
3. **Tests cover the condition found and fixed**
   **Given** the harnesses
   **When** they run
   **Then** a test that fails on the defect covers any plugin-side guard the fix adds
4. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** no warning is written, with the evidence recorded in Notes — this supersedes 1 to 3

## References

- parent — none
- source — story 38's plan, Implementation Notes: the 2026-11-17 Pro walk and the 2026-11-19 frame N4 (stage C)
- related — bug 59 (the `pricing_url` filter), chore 89 (the SDK kept unmodified), bug 135 (a plugin-side guard around the SDK)

## Notes

- Decision (owner, 2026-11-23): filed from story 38's findings batch; does not block the WordPress.org submission.
- Assumption: severity P3 — warnings only, on a site with debugging on; nothing breaks. Risk low.
- Open question: a plugin-side guard, an upstream report to Freemius, or accept and record (the owner's call, as for bug 135).
