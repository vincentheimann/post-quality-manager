---
id: 49
type: story
title: "Launch content: a demo, API-cost guidance and the announcement channels"
parent: none
covers: [UV7]
after: [41, 45]
assignee: ""
refined: false
hitl: true
risk: low
---

# Launch content: a demo, API-cost guidance and the announcement channels

## Description

`docs/user-paths.md` A1 says discoverability is near zero and A2 says evaluators bounce on the n8n + API-key prerequisite with no demo, no screenshots and no idea what the AI calls will cost them. Story 41 brings the screenshots; this story brings the rest of what a public launch needs to be found and evaluated: a short demo of the loop (trigger → review → fix next), a plain "what it costs in AI spend" guidance (tokens per review times the provider's price, with the quota and the token limit as the caps the plugin offers), and the announcement plan: the directory listing going live (story 38), the n8n workflow template offered in the n8n community template library (the plugin's own discovery channel, if the template passes their rules without site-specific values), a launch post on the website, and the communities the owner picks.

## Acceptance Criteria

Verify: a demo GIF or video exists and is embedded on the front page of postquality.com (story 45, or story 50 once it exists) and in the readme's Description; the cost guidance is a FAQ entry in the readme and a section on the website, with a worked example per supported provider and a date, and names the Monthly Quota and the token limit as the caps; the announcement plan lists each channel with its date and owner, and the n8n template library submission is either done or recorded as declined with the reason; `docs/user-paths.md` A1 and A2 record the new Signal values to watch (listing views, downloads, website visits).

## Boundaries

- No plugin code; readme text and website content only.
- Paid promotion and social accounts are the owner's call and outside this story.

## References

- requirements — docs/user-paths.md, A1 and A2 (the UV this converts)
- identity — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41: screenshots)
- home — _bmad-output/backlog/story-public-home-site-pricing-docs.md (story 45: where the content lives)
- external — https://n8n.io/creators/ (template library submission)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). Last of the pack; worth doing only once the listing (story 38) is live or imminent.
