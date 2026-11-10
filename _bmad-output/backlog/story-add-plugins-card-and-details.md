---
id: 70
type: story
title: "The Add Plugins card and its More Details view show the plugin as the owner approved"
parent: none
covers: [UV7]
after: [10, 51, 95]
assignee: ""
refined: false
hitl: true
risk: low
---

# The Add Plugins card and its More Details view show the plugin as the owner approved

## Description

Before installing, a site owner sees the plugin's card in WP Admin → Plugins → Add New: icon, name, short description, author, compatibility, rating, active installs and last updated. Behind it, More Details shows the banner, the readme's sections as tabs and the screenshots with their captions. The directory builds both from the readme, the plugin header and the directory's image files (icons, banners, screenshots), which go to the directory, not into the zip. Nobody has seen them assembled. This story renders the card and More Details on the showcase from those sources; the owner looks and approves, or names changes (hitl). Wording changes land in the readme or the plugin header; any other change becomes its own ticket that story 38 waits on.

## Acceptance Criteria

1. **The card shows the plugin as a new listing would**
   **Given** the showcase with the Free build installed, and a preview that answers for the directory with the plugin's own readme, header and image files
   **When** the Add Plugins screen searches for the plugin
   **Then** its card shows the icon, the name, the tagline verbatim as the short description, the author linked to the author's site, "Compatible with your version of WordPress", and the values a new listing has for rating, active installs and last updated
2. **More Details shows every readme section and the images**
   **Given** the same preview
   **When** More Details is opened from the card
   **Then** it shows the banner, the readme's sections as the directory's own parser groups them, and the three screenshots with their captions, with no raw markup and no empty tab
3. **The owner's review is settled in the sources**
   **Given** the two renderings, shown to the owner
   **When** the owner approves them or names changes
   **Then** each wording change is made in the readme or the plugin header, each other change is a filed ticket that story 38 waits on, and every check on the readme's text stays green

## Boundaries

- No new image, and no change to the name, slug or tagline: the Plugin Name header sets the slug at submission (`docs/brand.md` § Naming). A change of that kind becomes its own ticket.
- The Description section's rewrite stays story 60's (story 95, Q7 A).
- The preview ships in no build, and the walk's restore leaves the showcase as it was.

## References

- naming — docs/brand.md § Naming (name, tagline, the surface table) and § Imagery (the WordPress.org assets)
- sheet — docs/distribution.md (the readme header table; the approval line lands here)
- walk — docs/local-verification.md (Rules; Other checks, where the preview's recipe goes)
- record — memory/local-verification.md (captures on the showcase with Playwright)
- assets — _bmad-output/backlog/story-plugin-icon.md (story 51), _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41), _bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission.md (story 95)
- next — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38 waits on this one), _bmad-output/backlog/story-each-release-reaches-the-wordpress-org-listing.md (story 96 moves the directory files after it), _bmad-output/backlog/story-copywriting-skill-one-messaging-source.md (story 60, the Description rewrite)
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/ (which file feeds which part of the card)
- external — https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/ (name, short description, sections)
- external — https://developer.wordpress.org/reference/hooks/plugins_api/ (the filter that answers before any request reaches the directory)

## Notes

- Fact: filed 2026-10-16 on the owner's request ("create the WordPress library icon and content as in the Add Plugins page").
- Fact (2026-11-10): the prerequisites are done. Story 10 fixed the name and tagline (2026-10-08). Story 51 made the icons (2026-10-12). Story 41 made the banners (772 and 1544 wide) and three screenshots, and set the plugin header's Description to the tagline (2026-10-24). Story 95 completed the readme: Description, Installation, FAQ, External services, Screenshots, Changelog (2026-11-02). The former criterion 4 (the header Description) already holds and is dropped.
- Fact: the eight directory files are in `assets/` at the repository root (`icon.svg`, `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png`, `screenshot-1.png` to `screenshot-3.png`); the card prefers `icon.svg`. `bin/zip.js` and `bin/build-free.php` take only the plugin folder.
- Fact (WordPress core, from knowledge; confirm at the build): the card compares the readme's `Tested up to` with the site's full version, so `7.1` reads "Untested with your version of WordPress" on 7.1.4 unless the preview gives the site's point release, as the directory's answer appears to (the Freemius SDK's updater does the same). The card reads rating, number of ratings, active installs and last updated without checking they exist; a new listing has rating 0, 0 ratings, active installs 0 ("Less Than 10") and last updated at publication.
- Fact (2026-11-10): Plugin Check 2.1.0, installed on the showcase, carries a copy of the directory's readme parser with its Markdown (`includes/Lib/Readme/Parser.php`), so the preview can render the readme without downloading anything.
- Fact: Freemius turns its updater and its dialog off on the Add Plugins screen (SDK `class-freemius.php` L4717–4732); the Free build is used since the directory hosts it.
- Fact: the sidebar of More Details shows the installed version (3.13.1 today) while the Changelog's newest entry is 4.0.0; both read 4.0.0 at entry 4.8.
- Fact: likely findings at the sitting. `screenshot-2.png` still shows "✓Review" without a space, which the code fixed on 2026-10-29 (`deferred-work.md`, Post Reviews tabs). Caption 2 promises "the issues", which the image does not show.
- Fact: other checks read the readme's text, and a wording change keeps each green. `release-header-harness.php` Row 7 checks the name, Stable tag, Requires, License, Contributors, at most 5 tags, the short description against the tagline, the release-please block and the 10 KB cap (9,324 bytes, 916 left). postquality.com's cost-copy check reads the cost FAQ. External services comes from `docs/policies/readme-external-services.md`. The release job compares the Free build byte for byte.
- Decision (owner, 2026-10-29, story 38 split, Q6 A): this story comes before story 38's submission; 38's `after` lists 70.
- Decision (owner, 2026-11-10, `/prioritize`): refined now, built after bug 121.
- Decision (owner, 2026-11-10): wording fixes in the readme and the plugin header land in this story. A new capture, a name or tagline change, and the Description rewrite (story 60's) each become a ticket that story 38 waits on.
- Assumption: the preview is a must-use plugin on `plugins_api`. It answers a search with the plugin, and the details request for the slug with the full record, both read from the installed Free build's header and readme; it answers nothing else. Its source lives in `bin/`, its recipe in `docs/local-verification.md` § Other checks; the walk copies it and the eight images into the showcase and the restore removes them. Story 60's rewrite can reuse it.
- Assumption: the captures the owner looks at stay in the session scratchpad and are deleted after the sitting (`docs/local-verification.md` Rules: no capture from a site goes into the repository); the plan and `docs/distribution.md` record the approval date and what was approved.
- Open question: whether WP-CLI's `plugin list` shows a must-use plugin; the walk's after-restore check needs a line for the preview and its images if not.
