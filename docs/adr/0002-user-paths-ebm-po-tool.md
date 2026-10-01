# 0002. User paths with EBM value mapping as the product prioritization tool

- Status: accepted
- Date: 2026-07-24

## Context

The plugin needed a way to decide where effort goes next (and, later, to specify the setup wizard). Options considered: classic personas document, full EBM four-KVA scorecard (CV, UV, T2M, A2I), or journey maps annotated with value. Requirements from the Product Owner: useful for prioritization, grounded in evidence, referenced by both the README and the agent guidance.

## Decision

Maintain [docs/user-paths.md](../user-paths.md) as the single prioritization source: one persona (solo professional blogger, any niche), two journeys (new user from discovery, existing user's quality loop), each stage annotated with EBM **Current Value**, **Unrealized Value**, and a candidate **signal** — full four-KVA treatment rejected as ceremony for a solo project. The existing-user path is grounded in user #1's real usage, not hypotheticals; the doc is living (UV moves to CV when friction is removed). It is referenced from the README, `AGENTS.md` (read before product/UX decisions), and `memory/user-paths.md`.

## Consequences

- Feature debates resolve against the ranked UV table instead of intuition; the wizard spec derives from Path A.
- Signals stay anecdotal until the telemetry backlog item (`tasks/enhancements.md`) ships — CV/UV claims must be re-validated then.
- The doc must be maintained on every friction-removing release, or it silently becomes fiction.
