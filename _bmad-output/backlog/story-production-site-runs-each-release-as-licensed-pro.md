---
id: 98
type: story
title: "The owner's production site runs the new release as Pro, under a free lifetime license"
parent: none
covers: [O9]
after: [58]
assignee: ""
refined: true
hitl: true
risk: high
---

# The owner's production site runs the new release as Pro, under a free lifetime license

## Description

The owner's production site has run the Pro build from local zips of main, installed by hand for each walk. Pro has needed a Freemius license since story 44, and this site holds none. This story moves the site to 3.10.2's Pro build and unlocks it with a Pro license the owner issues to themselves in the Freemius dashboard, free and lifetime. After that the site runs what a paying customer runs, so it can serve as the first real check of each release.

The update is rehearsed first on the owner's Local copy of production. Production is then updated by hand, after a backup of its plugin folder and database. 3.10.2 stays pending in Freemius.

The agent runs the rehearsal and the production update over the project's SSH access: backup, install and WP-CLI checks. The owner's steps (hitl):
- start the Local copy for the rehearsal;
- create the license and enter its key on the site;
- re-import or update the workflow if the n8n Workflow page asks;
- run one command on a post;
- confirm the site afterwards.

## Acceptance Criteria

1. **A free lifetime Pro license exists**
   **Given** the Freemius dashboard of Post Quality Manager
   **When** the owner creates a license with **Create License** on the Pro plan's 1-site pricing, with no expiry
   **Then** the Licenses page lists it as lifetime, and Freemius records no payment and no subscription for it, so the funnel's purchase totals stay unchanged (ADR 0016)
2. **The update is rehearsed on the Local copy of production**
   **Given** the owner's Local copy of production, with its plugin folder and database backed up first, and WP-Cron and commands kept from running there
   **When** 3.10.2's Pro build replaces its plugin
   **Then** WordPress lists version 3.10.2, every stored name carries the `postquality_` prefix with no `pqm_` option left, the Settings and n8n Workflow pages open for an administrator, and `debug.log` gains no error or warning from the plugin
   **And** the copy is restored from its backup afterwards
3. **Production is backed up before it changes**
   **Given** the production site before the update
   **When** the update is about to run
   **Then** its plugin folder and its database are saved outside the site's web root, so the site can go back to the build it ran, with the data that build reads, in one restore
4. **Production runs 3.10.2**
   **Given** 3.10.2's Pro build: the GitHub Release asset `post-quality-manager-3.10.2.zip`
   **When** it replaces the plugin on the production site
   **Then** the Plugins screen shows 3.10.2, and `debug.log` gains no error or warning from the plugin
   **And** after **Update workflow** or a re-import, whichever the n8n Workflow page offers, the page reports the workflow at template version 8
   **And** one command run on a post saves its review
5. **The license unlocks Pro on the production site**
   **Given** the production site running 3.10.2
   **When** the owner enters the license key on the SDK's license screen
   **Then** Settings → License reads "Current plan: Pro — all agents, per-agent overrides, scheduled reviews and role settings are unlocked.", and no expiry or renewal notice appears
6. **Nothing secret or identifying reaches the repository**
   **Given** this story's records (ticket, plan, memory, PR text)
   **When** they are written
   **Then** they hold neither the license key nor the site's address or title: the sites are "the owner's production site" and "the owner's Local copy of production"

## Boundaries

- Must not change:
  - ADR 0013 D4. This license is a one-off grant from the owner to their own site, not a lifetime plan offered for sale.
  - Plugin code stays unchanged. A defect the update finds becomes its own ticket.
  - 3.10.2 stays pending in Freemius. Setting a version Released remains the owner's step (story 58).
- Out of scope:
  - licenses for other people (testers, partners);
  - the Free build's path through WordPress.org (story 96);
  - the Free and Pro prices;
  - Freemius's beta channel;
  - the production site taking later releases through the WordPress updater.

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D4: one Pro plan, yearly, no lifetime plan)
- decision — docs/adr/0016-funnel-read-from-existing-sources-site-unmeasured.md (purchases read from Freemius payments and subscriptions)
- related — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44: real licensing; AC1 walked with a sandbox license typed on the SDK screen)
- related — _bmad-output/backlog/story-release-uploads-zip-to-freemius.md (story 58: each release reaches Freemius as a pending version)
- related — _bmad-output/backlog/story-stored-names-move-to-the-postquality-prefix.md (story 91: the one-time stored-names upgrade a pre-3.10.0 build meets)
- guide — docs/local-verification.md (the walk on a Local site: backup, build install, `debug.log`, restore)
- data — docs/distribution.md (product, plans and pricing IDs; § Release order: the Pro build is the CI zip)

## Notes

- Decision: owner, 2026-10-29 — asked for this ticket: update the production site once the new release is ready, and give it a Pro license that is free and lifetime.
- Decision: owner, 2026-11-03 — the release is 3.10.2. It carries story 95 and template v8 (bug 97), and it is the release that completed story 58. 3.10.0 and 3.10.1 stay pending in Freemius, and 3.10.2 supersedes them with the same plugin code.
- Decision: owner, 2026-11-03 — the path: rehearse on the Local copy of production, then update production by hand.
  - 3.10.2 stays pending. Freemius's spec says a pending version is "not visible to customers", so the updater would not offer it.
  - Basis (agent's best-practice summary, accepted): a staging copy first; a files and database backup right before the change; the build customers get.
  - Freemius's beta channel was ruled out: the SDK's own text says "not on production sites".
- Decision: owner, 2026-11-03 — who: the agent, over the project's SSH access (backup, install, WP-CLI checks of the version, the stored names, the workflow report and `debug.log`). The owner: the steps in the Description. The license key never reaches the agent.
- Decision: owner, 2026-11-03 — risk high. If production runs a build older than 3.10.0, the update runs story 91's stored-names upgrade on the live database, and going back needs the database backup as well as the old folder. The check outside the criteria: the owner confirms the site after the update (their posts, the reviews and scores shown).
- Fact (Freemius documentation, read 2026-10-29): the Developer Dashboard's Licenses page has a **Create License** button for licenses issued without Checkout. In the matching API (`POST …/plans/{plan_id}/pricing/{pricing_id}/licenses.json`), `period: 0` or no `expires_at` means lifetime. An owner email set on the license must belong to a user who has already installed or activated the product; otherwise the key is entered on the site instead.
- Fact (SDK 2.13.5, `FS_Plugin_Updater::_maybe_adjust_source_dir`): an update through the SDK's updater keeps the installed folder name, though Freemius's Pro download roots at `post-quality-manager-premium`. Uploaded by hand, that download would install as a second plugin folder, so this story installs the GitHub Release asset, which roots at `post-quality-manager`.
- Fact (agent records, 2026-10-16): the Local copy's settings point at the owner's live n8n. No command or WP-Cron run happens on the copy, or it would call the live workflow. Walks have changed the copy since it was made, so it rehearses the update but is not an exact copy.
- Fact (project records, 2026-10-12): production's n8n setup was made by hand. So the n8n Workflow page may offer **Re-import the workflow** rather than **Update workflow**, unless the setup was adopted since.
- Open question: which build production runs today. The walk reads it first, and it decides whether the stored-names upgrade runs there.
- Open question (later, not this story): when production starts taking each release through the WordPress updater, which needs the owner to set each version Released in Freemius. Until then, each release reaches the site by hand.
- Refined 2026-11-03 (owner approved: update path, who, risk, the six criteria).
