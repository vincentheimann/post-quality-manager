---
id: 51
type: story
title: "The plugin icon is designed and produced"
parent: none
covers: [UV7]
after: [10]
assignee: ""
refined: false
hitl: true
risk: low
---

# The plugin icon is designed and produced

## Description

Owner request (2026-10-09): the icon gets its own ticket, carved out of story 41 so the mark exists early and on its own. Today the admin menu shows the stock `dashicons-chart-line` (`admin/class-pqm-admin.php`, `add_menu_page()`) and no icon file exists anywhere. This story produces the one mark the plugin shows everywhere: the admin menu icon (a monochrome SVG that follows the core admin color schemes), the WordPress.org directory icons (`icon-128x128.png`, `icon-256x256.png`, and `icon.svg`), and the same mark exported for the Freemius product image and the postquality.com favicon. The agent drafts a few directions (each with a sentence on what it means and how it reads at 20 px), the owner picks one (hitl), the agent produces the files and records the direction in `docs/brand.md`. Banners, screenshots, the imagery guidelines and the copy sweep stay in story 41.

## Acceptance Criteria

Verify: `docs/brand.md` records the owner-approved icon direction (mark, colors, what it must not contain) in the section story 41 extends; the admin menu shows the custom SVG icon (base64 data URI passed to `add_menu_page()`) and reads correctly at 20 px in every core admin color scheme; `assets/icon-128x128.png`, `assets/icon-256x256.png` and `assets/icon.svg` exist at the repository root (SVN assets, never in the zip, per story 38) and `docs/distribution.md` marks the icon row done; a favicon-ready export (SVG or 512 px PNG) is saved for story 45 and the Freemius product image; the mark carries no WordPress, Claude, Anthropic or n8n trademark (story 38 AC5).

## Boundaries

- Icon only: no banner, no screenshots, no logo lockup with the name, no copy changes (story 41).
- No new build step; the menu icon is a static SVG string, not generated at runtime.

## References

- parent slice — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41: direction for the rest, banners, screenshots, copy sweep)
- code — post-quality-manager/admin/class-pqm-admin.php, `add_menu_page()` (the stock dashicon to replace)
- canon — docs/brand.md (the section the direction extends)
- sheet — docs/distribution.md (asset rows to close)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC5: asset names and trademark rules)
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/ (icon sizes and names)
- external — https://developer.wordpress.org/reference/functions/add_menu_page/ (`$icon_url` accepts a base64 SVG)

## Notes

- Filed 2026-10-09 (owner). Sliced out of story 41 so the mark exists before the banners reuse it and before the Freemius product and postquality.com need an image. Story 41 now waits on this one.
- HITL: the owner picks the direction and approves the final files; the agent drafts and produces.
- Not ranked by `/prioritize` yet.
