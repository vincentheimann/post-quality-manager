---
id: 130
type: bug
title: "Three layout defects in the admin pages: crowded radio labels, a status box wider than its dividers, model ids split at their hyphens"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
severity: P3
estimate: ""
---

# Three layout defects in the admin pages: crowded radio labels, a status box wider than its dividers, model ids split at their hyphens

## Description

Three layout defects show in the admin pages: (c) was seen on WordPress 6.6 and 7.1.4, (d) and (e) on 6.6, and core's rules behind them are the same in trunk. (c) On Settings → AI, in the Blog Profile and Editorial Standards fieldsets, each radio sits right after the previous option's label, so each label reads as part of its neighbour. (d) On the n8n Workflow page, the status box is wider than the section dividers under it. (e) In Settings → AI's per-agent table, a model id such as `claude-sonnet-5-5` breaks across lines at its hyphens. Expected: options spaced so each label belongs to its own radio, the status box as wide as the dividers, and each model id on one line, with no page wider than the window at any width.

## Reproduction

1. Story 38's clean Local site (WordPress 6.6), the Free build of release v4.0.0, a webhook URL saved, viewed in a desktop browser 1280 px wide; the postquality.com capture taken on WordPress 7.1.4 shows (c) the same way.
2. (c) Settings → AI. Actual: in both fieldsets, the space between a label and the next radio (about one space character) is narrower than the space between a radio and its own label. Expected: the gap after each label is the wider one.
3. (d) n8n Workflow. Actual: the status box measures 797 px wide, the dividers under it 760 px. Expected: the same width.
4. (e) Settings → AI, the table "What Each Agent Sends to Claude". Actual: the id `claude-sonnet-5-5` wraps at its hyphens over two or three lines. Expected: the id on one line.

Evidence: the owner's look in Chrome and the agent's DOM read, story 38's Free walk, 2026-11-11.

## Cause Hypothesis

- (c) The plugin's stylesheet spaces the labels with a right margin, but core's rule for labels inside a fieldset in a form table sets the margin with `!important` and no right margin, so the plugin's margin never applies. Core sets no padding on those labels. At 782 px or less core stacks fieldset labels one per line, but the plugin's own inline-block rule for the Blog Profile labels outranks that rule, so only Editorial Standards stacks.
- (d) The status box caps its width at 760 px like the dividers, but its padding and borders add to that width (content-box sizing: 760 + 2 × 16 + 1 + 4 = 797 px); the dividers have no side padding or border.
- (e) The model id is a `code` element in a table cell that the browser may wrap at any hyphen; nothing asks it to keep the id whole.

## Acceptance Criteria

1. **(c) Each label belongs to its own radio**
   **Given** Settings → AI on WordPress 6.6 and on the current WordPress release, wider than 782 px
   **When** the Blog Profile and Editorial Standards fieldsets render
   **Then** the space between each label and the next radio is wider than the space between a radio and its own label, the same in both fieldsets; at 782 px or less each option in both fieldsets sits on its own line
2. **(d) The status box lines up with the dividers**
   **Given** the n8n Workflow page in each of its states
   **When** it renders at any width
   **Then** the status box's outer width is 760 px where the page is wider and the content width below it, the same as each divider the page shows
3. **(e) A model id reads whole, and no page is wider than the window**
   **Given** Settings → AI's per-agent table, with registry models and with a custom model id of any length the setting accepts
   **When** it renders at widths from 360 px to 1280 px
   **Then** above 782 px each registry model id reads on one line; at every width the document is no wider than the window and no cell is cut off (a frame around the table may scroll)
4. **The fix is measured, before and after**
   **Given** the three elements above
   **When** a headless browser measures them at 360, 782, 1024 and 1280 px on 4.0.0 and on the fix
   **Then** the measurements show each defect on 4.0.0 and its absence on the fix, and are recorded in the plan; a harness row or JS test covers any rule a test can read
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it runs on the current code
   **Then** the layout already holds, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 4

## Boundaries

- Must not change: the radios' behaviour (the preset choice, the Custom path and its text, story 26); the status box's text, states, colours and focus outline; the table's columns and values; anything else on these pages.
- No inline styles: classes in the page's stylesheet only (AGENTS.md, conventions).
- Out of scope (the triage's "cosmetic, from core's styles"): row alignment, the faint disabled checkbox on WordPress 6.6, a disabled Details button drawn grey, the 720 px intro cap.

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md L619–622 (the look's triage, defects (c), (d), (e) and the cosmetic items) and L625 (owner decision 7a)
- conventions — AGENTS.md, § Conventions that differ from defaults (no inline styles)
- core's rules — https://github.com/WordPress/wordpress-develop/blob/6.6/src/wp-admin/css/forms.css and https://github.com/WordPress/wordpress-develop/blob/trunk/src/wp-admin/css/forms.css (`.form-table td fieldset label`); https://github.com/WordPress/wordpress-develop/blob/6.6/src/wp-admin/css/common.css (`.widefat td` padding; `body` at 782 px or less)
- pattern — _bmad-output/backlog/bug-roles-table-cut-on-phones.md (bug 108: a table that scrolls in its own frame on phones)
- blocks — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38; the fix ships in 4.0.1 before submission)

## Notes

- Decision (owner, 2026-11-11, 7a): these defects ship in patch release 4.0.1, before story 38's submission.
- Fact (source read 2026-11-11, the plugin tree of v4.0.0, equal to 78c8bc8; plugin paths under `post-quality-manager/`, `src/` and `tests/` at the repository root):
  - (c) `post-quality-manager/admin/css/pqm-admin.css` L23 (`.pqm-radio-label { margin-right: 15px; }`), L25 (`.pqm-profile-fieldset .pqm-radio-label { display: inline-block; margin: 0 16px 6px 0; }`) and L26 (`.pqm-voice-fieldset { margin-bottom: 10px; }`); the fieldsets at `post-quality-manager/admin/class-pqm-admin.php` L936 (`pqm-profile-fieldset`) and L958 (`pqm-voice-fieldset`, whose labels get only L23).
  - (d) `post-quality-manager/admin/css/pqm-admin.css` L75–78 (`.pqm-workflow-status`: 1 px border, 4 px left border, padding 12 px 16 px, `max-width: 760px`) and L100 (`.pqm-disclosure`: `max-width: 760px`, top border only); the box at `post-quality-manager/admin/class-pqm-admin.php` L1700, the dividers at L1732, L1738 and L1745, shown per state (L1685–1689: in the not-set-up state with no saved key, none shows).
  - (e) `post-quality-manager/admin/css/pqm-admin.css` L67–68 (`.pqm-llm-preview { max-width: 900px; … }`, `.pqm-llm-preview code { font-size: 11px; … }`); the cell at `post-quality-manager/admin/class-pqm-admin.php` L1856; the table is `widefat striped`, five columns, with no scrolling frame.
  - Other code finds the status box by its class: `post-quality-manager/admin/js/pqm-admin.js` L321, L339, L376 and `src/n8n-connection/index.js` L452. Harness rows match its exact opening tag (`tests/harness/render-matrix-harness.php` L497–498, L1014) and the table's exact opening tag (L1447): a wrapper around the table keeps L1447 passing; a new class on the table breaks it.
- Fact (WordPress core CSS, 6.6 and trunk, read 2026-11-11): `.form-table td fieldset label { margin: 0.35em 0 0.5em !important; display: inline-block; }`; at 782 px or less, `.form-table fieldset label { display: block; }` (specificity 0,1,2, below the plugin's 0,2,0 Blog Profile rule); no core rule sets padding on these labels; `.widefat td, .widefat th { padding: 8px 10px; }`; at 782 px or less, `body { overflow-x: hidden; }`, so content wider than the window is cut off rather than scrolled; `box-sizing` is set on form fields only.
- Fact (the triage's suggestions, not decisions): (c) spacing through `padding-right` on the label, which core does not override (checked above); (e) `white-space: nowrap` on the table's `code`. The settings sanitizer keeps any length of `[A-Za-z0-9._:-]` in a custom model id (`post-quality-manager/admin/class-pqm-admin.php` L407), so a whole-id rule alone can push the table past a phone's width; criterion 3 guards it.
- Open question: whether the per-agent table already overflows at 360 px on 4.0.0 (five columns, 20 px of cell padding each, in a panel of about 340 px). Criterion 4's first measurement decides. If it does, a frame that scrolls is the only way to meet criterion 3, the bug grows into a phone fix, and the owner confirms that scope.
- Open question: the Blog Profile labels then stack at 782 px or less, which they do not do today. The change is shown to the owner at the pull request.
- Assumption: severity P3, cosmetic. Risk low: stylesheet changes that show at once and revert cleanly.
- Assumption: hitl false. The agent measures in headless Edge (criterion 4) on the showcase or story 38's clean site between its frames; captures stay in the session scratchpad, are deleted when the walk ends and are never committed (`docs/local-verification.md`, Rules). Story 38's 4.0.1 pass carries the owner's look.
- Handoff: bugs 125 and 129 edit `post-quality-manager/admin/class-pqm-admin.php` in other places; this bug needs no markup change unless the first open question picks a frame. All ship in 4.0.1.
