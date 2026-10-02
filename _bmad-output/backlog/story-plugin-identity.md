---
id: 10
type: story
title: "The plugin has a defined identity — wording, logo, imagery, copywriting"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# The plugin has a defined identity — wording, logo, imagery, copywriting

## Description

Owner request (2026-10-02). `docs/brand.md` covers admin tone and copy rules, but the plugin has no full identity: no logo or icon (WP admin menu icon, and the future WordPress.org asset set — banner, icon, screenshots), no tagline, inconsistent wording (the product is "Post Quality Manager" while admin pages title themselves "Content Quality"), and no copywriting pass across README, admin copy and a future listing. This ticket defines the identity — naming and wording rules, visual identity (logo/icon, color accents within the native WP admin constraints), imagery, and a copywriting sweep — as an owner-approved extension of `docs/brand.md`, plus the produced assets. It feeds UV7 (WordPress.org distribution; A1–A2 discoverability is the plugin's largest unrealized value) and the admin-UI rebuild (ADR 0005), which should apply the identity rather than invent its own.

## Acceptance Criteria

Verify: an owner-approved identity definition exists (extending `docs/brand.md`: name usage, tagline, wording rules incl. the "Post Quality Manager" vs "Content Quality" resolution, logo/icon direction, imagery guidelines), the core assets are produced (menu icon at minimum; WordPress.org banner/icon/screenshots may wait for UV7), and the wording pass is applied to README and admin copy — or sliced into follow-up tickets if the sweep outgrows one story.

## References

- docs/brand.md — the existing tone/copy canon this extends, never contradicts
- docs/user-paths.md — A1–A2 (discoverability), UV7 (distribution)
- design — `_bmad-output/initiative-post-quality-manager/ux-dashboard-post-reviews/DESIGN.md` (the rebuild applies the identity)

## Notes

- Open question: sequencing — does the identity land before the admin-UI rebuild epic (so the rebuild ships it) or alongside it? Settle at refinement.
- Assumption: `docs/brand.md` stays the canonical brand home and is extended, not replaced.
- HITL: the owner approves name/tagline, logo direction and imagery style; agent drafts options and produces/apply the rest.
