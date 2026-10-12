---
title: "The plugin icon is designed and produced"
ticket: 51
status: done
---

The owner picked the Q seal from three drafted directions (checked page, score bars, Q seal), then C1g from its refinements: a perfect ring whose tail continues into a check, with ring diameter, long arm and short arm in the ratio φ² : φ : 1. The direction, colours and rules are in `docs/brand.md` § Icon. The geometry was computed with a throwaway Python script (outside the repository) that centres the bounding box and scales it to a 19-unit width. Coordinates are rounded to 2 decimals, and the shapes are filled only, with no strokes.

Files: `assets/icon.svg` (colour tile), `assets/icon-128x128.png`, `assets/icon-256x256.png`, `assets/icon-512x512.png` (favicon and Freemius product image), all at the repository root and never in the zip. `post-quality-manager/admin/img/post-quality-manager.png` (256 px) is what the Freemius `plugin_icon` filter now returns instead of the SDK's stock icon. `PQM_Admin::MENU_ICON` holds the mono mark as a base64 SVG data URI and replaces `dashicons-chart-line` in `add_menu_page()`. Records: `docs/distribution.md` (icon row done, 512 px row, owner marker), `docs/release-notes/unreleased.md`, `memory/release-readiness.md`.

Verified: `php -l` is clean on both touched PHP files. The data URI decodes byte for byte to the mono SVG. The PNGs were rendered from `assets/icon.svg` by headless Edge at scale factor 1, and PIL confirms 128×128, 256×256 and 512×512 with `#1b2a41` corners. A preview of the produced files (the menu icon in the default, current-item and light schemes; the SVG at 16/32/48 px; the three PNGs) was rendered and checked by eye. No mark is clipped.

Skipped: an `index.php` in `admin/img/`, because `admin/css/` has none either. Add one when the plugin adopts that convention.

Owner check 2026-10-12: the menu icon approved in WP Admin on the owner's production site (not Local); `assets/icon-512x512.png` uploaded as the Freemius product image.

Status `built`; done is the owner's mark.
