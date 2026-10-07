# Architecture Decision Records

Significant decisions in this repo are recorded as lightweight ADRs — one file per decision, numbered sequentially, never rewritten (supersede with a new ADR instead).

**Write an ADR when a decision is** hard to reverse, shapes product direction, changes the data schema or an external contract (REST routes, webhook payload, workflow template), or picks between real alternatives. Routine implementation choices don't need one. When in doubt, use the `/adr` skill.

**How the three knowledge stores relate** (don't duplicate content between them):

- `docs/adr/` — **why** we decided: immutable records with context and alternatives.
- `memory/` — **what is true now**: current-state facts, updated in place, linking to the ADRs behind them.
- `.claude/skills/` — **how** we do recurring work: the BMad skills plus project checklists (`/adr`, `/prioritize`, `/release`).
- `_bmad-output/` — **what we are doing**: tickets, specs and build plans (ADR 0004); a ticket cites its ADR in References, never restates it.

## Format

`NNNN-short-slug.md`, kept short (aim for one screen):

```markdown
# NNNN. Title (a decision, not a topic)

- Status: accepted | superseded by [NNNN](NNNN-slug.md)
- Date: YYYY-MM-DD

## Context
What forced the decision — the problem, constraints, and real alternatives.

## Decision
What we chose, in one or two sentences. Bullet the key points.

## Consequences
What becomes easier, what becomes harder, what we committed to.
```

## Index

- [0001 — Record significant decisions as lightweight ADRs](0001-record-decisions-as-adrs.md)
- [0002 — User paths with EBM value mapping as the product prioritization tool](0002-user-paths-ebm-po-tool.md)
- [0003 — Freemium plan and role-based access share one feature gate](0003-freemium-plan-and-role-based-access.md)
- [0004 — Adopt the BMad Method as the delivery process](0004-adopt-bmad-method.md)
- [0005 — Rebuild Dashboard and Post Reviews on @wordpress/components](0005-wordpress-components-for-app-screens.md)
- [0006 — The license provider ships as a stub until Freemius is set up](0006-license-provider-ships-as-stub.md)
- [0007 — Adopt the minimal-code ladder natively in BMad; decline Caveman; Headroom stays machine-local](0007-minimal-code-ladder-native-in-bmad.md)
- [0008 — The plugin, not n8n, writes Takeaways and Sources into posts](0008-plugin-writes-agent-output-into-posts.md)
- [0009 — Agents run on several model providers through two request shapes, under one site-wide token limit](0009-multi-provider-models-and-token-limit.md)
- [0010 — One webhook token authenticates both directions of the n8n link](0010-one-token-authenticates-both-directions.md)
- [0011 — Two voice layers: editorial standards per site, a voice per author](0011-editorial-standards-and-author-voice.md)
