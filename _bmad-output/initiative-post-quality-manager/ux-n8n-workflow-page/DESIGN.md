---
name: Post Quality Manager — n8n Workflow page
description: The WordPress admin's own visual language, applied to one page; no plugin-specific theme.
status: final
created: 2026-10-14
updated: 2026-10-14
sources: ['docs/brand.md']
colors:
  primary: '#2271b1'
  primary-hover: '#135e96'
  ink: '#1d2327'
  ink-muted: '#50575e'
  surface: '#ffffff'
  canvas: '#f0f0f1'
  line: '#c3c4c7'
  success: '#00a32a'
  warning: '#dba617'
  error: '#d63638'
  info: '#72aee6'
typography:
  body:
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif'
    fontSize: 13px
    lineHeight: '1.5'
  heading-page:
    fontSize: 23px
    fontWeight: 400
  heading-section:
    fontSize: 1.3em
    fontWeight: 600
spacing:
  section-gap: 24px
  block-gap: 12px
components:
  status-block:
    background: '{colors.surface}'
    border: '{colors.line}'
    accent-not-set-up: '{colors.line}'
    accent-connected: '{colors.info}'
    accent-manual: '{colors.info}'
    accent-set-up: '{colors.success}'
    accent-outdated: '{colors.warning}'
    accent-failing: '{colors.error}'
  primary-action:
    background: '{colors.primary}'
    hover: '{colors.primary-hover}'
---

# n8n Workflow page — Design Spine

The look of this page belongs to WordPress. The tokens above restate the wp-admin defaults (WordPress 6.6+) so the experience spine and the mock can name them. They are not a theme to apply: the build uses wp-admin classes and adds no colour.

## Brand & Style

Native WordPress admin (`docs/brand.md` § Visual Design). The page should read like a core settings screen with a clear status at the top. It is quiet, plain and functional, and frames its problems as fixes.

## Colors

- `{colors.primary}`: the one primary button only.
- `{colors.success}`, `{colors.warning}`, `{colors.error}`, `{colors.info}`: the status block's left accent (map under Components) and the WordPress notice borders, including the info notice of state 2's leftovers variant. Each always comes with a sentence; colour is never the only signal.
- Nothing else is coloured.

## Typography

wp-admin's own stack and sizes. The H1 is the page title; each disclosure's summary is an H2. There is no other heading level.

## Layout & Spacing

- One column inside `.wrap`, at wp-admin's content width.
- Sections are separated by `{spacing.section-gap}`; elements inside a section by `{spacing.block-gap}`.
- The status block sits directly under the H1; the primary action directly under the status block.

## Components

- **Status block:** a white box with a 4 px left accent (the `.notice` anatomy without its dismiss button). It holds the sentence, the version line, and **Test again** as a secondary `.button`. Accent per state (EXPERIENCE.md → States):
  - 1 Not set up → `{colors.line}` (neutral);
  - 2 Connected, not set up and 3 Manual path → `{colors.info}`;
  - 4 Set up → `{colors.success}`;
  - 5 Needs attention → `{colors.warning}` when outdated or on the Free variant, `{colors.error}` when the ping fails or the workflow is mis-wired.
- **Primary action:** `.button.button-primary`.
- **Disclosure:** `<details>` with the summary styled as a section H2 and the WordPress disclosure caret.
- **Manual steps:** unchanged from entry 6.2 (numbered steps, `.pqm-copy-row`).

## Do's and Don'ts

- Do use `.notice`, `.button`, `.button-primary` and `.form-table` as they are.
- Do keep every style in `admin/css/pqm-admin.css` (AGENTS.md: no inline styles, except runtime-computed values).
- Don't add a second primary button, a new colour, an icon set or a card grid.
- Don't hide a state's problem behind a disclosure: the status block always says it.
