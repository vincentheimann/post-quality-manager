---
title: 'The Details tabs keyboard focus ring surrounds the tab label'
type: 'bugfix'
ticket: '132'
created: '2026-11-17'
status: done
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
baseline_revision: '5b84c73bdeaaab204e8803db2c0909ea93318841'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On WordPress 6.6 the Details tabs' keyboard focus ring is a 5 px pill drawn through the focused tab's label. Core insets the ring's `::before` 12 px from each edge for its 48 px tab, and the plugin's `height: auto` override makes the tab about 29 px tall. From WordPress 7.1 core forces `height: 48px !important`, so the override loses there and the ring is core's.

**Approach:** Keep the tabs' current height and spacing on every release (criterion 1). In the plugin's tab override, size the ring's `::before` for the tab it actually gets: at most 12 px from the top and bottom (core's inset on a 48 px tab), closer on the compact tab, so it encloses the label; 8 px from the sides, giving the ring core's 4 px gap around the label within the plugin's 12 px padding.

</frozen-after-approval>

## Implementation Notes

- Oneshot: one CSS rule in `src/post-reviews/style.css` and one row in `tests/harness/render-matrix-harness.php` (bug 130's precedent: a row reads the exact rule text), about 15 lines in 2 files, no schema, REST or template change.
- Facts read 2026-11-17: WordPress 6.6 core (`wp-includes/css/dist/components/style.css` L2755–2816) gives the tab `height: 48px; padding: 3px 16px` and the ring `::before` `top/bottom/left/right: 12px`. WordPress 7.1.4 (the showcase site) gives the tab `height: 48px !important` and the ring `inset: var(--wpds-dimension-padding-md, 12px)`. The plugin's override (`.pqm-detail-tabs .components-tab-panel__tabs-item`, specificity 0,2,0) sets `height: auto; padding: 6px 12px`; its padding wins on both releases, its height only on 6.6.
- Skipped: one tab height on every release (core's 48 px, or the compact tab forced with `!important`). Criterion 1 keeps today's look; the ticket's open question on it stays with the owner.
- Not checked: which WordPress release first ships the tab's `height: 48px !important`; the Intent's "from WordPress 7.1" rests on 7.1.4 and `@wordpress/components` 44.0.0.
- Built: `src/post-reviews/style.css` gains `.pqm-detail-tabs .components-tab-panel__tabs-item::before { top: calc(50% - 12px); bottom: calc(50% - 12px); left: 8px; right: 8px; }` with a comment naming the cause. A first draft capped the insets with `min(12px, …)`; `calc(50% - 12px)` alone already gives core's 24 px box at any tab height, so the cap went. The longhands (0,2,1) beat core's `top…left` (6.6) and `inset` (7.1.4), both at 0,1,1. The RTL build keeps the rule (left and right are equal).
- `tests/harness/render-matrix-harness.php`: one row reads the tab rule and the ring rule from `src/post-reviews/style.css`. On the 4.0.2 stylesheet it fails (481 passed, 1 failed); on the fix 482 pass.
- Checks: all 25 harness files pass; `npm run build` succeeds; `npm run test:unit` 214 passed; `php -l` clean on the harness.
- Deviation, the 7.1.4 measurement: no walk on the showcase. A static page links the showcase's 7.1.4 stylesheets read-only (`components/style.css`, `common.css`, `forms.css`) with the tablist markup captured on WordPress66 and each plugin stylesheet. It checks the cascade, not a live 7.1.4 site; the CSS change is all that differs.

- **Measured (criterion 2), 2026-11-17, an Opus agent, headless Edge at 1280 px and deviceScaleFactor 3, the focused "Takeaways" tab; gaps ring box to label top/bottom/left/right, negative = crossing:**
  - WordPress66 (WordPress 6.6, live; the 4.0.2 Free asset, then the branch's built `style-index.css` and `style-index-rtl.css` copied over the installed ones):
    - 4.0.2: tab 29 px, padding 6px 12px; `::before` 12 px in on every side; ring box 5 × 61.83 px against a 17 × 61.83 px label; gaps −6/−6/0/0.
    - Fix: tab 29 px, padding 6px 12px (unchanged); `::before` 2.5 px top and bottom, 8 px sides; ring box 24 × 69.83 px; gaps 3.5/3.5/4/4. The active underline (1.5 px, `rgb(56,88,233)`) and the text colours are unchanged; no console error; no `debug.log`.
  - WordPress 7.1.4's stylesheets (static page, cascade check): tab 48 px with both; ring box 24 px tall with both; gaps 3.5/3.5/0/0 on 4.0.2, 3.5/3.5/4/4 on the fix; ring to underline +9 px with both.
  - At 360 and 782 px no tab label wraps, on 6.6 or on the 7.1.4 page (on 6.6 the tablist keeps 680 px, as the posts table is wider than the screen).
  - Ring to the active tab's underline on 6.6: 4.0.2 +9 px; the fix overlaps it 0.5 px (deviceScaleFactor 3) to 1.5 px (1). A candidate C (`top: 2px; bottom: 4px`) cleared it (+1 / 0 px) but drew a 42 px ring on 7.1.4's 48 px tab. **Owner, 2026-11-17: the fix as built ships** (7.1 keeps core's ring; the touch on 6.6 is accepted).
  - The frame and a second identical frame for C each restored to three "same", WordPress 6.6. WordPress's empty `wp-content/upgrade/` folder was left on the site (not in the backup's file list).
- **Owner, 2026-11-17:** the WordPress floor stays 6.6 through story 38's submission (`memory/lifecycle.md`); this fix is measured on 6.6.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens, Opus), 5 findings: 1 patched, 1 resolved by the measurement, 1 accepted on the owner's word, 1 rejected low, 1 false.
- **Measurements not in the plan (AC 2)** — real at review time, resolved: the figures above.
- **7.1 checked on a static page, not a live 7.1 site** — low, rejected. The rule's effect depends only on the cascade, which the page uses with 7.1.4's own stylesheets; if 7.1's markup differed so the selector missed, core's ring would apply, which already enclosed the label on 4.0.2. The deviation is recorded above.
- **A wrapped two-line label would be crossed by a fixed 24 px ring** — false in practice: no label wraps at 360, 782 or 1280 px on 6.6 or the 7.1.4 page (the tablist keeps its width). A future longer translated label could; not guarded.
- **On 6.6 the ring touches the active tab's underline** — low, real (0.5–1.5 px overlap). Candidate C cleared it but changed 7.1's ring to near full height; the owner kept the fix as built (2026-11-17).
- **The comment stated "WordPress 7.1 forces that height" as verified** — low, patched: it now names 7.1.4, the release read.

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all pass, the new row included; the row fails on the 4.0.2 stylesheet.
- `npm run build` and `npm run test:unit` -- expected: build succeeds, tests green.

**Manual checks (headless Edge, criterion 2):**
- WordPress66 (WordPress 6.6), a frame per `docs/local-verification.md` on story 38's clean site: the 4.0.2 Free asset installed and measured, then the branch's built `style-index.css` and `style-index-rtl.css` copied over the installed ones (only these differ) and measured again; restore, three "same". The focused tab's `::before` box is measured against the label's box and the tab's box. Expected: on 4.0.2 the ring box is shorter than the label and crosses it; on the fix it encloses the label with a gap, and the tab's height and padding are unchanged.
- WordPress 7.1.4's stylesheets, a static page (see Implementation Notes): the same measurement. Expected: the tab is 48 px, the ring box 24 px tall with both stylesheets, 8 px from the sides on the fix.
