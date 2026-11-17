---
id: 132
type: bug
title: "The Details tabs' keyboard focus ring is drawn through the tab label instead of around the tab"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P2
estimate: ""
---

# The Details tabs' keyboard focus ring is drawn through the tab label instead of around the tab

## Description

On Post Reviews, a keyboard user who moves between the Details tabs (Review, Takeaways, History…) sees the focus indicator as a thin blue pill, about 5 px tall, drawn across the middle of the focused tab's label, so the label reads as struck through. The keyboard behaviour itself is right. Expected: a focus ring around the focused tab that leaves its label readable, like the ring on the page's other controls.

## Reproduction

1. Story 38's clean Local site (WordPress 6.6, PHP 8.1.29), the Free build of release v4.0.2, a webhook URL saved, one post with a review row; headless Edge 1280 px wide (story 38's frame F2-4, 2026-11-16).
2. Post Reviews → focus Details on a row, press Enter.
3. Press Tab to focus the selected tab, then ArrowRight.
4. Actual: the focused tab ("Takeaways") shows a pill about 5 px tall through its label. Expected: a ring around the tab, clear of the label. Other controls on the page (Details, Open in Editor, Review selected) show a normal ring.

Evidence: the checklist row P296 in story 38's pass list (fail), and the captures `tabs-focus.png` and `tabs-focus-zoom.png` in the session scratchpad of frame F2-4 (not committed).

## Cause Hypothesis

WordPress core draws the tab's focus ring as a box shadow on a pseudo-element inset 12 px from the top and bottom of the tab, sized for core's 48 px tab. The plugin's Post Reviews stylesheet sets the Details tabs' height to `auto` with smaller padding (the mockup's underlined tabs), which makes the tab about 29 px tall, so the inset box is left about 5 px tall across the label. The keyboard handling comes from core's tab component and is not involved.

## Acceptance Criteria

1. **The focus ring surrounds the tab**
   **Given** the Details panel on Post Reviews, on WordPress 6.6 and on the latest release the readme's "Tested up to" names
   **When** a tab receives keyboard focus
   **Then** the focus indicator encloses the tab's label without crossing it, in the accent colour and width of the focus rings on the page's other controls
   **And** the tabs keep their current height, spacing, colours and the active tab's underline
2. **The fix is measured, before and after**
   **Given** a focused Details tab
   **When** a headless browser measures the focus indicator's box against the tab's label box on 4.0.2 and on the fix, on both WordPress versions of criterion 1
   **Then** the measurements show the indicator crossing the label on 4.0.2 (on 6.6 at least) and enclosing it on the fix, and are recorded in the plan; a harness row or JS test covers any rule a test can read
3. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it runs on the current code
   **Then** the ring already surrounds the tab, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 and 2

## Boundaries

- Must not change: the tabs' keyboard behaviour (arrow keys, Home and End, Tab into the panel), their ARIA attributes, the tab set and its order, and the focus rings of every other control.
- Must not change: the ring shows for keyboard focus only, as core's does; a mouse click draws none.
- No inline styles: classes in the page's stylesheet only (AGENTS.md, conventions).

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md, pass list row P296 (fail, frame F2-4) and P311
- spec — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-manual-pass-epic-done-when-checklist.md, § 5 "Tabs" ("with a visible focus ring")
- spec — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/epic-ui-rebuild.md, Done when 4 (tabs pass the ARIA checks)
- origin — _bmad-output/initiative-post-quality-manager/epic-ui-rebuild/story-post-reviews-detail-panel-and-trigger-flow-plan.md (story 5.4, which built the panel; its check L126 asks only "tabs by keyboard")
- pattern — _bmad-output/backlog/bug-crowded-radios-wide-status-box-and-split-model-id.md (bug 130: a core rule beaten by a plugin rule, measured before and after in headless Edge)
- blocks — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38; the fix ships in 4.0.3 before submission)

## Notes

- Decision (owner, 2026-11-17): a bug fixed in patch release 4.0.3, before story 38's submission.
- Fact (source read 2026-11-17, main 8f4af64): `src/post-reviews/style.css` L307–311 (`.pqm-detail-tabs .components-tab-panel__tabs-item { height: auto; padding: 6px 12px; color: #50575e; }`); the panel's `TabPanel` at `src/post-reviews/detail-panel.js` L413. No other `TabPanel` in the plugin.
- Fact (WordPress 6.6 core, `wp-includes/css/dist/components/style.css` L2755–2816, read 2026-11-17 on story 38's site): the tab is `height: 48px; padding: 3px 16px`; its `:before` sits at `top: 12px; bottom: 12px; left: 12px; right: 12px`, and `:focus-visible:before` gives it the focus box shadow.
- Fact (`@wordpress/components` 44.0.0 on npm, read 2026-11-17): the tab carries `height: 48px !important`, so the plugin's `height: auto` loses there. Which WordPress release first ships that rule is not checked; the defect may show only on the older releases in the supported range (6.6 to 7.1), and the tabs may already look different from the mockup on the newest.
- Open question: whether the tabs keep the mockup's compact height on every supported release, or take core's 48 px. Criterion 1 keeps today's look on 6.6; the builder shows the owner both versions' captures at the pull request.
- Assumption: severity P2: keyboard users' focus indicator is impaired (an accessibility check a directory reviewer may run), and focus still shows, so a workaround exists. Risk low: a stylesheet change that shows at once and reverts cleanly.
- Assumption: hitl false. The agent measures in headless Edge on story 38's clean site between its frames, never during one; captures stay in the session scratchpad and are never committed (`docs/local-verification.md`, Rules).
- Done 2026-11-17 (owner's word): fixed by #19 (main 89659ee), released in 4.0.3 (#20; notes #21), measured before and after in headless Edge on WordPress 6.6 and on 7.1.4's stylesheets (plan, Implementation Notes). The open question on the tab height was settled by criterion 1: the tabs keep their compact size; the owner kept the centred 24 px ring over a near full-height one on 7.1, accepting that on 6.6 it touches the active tab's underline. Story 38 re-ran P296 and P311 on the published 4.0.3: pass.
