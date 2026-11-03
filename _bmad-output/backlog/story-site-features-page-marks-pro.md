---
id: 100
type: story
title: "postquality.com's docs show every feature, and which ones are Pro"
parent: none
covers: [O9]
after: [86, 98]
assignee: ""
refined: true
hitl: true
risk: low
---

# postquality.com's docs show every feature, and which ones are Pro

## Description

A visitor the homepage has made curious wants to know what the plugin does before installing it. Today the docs page gives the Settings features two lines. This story adds a Features page to the site's docs: one section per Settings tab and per command, with a capture of each screen from the showcase site. Every Pro feature carries a clear Pro mark. The homepage and the docs page link to it.

The owner activates their lifetime Pro license on the showcase, so it can run the Pro build for the captures, and deactivates it after (hitl). The owner also reviews the copy and the captures in the site PR.

## Acceptance Criteria

1. **The Features page exists in the docs**
   **Given** the published site
   **When** a visitor opens the docs
   **Then** a Features page sits under the docs path, linked from the docs page's "Using it" section and from the homepage, in both launch states of the site
2. **Every Settings tab and every command has a section**
   **Given** the Features page
   **When** it is read
   **Then** it has one section for each Settings tab (Connection, AI, Usage & Limits, Automation, Roles) and for each command (Review, Key Takeaways, Sources, SEO, Audit, Rewrite) and Insert into post, each saying what the feature does for the blogger
3. **Pro features are marked**
   **Given** a section about a Pro feature (per-agent overrides, Automation, Roles, Sources, SEO, Audit, Rewrite, and what else the Free build lacks)
   **When** it is read
   **Then** it carries one consistent Pro mark that links to the pricing page, and the free features carry none; before the launch, the wording follows the site's pre-launch state (Pro goes on sale with the first public release)
4. **The captures come from the showcase**
   **Given** the captures on the page
   **When** they are looked at
   **Then** each shows the showcase site's sample content, never the owner's production site, its title or its posts, with alt text and a caption, in the site's existing capture format (desktop and phone)
5. **The showcase is left as it was**
   **Given** the showcase site before the capture walk
   **When** the walk ends
   **Then** its plugin, database and settings are restored, and the license is active there only while capturing (deactivated before the restore, so no slot stays taken)
6. **The copy follows the brand rules**
   **Given** the page's text
   **When** it is reviewed
   **Then** it follows `docs/brand.md` (naming, tone, no third-party trademark misuse), names features for the persona in `docs/user-paths.md`, and states no KPI that is not measured (story 53)

## Boundaries

- Must not change:
  - the existing docs page's setup instructions, other than the link to the new page;
  - the pricing page;
  - the site's launch-state switch (`launched`);
  - the plugin's code.
- Out of scope:
  - story 60's copywriting skill;
  - story 63's interest capture;
  - a features section on the homepage beyond the link;
  - translations.

## References

- related — _bmad-output/backlog/story-production-site-runs-each-release-as-licensed-pro.md (the owner's lifetime license)
- related — the site repository `postquality.com`: `docs.md`, `pricing.html`, `_includes/screenshot.html`, `_config.yml` (`launched`)
- rules — docs/brand.md; docs/user-paths.md; memory/lessons.md 2026-10-24 (captures only from the showcase)
- guide — docs/local-verification.md (the showcase walk: backup, build install, restore)

## Notes

- Decision: owner, 2026-11-03 — asked for this PBI: the most important features (the Settings → AI tab's sections, Automation, Roles…) in the site's documentation, with a clear Pro mark, for visitors the homepage interested.
- Decision: owner, 2026-11-03 — a new docs page (Features); scope: every Settings tab plus the commands and Insert into post; captures of the Pro screens from the showcase running the Pro build under the owner's lifetime license; built in parallel with the LCM work.
- Decision: owner, 2026-11-03 — the analysis and the implementation plan are written by a Fable 5.1 agent; the implementation runs on Opus 5.5 (agent memory, model rule exception).
- Fact (2026-11-03): the Free build has no Automation or Roles tab; the Pro build without a license shows Pro sections locked. Unlocked Pro captures need the Pro build with a license on the showcase.
- Decision: owner, 2026-11-03, plan checkpoint — the Pro mark is the site's chip as a "Pro" link to the pricing comparison; the current 1-site license is tried on the showcase first (`.local` sites are free activations), raised to 2 only if refused; a fresh Pro-build capture set; the site README's drift rule in full; the homepage link sits under the Free/Pro lists. The plan's frozen block holds the details.
