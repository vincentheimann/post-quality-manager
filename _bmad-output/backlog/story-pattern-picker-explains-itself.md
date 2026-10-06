---
id: 24
type: story
title: "The pattern picker explains itself: edit links, a create button and the listing rule"
parent: none
covers: []
after: [23]
assignee: ""
refined: true
hitl: false
risk: low
---

# The pattern picker explains itself: edit links, a create button and the listing rule

## Description

Owner feedback 2026-10-06 on story 23: the Pattern dropdown in Agent-Specific Overrides "isn't satisfying yet". It was a bare select at the bottom of the Takeaways / Sources box: no way to jump to the pattern, a sentence of instructions as the empty state, and no word on why a pattern is missing from the list (the owner's new pattern had no List block). This story makes the row explain itself: it reads "Pattern to use", says where the items land and that only published patterns containing a List block are listed, links to the chosen pattern and to Appearance → Patterns, and offers "Create a pattern" when none qualifies. A live preview was built and removed the same day: rendering through the front end loaded third-party scripts into Settings.

## Acceptance Criteria

Verify: in the Takeaways and Sources boxes the row reads "Pattern to use" with the select (Plain list + "Your patterns"), an "Edit pattern" link (chosen pattern only) and a "Manage patterns" link; the description states the placement and the List-block listing rule; with no eligible pattern the row offers "Create a pattern"; a stored pattern that no longer qualifies shows as Plain list with no Edit link; no preview and no front-end hook.

## References

- settings UI — post-quality-manager/admin/class-pqm-admin.php (Overrides loop, Pattern row from story 23)
- insert logic — post-quality-manager/includes/class-pqm-blocks.php (`patterns()`, `place()`)
- settings JS/CSS — post-quality-manager/admin/js/pqm-admin.js, post-quality-manager/admin/css/pqm-admin.css
- parent story — story 23 (PR #36)
