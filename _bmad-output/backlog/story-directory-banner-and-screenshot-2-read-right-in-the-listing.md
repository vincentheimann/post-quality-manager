---
id: 122
type: story
title: "The directory banner leaves room for the plugin's name, and screenshot 2 shows today's tabs"
parent: none
covers: [UV7]
after: [70]
assignee: ""
refined: false
hitl: true
risk: low
---

# The directory banner leaves room for the plugin's name, and screenshot 2 shows today's tabs

## Description

Story 70's preview of the directory listing (2026-11-10) found two images that read wrong. In More Details, WordPress lays the plugin's name in a dark label over the banner's lower left, right on the banner's own tagline line. Screenshot 2 shows the review tabs as "✓Review" and "✓Takeaways" with no space, a label the code fixed on 2026-10-29. This story makes a new banner and a new screenshot 2; story 38 submits them.

## Acceptance Criteria

1. **The name label covers no banner text**
   **Given** the banner pair in `assets/` (772 × 250 and 1544 × 500)
   **When** More Details opens on the story 70 preview, at both sizes
   **Then** the name label WordPress lays over the lower left covers no text or mark of the banner, and the banner still carries the brand's lockup and tagline (`docs/brand.md` § Imagery)
2. **Screenshot 2 shows the current review**
   **Given** a new capture of the review on the showcase (demo post, fictional scores)
   **When** it replaces `assets/screenshot-2.png` and the website's copy it comes from
   **Then** its tabs read as the plugin shows them today, and caption 2 in `readme.txt` still describes it
3. **The owner approves both in the preview**
   **Given** the two new images
   **When** the story 70 recipe renders the card and More Details
   **Then** the owner approves them, and `docs/distribution.md` records the approval

## Boundaries

- Must not change: the name, the tagline, the icon, screenshots 1 and 3, the readme's wording beyond caption 2.

## References

- found by — _bmad-output/backlog/story-add-plugins-card-and-details-plan.md (story 70, the sitting of 2026-11-10)
- recipe — docs/local-verification.md § Other checks, "Add Plugins preview (story 70)"
- imagery — docs/brand.md § Imagery (the banner's composition, its source `assets/src/banner.html`, the screenshots' sources on postquality.com)
- next — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38 waits on this one)

## Notes

- Fact (2026-11-10): `assets/screenshot-2.png` is a copy of the website's `assets/img/review.png` (`docs/brand.md` § Imagery), so the recapture also changes postquality.com through a site PR.
- Decision (owner, 2026-11-10, story 70's sitting): both changes are wanted (delegated to best practice); one ticket covers them, since both are directory images made by story 41's pipeline.
- Open question: whether the banner keeps the tagline at all, or the lockup alone, once the lower left is clear. Settle it at refinement against `docs/brand.md` § Imagery.
