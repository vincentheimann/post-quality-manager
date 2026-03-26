# Turbo-Drill — Brand Guidelines

_This file is the single source of truth for brand, copy, and visual decisions.
The agent must read this file before writing any UI text, labels, or copy._

---

## Brand Identity

**Name:** Turbo-Drill
**Domain:** turbo-drill.com
**Category:** Certification quiz & mastery platform
**Target audience:** Scrum and Agile professionals preparing for Scrum.org certifications (PSPO I & II, PSM I & II, EBM, PAL, SPS)

---

## Value Proposition

> Turbo-Drill is the precision quiz platform for Scrum and Agile professionals
> who want to master certification knowledge fast, build real confidence,
> and pass on the first try.

**Three pillars:**
- **Speed** — faster than reading a book or taking a full course
- **Depth** — conceptual understanding, not trivia memorization
- **Confidence** — the real emotional outcome users are buying

---

## Tagline

> **"Drill deep. Pass first."**

**Why it works:**
- Mirrors the brand name directly for memory anchoring
- Two imperative verbs create active, high-agency framing
- "First" activates achievement motivation subtly
- 3 + 3 syllable rhythm makes it naturally memorable

**Usage:** Homepage H1, meta descriptions, social profiles, email signatures.
Do NOT paraphrase or replace the tagline with alternatives in UI copy.

---

## Brand Personality

| Dimension | Turbo-Drill IS | Turbo-Drill is NOT |
|---|---|---|
| **Tone** | Direct, confident, encouraging | Corporate, academic, preachy |
| **Voice** | A sharp senior colleague who already passed | A textbook or a teacher |
| **Energy** | Focused, fast, purposeful | Gamified, casual, noisy |
| **Promise** | Mastery through precision drilling | Entertainment or question-bank bulk |

---

## Copy Rules (Agent Instructions)

### Do
- Use **"drill"** as the primary active verb for quiz interactions
- Frame everything as **gain and achievement** — never as fear of failure
- Use **second person** ("your", "you") to keep copy personal and direct
- Use **short sentences** — max 10 words for UI labels and CTAs
- Use **specific language** — "85% on your last drill" beats "good job"
- Match the user's ambition: they are professionals, not students

### Don't
- Never use negative framing ("don't fail", "avoid mistakes", "warning")
- Never use passive voice in CTAs or headlines
- Never use generic verbs: ~~"Start quiz"~~, ~~"Begin"~~, ~~"Take test"~~
- Never use red for wrong answers — use **amber** (red triggers threat response)
- Never use filler words: ~~"Please"~~, ~~"Just"~~, ~~"Simply"~~

---

## Core Vocabulary

| Generic word | Turbo-Drill word |
|---|---|
| Quiz / Test | Drill |
| Start quiz | Start drilling |
| Wrong answer | Not quite / Review this |
| Correct answer | Nailed it |
| Score | Drill score |
| Progress | Mastery progress |
| User profile | Your drills |
| Retry | Drill again |

---

## UI Copy Patterns

### Page headlines
| Page | H1 |
|---|---|
| Login | Your certification starts here |
| Quiz list | Choose your drill |
| Quiz in progress | [Quiz Name] Drill |
| Results | Drill complete |
| Profile | Your mastery progress |

### CTA Buttons
| Action | Label |
|---|---|
| Start a quiz | Start drilling |
| Retry a quiz | Drill again |
| View results | See your score |
| Sign in | Sign in with Google |
| Sign out | Sign out |

### Results / Score Screen — Mastery Messages
Shown below the score, based on percentage:

| Score | Mastery level label | Message |
|---|---|---|
| 90–100% | **Strong result** | You passed with a great score. |
| 75–89% | **Strong foundation** | Solid performance. A few more drills and you're there. |
| 60–74% | **Building momentum** | Good progress. Focus on the questions you missed. |
| 40–59% | **Keep drilling** | You're getting there. Repetition is the path to mastery. |
| 0–39% | **Early stage** | Every expert started here. Drill again and track your gains. |

### Empty States
| Context | Copy |
|---|---|
| No drills started yet | "Your first drill is one click away." |
| No score history | "Complete a drill to start tracking your mastery." |
| Quiz list loading | "Loading your drills…" |

---

## Visual Identity

### Color Palette

| Role | Name | Hex | Usage |
|---|---|---|---|
| Primary | Deep Blue | `#1A3C6B` | Headers, nav, brand elements |
| Accent | Electric Orange | `#F4651A` | CTAs only — buttons, key actions |
| Background | Soft White | `#F7F8FA` | Page backgrounds |
| Surface | White | `#FFFFFF` | Cards, modals |
| Text primary | Near Black | `#1A1A2E` | Body text, headings |
| Text secondary | Cool Grey | `#6B7280` | Subtitles, hints |
| Correct | Calm Green | `#22C55E` | Correct answer indicator |
| Incorrect | Amber | `#F59E0B` | Wrong answer indicator — NOT red |
| Correct bg tint | `#F0FDF4` | Answer card background when correct |
| Incorrect bg tint | `#FFFBEB` | Answer card background when incorrect |

### Typography
- **Headings:** Inter or Roboto, Bold (700) — clear and authoritative
- **Body:** Inter or Roboto, Regular (400) — high readability under cognitive load
- **Scores / Numbers:** Tabular numerals, Semibold (600) — precise and scannable

### Iconography
- Use MUI icons only — keep visual language consistent
- Prefer **filled** icons for active states, **outlined** for inactive
- No decorative icons — every icon must carry meaning

---

## Brand Positioning

### Market gap
Competitors (Volkerdon, mlapshin, pspo1.com) position around **question quantity**.
Turbo-Drill positions around **quality of learning and confidence at exam time**.

### One-sentence positioning
> Turbo-Drill is the only Scrum certification platform built around mastery and
> confidence — not just question count.

---

## Certification Roadmap (Scaling Plan)

| Priority | Certification | Org |
|---|---|---|
| ✅ Live | PSPO I | Scrum.org |
| 🔜 Next | PSPO II | Scrum.org |
| 🔜 Next | PSM I | Scrum.org |
| 📋 Planned | EBM | Scrum.org |
| 📋 Planned | PSM II | Scrum.org |
| 📋 Planned | PAL-EBM | Scrum.org |
| 📋 Planned | SPS | Scrum.org |

---

## Related Project Files
- `AGENTS.md` — stack rules and development constraints
- `WORKFLOW.md` — agent thinking and execution behavior
- `docs/adr/001-vite-spa-over-nextjs.md` — framework decision
- `docs/adr/002-firebase-over-supabase.md` — backend decision
