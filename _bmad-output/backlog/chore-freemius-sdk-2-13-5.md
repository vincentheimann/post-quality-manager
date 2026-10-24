---
id: 89
type: chore
title: "The vendored Freemius SDK is 2.13.5, unmodified"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# The vendored Freemius SDK is 2.13.5, unmodified

## Description

Freemius released SDK 2.13.5 on 2026-10-22, a security hardening of input handling and output escaping in the admin notices and the opt-in flow, and asks every product to update. The plugin vendors 2.13.4, and the WordPress.org review team asks for the latest stable version of every bundled library. This chore replaces `vendor/freemius/` with the 2.13.5 tag, unmodified.

## Acceptance Criteria

1. **The vendored copy is the tag**
   **Given** the Freemius SDK repository at tag 2.13.5
   **When** its tree is compared with `post-quality-manager/vendor/freemius/`, the files the plugin leaves out as it did for 2.13.4 excepted
   **Then** no file differs
2. **Nothing the plugin relies on moved**
   **Given** the updated SDK
   **When** every harness, `npm run test:unit`, the Free build (`bin/build-free.php`) with its harness, and Plugin Check on the Free build run
   **Then** all are green and Plugin Check reports no new error or warning
3. **The records name the version**
   **Given** the merged change
   **When** `docs/distribution.md` and `memory/freemium-and-roles.md` are read
   **Then** they say 2.13.5, and the SDK findings recorded for 2.13.4 are re-checked against 2.13.5 (the `pricing_url` probe of bug 59, the deactivation feedback of bug 90)

## Boundaries

- Must not change: the SDK's code (the copy stays unmodified), the init keys, the filters the plugin sets.

## References

- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (row 14)
- external — https://github.com/Freemius/wordpress-sdk/releases (2.13.5, "Latest", 22 Oct 2026)
- precedent — memory/freemium-and-roles.md (how 2.13.4 was vendored, story 44)

## Notes

- Decision (owner, 2026-10-24): vendored in this sprint, before story 88's rename.
