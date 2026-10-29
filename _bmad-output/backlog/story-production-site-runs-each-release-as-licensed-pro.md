---
id: 98
type: story
title: "The owner's production site runs the new release as Pro, under a free lifetime license"
parent: none
covers: [O9]
after: [58]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The owner's production site runs the new release as Pro, under a free lifetime license

## Description

The owner's production site has run the Pro build from local zips of main, installed by hand for each walk. Pro has needed a Freemius license since story 44, and this site holds none. Once the next release is ready, the site moves to the Pro build of that release, unlocked by a Pro license the owner issues to themselves in the Freemius dashboard, free and lifetime. After that the site runs what a paying customer runs, so it can serve as the first real check of each release.

## Acceptance Criteria

1. **A free lifetime Pro license exists**
   **Given** the Freemius dashboard of Post Quality Manager
   **When** the owner creates a license with **Create License** on the Pro plan's 1-site pricing, with no expiry
   **Then** the Licenses page lists it as lifetime, and Freemius records no payment and no subscription for it, so the funnel's purchase totals stay unchanged (ADR 0016)
2. **The license unlocks Pro on the production site**
   **Given** the production site running the Pro build of the release
   **When** the license key is entered on the SDK's license screen
   **Then** Settings → License reads "Current plan: Pro — all agents, per-agent overrides, scheduled reviews and role settings are unlocked.", and no expiry or renewal notice appears
3. **The production site runs the new release**
   **Given** the next published release (3.10.0 or later) and its Pro build: the `post-quality-manager-<version>-pro.zip` artifact of its Release run, or Freemius's Pro download
   **When** the production site is updated to it
   **Then** the Plugins screen shows that version; the n8n Workflow page reports the workflow at that release's template version, after **Update workflow** if the page offers it; and one command run on a post saves its review
4. **The update can be undone**
   **Given** the update in criterion 3
   **When** it is about to run
   **Then** the build it replaces is kept, so the site can go back to it in one step
5. **Nothing secret or identifying reaches the repository**
   **Given** this story's records (ticket, plan, memory, PR text)
   **When** they are written
   **Then** they hold neither the license key nor the site's address or title: the site is "the owner's production site"

## Boundaries

- Must not change: ADR 0013 D4. This license is a one-off grant from the owner to their own site, not a lifetime plan offered for sale. Plugin code stays unchanged; a defect the update finds becomes its own ticket.
- Out of scope: licenses for other people (testers, partners); the Free build's path through WordPress.org (story 96); the Free and Pro prices.

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D4: one Pro plan, yearly, no lifetime plan)
- decision — docs/adr/0016-funnel-read-from-existing-sources-site-unmeasured.md (purchases read from Freemius payments and subscriptions)
- related — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44: real licensing; AC1 walked with a sandbox license typed on the SDK screen)
- related — _bmad-output/backlog/story-release-uploads-zip-to-freemius.md (story 58: each release reaches Freemius, the Pro build comes back as an artifact)
- data — docs/distribution.md (product, plans and pricing IDs)

## Notes

- Decision: owner, 2026-10-29 — asked for this ticket: update the production site once the new release is ready, and give it a Pro license that is free and lifetime.
- Fact (Freemius documentation, read 2026-10-29): the Developer Dashboard's Licenses page has a **Create License** button for licenses issued without Checkout. In the matching API (`POST …/plans/{plan_id}/pricing/{pricing_id}/licenses.json`), `period: 0` or no `expires_at` means lifetime. An owner email set on the license must belong to a user who has already installed or activated the product; otherwise the key is entered on the site instead.
- Open: who runs the update — the owner, or the agent over the project's SSH access at the owner's request, as in earlier walks.
- Open: `release.yml` sets every version to `release_mode: pending` on Freemius (story 58). Unverified whether a licensed site's updater offers a pending version, or the Pro zip is installed by hand until the launch (entry 4.8).
- Open: which release. 3.10.0 carries story 95. Bug 97 (template version 8) is in it only if its PR merges before the release PR; criterion 3's template check then includes **Update workflow**.
