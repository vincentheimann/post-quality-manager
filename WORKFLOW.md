# Agent Workflow Instructions

## Workflow Orchestration

### 1. Plan Mode Default
- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately — don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy
- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One task per subagent for focused execution

### 3. Self-Improvement Loop
- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review `tasks/lessons.md` at the start of each session

### 4. Verification & Definition of Done
- Never mark a task complete without proving it works.
- **Definition of Done:**
  - Code follows the KISS principle.
  - Every touched PHP file passes `php -l` (a local PHP CLI is available).
  - No WP debug errors or warnings in PHP logs.
  - REST API endpoints return 200 OK with correct JSON payload and permissions.
  - UI interactions (buttons, tabs) have been manually tested for loading/error states.
- Diff behavior between main and your changes when relevant.
- Ask yourself: "Would a staff engineer approve this?"

### 5. Demand Elegance (Balanced)
- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes — don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing
- When given a bug report: just fix it, don't ask for hand-holding
- Point at logs and errors, then resolve them
- Fix failing endpoints/UI bugs without being told how

---

## Task Management

1. **Plan First** — Write plan to `tasks/todo.md` with checkable items before touching code
2. **Verify Plan** — Check in with user before starting implementation
3. **Track Progress** — Mark items complete as you go
4. **Explain Changes** — Provide a high-level summary at each step
5. **Document Results** — Add a review section to `tasks/todo.md` when done
6. **Capture Lessons** — Update `tasks/lessons.md` after any user correction

---

## Core Principles
- **Simplicity First** — Make every change as simple as possible, impact minimal code (KISS Principle)
- **No Laziness** — Find root causes, no temporary fixes, senior developer standards
- **Minimal Impact** — Changes should only touch what is necessary, avoid introducing bugs
