---
id: 122
type: story
title: "The directory banner leaves room for the plugin's name, and screenshot 2 shows today's tabs"
parent: none
covers: [UV7]
after: [70]
assignee: ""
refined: true
hitl: true
risk: low
---

# The directory banner leaves room for the plugin's name, and screenshot 2 shows today's tabs

## Description

Story 70's preview of the directory listing (2026-11-10) found two images that read wrong. In More Details, WordPress lays the plugin's name in a dark label over the banner's lower left, on top of the banner's last tagline line. Screenshot 2 shows the review tabs as "✓Review" and "✓Takeaways" with no space, a label the plugin fixed on 2026-10-29. This story renders a new banner pair that keeps the lockup and the tagline above that label, and recaptures screenshot 2 on the showcase; postquality.com's review picture is the same capture, so a site PR replaces it too. Story 38's submission waits on this story; story 96's first deploy puts both images on the listing. The person's step: the owner approves both images in the story 70 preview (criterion 4).

## Acceptance Criteria

1. **The name label covers no banner text**
   **Given** the new banner pair (772 × 250 and 1544 × 500)
   **When** More Details opens on the story 70 preview in a window at least 792 px wide, at a device pixel ratio of 1 and of 2
   **Then** the name label WordPress lays over the lower left covers no text or mark of the banner, and a strip of the band shows between the label and the banner's lowest line of text
   **And** the banner carries the brand's lockup and the tagline verbatim, and nothing else
2. **The banner's source and rules match the files**
   **Given** the banner's source page and its render recipe in `docs/brand.md` § Imagery and the WordPress.org assets
   **When** both files are rendered again by that recipe, font check included
   **Then** the renders are pixel-identical to the committed files
   **And** the section describes the new layout and the clear space it keeps for the label, and no longer calls the banners the sharing card's composition
3. **Screenshot 2 shows today's review**
   **Given** a new capture of the review panel on the showcase, Free build of the current main, a demo post with a fictional review
   **When** it replaces screenshot 2 and postquality.com's review picture (PNG and WebP)
   **Then** the tabs read as the plugin shows them today, with a space after each check mark, and the frame shows what caption 2 says: the score on four pillars and the top edits
   **And** screenshot 2 is a byte copy of the site's new PNG, each site file stays under 250 KB, and the site's size attributes and alt text match the new frame
4. **The owner approves both in the preview**
   **Given** the new banner pair and screenshot 2
   **When** the story 70 recipe renders the card and More Details
   **Then** the owner approves them, and `docs/distribution.md` records the approval with its date

## Boundaries

- Must not change: the name, the tagline's words, the icon, the sharing card, screenshots 1 and 3, the plugin's code, and the readme's wording (caption 2 changes only if the new frame no longer matches it, on the owner's word).
- Captures come only from the showcase: no real post, blog, user or domain in the frame, and no WordPress admin bar.
- The capture and the preview run inside the walk (backup, Free build, restore): the showcase ends as it was, its plugin version included.

## References

- found by — _bmad-output/backlog/story-add-plugins-card-and-details-plan.md (story 70, the sitting of 2026-11-10)
- recipe — docs/local-verification.md § Other checks, "Add Plugins preview (story 70)"
- imagery — docs/brand.md § Imagery and the WordPress.org assets (the banner's composition and render recipe, the screenshots' sources)
- assets record — docs/distribution.md, the directory assets table and the card preview line
- site captures — https://github.com/vincentheimann/postquality.com/blob/main/README.md, "Captures" (how the site's pictures are taken, cropped and exported)
- label layout — WordPress 7.1.4 `wp-admin/css/common.css`, `#plugin-information-title.with-banner` and its `h2` (read on the showcase)
- next — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38 waits on this one)
- next — _bmad-output/backlog/story-each-release-reaches-the-wordpress-org-listing.md (story 96 moves and deploys the directory images)

## Notes

- Fact (2026-11-10): `assets/screenshot-2.png` is a copy of the website's `assets/img/review.png` (`docs/brand.md` § Imagery); the site also serves `review.webp` from the same capture.
- Fact (2026-11-10, WordPress 7.1.4 core CSS): in a window of at least 792 px the More Details frame is 772 px wide and the banner fills 772 × 250. The label starts 26 px from the left and 174 px from the top, set in 30 px Helvetica Neue (a fallback sans where it is missing) at line height 1.68, so it covers about x 26–360 and y 174–225, plus a faint 30 px glow. Below 792 px the title area is 100 px high, the banner is scaled to the frame's width and shows only its upper part, and the label sits 30 px from the top in 20 px type.
- Fact (2026-11-10): the space after the check mark was fixed in the plugin on 2026-10-29; postquality.com's Features captures of 2026-11-03 already show it.
- Decision (owner, 2026-11-10, story 70's sitting): both changes are wanted (delegated to best practice); one ticket covers them, since both are directory images made by story 41's pipeline.
- Decision (owner, 2026-11-10, refinement): the banner keeps the lockup and the tagline. The tagline moves up to two lines across the full measure so that everything ends above the label (option A of three mocks: lockup and tagline, icon and tagline, lockup alone). The mock the owner picked: content top-aligned in the 40 px margin, the lockup unchanged, the tagline 16 px under it in 20 px type over the 692 px measure (at 22 px two lines do not fit). This settles the open question of story 70's sitting.
- Decision (owner, 2026-11-10, refinement): the narrow view, a window under 792 px where core moves the label over the lockup, is out of scope. It is a limit of core's layout and holds for any banner with the brand at the left.
- Decision (owner, 2026-11-10, approving the refinement): screenshot 2 keeps its frame: the Free build (the directory hosts the Free build, so only the Free tabs show), the same demo post and fictional review as the site's capture of 2026-10-23, the same crop and size (960 × 824). Caption 2 and the site's alt text then stay true.
- Fact (2026-11-10, read with WP-CLI): the showcase still holds that demo post with its fictional Review and Takeaways results; its plugin is 3.9.0, so the capture installs a Free build of main inside the walk.
- Assumption: wordpress.org's own plugin page shows the banner with no label over it; the builder checks one live listing before the sitting.
- Fact: `docs/local-verification.md` keeps a walk's captures out of the repository; the directory images are the exception by design: showcase captures with fictional posts (`docs/brand.md` § Imagery, `memory/lessons.md` 2026-10-24).
- Fact: the site's `review.png` is the source of screenshot 2, so the site PR merges before or with the plugin PR, and both mains carry the same capture.
- Decision (owner, 2026-11-10): refined and approved after the `validate.md` check (3 fixes, 5 suggestions applied); story 122 joins story 96's `after`.
