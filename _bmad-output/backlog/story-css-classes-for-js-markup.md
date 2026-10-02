---
id: 5
type: story
title: "JS-generated admin markup carries CSS classes, not inline styles"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# JS-generated admin markup carries CSS classes, not inline styles

> **Dropped 2026-10-02:** subsumed by `epic-ui-rebuild` (spec CAP-3/CAP-6 — one render source on @wordpress/components deletes the string-built markup and inline styles wholesale; see its story 7 "PHP strip and dead weight").

## Description

`pqm-admin.js` still builds HTML strings with `style=""` attributes (rewrite confirmation card, action buttons, summary paragraph). The PHP templates are clean; sweep the JS render helpers into `pqm-admin.css` classes for CSP-friendliness.

## Acceptance Criteria

Verify: `grep -c 'style="' post-quality-manager/admin/js/pqm-admin.js` returns 0 and the affected elements render unchanged.

## References

- code — post-quality-manager/admin/ (pqm-admin.js, pqm-admin.css)

## Notes

- Technical debt, no user-path stage; schedule when touching the admin JS for another reason.
