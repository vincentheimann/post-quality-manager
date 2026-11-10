---
id: 70
type: story
title: "The Add Plugins card and its More Details view show the plugin as the owner approved"
parent: none
covers: [UV7]
after: [10, 51, 95]
assignee: ""
refined: true
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
   **Then** its card shows the icon, the name, the tagline verbatim as the short description, the submitting WordPress.org account by its profile's display name, linked to that profile, "Compatible with your version of WordPress", and the values a new listing has for rating, active installs and last updated
2. **More Details shows every readme section and the images**
   **Given** the same preview
   **When** More Details is opened from the card
   **Then** it shows the banner, the readme's sections as the directory's own parser groups them, and the three screenshots with their captions, with no raw markup and no empty tab, and its sidebar shows the version, requirements, `Tested up to` and contributors that the plugin header and the readme give
3. **The owner's review is settled in the sources**
   **Given** the two renderings, shown to the owner
   **When** the owner approves them or names changes
   **Then** each wording change is made in the readme or the plugin header, each other change is a filed ticket that story 38 waits on, every check on the readme's text stays green, and `docs/distribution.md` records the approval date and what was approved

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
- Fact (2026-11-10, checked on the showcase): Plugin Check 2.1.0 carries a copy of the directory's readme parser (`includes/Lib/Readme/Parser.php`) without its Markdown: `parse_markdown()` returns the text unchanged when `\WordPressdotorg\Plugin_Directory\Markdown` does not exist, and neither Plugin Check nor WordPress 7.1.4 defines it. Alone, the parser leaves `*` lists and `**` in the sections, and the screenshot captions, read from the rendered list, come out empty.
- Fact: Freemius turns its updater and its dialog off on the Add Plugins screen (SDK `class-freemius.php` L4717–4732); the Free build is used since the directory hosts it.
- Fact: the sidebar of More Details shows the installed version (3.13.1 today) while the Changelog's newest entry is 4.0.0; both read 4.0.0 at entry 4.8.
- Fact: likely findings at the sitting. `screenshot-2.png` still shows "✓Review" without a space, which the code fixed on 2026-10-29 (`deferred-work.md`, Post Reviews tabs). Caption 2 promises "the issues", which the image does not show.
- Fact: other checks read the readme's text, and a wording change keeps each green. `release-header-harness.php` Row 7 checks the name, Stable tag, Requires at least and Requires PHP, `Tested up to` as major.minor, License, Contributors, at most 5 tags, the short description against the tagline, the release-please block and the 10 KB cap (9,324 bytes, 916 left). postquality.com's cost-copy check reads the cost FAQ. External services is written in the readme itself (story 95; `docs/policies/readme-external-services.md` only points to it): a wording change that alters what it says is sent also changes `PostQuality_Admin::privacy_policy_content()` and `docs/policies/privacy.md`. `free-build-harness.php` checks on every PR that the Free build's readme equals the source; the release job compares the Free build's PHP as token streams and every other file byte for byte, except the readme's Contributors line.
- Decision (owner, 2026-10-29, story 38 split, Q6 A): this story comes before story 38's submission; 38's `after` lists 70.
- Decision (owner, 2026-11-10, `/prioritize`): refined now, built after bug 121.
- Decision (owner, 2026-11-10): wording fixes in the readme and the plugin header land in this story. A new capture, a name or tagline change, and the Description rewrite (story 60's) each become a ticket that story 38 waits on.
- Assumption: the preview is a must-use plugin on `plugins_api`. It answers a search with the plugin, and the details request for the slug with the full record, both read from the installed Free build's header and readme through Plugin Check's copy of the directory's parser, with the directory's own Markdown class and the Michelf MarkdownExtra library it extends fetched into the session scratchpad for the sitting and deleted after (owner decision below); it answers nothing else. Its source lives in `bin/`, its recipe in `docs/local-verification.md` § Other checks; the walk copies it and the eight images into the showcase and the restore removes them. Story 60's rewrite can reuse it.
- Assumption: the captures the owner looks at stay in the session scratchpad and are deleted after the sitting (`docs/local-verification.md` Rules: no capture from a site goes into the repository); the plan and `docs/distribution.md` record the approval date and what was approved.
- Fact: refined 2026-11-10 against the repository (a read-only review by a fresh agent: prerequisites, every reference, each claim of the old text); the owner approved it the same day ("Approve both"). The `validate.md` check on the refined ticket runs before its build.
- Fact: the walk's after-restore check needs a line confirming the preview's file and the copied images are gone: WP-CLI's `plugin list` lists a must-use plugin (status `must-use`), but the images show in none of step 11's three checks.
- Decision (owner, 2026-11-10, the `validate.md` check before the build): the preview renders the readme with the directory's own Markdown, fetched into the scratchpad at the sitting and deleted after, rather than a converter of our own or a new dev dependency, so the rendering matches the listing. Nothing of it ships or enters the repository but the recipe.
- Decision (owner, 2026-11-10): story 96 waits on this one; 70 joins its `after`.
- Decision (owner delegated, 2026-11-10): from the check's suggestions, the sidebar joins criterion 2, the approval record joins criterion 3, and the after-restore line above replaces the open question on must-use plugins. The restore stays a Boundary, not a criterion: it is the walk's standing rule (`docs/local-verification.md` Rules), not plugin behavior.
- Fact: the `validate.md` check ran on 2026-11-10 (a fresh agent, read-only, on this branch's base `chore/records-111-done`). It found the parser blocker above, corrected the External services source and the facts on Row 7 and the Free build comparison, and confirmed every path, the three external links, the prerequisites (10, 51, 41, 95 done) and the other facts.
- Decision (owner, 2026-11-10, the sitting): criterion 1 named "the author linked to the author's site", but the directory's "By" line is the submitting WordPress.org account by its profile's display name, linked to that profile, never the plugin header's Author (the build's review pass 1, checked on the live API). Criterion 1 now says so. The owner sets the `postquality` profile's display name on WordPress.org before story 38 submits (`docs/distribution.md`).
- Decision (owner, 2026-11-10, the sitting; the changes delegated to best practice): approved, with two readme fixes in this story (caption 2 names what the image shows; the readme's URLs are Markdown links, which the directory renders as links) and story 122 (a banner that leaves its lower left to the name label, and a new screenshot 2 with today's tabs), which story 38 waits on.
