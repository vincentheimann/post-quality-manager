---
id: 95
type: story
title: "The Free build meets the directory's rules before submission"
parent: none
covers: [UV7]
after: [9, 41, 46, 47, 54, 91]
assignee: ""
refined: true
hitl: true
risk: medium
---

# The Free build meets the directory's rules before submission

## Description

The Free build passes what the WordPress.org review team and the directory's submission scan check, before the release that goes to WordPress.org is cut. Plugin Check finds no error and only the accepted warnings. The readme is complete, valid and under 10 KB. Every external service is disclosed in the readme and in WordPress's privacy policy guide. This is the first part of the WordPress.org listing: story 38 submits the build, and story 96 deploys each release once the listing is approved. The owner starts the showcase site in Local for the two checks that need a WordPress, and runs the readme validator if the agent cannot reach it (Notes, hitl).

## Acceptance Criteria

1. **Plugin Check finds no error, and only the accepted warnings**
   **Given** the Free build of the branch
   **When** Plugin Check runs on it with every category, the Plugin Repo category the directory's submission form runs included, and with the vendored Freemius SDK included
   **Then** it reports no error
   **And** every warning left is an accepted one, its reason recorded in the plan: the direct queries and the missing cache on the plugin's own reviews table (entry 5's W1, W2), the direct call that checks an Anthropic key once during setup (D3), and any inside the unmodified Freemius SDK
   **And** no warning names the translations-folder header or the translation loader (D2)
2. **The readme passes the validator, with every section the directory expects**
   **Given** the readme in the Free build
   **When** the WordPress.org readme validator checks it
   **Then** it passes, and it holds Description, Installation, FAQ, External services, Screenshots and Changelog
   **And** the FAQ says the plugin is single-site only, what Free includes and what Pro adds; the Changelog holds the current release only, in two or three lines, with a link to the GitHub Releases, and there is no Upgrade Notice (D8)
3. **The readme stays under 10 KB**
   **Given** the readme
   **When** the harness sweep runs
   **Then** it passes while the file is under 10 KB, and fails, naming the size, once the file reaches 10 KB
4. **The source of the minified admin app is one link away**
   **Given** the readme in the Free build
   **When** a reviewer looks for the admin app's source (guideline 4)
   **Then** the readme links the public repository, and the repository's README says how to build the admin app from its source
5. **Every external service is disclosed, in the readme and in the privacy guide**
   **Given** the readme and every request the Free build and its workflow make
   **When** the two are compared
   **Then** the External services section names each service contacted: the user's n8n instance, the AI provider the workflow calls, the notification address the user sets, the plugin's one-time key check with Anthropic, and Freemius
   **And** for each it says what is sent and when, and its terms and privacy links open
   **And** an administrator finds the plugin's suggested privacy-policy text in WordPress's privacy policy guide (Settings → Privacy), stating the same facts

## Boundaries

- Must not change: Review, Key Takeaways and every other behaviour of either build. A site holding a language pack for the plugin still shows the plugin's PHP strings and its admin app in that language after D2. The code and stored names (stories 88, 91). The readme header values the data sheet lists: name, tagline, tags, `Requires at least` 6.6, `Requires PHP` 8.0, `Contributors` = `postquality` only (D6), `Stable tag` as release-please sets it. GPL-2.0-or-later for every shipped file. The release job's comparison of Freemius's Free build with the local strip passes unchanged.
- Out of scope: translation content (language packs come from translate.wordpress.org); a rewritten Description (story 60, Q7 A); the pre-production pass and the submission (story 38); the deploy, the listing's images and the `.wordpress-org/` folder (story 96); a Free vs Pro table in the README.

## References

- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (rows #2–#4, #15–#18, #24, #25, #30; D2, D3, D8)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38: submits this build)
- data sheet — docs/distribution.md, § WordPress.org plugin directory (the readme header and section tables)
- disclosure draft — docs/policies/readme-external-services.md (story 46: the External services block and the suggested privacy text)
- accepted warnings — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/story-the-plugin-passes-plugin-check-and-the-check-blocks-ci-plan.md, § Warnings (W1, W2 stay; W6, W7 resolve here)
- naming — docs/brand.md, § Naming (the tagline; "PQM" never reaches a user)
- decision — docs/adr/0014-two-builds-from-one-source.md (the Free build is what the directory hosts)
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8: nothing leaves before consent)
- walk — docs/local-verification.md, § Other checks (Plugin Check through WP-CLI on a Local site)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (guidelines 4, 6, 7)
- external — https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/ and the validator https://wordpress.org/plugins/developers/readme-validator/
- external — https://developer.wordpress.org/plugins/wordpress-org/common-issues/ (readme, external services)
- external — Plugin Check https://wordpress.org/plugins/plugin-check/
- external — https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/ (the submission scan)

## Notes

- Decision (owner, 2026-10-29, story 38 split, Q1 A): this story takes the pre-split story 38's AC1 (Plugin Check), AC2 (the readme), AC3 (external services) and AC4's second half (the source of the minified bundle). It lands before entry 4.8's release PR, so the 4.0.0 Free build is the one story 38 submits. `tickets.py` cannot express a backlog ticket before an epic entry, so this line carries it.
- `after` (2026-10-29): 9 (the Local tooling that runs Plugin Check with `vendor/` included and shows WordPress's privacy policy guide), 41 (the readme's Screenshots and the copy sweep), 46 (the disclosure draft), 47 (the data sheet's readme fields), 54 (the Free build), 91 (the prefix, so Plugin Check runs its prefix rules; through 91, story 88 and chore 89's SDK 2.13.5). All done.
- Decision (owner, 2026-10-24, audit D2): the `Domain Path` header and the `load_plugin_textdomain()` loader go. Directory installs get language packs from translate.wordpress.org. This reverses the PHP half of epic-delivery-pipeline entry 6.
- Decision (owner, 2026-10-24, audit D3): the one-time key check to the Anthropic API stays, as an accepted warning. `wp_ai_client_prompt()` needs WordPress 7.0, above the 6.6 floor, and the call checks a key; it sends no prompt.
- Decision (owner, 2026-10-24, audit D8): the Changelog is one short entry with a link to GitHub Releases; no Upgrade Notice. Story 15's upgrade notes speak to 3.x upgraders, who do not exist in the directory.
- Decision (owner, 2026-10-29, Q7 A): the readme's Description stays as story 41's copy sweep left it. Story 60's rewrite ships with a later release (D4, story 96).
- Decision (owner, 2026-10-29, Q8 A): the readme links the public repository; `src/` does not ship in the zip. The repository keeps its name on the fresh public repository (chore 87 AC4), so the link is written now and opens once entry 4.8 makes it public. Story 38 checks it opens. Today's README has no build steps (read 2026-10-29); this story adds them.
- Fact (Plugin Check 2.1.0 on the showcase, Free build, 2026-10-26, after story 91): 0 errors, 22 warnings: 19 on the reviews table (W1, W2), the `Domain Path` header, the loader, and `AIProvider.DirectIntegration`. Story 88's `PrefixAllGlobals` warnings on core's `the_content` filter and story 91's `SlowDBQuery` carry justified ignores since (read in the code 2026-10-29). Expected after D2: 20, in CI, which skips `vendor/`.
- Open question: what Plugin Check reports inside `vendor/` (not recorded yet, audit #15). The SDK stays unmodified (chore 89): a warning there is recorded with its reason. An error there goes to the owner before the build continues, since the submission scan would block it.
- Fact (WordPress.org, read 2026-10-29): the submission form runs Plugin Check's Plugin Repo category and blocks error-level findings; it does not replace the manual review.
- Consumers of D2 (lesson 2026-10-13), changed together: the main file's header; the loader on `init`; the admin app's script-translations path; `release-header-harness.php` Row 4, which asserts the header and the loader; `tests/i18n/README.md`, whose site check copies the fixtures into the plugin's `languages/` (a language-pack folder after D2); the data sheet's "Text Domain / Domain Path" row; the entry 4.6 line in `memory/delivery-pipeline.md`.
- Consumers of the readme (lesson 2026-10-13): the directory (validator, 10 KB, sections); Plugin Check's readme checks (`Stable tag` = `Version`, `Tested up to` = the current major); `release-header-harness.php` Row 7; release-please's `Stable tag` markers; Freemius, which refuses a zip without a readme; the release job's Free build comparison, which reads the readme byte for byte except `Contributors`, so a `fs_premium_only` block would also need `bin/build-free.php` to strip it; story 70's preview.
- Consumers of the disclosure text: `docs/policies/readme-external-services.md` stays in step with the readme. `docs/policies/privacy.md` and the site's `/privacy/` copy change only if a fact changes; the site copies `docs/policies/`. README.md is read by harnesses (`caps-harness.php` Row 7 parses its capability table): the build steps go outside that table.
- Assumption: the readme carries no Pro-only text, so no `fs_premium_only` block; one FAQ answer says what Pro adds. The stripper and the release comparison stay untouched.
- Assumption: the External services text and the privacy text keep their "on Pro" clauses: one text serves both builds, and they disclose what the plugin does once Pro is installed.
- Assumption: the External services text is shortened to fit the 10 KB cap, facts kept: the draft block is about 6 KB and today's readme 2.3 KB. The n8n API calls become one paragraph. The draft lacks the notification address (audit #4, checked 2026-10-29); criterion 5 adds it.
- Assumption (audit, unverified): the Pro build gets language packs through the SDK's WordPress.org translation fetch (org-compliant freemium plugins only). No translation exists yet, so D2 loses no string today; verify it when D2 is built.
- Assumption (agent, 2026-10-29, the set's collision check): this story merges before chore 87's history rewrite starts, or on the fresh repository before entry 4.8's launch PR. A merge on the old repository after the rewrite would be lost, and the launch PR also edits README.md (its installation section; this story adds the build steps).
- Handoff: story 70 previews the readme this story completes, so 70 lists 95 in `after`; story 38 checks the readme's links open; story 96 deploys the readme as the listing's page.
- hitl (owner): start the showcase site in Local for criterion 1's Plugin Check run and criterion 5's privacy-guide check (`docs/local-verification.md`: starting a site stays the owner's step). Paste the readme into the WordPress.org readme validator, or let the agent drive it through the browser, if the agent cannot reach the validator itself (audit § 3).
- Records at done: the data sheet lines that cite story 38 for the readme (the readme header table's intro, the `Tested up to` and `Stable tag` rows' "story 38 AC2", the third-party legal links row) name this story; the story 46 line in `memory/release-readiness.md` ("story 38 pastes the readme block") names this story; `docs/brand.md` § Naming's "(story 38 AC5)" names story 38's submission criterion; the entry 4.9 line in `memory/delivery-pipeline.md` ("story 38 packages it", about a LICENSE copy in the plugin folder) is corrected, since the header, the readme and the root LICENSE meet guideline 1 (audit #1); `memory/delivery-pipeline.md` (the accepted warnings and the translations line); `docs/policies/readme-external-services.md`.
- Risk: medium (agent, 2026-10-29): the change touches a shared harness and the translation path, and a lost translation path shows only on a site with a language pack; a revert is clean.
- Refined 2026-10-29 (owner approved the nine defaults of the split; text reviewed in the PR).
