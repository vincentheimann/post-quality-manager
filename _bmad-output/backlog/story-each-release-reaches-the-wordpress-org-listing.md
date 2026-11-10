---
id: 96
type: story
title: "Each release reaches the WordPress.org listing, and sites see the update"
parent: none
covers: [UV7]
after: [58, 95, 70]
assignee: ""
refined: true
hitl: true
risk: high
---

# Each release reaches the WordPress.org listing, and sites see the update

## Description

Once story 38's listing is approved, every published release brings its checked Free build to the WordPress.org listing from the release workflow, and sites running the Free build see the update under Dashboard → Updates (UV7, and A3's update channel). The first deploy, for the version the review team approved, is started by hand. Release Confirmations hold each new version until the owner confirms it. The owner stores the SVN credentials as repository secrets and confirms each new version (Notes, hitl).

## Acceptance Criteria

1. **A release deploys its checked Free build to the listing**
   **Given** an approved listing and a published release whose Free build check passed
   **When** the release run continues
   **Then** that Free build becomes the listing's trunk and its version tag
   **And** the listing's icons, banners and screenshots are exactly the eight directory files, nothing else from the repository
2. **The approved version is deployed once by hand**
   **Given** the listing approved for a version whose release run passed its Free build check
   **When** the owner starts the deploy by hand for that version
   **Then** it deploys that run's checked Free build as in criterion 1, and creates no release and no Freemius upload
3. **An unchecked build never reaches the listing**
   **Given** a release whose Free build check failed
   **When** the release run ends
   **Then** nothing is deployed to the listing
4. **A version already in the listing is never deployed twice**
   **Given** a version already tagged in the listing
   **When** the deploy runs for it again
   **Then** it changes nothing and says the version is already there
5. **A new version reaches sites only after the owner confirms it**
   **Given** Release Confirmations on for the listing
   **When** a deploy tags a new version
   **Then** no site is offered that version until the owner confirms it from the directory's email
6. **Credentials and third-party code are controlled**
   **Given** the SVN credentials the deploy needs
   **When** the release workflow runs
   **Then** they come from repository secrets only and never appear in the repository, a log or an artifact, and every third-party step the deploy adds is pinned by commit
   **And** before approval the deploy runs as a dry run and needs no credentials
7. **Free sites get the update; Pro sites never get the Free build**
   **Given** a site running an earlier Free build and a site running the Pro build
   **When** WordPress checks for updates after a confirmed version reached the listing
   **Then** the Free site is offered that version under Dashboard → Updates
   **And** the Pro site is never offered the directory's Free build

## Boundaries

- Must not change: the release order (ADR 0014): Freemius upload, Free build check, then the listing. Setting a version Released in Freemius stays the owner's step (story 58). What the GitHub Release carries (entry 4.8). Release-please's flow, the release workflow's existing steps and the CI jobs.
- Out of scope: a workflow that updates the readme or the listing's images between releases (D4); a Live Preview blueprint (D5); the website's launched state (story 92).
- Owner only: the steps under hitl in Notes. The agent never holds the SVN password.

## References

- requirements — docs/user-paths.md, A3 (the update channel)
- prerequisite — _bmad-output/backlog/story-release-uploads-zip-to-freemius.md (story 58: the checked Free build artifact of the release run)
- prerequisite — _bmad-output/backlog/story-free-build-meets-the-directory-rules-before-submission.md (story 95: the readme and build the listing shows)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38: the approval this story's checks need)
- consumer — _bmad-output/backlog/story-postquality-com-sells-pro-through-the-live-checkout.md (story 92, day L)
- decision — docs/adr/0014-two-builds-from-one-source.md (release order)
- data sheet — docs/distribution.md, § WordPress.org plugin directory (the SVN row), § Release order, § Launch plan rows 0 and L
- assets — docs/brand.md, § Imagery and the WordPress.org assets (the eight directory files)
- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (rows #11, #33, #35–#40; D4, D5)
- skill — .claude/skills/release/SKILL.md (steps 7–9)
- external — https://github.com/10up/action-wordpress-plugin-deploy (the SVN deploy action)
- external — https://make.wordpress.org/meta/handbook/tutorials-guides/svn-access/ (the SVN password)
- external — https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/ (Release Confirmations)

## Notes

- Decision (owner, 2026-10-29, story 38 split, Q1 A): this story takes the pre-split story 38's AC7 (the deploy from the release and the update in Dashboard → Updates). It can be built and dry-run while story 38's review is open; it is done only once 38's listing is approved and the deploys have reached it. Criteria 2–7 can pass with the first deploy; criterion 1 needs the first release published after approval.
- Decision (owner delegation, best practice, 2026-10-29): story 92 (the website sells Pro on day L, the day the listing goes live) waits for this story's first deploy (criterion 2), not for the first release after approval (criterion 1). `tickets.py next` counts a prerequisite in review as met, so 92 can start once this story is built and the approved version is in the listing; this story stays in review until the first release after approval passes criterion 1, then it is done.
- `after` (2026-10-29): 58, because the deploy reads the checked Free build artifact of the release run; 95, because the readme in that build becomes the listing's page. Story 38 is not in `after`: only the checks wait on its approval (line above).
- Decision (2026-10-12/13, story 58): the deploy reads the checked Free build artifact of the same release run, since a `GITHUB_TOKEN` run starts no other workflow. Path for the build: `.github/workflows/release.yml`, a job after "Upload the Freemius Free build", or one that `needs: package` and downloads it.
- Decision (owner, 2026-10-24, audit #35, #36, #38): the deploy passes the released version itself (the action's default reads a tag ref, and the release workflow runs on a push to `main`); it deploys the unzipped Free build's `post-quality-manager/` folder (with that folder set, the action ignores `.distignore`); the action is pinned by commit; its first run on the public repository is a dry run.
- Decision (owner, 2026-10-24, audit D4): no workflow that updates the readme or assets between releases at launch. A new WordPress major gets a patch release.
- Decision (owner, 2026-10-24, audit D5): no Live Preview at launch. A Playground preview cannot reach an n8n instance.
- Decision (owner, 2026-10-29, Q3 A): the owner starts the first deploy by hand, for the version the review team approved. It reads that version's release run artifact.
- Decision (owner, 2026-10-29, Q4 A): Release Confirmations are on. With the SVN password in a public repository's secrets, nothing reaches sites until the owner confirms it, a step like Freemius's Released.
- Decision (owner, 2026-10-29, Q9 A): risk high. A released tag reaches every directory install and cannot be changed, readme included, and the SVN secret can publish to every install. The check outside the criteria: the owner reads the dry run's log before the first real deploy, and confirms each new version (Release Confirmations).
- Fact (WordPress.org, read 2026-10-29): the SVN password is separate from the account password. It is generated under the profile's Account & Security → SVN credentials; the account password still works for SVN "temporarily". Release Confirmations work on tags: each new tag waits for a committer's click on an emailed link, the email must reach the committer directly, and a released tag can no longer change.
- Fact (WordPress core): the update check matches a site's plugin by its folder and main file, so a Free build installed from a GitHub Release is offered the listing's newer version. The Pro build's folder is `post-quality-manager-premium/`, and the Freemius SDK serves its updates.
- Fix (for this story): `docs/distribution.md`'s row "SVN deploy credentials" says "the wordpress.org username and password". It becomes `SVN_USERNAME` = `postquality` and `SVN_PASSWORD` = the SVN password.
- Assumption: the eight directory files move from `assets/` into `.wordpress-org/`, one copy with no drift. `docs/brand.md` § Imagery and `docs/distribution.md` follow the new paths. `assets/` keeps `src/`, the lockups, `icon-512x512.png` and `portfolio/`.
- Assumption: the README's "Install from WordPress.org" link and the support forum link in the issue forms' config land with the first deploy (audit #41, #42); a Free vs Pro table stays out.
- Assumption (agent, 2026-10-29, the set's collision check; for the owner to confirm): this story is built after entry 4.8's launch PR, because both change the release workflow (the launch PR moves the publish step after the Free build check), and the dry run runs on the public repository. `tickets.py` cannot express that, so this line carries it. It is also built after story 70, because 70's approved changes can touch the directory images this story moves; 70 joined `after` (owner, 2026-11-10, at story 70's `validate.md` check).
- Assumption: criterion 7 runs on the clean site story 38's pass used, with an earlier Free build installed, and on a site holding the Pro build of the same release.
- Open question (for the build): how the deploy tells a dry run from a real one, so a release on the public repository before approval runs it without an SVN repository (a repository variable, or the SVN secrets being present).
- Open question (for the build): the release run's artifact is kept 90 days. If the approval comes later, the manual deploy needs another source; a re-run of that release's `package` job fetches the Free build from Freemius again (story 58 AC3).
- Consumers of the deploy: `/release` steps 7–9 add the confirmation step; `docs/distribution.md` § Release order names the listing after the Free build check; chore 87 AC7 compares the release workflow's secrets, and the SVN pair is created on the public repository by this story, not copied.
- hitl (owner), in order:
  1. After story 38's approval: generate the SVN password (Account & Security → SVN credentials) and store `SVN_USERNAME` and `SVN_PASSWORD` as secrets of the public repository.
  2. Turn on Release Confirmations for the listing, start the first deploy by hand, and confirm each new version from the emailed link.
- Records at done: `docs/user-paths.md` A1 (the listing is live) and A3 (the update channel); the initiative Notes' parked update-channel line, closed; `docs/distribution.md` (the SVN row, § Release order, the asset rows and the open marker that say "story 38 uploads", launch plan row 0); ADR 0014's "story 38 AC7" and `docs/brand.md` § Imagery's "story 38's deploy" name this story; `.claude/skills/release/SKILL.md` step 9 ("story 38"); `memory/delivery-pipeline.md`.
- Refined 2026-10-29 (owner approved the nine defaults of the split; text reviewed in the PR).
