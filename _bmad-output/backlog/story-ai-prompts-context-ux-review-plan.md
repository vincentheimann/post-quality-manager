---
title: 'AI Prompts & Context holds the editorial standards, says what the AI receives and never overwrites typed text silently'
type: 'feature'
ticket: 26
created: '2026-10-07'
status: done
baseline_revision: 'd449d9bcc11be3efbb05f189dc080fad039a9f1f'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context:
  - '{project-root}/_bmad-output/backlog/story-ai-prompts-context-ux-review-findings.md'
  - '{project-root}/docs/adr/0011-editorial-standards-and-author-voice.md'
  - '{project-root}/docs/brand.md'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** In Settings → AI Prompts & Context the Blog Profile and the voice presets read as settings but only fill text boxes, so three controls fight over two fields: a click can replace typed text, the radio can name text the field no longer holds, the agents got the voice from code while a preset was selected, empty fields send hidden defaults, and the one site-wide "Brand Voice" cannot express the owner's model — shared editorial rules plus each author's own voice (findings F1–F6, ADR 0011).

**Approach:** Make the section the **editorial layer** of ADR 0011 with the lowest cognitive load: **the radio is the setting; Custom opens the text box.** Decisions (owner, 2026-10-07; D2–D5 renegotiated the same day after two builds — a fill-and-undo, then a fill-or-offer — both read as text-box mechanics):
- D1 "Brand Voice & Tone" becomes **Editorial Standards**: the voice and rules every post follows — a solo blogger's own, a team's shared ones — sent to Review and Rewrite. Named for the whole persona, never "corporate".
- D2 Two rows, each one radio group: **Blog Profile** (six profiles + Custom) sets Website Context; **Editorial Standards** (Professional, Casual, Direct + Custom) sets the standards. A selected preset *is* the setting: its text is resolved when the command runs, from code, in the **site language** whoever triggers, so a plugin update that improves a preset reaches every site on it. Custom sends the stored text.
- D3 Under a selected preset the page shows the preset's text **read-only, muted** (what the AI gets; copy it if you want to start Custom from it). The textarea appears **only under Custom**, and Custom starts with the stored custom text, never pre-filled from a preset (the 2026-07-23 "presets stay editable" rule is replaced: Custom is the one editable path).
- D4 No fill, no undo, no offer, no dialog. Switching radios never writes into the textarea; Save stays the only commit point. Custom stays visible in both groups.
- D5 Each row says which agents use it; the Custom textarea says what an empty field sends. The template keeps its defaults.

## Boundaries & Constraints

**Always:** stored keys `blog_profile`, `brand_voice_preset`, `brand_voice`, `website_context` keep their names and the sanitizer its contract (the hidden textarea still posts, so custom text survives a round trip through a preset); Custom visible in both groups; copy per `docs/brand.md`, every string translatable; no `confirm()` or modal.

**Never:** change the n8n template or its version; touch Agent-Specific Overrides, the AI Model section or tabs (story 27); add per-user data or profile UI (story 37); add a REST route or DB change; React-ify the Settings page (ADR 0005); no upgrade migration (owner: no real users; a site whose text diverged from its selected preset now sends the preset — accepted).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Preset selected | `brand_voice_preset=direct`, any `brand_voice` | payload `brand_voice` = Direct instruction in the site locale; page shows it muted, no textarea | No error expected |
| Custom voice | `custom` + typed text | payload = typed text; textarea shown with it | No error expected |
| Custom, empty | `custom`, `brand_voice=''` | payload `''` (template default); textarea description names the default | No error expected |
| Unknown preset key | `retired`, stored text | payload = stored text (treated as Custom) | No error expected |
| Profile selected | `blog_profile=technical`, any `website_context` | payload `website_context` = Technical text in the site locale; muted preview; no textarea | No error expected |
| Custom profile | `custom` + typed text | payload = typed text | No error expected |
| Trigger by a user in another locale | admin profile fr_FR, site en_US, preset selected | payload text in en_US | No error expected |
| Round trip through a preset | Custom text saved, switch to Technical, Save, switch back to Custom | the custom text is back in the textarea | No error expected |
| Switch radios in the browser | pick Technical, then Custom | preview updates / hides; textarea appears under Custom with the stored custom text and takes focus; nothing is written into it | No error expected |

</frozen-after-approval>

## Code Map

- `post-quality-manager/post-quality-manager.php:260` `pqm_voice_presets()` -- keep; `pqm_resolved_voice()` (added this story) -- rewrite: known preset → its instruction, else stored text; add `pqm_resolved_context(array $settings): string` on `PQM_Admin::blog_profiles()` (the class is required unconditionally at `:39`, so cron/REST see it); both run inside `switch_to_locale(get_locale())` / `restore_previous_locale()` (one small wrapper), used by `pqm_n8n_payload()` for `brand_voice` and `website_context`.
- `post-quality-manager/admin/class-pqm-admin.php:676-738` -- section markup → two rows, each: radios, `p.description.pqm-preset-text` (muted preview, server-rendered for the current choice, `hidden` under Custom), a `div.pqm-custom-field` wrapper (`hidden` unless Custom) holding the textarea + its description; `:186-203` bridge: keep `blogProfiles` / `voicePresets` text maps, drop `textReady`/`replaceText`/`keepText`; `pqm-admin` script deps back to `[]` (no `wp-a11y`); `:270` `blog_profiles()` keep; sanitizer unchanged.
- `post-quality-manager/admin/js/pqm-admin.js` -- replace `bindStartingPoints()` with `bindPresetChoice(selector, preview, wrapper, texts)`: on change set `preview.textContent`, toggle `preview.hidden` / `wrapper.hidden`, focus the textarea when Custom is revealed. No writes into textareas.
- `post-quality-manager/admin/css/pqm-admin.css:17-19` -- keep; `.pqm-preset-text` needs at most a max-width/margin rule.
- `post-quality-manager/templates/n8n-workflow.json` -- read only (defaults *"a general audience blog"*, *"- Professional yet accessible\n- Active voice preferred"*; context → Review/Rewrite/SEO/Audit, voice → Review/Rewrite).
- `src/setup/steps.js:570-576`, `README.md:23,30,51` -- copy: a profile is chosen, Custom opens a text field.
- `tests/harness/voice-harness.php` -- rewrite for the matrix (stub `get_locale`, `switch_to_locale`, `restore_previous_locale`; prove the locale switch is called and restored).

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/post-quality-manager.php` -- `pqm_resolved_voice()` / `pqm_resolved_context()` + site-locale wrapper; payload uses both -- D2.
- [x] `tests/harness/voice-harness.php` -- rewrite: matrix rows 1–8 -- proof.
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- two-row markup with muted preview and Custom-only textarea, descriptions per D5, bridge/i18n cleanup, script deps -- D1, D3, D5.
- [x] `post-quality-manager/admin/js/pqm-admin.js` -- `bindPresetChoice()` for both groups -- D3, D4.
- [x] `post-quality-manager/admin/css/pqm-admin.css` -- `.pqm-preset-text` rule if needed.
- [x] `src/setup/steps.js`, `README.md` -- copy matches D2/D3.

**Acceptance Criteria:**
- Given either row, when a preset is selected, then its text is shown muted and no textarea is visible; when Custom is selected, then the textarea appears with the stored custom text and nothing was written into it.
- Given a selected preset, when any command runs — by any user, or cron — then the payload carries that preset's text in the site language.
- Given custom text saved, when the admin switches to a preset, saves, and switches back to Custom, then the custom text is still there.

## Implementation Notes

- 2026-10-07: rows reordered to Blog Profile → Website Context → Editorial Standards, so the profile sits above the one field it fills. `brand_voice_preset` dropped from `blog_profiles()` data too (dead once the bridge stopped sending it); the function stays. Editorial Standards placeholder now shows example rules instead of a tone.
- `pqmAdmin.blogProfiles` is now `key => website_context` (string map, like `voicePresets`); the JS helper `bindFillWithUndo()` serves both radio sets. It shows the Undo notice only when the fill replaced non-empty, different text; Custom never fills (the profile bridge carries `custom => ''`, so the guard is explicit).
- Verified: `php -l` clean (3 files), `node --check` clean, all 13 harnesses green (`voice-harness.php` 31 checks, rows 1–5 + empty + unknown key), `npm run build` and `npm run lint:js` clean; matrix rows 6–8 plus Custom/empty/successive-fill cases exercised against the real `pqm-admin.js` in jsdom (scratch script, not committed). Owner walk on Local still open.
- 2026-10-07, after review: D4 renegotiated by the owner (Undo → prevention). `bindFillWithUndo()` replaced by `bindStartingPoints()`; i18n keys `filledFrom`/`undo` replaced by `textReady`/`replaceText`/`keepText`; profile and preset descriptions reworded; README, findings §5 and memory updated. The "Keep my text" link stands in for the × dismiss named in the discussion (no core dismiss script needed for a dynamically inserted notice). Re-verified after the change (see below).
- 2026-10-07, third pass: owner renegotiated D2–D5 (radio = setting, Custom opens the textarea, preset text resolved at send time in the site locale, no migration). Code re-derived from this version of the plan; the fill-and-undo and fill-or-offer implementations are superseded. Lesson 2026-07-23 replaced (see `memory/lessons.md` 2026-10-07).
- 2026-10-07, third-pass build: `pqm_in_site_locale()` wraps `pqm_resolved_voice()` / `pqm_resolved_context()` (each resolver switches itself, so every caller gets the site language); `rest-reviews-harness.php` gained the three locale stubs because it runs `pqm_n8n_payload()` for real. Verified: `php -l` clean, `node --check` clean, 13 harnesses green (`voice-harness.php` 23 checks: rows 1–8, locale switch + restore incl. no-switch and exception paths), `npm run build` + `npm run lint:js` clean; rows 8–9 against the real rendered `render_settings()` markup + `pqm-admin.js` in jsdom (17 checks, scratch, not committed). After review: bridge maps and server previews resolved in the site locale (previews via the resolvers with empty stored text); Custom focuses the textarea only after a pointer pick (keyboard arrowing stays in the group); previews `aria-live="polite"`, fieldsets get a screen-reader `<legend>`; CSS selector `.form-table td .pqm-preset-text`; `render-matrix-harness.php` asserts the rendered rows (15 checks, 160 total); jsdom re-run 20 checks incl. the keyboard path.
- 2026-10-07: owner walk on Local passed (both radio groups, Custom round trip, one Review with the expected `brand_voice` / `website_context` in n8n).
- Matrix test audit (step 3): rows 1–5 covered by `voice-harness.php` (ran, green); rows 6–9 are browser behavior with no repo JS test path (open decision in `deferred-work.md`, story 5.5 entry) — covered by the plan's owner walk, jsdom run as supporting evidence only.

## Plan Change Log

## Review Triage Log

**Pass 1 (2026-10-07, lens `quick`)** — high 1, medium 1, low 3, false 0, maybe-false 0. Routes: patch 2, rejected 3.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | `pqm-admin.js` `bindFillWithUndo()`: a second pick, Custom after a fill, or arrowing through the radios replaces the snapshot, so typed text is unrecoverable | high | patch | Each `change` runs `clearNotice()` and takes `before = area.value`; arrow keys fire `change` per radio passed. Breaks AC1 on the keyboard path. Fix: keep the first snapshot while a notice is open. |
| 2 | `pqm-admin.js`: notice inserted already filled with `role="status"`, often not announced | medium | patch | Live regions announce changes inside an existing region; this one is created with its text. Fix: `wp.a11y.speak()` (core `wp-a11y` dependency), drop the role. |
| 3 | `pqm_resolved_voice()` + textarea: after an untouched Save the stored text is in the saving admin's locale; before, a preset was translated at trigger time (cron = site locale) | low | rejected | Real, and the intended effect of D2/F3 (one source). Reachable only with translations installed: the plugin ships no `languages/` and is not on WordPress.org, so no language packs reach any site today. Fix would add a normalization branch. Surfaced to the owner. |
| 4 | Matrix row 4 / harness label: sites that typed a voice, then picked a preset before 1fc13d0 (2026-07-23, textarea hidden) and never saved since also diverge | low | rejected | Real, but no real users exist (owner, 2026-10-07) and the owner's site has saved Settings since; fix edits this plan's wording. Surfaced to the owner. |
| 5 | Empty Editorial Standards under a non-Custom preset (JS off or direct POST) resolves to the preset instruction, not the described default | low | rejected | Real by D2's resolution rule; with JS on, clearing the text flips the radios to Custom, so everyday use never meets it; fix adds a sanitizer branch. |

Patches 1–2 applied by the implementer (snapshot kept while the notice is open; `wp.a11y.speak()` with core `wp-a11y` as a `pqm-admin` dependency, role dropped). Re-verified: `php -l`, `node --check`, 13 harnesses, build and lint all green. *(Pass 1 reviewed the fill-and-undo build, superseded the same day by the owner's renegotiation of D2–D5.)*

**Pass 2 (2026-10-07, lens `quick`, third build — radio is the setting)** — high 0, medium 3, low 6, false 0, maybe-false 0. Routes: patch 5, rejected 0, defer 1, fixed in documents 3.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | Preview and bridge maps in the viewer's locale, payload in the site locale — page can differ from what the AI gets (D3) | medium | patch | `__()` in the bridge/markup runs under `determine_locale()`; the resolvers switch to `get_locale()`. No translations ship today, but D3 is the row's promise. Fix: build both inside `pqm_in_site_locale()`. |
| 2 | Arrowing onto Custom moves focus into the textarea (WCAG 3.2.2) | medium | patch | Browsers fire `change` per arrow step; Custom is last and wraps. Fix: focus only on pointer-driven changes. |
| 3 | Preview never announced; no group name (no `<legend>`, `<label for>` gone) | medium | patch | Preview is rewritten via `textContent` with no live region; legends were missing before too. Fix: `aria-live="polite"` on the two previews, screen-reader legends. |
| 4 | `.pqm-preset-text { margin-top }` loses to core `.form-table td p` (0,1,2) | low | patch | Specificity (0,1,0) vs core's rule. Fix: `.form-table td .pqm-preset-text`. |
| 5 | Rendered matrix rows (preview shown/hidden, wrapper hidden, textarea keeps `name`, Custom default text) have no harness assertion | low | patch | AGENTS.md: extend a harness for matrix rows; `render-matrix-harness.php` renders Settings already. |
| 6 | AGENTS.md managed block still states the replaced 2026-07-23 rule | medium | defer | True; agent-context files are never edited inside a build — `deferred-work.md` entry, refresh via `bmad-project-context`. |
| 7 | `docs/user-paths.md` A6 and `memory/pivot-agnostic.md` still say presets pre-fill fields | low | fixed (docs) | Reworded in this pass. |
| 8 | ADR 0011 line 8 read `"Editorial Standards", not "Editorial Standards"` — rename artifact | low | fixed (docs) | My rename script hit its own sentence; restored "Corporate Standards" as the rejected name. |
| 9 | ADR 0011 last bullet and findings §5.1 still described presets as fill-in starting points / leaving the section | low | fixed (docs) | Both now say the presets are the Editorial Standards choices and reappear on the author's profile (story 37). |

Patches 1–5 applied by the implementer (previews and bridge maps via `pqm_in_site_locale()` / the payload resolvers; pointer-only focus; `aria-live` + screen-reader legends; `.form-table td .pqm-preset-text`; 15 render checks). Re-verified by the build session: `php -l` ×5, `node --check`, 13 harnesses, build and lint all green.

## Design Notes

Row copy (brand.md: concise, colleague tone). **Blog Profile** — description under the radios: *"Who you write for — sent as the Website Context with every Review, Rewrite, SEO and Audit. Custom: describe your site and readers in two or three sentences."*; the Custom textarea description adds *"Empty: the AI assumes 'a general audience blog'."* **Editorial Standards** — *"The voice and rules every post follows: your own if you write alone, the shared ones for a team. Sent with every Review and Rewrite."*; Custom textarea: *"Empty: the AI falls back to 'professional yet accessible, active voice'."* Preview: `<p class="description pqm-preset-text">` with the preset text only (no "Preview:" label — the muted style says it). Locale: `$switched = switch_to_locale(get_locale()); try { … } finally { if ($switched) restore_previous_locale(); }` — `get_locale()` is the site setting, `determine_locale()` the viewer's.

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: no syntax errors.
- `node --check post-quality-manager/admin/js/pqm-admin.js` -- expected: clean.
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green, `voice-harness.php` included.
- `npm run build && npm run lint:js` -- expected: clean.

**Manual checks (if no CLI):**
- Owner walk on Local: matrix rows 8–9 (round trip, switching), then one Review whose n8n execution shows the expected `brand_voice` and `website_context`.
