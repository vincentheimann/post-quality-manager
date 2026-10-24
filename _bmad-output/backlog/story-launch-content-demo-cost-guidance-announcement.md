---
id: 49
type: story
title: "Launch content: a demo, API-cost guidance and the announcement channels"
parent: none
covers: [UV7]
after: [45]
assignee: ""
refined: true
hitl: true
risk: low
---

# Launch content: a demo, API-cost guidance and the announcement channels

## Description

`docs/user-paths.md` A2 says evaluators bounce on the n8n + API-key prerequisite without knowing what the AI calls will cost them, and A1 says nothing leads a blogger to the plugin. Since story 86 (2026-10-23) postquality.com shows the plugin in four real captures and its cost FAQ promises "a worked cost example is coming"; that promise is this story's first job. The story brings what a public launch still needs to be found and evaluated: a dated, worked example of what one review costs in AI spend at the plugin's defaults (one Anthropic call per command, the default model, the per-agent output caps), written once and shown on the site and in the plugin's readme; the demo of the loop (trigger → review → fix next) as the three captures the site already carries, in loop order, no video (owner, 2026-10-22); and the announcement plan: which channel says what on which day around the first public release (entry 4.8), with the n8n community template library either submitted or declined with the reason. The owner approves the figures and names the communities (hitl); everything else is the agent's.

## Acceptance Criteria

1. **One worked cost example, measured and dated**
   **Given** the plugin's defaults (default model, one call per command, the per-agent Max Output Tokens shown on Settings → AI) and three fictional posts of the demo site at typical lengths (short, average, long)
   **When** the input tokens of each post plus the settings text are counted with the provider's token-count endpoint (free of charge) and priced at the provider's published list prices on the day
   **Then** a reader finds, in the plugin repository, one example page with the date, the model, the three posts' word and token counts, the price of one review of each, and the monthly figure for one review per post at 20 and at 80 posts; every figure traceable to the counts and the prices quoted, with a line that says prices change and where to check them
2. **The same figures on the site and in the readme**
   **Given** the site's cost FAQ (`/docs/#cost`) and the plugin's readme
   **When** both are read after the build
   **Then** both carry the example's headline figures and date, the "worked example is coming" placeholder is gone in both launch states, and a drift check compares the two copies with the source page
3. **Honest about limits**
   **Given** every sentence of the cost guidance
   **When** it is read against what the plugin does
   **Then** it says the provider bills the reader's own key, that the spend limit lives in the provider's account, that the plugin's Monthly Quota counts an Author's runs and never money, and names no token limit (ADR 0009's is not built) and no second provider; nothing promises a cap the plugin does not enforce
4. **The demo is the loop in three frames**
   **Given** the home page's How it works and the readme's Screenshots section
   **When** a visitor reads them top to bottom
   **Then** the three captures tell trigger → review → fix next in that order with one caption each, the captions say the content is fictional, and no video or animated image is added (owner, 2026-10-22)
5. **An announcement plan with dates and owners**
   **Given** the first public release as day 0 (entry 4.8)
   **When** the plan in `docs/distribution.md` is read
   **Then** each channel has its day relative to day 0, its owner, the text's source and its state: the WordPress.org listing (story 38), the public repository and GitHub release (4.8), postquality.com switched to `launched: true` plus the one message to the interest list (owner, 2026-10-22: one message, then the list is deleted), the Freemius plans released (story 44), the n8n community template library (submitted, or declined with the reason recorded), and the communities the owner names; nothing is posted before the owner says so
6. **The signals to watch are written down**
   **Given** `docs/user-paths.md` A1 and A2
   **When** their Signal lines are read
   **Then** they name the figures the launch will produce without site analytics: Freemius opt-in installs and sales, WordPress.org downloads and active installs, interest emails received; the owner reads them at the first retrospective after launch
7. **Nothing else changes**
   **Given** the boundaries below
   **When** the change is reviewed
   **Then** no plugin code changed, the site's pages, prices and absence of third-party requests are unchanged, and the owner has approved the example's figures and the channel list (hitl)

## Boundaries

- Must not change: plugin code; prices, plans and pricing IDs; the site's URLs; the four captures (story 86).
- Out of scope: the WordPress.org readme beyond its FAQ entry and the screenshot order (story 38 completes the readme; story 41 produces the banner set); KPI claims (story 53: no figure that was not measured); the copywriting pass (story 60); the interest tool (story 63); naming n8n Cloud before its walk passes; paid promotion and social accounts (the owner's call); a merge.

## References

- requirements — docs/user-paths.md, A1 and A2 (the UV this converts; Signal lines edited by criterion 6)
- site — _bmad-output/backlog/story-site-navy-and-check.md (story 86: the captures, the cost FAQ with its placeholder, both launch states)
- data sheet — docs/distribution.md (the announcement plan's home; the release order and the hosted checkout rows)
- decisions — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8: no site analytics, Freemius opt-in as the A1/A2 signal source); docs/adr/0009-multi-provider-models-and-token-limit.md (the token limit is decided, not built)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38: the readme's full FAQ and Screenshots)
- external — https://n8n.io/creators/ (template library submission)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). Last of the pack; worth doing only once the listing (story 38) is live or imminent.
- Linked (owner, 2026-10-09): the promotional claims and KPI figures come from story 53 (`docs/messaging.md`); the demo script and the announcement reuse its pitch and benefit lines. Since the 2026-10-24 refinement the cost example carries measured counts and quoted prices only, so it does not wait on story 53.
- Corrected 2026-10-24: the acceptance text called the token limit a cap. The site's docs FAQ had made the same promise and was fixed live on 2026-10-22 (postquality.com PR #2): the plugin bounds run counts, never spend. Ranked first in the agent lane by `/prioritize` 2026-10-24 (the cost example and the demo are the owner's launch gates since 2026-10-22).
- Refined 2026-10-24 (Fable 5.1, by hand from the workspace root; the owner delegated the decisions in advance, "already approve and continue"). `after` loses story 41: the screenshots this story needed came from story 86; the WordPress.org banner set stays 41's and blocks only story 38.
- Decision (owner delegation, 2026-10-24): the token counts are measured with Anthropic's token-count endpoint using the owner's development key (the one chore 81 revokes at the end), on three demo-site posts; the counting call is free of charge and sends fictional text only. If the key is unavailable at build time, the example states an estimate from the text length and says so.
- Decision (owner delegation, 2026-10-24): the demo stays static, three captures in loop order, consistent with the owner's "four screenshots, no video" of 2026-10-22; a short clip can be added later as its own small ticket if the owner wants motion.
- Decision (owner delegation, 2026-10-24): the example is written once in the plugin repository (a page under `docs/`) and copied to the site FAQ and the readme FAQ with a drift check, the pattern story 85 set for the policies.
- Decision (owner delegation, 2026-10-24): the announcement plan is a table in `docs/distribution.md` with days relative to entry 4.8, not calendar dates, because the release day depends on GitHub Actions credit (blocked until 2026-11-01) and the owner's Freemius steps.
- Assumption: the n8n community template library wants a template without site-specific values; the shipped template carries `__PQM_SITE_HOST__` and `__PQM_WEBHOOK_PATH__` placeholders the plugin fills, so a submission would need a generic variant and a creator account. Recommendation: record as declined for the first release, revisit after launch.
- Open question (owner, at the build's hitl step): which communities to name in the plan (WordPress forums, a subreddit, LinkedIn, newsletters); the plan lists the owner's picks only, no posting before the owner's word.
