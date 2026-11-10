---
id: 38
type: story
title: "The plugin is accepted in the WordPress.org plugin directory"
parent: none
covers: [UV7]
after: [10, 15, 41, 9, 44, 46, 47, 54, 58, 65, 88, 89, 91, 95, 70, 122]
assignee: ""
refined: true
hitl: true
risk: high
---

# The plugin is accepted in the WordPress.org plugin directory

## Description

The Free build of the release that goes to WordPress.org is submitted to the plugin directory and approved under the slug `post-quality-manager` (UV7: A1–A2). Before submission, that release passes the pre-production pass on a clean WordPress 6.6 site, in both builds. Story 95 made the Free build meet the directory's rules; story 96 deploys each release once the listing is approved. The owner does four steps no agent can do: create the clean site and look at the plugin's pages there, turn on two-factor sign-in on the WordPress.org account, submit the build, and answer the review team's emails (Notes, hitl).

## Acceptance Criteria

1. **The Free build installs clean on WordPress 6.6**
   **Given** a clean WordPress 6.6 site the owner created in Local, with `WP_DEBUG` on and no other plugin
   **When** the Free build of the release that goes to WordPress.org is installed and activated, and the local verification walk runs on it
   **Then** the debug log holds no notice, warning or error from the plugin; Review and Key Takeaways reach the webhook listener; no Pro command or setting is reachable; the "Get Pro" section on Settings is the only upsell
   **And** with the opt-in skipped and no webhook set, the site sends no request on the plugin's behalf, and every link in the readme opens without signing in
2. **The Pro build of the same release passes the same walk**
   **Given** the same site, restored to its state before the first walk
   **When** the Pro build of that release is installed and activated, a sandbox license is activated, and the walk runs with its cron step
   **Then** the debug log holds no notice, warning or error from the plugin, the paid agents reach the webhook listener, and the scheduled review runs
3. **Every plan's manual checks that still describe shipped behaviour pass**
   **Given** the manual checks of every plan, each marked as shipped behaviour or as retired by a named later ticket
   **When** each shipped check runs on the clean site, on the build it names
   **Then** each passes, and the list with every result is recorded in the plan
   **And** a failure is filed as its own ticket and blocks the submission until a release fixes it
4. **The directory accepts the plugin under its slug**
   **Given** the pre-production pass passed and the `postquality` account has two-factor sign-in on
   **When** the owner submits the Free build through the directory's add-plugin form
   **Then** the form's scan reports no error, and the review team approves the plugin named Post Quality Manager with the slug `post-quality-manager`
   **And** a slug other than `post-quality-manager` is corrected with the review team before approval, since it can never change afterwards
   **And** each review-team finding is filed as its own ticket and fixed in a patch release, and the reply carries that release's checked Free build

## Boundaries

- Must not change: the build that is submitted is the checked Free build of a published release (story 58), never a local build. This story changes no plugin code: a defect found by the pass or by the review team becomes its own ticket. Walks run on the clean site only, never on the owner's production site or its copy (`docs/local-verification.md`, Rules). What the GitHub Release carries (entry 4.8, ADR 0014 amended 2026-10-28).
- Out of scope: the directory's rules for the build (story 95); the deploy to the listing and the update channel (story 96); the website's launched state (story 92); a rewritten Description (story 60); translation content.
- Owner only: the steps under hitl in Notes. The agent never holds the WordPress.org password.

## References

- requirements — docs/user-paths.md, A1–A2 (UV7)
- prerequisite — _bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission.md (story 95: the build meets the directory's rules)
- prerequisite — _bmad-output/backlog/story-add-plugins-card-and-details.md (story 70: the owner approves the card before submission)
- next — _bmad-output/backlog/story-each-release-reaches-the-wordpress-org-listing.md (story 96: the deploy after approval)
- gate — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-first-public-release-is-published.md (entry 4.8: the public repository and the release that is submitted)
- walk — docs/local-verification.md (Rules "Which site", the walk, "Not covered: the pre-production pass")
- decision — docs/adr/0014-two-builds-from-one-source.md (the Free build is what the directory hosts)
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8: nothing leaves before consent; D9: the support forum)
- naming — docs/brand.md, § Naming (the slug is a one-way door)
- data sheet — docs/distribution.md, § WordPress.org plugin directory, § Launch plan row 0
- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (the repository against the directory's practices, 2026-10-24)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (the 18 guidelines)
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/ (submission and review)
- external — https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/ (two-factor sign-in; the submission scan)

## Notes

- Decision (owner, 2026-10-07): filed at the delivery-pipeline inception. `tickets.py` cannot express a backlog → epic prerequisite, so this line carries it: this story waits on entry 4.8 (the public repository the readme links, and the release that is submitted), and through it on chore 87. Checked 2026-10-29: `tickets.py` refuses `"4.8"` and `"epic-delivery-pipeline"` in a backlog ticket's `after`.
- Decision (owner, 2026-10-08, `/prioritize`): story 41 (the asset set) and story 9 (the tooling of the pre-production pass) join `after`.
- Decision (owner, 2026-10-09, `/prioritize`, ADR 0013 D1): 44, 46 and 47 join `after`; real licensing gates the listing and entry 4.8.
- Decision (owner, 2026-10-09, ADR 0014): two builds from one source; the directory hosts the Free build; story 54 joins `after`.
- Decision (2026-10-12/13, story 58): the Freemius upload and the fetch of both generated builds are story 58's; the submitted build is the checked Free build of the release run, so 58 joins `after`. The deploy that reads it is story 96's since 2026-10-29.
- Decision (owner, 2026-10-24, the audit beside this file): story 88 and chore 89 join `after`; story 91 split from 88 on 2026-10-26. Since 2026-10-29 the audit's D2, D3, D6 and D8 live in story 95, and D4, D5 and the deploy settings in story 96.
- Decision (owner, 2026-10-27): story 9 is done with its tooling; the pre-production pass runs inside this story, before submission (criteria 1–3). Story 9 stays in `after` for that tooling.
- Decision (owner, 2026-10-28; ADR 0014 amended): the switch of the GitHub Release asset to the Free build is entry 4.8's, at 4.0.0.
- Decision (owner, 2026-10-29, Q1 A): story 38 splits in three. Story 95 takes the pre-split AC1–AC3 and AC4's second half (the directory's rules) and ships first, before entry 4.8's release PR. This story keeps the pre-split AC5 (identity, now the submission's criterion 4), AC6 (the freemium rules, now criterion 1) and the pre-production pass of 2026-10-27. Story 96 takes the pre-split AC7 (the deploy and the updates), and story 92 moves after 96. 95 joins `after`.
- Decision (owner, 2026-10-29, Q2 A): the pass covers both builds of the release, and every plan's manual checks that still describe shipped behaviour. A check of retired behaviour is listed with the ticket that retired it. About 69 plans name manual checks (2026-10-29).
- Decision (owner, 2026-10-29, Q5 A): each fix for a review-team finding ships in a patch release, and the reply carries that release's checked Free build.
- Decision (owner, 2026-10-29, Q6 A): story 70 (the Add Plugins card and More Details preview) comes before submission and joins `after`.
- Decision (owner, 2026-10-29, Q9 A): risk high (was medium). The slug can never change after approval. The check outside the criteria: the owner reads the slug the form shows before submitting, and the approval email names it.
- State of `after` on 2026-10-29 (`tickets.py status`): 9, 10, 15, 41, 44, 46, 47, 54, 65, 88, 89, 91 done. 58 in progress: its AC3 re-run waits for the first release after the GitHub Actions minutes reset on 2026-10-30; 3.10.0 is not released yet. 95 and 70 not started. Entry 4.8 and chore 87 not started.
- Fact (WordPress.org, read 2026-10-29): since 2024-10-01 every plugin owner and committer account needs two-factor sign-in, and the submission form runs Plugin Check's Plugin Repo category, blocking error-level findings; it does not replace the manual review.
- Handoff: entry 4.8's install check (its step 5) may use the same clean site. The pass then starts from a fresh WordPress 6.6 install, or from the plugin deleted by an administrator, so nothing of that check remains. Story 96's update check reuses the site after approval.
- Decision (story 118, 2026-11-09): the clean site runs PHP 8.1, the plugin's floor (ADR 0018); Local offers 8.1 builds and no 8.0 build. How it gets to WordPress 6.6 (WP-CLI's `core update --version=6.6 --force` is one way) is settled when the pass is prepared (`docs/local-verification.md`, one-time setup).
- Open question (for the build): how the Pro walk gets its sandbox license. Story 44's pass used a sandbox purchase on the account's address. Every license, purchase and install the pass creates is listed and removed afterwards (owner rule 2026-10-20, chore 81).
- Open question (at submission): the slug is still free; `Tested up to` is the current major; the tag "content quality" is shared by other plugins (the directory hides unique tags); the data sheet's CHECK markers.
- hitl (owner), in order:
  1. Create the clean WordPress 6.6 site in Local (a name no other Local site carries, PHP 8.1, MySQL), before entry 4.8's install check if that check uses it, and start it for each walk. Look at the plugin's pages in the browser; look and feel stays the owner's pass.
  2. Turn on two-factor sign-in on the `postquality` WordPress.org account, if it is not on.
  3. Submit the Free build zip through the directory's add-plugin form, signed in as `postquality`, and check the slug the form shows.
  4. Answer the review team's emails from the account's address. The agent drafts the answers and files each finding as a ticket.
- Records at done: `docs/distribution.md` § Launch plan row 0 (WordPress.org: submitted, approved, with dates) and the slug row (approved); the data sheet rows that name "story 38" for the account and the tags, checked; `memory/delivery-pipeline.md` (its line "WordPress.org distribution is backlog story 38" names the three stories).
- `after` (2026-10-29): today's list plus 95 and 70, as the owner set it at the split. Several entries are also reached through 95 (9, 41, 46, 47, 54, 91 and what they wait on); they stay as the record of why each joined.
- Dropped (overtaken, 2026-10-29):
  - "The version appears in one more place CI keeps in step" (old AC2): done 2026-10-13; release-please bumps `Stable tag`, and Row 7 checks it.
  - "Freemius SDK integration out of scope unless the review requires it" (old Boundaries): story 44 integrated the SDK.
  - "Deployed to its SVN from the GitHub release" and "deploys the zip contents" (old Description, AC7): the deploy takes the Free build Freemius generated, from the release run (ADR 0014, story 58); story 96 owns it.
  - The switch of the GitHub Release asset: entry 4.8's (ADR 0014 amended 2026-10-28).
  - Plugin Check "in WP Admin" (old AC1): WP-CLI on a Local site (`docs/local-verification.md`, Other checks); story 95 owns it.
  - The GPL check as a criterion (old AC4 first half) and old AC5's trademark wording: already met (audit #1, #12). Story 95's Boundaries keep the licence; criterion 4 keeps AC5's name and slug check at approval; AC5's `.wordpress-org/` folder went to story 96.
  - Story 15's release notes feeding the readme Changelog: D8 replaced them with one short entry. Story 15 stays a done prerequisite.
  - The open question on Plugin Check in CI: answered by entries 4.4 and 4.5 and story 54's Free build job.
  - The 2026-10-09 finding on "visible-but-locked": answered by ADR 0014.
  - The bearer-token fetch in story 54's inputs: story 58 replaced it.
  - The delivery-pipeline spec reference (CAP-2, CAP-3): the readme has been in the zip and versioned since 2026-10-13.
- Refined 2026-10-29 (owner approved the nine defaults of the split; text reviewed in the PR).
