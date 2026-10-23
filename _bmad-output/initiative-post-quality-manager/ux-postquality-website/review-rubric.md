# Spine Pair Review — postquality.com

## Overall verdict
A strong contract for the home page. Every colour token has a hex value and a contrast ratio, and I recomputed all of them. Every `{path}` reference resolves. All four journeys and story 57's eleven audit questions map to flows and components. Five of the six `[NOTE FOR UX]` markers are genuinely settled by the planned mocks.

It is not yet a complete build contract for the other pages. The "standard page layout" that the policy pages, `/support/` and 404 rely on is defined nowhere, and no mock will draw it. `/pricing/`'s Free vs Pro comparison has no spec in either spine. Fix those two, decide the Version line and define the mixed launch states before the site build story starts.

## 1. Flow coverage — strong
Checked the sources against the flows:
- The four journeys in `.working/journeys-and-pages-draft.md`, as amended by `.memlog.md` (Tom is Flow 4's protagonist; Journey 2 step 3 is decided).
- Story 57's 11 audit questions and OQ5–8.
- The story's best-practice list.

All four journeys are Key Flows with a named protagonist, numbered steps and a bold climax. Flows 1–3 have failure paths: no email app plus an honest exit; no listing yet; Pro not on sale, with USD back on reload. "Story 57 questions answered" maps every audit question and OQ5–8 to a section.

### Findings
- **medium** Getting started promises "one screenshot per step" in three places: Flow 2 step 4, the page map's `/docs/` row and the State Patterns row "Free installable / `/docs/`". But § Screenshots fixes four screenshots (owner decision, Q8; memlog 27) and gives Getting started only screenshot 3. The capture session on the Local sandbox and the `/docs/` build will disagree on how many images exist (EXPERIENCE § Page map, § State Patterns, § Key Flows Flow 2, § Screenshots). *Fix:* either say Getting started shows screenshot 3 once and drop "per step", or add per-step captures to § Screenshots (3a–3c) with the owner's agreement, since the four-screenshot list is an owner decision.
- **low** Flow 4 has no failure path. Separately, the support policy (`docs/policies/support.md`) sends free-plugin questions to the WordPress.org forum once the plugin is listed, yet the page map gives `/support/` the same email action after launch (EXPERIENCE § Page map `/support/` row; § Key Flows Flow 4). *Fix:* add "Failure: no email app, so Tom copies the address from the footer." Make the after-launch cell "Email for Pro; the forum for Free once listed (policy text)".
- **low** Story 57's front-page list asks for a compatibility line with `Tested up to`. The spines show WordPress 6.6+ and PHP 8.0+ (What you need chips, Requirement line) but neither place nor drop `Tested up to` (EXPERIENCE § Component Patterns: What you need block, Version line). *Fix:* add it to the after-launch Version line ("Version X, updated <date>, tested up to WordPress 7.1"), or record that it is dropped.

## 2. Token completeness — strong
Checked 16 colours, 23 typography roles, 4 radii, 19 spacing tokens and 27 component token groups. Checked the references: 53 `{path}` in DESIGN.md and 6 in EXPERIENCE.md.

Every colour is a hex value. Light-only is a recorded decision (memlog 39), so no dark pairs are owed. I recomputed every ratio in DESIGN § Colors with WCAG relative luminance; all match to two decimals. That includes the green-on-band 2.72:1 failure that changed the focus ring.

Every load-bearing pair carries a stated ratio:
- button fills and the focus ring on every surface;
- muted text on each surface;
- green as a graphic on each surface.

### Findings
- **low** Six phone sizes exist only as numbers in the Typography table's Phone column, while other roles got `-mobile` tokens (DESIGN § Typography; frontmatter `components.need-item.title-size: 21px`, which has no typography role). A builder that mirrors the frontmatter into CSS custom properties will miss them:
  - ticks and requirement line 15
  - caption 14
  - eyebrow 13
  - What you need card text 16
  - pillar names 19
  - What you need title 21 → 19

  *Fix:* add `caption-mobile`, `body-sm-mobile`, `label-caps-mobile`, `body-card-mobile` and `title-mobile`, plus a role for the 21 px item title. Or say once that phone sizes outside the tokens come from the table.
- **low** Several component values are neither dimensions nor references, so a resolver cannot flatten them: T-unit values in `lockup-b` / `lockup-c` / `icon-f` (`0.63 T`, `0.375 T`, `0.5 T`), `wordmark-weights: '600 + 400'`, `sharing-card.size: 1200 x 630 px` and the padding shorthands (DESIGN frontmatter `components`). *Fix:* fine for a human or agent builder. If the frontmatter is to feed CSS generation, leave the T-unit specs in prose only (they are already in § Components → Lockup) and keep px values in the frontmatter.

## 3. Component coverage — adequate
Checked that each of the 24 named components (Lockup plus 23 page components) has a DESIGN.md row and an EXPERIENCE.md row with real rules. Frontmatter tokens exist for 20 of them; the four without are `[NOTE FOR UX]` items. Then checked every block named in the page map, State Patterns and flows for a row.

Judgement on the six `[NOTE FOR UX]` markers:
- **Settled by the mocks (5):** the header's small-button size, the Menu disclosure, the pricing pack cards (Plan card), the Currency switch's selected tab and the FAQ summary. The `/pricing/`, `/docs/` and phone Menu mocks will draw these.
- **A missing decision (1):** the Version line (finding below).

Coverage is complete for `/` and thin for the pages no mock will draw.

### Findings
- **high** "The standard page layout" is cited for `/privacy/`, `/terms/` and `/refund/` (EXPERIENCE § Page map; memlog 41), and implicitly for `/support/` and 404. It is defined nowhere, and the memlog keeps these pages out of every mock. Missing:
  - the page title off the home page: the Typography table maps every page's h1 to `display` 64 px, with no band and no surface named;
  - an h3 role (`privacy.md` has three h3s);
  - list spacing;
  - the prose measure;
  - the 404 heading.

  *Fix:* add a "Text page" row to DESIGN § Components and EXPERIENCE § Component Patterns: header; page title (role, size, surface); body `{typography.body}` at a maximum measure; h2 `headline`; h3 `title`; lists; footer. Or say these pages reuse the `/docs/` mock's layout and name the parts they take.
- **high** `/pricing/` opens with a "Free vs Pro comparison (cards on phones)" that has no row in either spine and is outside the `/pricing/` mock's listed scope (memlog 41: currency switch, pack cards, FAQ) (EXPERIENCE § Page map; § Responsive & Platform). It is the first block on the main conversion page. Its checks carry meaning, which collides with the Accessibility Floor rule that checks are decorative and hidden from assistive technology. *Fix:* add a "Comparison table" component with:
  - a visual spec: header row, `{colors.hairline}` row rules, included and not-included marks;
  - the phone card form;
  - a behaviour rule: real `<th scope>` headers, and a text equivalent for every mark ("Included" / "Not in Free").

  Add it to the `/pricing/` mock's scope.
- **medium** In-text links have no spec (DESIGN § Colors, `navy` row; § Components has no text-link row). Links are navy on white, but navy against `ink-body` is 1.18:1 and against `ink-muted` 1.97:1, so colour alone cannot mark a link inside a sentence. That fails WCAG 1.4.1, and Lighthouse's link-in-text-block audit would flag it under the story's "no accessibility error on `/` and `/pricing/`" criterion. Affected: "No n8n yet?", the plan note link, the Requirement line link, and every link in the docs and policy text. *Fix:* add a Text link row. Links in running text are always underlined (give the offset and thickness), with a hover rule. Name the places that drop the underline: header nav, footer nav, buttons.
- **medium** The Version line's `[NOTE FOR UX]` does not depend on any mock: DESIGN § Components says no mock covers it, and it is EXPERIENCE § Open Questions 1. Yet its before-launch form, the status line "Pre-release, not installable yet", ships in today's state on `/` (EXPERIENCE § State Patterns, first row). It is missing from the hero order in § Component Patterns → Hero (eyebrow, h1, lead, requirement line, actions, action note, ticks). Together with the action note and the Free card footnote, `/` would say "not installable yet" three times. *Fix:* decide now:
  - position: for example directly under the actions, before the action note;
  - type and colour: `{typography.caption}` in `{colors.ink-muted-on-dark}` on the band;
  - whether the action note keeps its "Not installable yet." opening.
- **low** The header's "small" primary button collides with the Accessibility Floor's "buttons at least 52 px tall" (DESIGN § Components → Header; EXPERIENCE § Accessibility Floor → Targets). *Fix:* when the mock sets the size, give the header button its own floor in the Targets line, for example 44 px like the Menu.
- **low** Two small gaps:
  - The line icons (n8n flow, key, bill, shield, archive box, info, mail, download, arrows) have sizes and colours but no named set, stroke width or source (DESIGN § Components: What you need block, Assurance item, Primary button, Requirement line).
  - The interest email in the `/docs/` install section is not specified as either a button or a text link (EXPERIENCE § Page map `/docs/`; § State Patterns "Before launch / `/docs/`").

  *Fix:* name the inline SVGs in `direction-navy-and-check.html` as the source (stroke width, caps). Say the install section uses the primary button on light.

## 4. State coverage — adequate
Checked each surface for its states:
- launch states for `/`, `/pricing/`, `/docs/` and the header;
- repository public;
- currency chosen;
- Menu open and closed;
- hover and focus;
- 404;
- no email app;
- interest tool replaced.

The claim that static pages have no loading, empty, offline or error states of their own holds. Mixed launch states, reload behaviour and action notes beyond `/` are not fully covered.

### Findings
- **medium** State Patterns says the two launch triggers are independent: the free install switches when the plugin is listed or the repository is public, the buy buttons when Freemius releases the plans. Yet the rows cover only "neither", "Free installable" and "Pro on sale" (EXPERIENCE § State Patterns intro and rows). Nothing says what shows in the two mixed cases:
  - **Pro on sale, free plugin not installable:** the home Pro card's footnote "Pro goes on sale with the first public release" becomes false, and the hero still asks for the interest email.
  - **Free installable, Pro not on sale:** the `/pricing/` interest button still sends "Tell me when Post Quality Manager is available" about a plugin that already is.

  *Fix:* add a 2 × 2 table (free × Pro). For each cell give the header action, hero actions, card footnotes, `/pricing/` action and the interest subject.
- **low** "A reload shows USD again" (EXPERIENCE § Component Patterns → Currency switch; State "Currency chosen"; Flow 3 failure) is not guaranteed. Firefox restores radio-button state on reload, and browsers restore it on back navigation, unless the inputs carry `autocomplete="off"`. *Fix:* require `autocomplete="off"` on the radio group, or soften the claim to "nothing is stored by the site".
- **low** Two rules are met only on `/`: "A reason beside each action" (EXPERIENCE § Voice and Tone) and "Links that leave the page say where they go" (§ Accessibility Floor). The header's mailto button on every page and the `/pricing/` before-launch interest button have no action note. *Fix:* give the `/pricing/` interest button its own note (the hero's wording). Decide whether the header button may rely on its label alone, and record that.

## 5. Visual reference coverage — adequate
Checked:
- **Visual folders:** `mockups/` and `wireframes/` do not exist and `imports/` is empty, so there are no orphans.
- **Inline references:** both spines link their composition references inline and say what each shows:
  - `direction-navy-and-check.html`: home before launch, the hero after launch, phone;
  - `logo-lockups.html`: header, footer, 404, favicon, sharing card;
  - `compare-home-first-screens.html` and the three rejected directions, under Inspiration.
- **Spine-wins rule:** stated once in each spine's preamble.
- **Pending markers:** the five marker gaps that wait on mocks name the mock that will settle them.

The weak points are where the references live and one source whose mocks contradict the spine.

### Findings
- **medium** Every composition reference lives in `.working/`, the facilitator's scratch folder (DESIGN and EXPERIENCE preambles). So does the Plan card's content pointer, "per sources extract § Product facts" (EXPERIENCE § Component Patterns → Plan card). If `.working/` is pruned at Finalize or not handed to the build story, those links break. *Fix:* at Finalize, copy `direction-navy-and-check.html` and `logo-lockups.html` into `imports/` (or `mockups/`) and relink. Write the Free and Pro feature lists into the Plan card row instead of pointing at the extract.
- **medium** `logo-lockups.html` is named the composition reference for the 404 page and the footer, but its mocks contradict the spine. The 404 mock draws a white header with a grey rule, WP-blue (#2271b1) links and a 28 px / 600 heading, and the sheet tests a light footer on #f0f0f1. EXPERIENCE's "Overridden in the chosen direction" list covers only the direction file, and DESIGN gives the 404 nothing visual beyond icon F at 64 px. A builder will copy the sheet. *Fix:* list the sheet's overrides next to its link (header = band strip, links navy, footer white) and specify the 404 inside the Text page row from § 3.

## 6. Bloat & overspecification — adequate
Checked for source restatement, duplicated prose, pixel specs where tokens exist, and sections no consumer would read. DESIGN's prose carries decisions; EXPERIENCE stays mostly terse and free of editorial voice.

### Findings
- **low** Draft copy is written out in several places, and story 60 will replace all of it:
  - The before-launch action note appears in Voice and Tone and again in Component Patterns → Action note.
  - The Pro footnote appears in Component Patterns and State Patterns.
  - "not installable yet" appears nine times across the two spines.

  *Fix:* keep each string once (a copy table in Voice and Tone, or the Action note row) and refer to it by name elsewhere.
- **low** The Typography table restates every desktop value already in the frontmatter (DESIGN § Typography). *Fix:* keep the role, token and Phone columns and drop the desktop numbers.
- **low** "Honest nudges" re-indexes components already specified, and "Success signals" is a single `[ASSUMPTION]` line (EXPERIENCE). *Fix:* fold Success signals into Foundation or Open Questions. Keep Honest nudges only if the owner wants the vision traceable inside the spine.

## 7. Inheritance discipline — strong
Checked:
- Both `sources:` lists resolve (6 paths in EXPERIENCE, 5 in DESIGN).
- Flow titles match the journeys draft verbatim (Flow 4's protagonist per memlog 42).
- Glossary terms match the extract and `docs/brand.md`: Post Quality Manager / Post Quality, the four pillars, Fix next, Top edits, Blog Profile, Editorial Standards, setup guide, Getting n8n, Getting started.
- Later memlog entries override earlier ones correctly:
  - the focus ring is navy or white, not green;
  - the header button repeats the site-wide action, not each page's own action;
  - the assurance item's green edge stays.
- Component names match across sections and spines.
- All 6 EXPERIENCE token references resolve to DESIGN tokens by name.

### Findings
- **low** Frontmatter component keys do not map one-to-one onto the prose names (DESIGN frontmatter `components` vs § Components): `need-item` ↔ What you need block, `step-number` ↔ Steps, `flow-node` ↔ Flow diagram, `plan-card-free` / `plan-card-pro` / `plan-note` ↔ Plan card, `lockup-b/c/d` and `icon-f` ↔ Lockup. *Fix:* rename the keys, or add one line under § Components mapping each key to its row.
- **low** Plan footnotes belong to the Action note in EXPERIENCE (§ Component Patterns → Action note) but to the Plan card in DESIGN (§ Components → Plan card, "a muted footnote"). *Fix:* pick one owner in both spines.
- **low** Two `[ASSUMPTION]`s could be resolved from the record:
  - Flow 3 step 1 ("the plugin links to the pricing page") is partly on record already. Bug 59 (`_bmad-output/backlog/bug-upgrade-links-lead-nowhere.md`) says Settings → "Get Pro" reaches the public pricing page, while the Freemius Upgrade links lead nowhere.
  - The two Foundation assumptions (form factor, stakes) are still "to confirm" (memlog 11–12), although later owner decisions (phone Menu, 360 px floor) imply them.

  *Fix:* cite bug 59 as Flow 3's dependency. Have the owner confirm the two Foundation assumptions at Finalize, or turn them into decisions.

## 8. Shape fit — strong
Checked:
- **DESIGN.md:** all eight sections are present, in canonical order (Brand & Style → Colors → Typography → Layout & Spacing → Elevation & Depth → Shapes → Components → Do's and Don'ts).
- **EXPERIENCE.md defaults:** all eight required sections are present.
- **Required when applicable:** both are present. Inspiration & Anti-patterns is triggered by the reference sites and rejected directions on record; Responsive & Platform by the several breakpoints.
- **Invented sections:**
  - Screenshots: the capture list for the build;
  - Content sources and drift checks: who owns each piece of copy;
  - Story 57 questions answered: traceability for the story's acceptance;
  - Open Questions.

  All earn their place, apart from the two noted in § 6.
- **Frontmatter:** the extra keys in DESIGN (`status`, `updated`, `sources`) are harmless.

### Findings
- None beyond § 6's note on Honest nudges and Success signals.

## Mechanical notes
- All 53 `{path}` references in DESIGN.md and all 6 in EXPERIENCE.md resolve. Both `sources:` lists resolve, including `../../backlog/story-site-content-style-checkout-best-practices.md`.
- The frontmatter parses as YAML, `&sans` / `*sans` anchors included (checked with PyYAML). A DESIGN.md tool that does not expand anchors would read `*sans` as the font family.
- `spacing.gap-xs` (10 px) is defined and never used. `bp-medium`, `bp-phone`, `min-viewport` and `header-height` are breakpoints and a height filed under `spacing`.
- `typography.title-lg`, `label-caps` and `nav` have no `lineHeight`.
- There is no frontmatter component group for Menu disclosure, Version line, Currency switch or FAQ (the `[NOTE FOR UX]` set). Add them when the mocks land.
- Markers:
  - **`[NOTE FOR UX]`:** six, all in DESIGN.md; five wait on the mocks, one (Version line) is a missing decision.
  - **`[ASSUMPTION]`:** none in DESIGN.md; six in EXPERIENCE.md (Foundation ×2, Flow 1 step 1, Flow 3 step 1, Success signals, the Q10 row of "Story 57 questions answered").
- Stale wording: EXPERIENCE § Responsive & Platform says "Comparison and price tables become cards on phones", but the price table was replaced by the pack plan cards and the currency switch.
- DESIGN § Colors cites `ink-muted` 6.43:1 on #f0f0f1, a WP canvas grey outside the site palette (left over from the lockup sheet's light-footer test).
- Every contrast ratio in DESIGN § Colors recomputes correctly (WCAG 2.x relative luminance).
- EXPERIENCE § Responsive & Platform, "about 340 px used" at the 360 px floor: say whether this includes the two 16 px gutters. If it does not, 340 px exceeds the 328 px content width and the header overflows.
- No Mermaid in either spine.
- Both spines are `status: draft`, `updated: 2026-10-22`. The memlog decisions up to the last entry (green assurance edge) are reflected.
