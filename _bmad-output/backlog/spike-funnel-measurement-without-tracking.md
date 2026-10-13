---
id: 64
type: spike
title: "How do we know a nudge works, with no tracking script on the site?"
parent: none
covers: [UV7, O9]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# How do we know a nudge works, with no tracking script on the site?

## Description

ADR 0013 D8 and `docs/policies/privacy.md` promise a site with no cookies and no analytics, and `docs/user-paths.md` A1 names repo traffic, stars and downloads as the only discoverability signals. Stories 57, 61, 62 and 63 add nudges whose value is unknown without a count of visits, installs, opt-ins and purchases. This spike answers how the funnel is measured within that promise, or records the decision to stay blind, before those stories spend effort on copy nobody can evaluate.

## Approach

1. List what each existing source already gives for free: Freemius (installs, opt-ins, activations, uninstall reasons, purchases, per version), GitHub (release downloads, traffic, stars), WordPress.org once listed (active installs, downloads), the owner's mail.
2. For the gap, visits and page-level interest on postquality.com, compare: no measurement; server-side or cookieless counting that a privacy policy can describe without consent (checked against GDPR and the Swiss FADP, the owner being in Switzerland); a consent banner with analytics (rejected unless the owner wants it: it costs the no-tracking promise).
3. Define the three to five funnel figures that would change a decision (A1 → A2 → A3 → A5 → purchase), the source of each, and the cadence of reading them.
4. Record the answer as an ADR amendment to 0013 (D8) or a new ADR via `/adr`, and update `docs/user-paths.md`'s Signal lines and `docs/policies/privacy.md` if anything changes.
5. Time box: one session. Good enough: the owner can say, for each nudge story, how its effect will be read, or that it will not be.

## Acceptance Criteria

1. **The sources are compared on what decides**
   Verify: a table in the spike's findings file lists each source, the figures it gives, whether consent is needed, cost, and what it cannot tell
2. **The funnel figures are defined**
   Verify: the findings file names the figures, their source and the reading cadence; each nudge story (57, 61, 62, 63) can cite one
3. **The decision is recorded where the waiting tickets find it**
   Verify: an ADR (amendment to 0013 D8 or new), `docs/user-paths.md` Signal lines, and, if anything changes, `docs/policies/privacy.md`
4. **The owner has accepted it**
   Verify: the ADR's status and a dated Decision line in this ticket's Notes

## References

- parent — none
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8 telemetry)
- signals — docs/user-paths.md (A1, A2 Signal lines; EBM summary)
- policy — docs/policies/privacy.md
- telemetry — memory/MEMORY.md (the local-only `epic-ebm-telemetry` D8 names)
- waiting — stories 57, 61, 62, 63 in _bmad-output/backlog/

## Notes

- Filed 2026-10-13 (agent proposal, owner agreed): a decision several tickets adopt, so an ADR, not a choice inside one of them.
- Assumption: Freemius's dashboard figures are enough for everything after install; the open part is the site.
- Open question: whether the owner wants figures at all before the first public release, or only after.
