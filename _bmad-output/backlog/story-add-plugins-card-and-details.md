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

Owner request (2026-10-16): the plugin's card in WP Admin → Plugins → Add New (icon, name, short description, "By author", rating, active installs, last updated, compatibility) and the "More Details" modal behind it (banner, Description / Installation / FAQ / Screenshots / Changelog / Reviews tabs) are what a site owner sees before installing. Every field on that card comes from a file this repository ships: the icon from `assets/icon-128x128.png` / `icon-256x256.png` in SVN (story 51, done), the name from the first line of `readme.txt`, the short description from the readme's tagline line (story 10), the "By" line from the plugin header's `Author` / `Author URI`, compatibility from `Tested up to`; the modal reads the banners and screenshots (story 41) and the readme sections (story 38). Rating, installs and last-updated are computed by WordPress.org and need nothing from us. Nobody has yet seen those inputs assembled into the card WordPress renders. This story renders the card and the modal on Local from the real files, the owner approves what they see (hitl), and the fixes they ask for land in the files that feed the card, never in a copy. It also closes the one gap already visible: the plugin header `Description` (shown in the installed Plugins list) still reads "Content quality dashboard — store review results from n8n workflows…", which names n8n and predates the tagline.

## Acceptance Criteria

1. **The card renders from the real files**
   **Given** the Local site with the plugin's `readme.txt`, plugin header and `assets/` icon files
   **When** the Add Plugins screen is opened (through a development-only hook on Local that feeds the plugin's own data where the directory API would answer; it ships in no build)
   **Then** the card shows the icon, "Post Quality Manager", the tagline verbatim, "By Vincent Heimann" linking to postquality.com and "Compatible with your version of WordPress", and the screenshot is attached to the story's plan
2. **The More Details view renders from the same files**
   **Given** the same hook
   **When** "More Details" is opened
   **Then** the modal shows the banner (a placeholder until story 41 produces it, called out as such), the Description tab from the readme and the Installation / FAQ / Screenshots / Changelog tabs as far as story 95 has filled them, with no raw markup or empty tab
3. **The owner approves and the sources carry the result**
   **Given** the two renderings
   **When** the owner reviews them
   **Then** every change they ask for is made in the source that feeds the card (`readme.txt`, the plugin header, `docs/brand.md` § Naming, `assets/`), `docs/distribution.md` records "card preview approved <date>" next to the readme header table, and `release-header-harness.php` Row 7 stays green
4. **The header Description matches the tagline rules**
   **Given** `post-quality-manager.php`
   **When** the installed Plugins list shows the plugin
   **Then** its `Description` is the tagline from `docs/brand.md` (or a sentence derived from it that the owner approves), provider-neutral, with no "Content quality dashboard" and no engine or vendor name

## Boundaries

- Produces no new asset: the icon is story 51's, the banners and screenshots stay story 41's, the readme sections stay story 95's. If a rendering shows an asset or section is wrong, the finding goes to that story's Notes.
- The preview hook is development-only: a file under `bin/` or a Local mu-plugin, listed in `docs/distribution.md`, never in the zip or the Free build.
- Does not touch the name, slug or tagline decisions (story 10): a "Name – keywords" title as in other directory listings is ruled out by `docs/brand.md` § Naming ("Y – X" is not acceptable); the owner can reopen that only through story 10.

## References

- icon — _bmad-output/backlog/story-plugin-icon.md (story 51, done 2026-10-12: the files the card shows)
- naming — docs/brand.md § Naming (name, tagline, the surface table this story renders)
- sheet — docs/distribution.md (readme header table and the plugin-header rows; this story adds the approval line)
- assets and readme — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41) and _bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission.md (story 95: the readme sections and the disclosures)
- code — post-quality-manager/post-quality-manager.php (the header `Description` AC4 rewrites); post-quality-manager/readme.txt
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/ (which file feeds which part of the card)
- external — https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/ (name, short description, sections)
- external — https://developer.wordpress.org/reference/hooks/plugins_api_result/ (the filter a Local preview can use)

## Notes

- Filed 2026-10-16 (owner): "create the WordPress library icon and content as in the Add Plugins page". The icon already exists (story 51) and the content sources are ticketed (10, 38, 41, 47); what nobody had was the assembled card, so this story is the preview-and-approve sitting plus the header `Description` fix. Low risk: one header line and a development-only hook.
- HITL: the owner approves the two renderings; the agent builds the preview and applies the fixes.
- Decision (owner, 2026-10-29, story 38 split, Q6 A): this story comes before story 38's submission; 38's `after` lists 70. It runs after story 95, which completes the readme sections it previews; both edit the readme, so 95 joins `after`.
- Not ranked by `/prioritize` yet. Natural slot: after story 41 produces the banner and screenshots (so the modal is complete), before story 38 submits; the card itself (AC1, AC4) needs only 10 and 51, which is why `after` lists those two.
