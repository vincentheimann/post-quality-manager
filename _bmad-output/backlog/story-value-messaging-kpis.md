---
id: 53
type: story
title: "The promotional wording states the plugin's added value in measured KPIs"
parent: none
covers: [UV7, O9]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# The promotional wording states the plugin's added value in measured KPIs

> **Needs refinement (owner, 2026-10-09):** the KPI figures are placeholders until the owner supplies real ones. Refine this ticket with those figures before it is built; no number below may be published as it stands.

## Description

Owner request (2026-10-09): the promotional content needs its wording, and that wording should sell the plugin by what it saves and improves, in numbers, not by its feature list. `docs/brand.md` has a one-line value proposition ("Streamline your content quality control…") and no figure. This story defines the value KPIs, gets real values for them from the owner, and turns them into the promotional wording every public surface reuses: the readme Description and short pitch (story 38), the pricing and front pages of postquality.com (stories 45 and 50), the launch announcement and demo script (story 49) and the Freemius product description (story 44).

The owner's first example: on a blog with 20 or 80 authors, the time saved is the equivalent of about 2 hours per week. The scale of that figure is open (per author, per editor or per blog) and so is how it was measured; refinement settles both.

Candidate KPIs, to keep, drop or replace at refinement:

- **Editorial time saved:** hours per week, per author or per editor, against reviewing by hand; stated for a small and a large team (for example 20 and 80 authors).
- **Review coverage:** share of the back-catalog that has a current review, before and after.
- **Turnaround:** time from "Review" to a stored score and the fix list.
- **Quality lift:** score change between a review and the next review after edits (`docs/user-paths.md` UV6 signal).
- **Cost per review:** AI spend per post, set against an editor's time for the same check (shares its numbers with story 49's cost guidance).

## Acceptance Criteria

Verify: a messaging file (`docs/messaging.md`, linked from `docs/brand.md` § Value Proposition) lists each kept KPI with its definition, its value, the team size it applies to, its source (owner measurement, customer case or calculation, with the method) and the date; it gives one value proposition, one short pitch (≤ 150 characters, usable beside the tagline) and three to five benefit lines, each tied to a KPI; the wording follows `docs/brand.md` voice and copy rules; every published figure carries its basis in the text next to it ("on a blog with 20 authors, measured by the owner over N weeks"); stories 38, 44, 45, 49 and 50 point at the file instead of writing their own claims.

## Boundaries

- Wording and figures only: no page layout, no visuals (stories 41, 45, 50), no demo (story 49).
- No figure without a source and a date; an estimate is labelled as one, and a guess is never published.
- No comparison naming a competitor and no third-party trademark in the claims (story 38 AC5).

## References

- canon — docs/brand.md (voice, copy rules, tagline, the value proposition this replaces)
- signals — docs/user-paths.md (A1, A2 for evaluators; UV6 for the quality lift)
- consumers — _bmad-output/backlog/story-launch-content-demo-cost-guidance-announcement.md (story 49), story-public-home-site-pricing-docs.md (story 45), story-astro-landing-page.md (story 50), story-wordpress-org-directory-listing.md (story 38), story-real-license-unlocks-pro.md (story 44)
- sheet — docs/distribution.md (the description fields per platform that quote the pitch)

## Notes

- Filed 2026-10-09 (owner). HITL: the owner supplies the real KPI values and approves the wording; the agent drafts the KPI definitions, the calculation method and the copy.
- Open questions for refinement: is the 2 hours a week per author, per editor or per blog; how was it measured; which team sizes to quote; which KPIs to keep.
- Not ranked by `/prioritize` yet. Best refined before story 49 and before story 45 writes the pricing page.
