---
title: 'The n8n Workflow page tells the user how to link credentials after an import'
type: 'bugfix'
ticket: ''
created: '2026-10-06'
status: 'built'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'a25eda5460d9b8c0a66bd2e077a91738bb3fe1d6'
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** After importing the workflow (file or URL), n8n flags every Claude node and `Update WP Post (Rewrite)` with "Credentials for 'Header Auth' / 'Basic Auth' are not set" and refuses to publish. n8n links credentials by an instance-specific ID that a template can't carry; opening a node once makes n8n pick the saved credential of that type. The page never says so, and the update notice even promises "your settings and credentials are untouched". The owner hit this on 2026-10-06 and asked for an infobox: a frustrating bottleneck.

**Approach:** One small info box (`notice notice-info inline`), rendered by one helper in both import places: the setup wizard's Import step and the connected page's Workflow File section. It names the warning the user sees and the fix: open each flagged node once (the six Claude nodes and Update WP Post (Rewrite)), close it, save, then activate. The update notice stops promising untouched credentials.

</frozen-after-approval>

## Implementation Notes

Oneshot: copy + one private render helper in `admin/class-pqm-admin.php`, plus a render check. The copy describes n8n's behavior without calling it an n8n bug, because that isn't confirmed upstream.

## Verification

**Commands:**
- `php -l post-quality-manager/admin/class-pqm-admin.php`; `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green

## Review Triage Log

Quick pass (1 lens): 1 medium, 4 low, all patched. All 10 harnesses green (render-matrix 113).
- medium, patch: a first-time user may have no saved credential at all, so "open the node" fixed nothing for them. The hint now says to create it from the credential field in the node.
- low, patch: the wizard said "activate" twice (hint + old line). The old line was dropped.
- low, patch: the connected-page check was page-wide. It is now scoped to the Workflow File section.
- low, patch: the update-notice check grepped the source. It now renders the notice with an older seen version (`get_option` stub reads `$GLOBALS['tw_options']`).
- low, patch: the hint's node list was literal with nothing tying it to the template. The harness now counts the template's credentialed nodes (6 Claude header-auth + Update WP Post basic-auth), so ADR 0009's provider work will trip it.
