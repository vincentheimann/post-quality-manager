---
title: "Findings — AI Prompts & Context (story 26)"
created: '2026-10-07'
note: 'findings reviewed with the owner 2026-10-07; target design per ADR 0011. Companion of story-ai-prompts-context-ux-review-plan.md, not a plan.'
---

# Findings — AI Prompts & Context (story 26)

Review of the Settings section **AI Prompts & Context** on `feat/26-ai-prompts-context-review` (base `8bced41`). Input from story 6 criterion 5: walked green 2026-10-07, no findings.

## 1. The controls

| # | Control | Stored as | What it sends to the agents |
|---|---|---|---|
| C1 | Intro paragraph + "Agnostic Scoring Framework" notice | — | Nothing (information only) |
| C2 | **Blog Profile** radios: Marketing, Technical/IT, Lifestyle, Food, Travel, Health & Wellness, Custom | `blog_profile` | **Nothing.** It is a one-shot fill in the browser (`pqm-admin.js`); the payload never reads it |
| C3 | **Brand Voice** preset radios: Professional, Casual, Direct, Custom | `brand_voice_preset` | When not Custom: the preset's instruction from code (`pqm_voice_presets()`), translated **at send time** — the stored voice text is ignored |
| C4 | **Brand Voice** textarea | `brand_voice` | Only while C3 is Custom |
| C5 | **Website Context** textarea | `website_context` | Always |

## 2. How they depend on each other (browser, before Save)

- Picking a profile **replaces** C5 and C4 and selects its voice preset in C3.
- Picking a voice preset **replaces** C4.
- Typing in C5 flips C2 to Custom. Typing in C4 flips C3 to Custom — but **not** C2.
- Picking Custom in C2 or C3 changes no text.

## 3. Which agent receives what (`templates/n8n-workflow.json`)

| Agent | Website Context | Brand Voice | Agent override (other section) |
|---|---|---|---|
| Review | yes | yes | yes |
| Rewrite | yes | yes | yes |
| SEO | yes | — | yes |
| Audit | yes | — | yes |
| Takeaways | — | — | yes |
| Sources | — | — | yes |

Empty fields are not empty for the AI: an empty context becomes *"a general audience blog"*, an empty voice becomes *"- Professional yet accessible / - Active voice preferred"*. The page shows neither.

## 4. Findings

- **F1 — Typed text is overwritten silently (fails the story's criterion).** Picking a profile replaces both textareas; picking a voice preset replaces the voice. The only undo is to leave without saving, which also drops every other unsaved change on the page.
- **F2 — Blog Profile looks like a setting but is not one.** It sends nothing, yet stays selected ("Technical/IT") after the voice is edited or another voice preset is picked, so it can name text the fields no longer hold. The setup dialog's last step says it "tunes the reviews to your niche and voice", as if it acted on its own.
- **F3 — Brand Voice has two sources.** With a preset selected, the agents get the preset text from code, not the stored text. Consequences: (a) the language follows whoever triggers — a manual run by an admin in French sends French, cron sends the site language — while the page shows the viewer's language; (b) a plugin update that rewords a preset changes what the agents receive with no Settings change.
- **F4 — Hidden defaults.** The two fallbacks in §3 reach the AI without the page saying so.
- **F5 — The page does not say who uses what.** Takeaways and Sources use neither field; SEO and Audit ignore the voice.
- **F6 — Docs drift.** README lists three profiles (there are six plus Custom).
- Out of scope, noted: Agent-Specific Overrides (another section) also add text per agent; story 27 (tabs) moves the final section.

## 5. Target design (owner, 2026-10-07 — ADR 0011)

The owner's model has two layers: **editorial standards** every post follows, and **each author's own voice**. The section becomes the editorial layer; the author layer is story 37.

1. **Editorial Standards** replaces "Brand Voice & Tone": admin-owned rules, sent to Review and Rewrite for every author. The tone presets stay in this section as the Editorial Standards choices and reappear on the author's profile for their own voice (story 37).
2. **The radio is the setting.** A selected profile or preset is resolved when the command runs, in the site language; Custom sends the stored text (owner, 2026-10-07, third pass — lowest cognitive load).
3. **Two rows, one radio group each.** Blog Profile sets Website Context, Editorial Standards sets the standards; the preset text shows muted, read-only; the textarea appears only under Custom and starts with the stored custom text.
4. **Nothing is ever written into the textarea by a click.** No fill, no undo, no offer, no dialog; Save is the only commit point. (Two earlier builds the same day — fill-and-undo, then fill-or-offer — were superseded by this model.)
5. **Say what the AI gets.** Under each field: which agents use it, and what an empty field sends. Template defaults stay.
6. README and the setup dialog's last step say the same.
