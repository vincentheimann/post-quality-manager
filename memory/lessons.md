# Agent Lessons Log

_Updated by the agent after any user correction. Reviewed at the start of each session and loaded into every `bmad-build` run as a persistent fact (`_bmad/custom/bmad-build.toml`). Lived in `tasks/` until 2026-10-01 (ADR 0004)._

---

<!-- The agent appends entries here as the project progresses. Format:

## [YYYY-MM-DD] — Short description of the mistake
**What happened:** ...
**Root cause:** ...
**Rule going forward:** ...

-->

## [2026-07-24] — Dismissing ADR/skill as vehicles because the practice "didn't exist yet"
**What happened:** When placing the user-paths doc, the agent reasoned "the repo has no ADR practice, skills are workflows" and wired references only into AGENTS.md + memory. The user corrected: ADRs, skills, and living memory are practices that *should* be applied in this repo — the absence of a practice was the gap to fix, not a constraint to design around.
**Root cause:** Treating the current state of the workspace as fixed. The agentic workspace is itself a product the user actively invests in; "we don't do X here" is an invitation to propose adopting X, not a reason to route around it.
**Rule going forward:** When a decision reveals a missing practice (decision records, telemetry, a recurring workflow without a skill), surface it as an adoption proposal instead of silently choosing the existing structure. For knowledge placement use the triad: ADR = why, memory = what is true now, skill = recurring how.

## [2026-07-23] — Removing a user-facing "Custom" option during a UX redesign
**What happened:** A settings-UX plan proposed replacing the Blog Profile radios (which include "Custom") with preset-only one-shot buttons, dropping the explicit Custom option. The user rejected it: presets are useful, but the custom possibility is *more* important than the presets.
**Root cause:** Optimizing for control-model purity (action vs. state) over the user's actual priority — full ownership of the AI prompt inputs. Presets are onboarding aids; custom input is the product.
**Rule going forward:** In any preset-driven UI here, every preset must remain fully editable AND an explicit "Custom" path must stay visible. Never remove an escape hatch to simplify a control; simplify around it.

## [2026-07-23] — confirm() dialog on a pre-fill that only takes effect on Save
**What happened:** The profile/voice preset pre-fill asked "Apply this profile?" via confirm() before overwriting the textareas. The user flagged it as too intrusive: the settings form is not persisted until Save, so the overwrite is reversible by simply not saving.
**Root cause:** Treating an in-form field fill like a destructive action. Confirmation dialogs are for irreversible operations; a pre-fill inside an unsaved form is not one.
**Rule going forward:** Never gate in-form, not-yet-persisted changes behind confirm(). Reserve confirmation for actions with immediate side effects (AJAX writes, deletions, resets that persist). The Save button is the real commit point.

## [2026-03-27] — Hardcoding niche dimensions in AI Prompts
**What happened:** We initially hardcoded "Neuroscience" and "Marketing" as scoring dimensions in the n8n AI workflow, making the plugin highly specific to one niche. We had to do a massive refactoring to replace them with universally applicable pillars.
**Root cause:** Overfitting the prompt to the specific use-case of the current website instead of thinking about the plugin as a distributable, agnostic WordPress tool.
**Rule going forward:** Always build AI prompts and data schemas using universally accepted content pillars (Structure, Readability, Engagement, Credibility). Any niche-specific grading should be handled dynamically via user settings (like the Brand Voice preset), not hardcoded in the core prompts.
