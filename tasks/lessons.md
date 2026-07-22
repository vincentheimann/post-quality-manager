# Agent Lessons Log

_Updated by the agent after any user correction. Reviewed at the start of each session._

---

<!-- The agent appends entries here as the project progresses. Format:

## [YYYY-MM-DD] — Short description of the mistake
**What happened:** ...
**Root cause:** ...
**Rule going forward:** ...

-->

## [2026-03-27] — Hardcoding niche dimensions in AI Prompts
**What happened:** We initially hardcoded "Neuroscience" and "Marketing" as scoring dimensions in the n8n AI workflow, making the plugin highly specific to one niche. We had to do a massive refactoring to replace them with universally applicable pillars.
**Root cause:** Overfitting the prompt to the specific use-case of the current website instead of thinking about the plugin as a distributable, agnostic WordPress tool.
**Rule going forward:** Always build AI prompts and data schemas using universally accepted content pillars (Structure, Readability, Engagement, Credibility). Any niche-specific grading should be handled dynamically via user settings (like the Brand Voice preset), not hardcoded in the core prompts.
