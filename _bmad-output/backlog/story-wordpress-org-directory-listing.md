---
id: 38
type: story
title: "The plugin is accepted in the WordPress.org plugin directory"
parent: none
covers: [UV7]
after: [10, 15, 41, 9, 44, 46, 47, 54, 58, 65, 88, 89, 91]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The plugin is accepted in the WordPress.org plugin directory

## Description

UV7 proper (A1–A2: discoverability and the update channel). Once the plugin has an identity (story 10: name, slug, tagline, wording rules; story 41: icon, banner, screenshots, copy sweep) and CI-cut releases (`epic-delivery-pipeline`), it is submitted to the WordPress.org plugin directory and, after approval, deployed to its SVN from the GitHub release. The free tier (Review + Key Takeaways, ADR 0003) is what the directory hosts; Pro stays a hosted license. Everything the plugin review team checks is listed below so the gaps close before submission, not during a weeks-long review loop.

## Acceptance Criteria

1. **Plugin Check is clean**
   **Given** the release zip
   **When** the official Plugin Check (PCP) runs on it, in CI and in WP Admin
   **Then** it reports no errors (escaping, sanitizing, `$wpdb->prepare`, i18n, enqueueing, direct file access guards, prefixing)
   **And** the only warnings left are the accepted ones, each with its reason in the plan: the direct queries on the custom table (entry 5's triage) and `AIProvider.DirectIntegration` on the one-time key check (D3); the `Domain Path` header and the textdomain loader are gone (D2); one WP Admin run on the Free zip includes `vendor/` and its result is recorded
2. **readme.txt in the directory format**
   **Given** `post-quality-manager/readme.txt`
   **When** validated with the WordPress.org readme validator
   **Then** it passes: short description ≤ 150 chars, `Stable tag` = the release version (never `trunk`), `Tested up to` = the current WordPress major, `Requires at least` 6.6, `Requires PHP` 8.0, `Contributors` = `postquality` only (D6), Description, Installation, FAQ, Screenshots, Changelog, and the version appears in one more place CI keeps in step (epic 4 CAP-3)
   **And** the Changelog holds the current release only, in two or three lines, with a link to GitHub Releases for the rest, and there is no Upgrade Notice (D8); the file stays under 10 KB, which a harness asserts
3. **External services are disclosed**
   **Given** the readme
   **When** the review team reads it
   **Then** an "External services" section names every service the plugin or its workflow contacts: n8n (the user's own instance), the AI provider(s) the workflow calls, the plugin's own one-time key check to the Anthropic API, and Freemius; it says exactly what is sent (post title, content, excerpt, Yoast meta, settings text, the notification address), when, and links each service's terms and privacy policy, starting from `docs/policies/readme-external-services.md`; the plugin suggests privacy-policy text via `wp_add_privacy_policy_content()`
4. **GPL and human-readable code**
   **Given** the zip
   **When** audited
   **Then** every file and asset is GPL-compatible; the minified `admin/build/` is accompanied by the `src/` folder in the zip or by a readme link to the public repository and build steps
5. **Identity and trademarks**
   **Given** story 10's identity
   **When** the slug, name and assets are submitted
   **Then** the slug is `post-quality-manager`, the name contains no third-party trademark (no "WordPress", "Claude", "n8n" in the name), the copy claims no affiliation, and the SVN `assets/` is fed from a `.wordpress-org/` folder in the repository holding exactly the eight directory files (`icon-128x128.png`, `icon-256x256.png`, `icon.svg`, `banner-772x250.png`, `banner-1544x500.png`, `screenshot-1..3.png`, numbered to match the readme); nothing else from the repository's `assets/` is published
6. **Freemium rules**
   **Given** the Free build (story 54)
   **When** the review team installs it
   **Then** Review and Key Takeaways work fully, no Pro code, command or template branch is present, the one "Get Pro" section on the Settings page is the only upsell (no notice, toast or widget), and no data leaves the site except the user-configured n8n call and the Freemius opt-in after consent
7. **Deployed from the release**
   **Given** an approved listing
   **When** a GitHub Release is published
   **Then** a workflow deploys the zip contents to the SVN `trunk` + `tags/<version>` and the `assets/` folder, and installed sites see the update in Dashboard → Updates (this retires the parked "update channel" question)
   **And** the deploy step names its version and its folder: `VERSION` from the release run, since the action's default reads a tag ref and `release.yml` runs on a push to `main`; `BUILD_DIR` = the unzipped Free build's `post-quality-manager/` folder, since with it set the action ignores `.distignore` and `.gitattributes`; the action is pinned by SHA and its first run on the fresh public repository (chore 87) is a dry run

## Boundaries

- Must not change: the feature gate (ADR 0003), the stored settings keys (story 91 renames them before this story; this story keeps the names it finds).
- Out of scope: translations content (language packs come from translate.wordpress.org once listed — bundled `languages/` becomes unnecessary for directory installs); Freemius SDK integration (deferred-work) unless the review requires the license flow to be visible; a workflow that updates the readme or assets between releases (D4); a Live Preview blueprint (D5).

## References

- requirements — docs/user-paths.md, A1–A2 (UV7) and A3 (update channel)
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (GitHub Releases first, WordPress.org afterwards, same gate)
- decision — docs/adr/0014-two-builds-from-one-source.md (the Free build is what the directory hosts; AC6 and AC7 read from it)
- spec — _bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md (CAP-2 zip, CAP-3 versions; this story adds `readme.txt` to both)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (the 18 guidelines)
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/ and the readme validator https://wordpress.org/plugins/developers/readme-validator/
- external — Plugin Check: https://wordpress.org/plugins/plugin-check/ and the CI action https://github.com/WordPress/plugin-check-action
- external — SVN deploy action: https://github.com/10up/action-wordpress-plugin-deploy
- audit — _bmad-output/backlog/story-wordpress-org-directory-listing-audit.md (the repository against the directory's practices, 2026-10-24: 43 rows, D1–D8)
- external — https://developer.wordpress.org/plugins/wordpress-org/common-issues/ (prefixes, libraries, readme)

## Notes

- Decision (owner, 2026-10-07): filed during the delivery-pipeline inception; blocked by story 10 (identity) and story 15 (release notes feed the readme changelog); also needs `epic-delivery-pipeline` done (the zip and the version check) — tickets.py cannot express a backlog → epic prerequisite, so this line carries it.
- Decision (owner, 2026-10-08, `/prioritize` D1/D6): also waits on story 41 (the asset set AC5 needs) and story 9 (the agent re-run of every manual check is the pre-production gate before submission).
- Open question: whether to run Plugin Check in CI already inside `epic-delivery-pipeline` (recommended: yes, as a non-blocking report first, blocking once clean) — settle at that epic's inception.
- Open question: `src/` inside the zip or a readme link to the repo for the minified bundle — the review team accepts either; the link keeps the zip small.
- Inputs filed 2026-10-09 (release-readiness pack): story 47 (the distribution data sheet: `Contributors`, tags, `Donate link`, `Tested up to`, the SVN secret name) feeds AC2 and AC5; story 46 (policies) feeds AC3's External services and privacy text; story 44 (real licensing) decides what AC6's "without a license" means; story 45 gives `Plugin URI` a home other than GitHub. `/prioritize` decides which of them join `after`.
- Decision (owner, 2026-10-09, `/prioritize`): 44, 46 and 47 join `after`. Real licensing gates this listing, not entry 4.8: a public GitHub release reaches almost nobody (A1), the directory is where real users come from, so the stub license must be gone before submission. Spike 43's D1 can still pull 44 ahead of 4.8.
- Finding (agent, 2026-10-09): AC6 "Pro features are visible-but-locked" contradicts guideline 5 (no locked functionality in a directory plugin) — see story 44's Notes for the quotes and the proposed two-build model; AC6 is rewritten once the owner decides.
- Decision (owner, 2026-10-09, spike 43, ADR 0013): D1 pulled story 44 ahead of entry 4.8 too, so the stub license never reaches a public release; this listing's AC6 is checked against real Freemius licensing.
- Decision (owner, 2026-10-09, ADR 0014): two builds from one source; AC6 rewritten (the Free build has no Pro code, one upsell), the one-zip boundary dropped, story 54 joins `after`. AC7's SVN deploy pushes the Freemius-generated Free build, not the GitHub Release zip; the Plugin Check open question is answered by story 54 (CI checks the Free build).
- Inputs from story 54 (2026-10-12): `readme.txt` (created here) wraps Pro-only text in `[//]: # fs_premium_only_begin` / `[//]: # fs_premium_only_end` (Freemius strips it from the Free readme). AC7's SVN deploy takes the Free build Freemius generated, fetched with a product-scoped bearer token kept as a GitHub secret (`GET /products/41144/tags/{tag_id}.zip?is_premium=false`; `POST …/tags.json` uploads, `PUT …/tags/{tag_id}.json` with `release_mode` releases); the manual order is `docs/distribution.md` § Release order. CI's `free-build` job already runs Plugin Check on the locally stripped build.
- Hand-off (2026-10-12, story 58 filed by the owner): the Freemius upload and the fetch of the generated Free and Pro builds move to story 58, which leaves them as artifacts `post-quality-manager-<version>-free.zip` / `-pro.zip` of the release run; this story keeps the SVN deploy (AC7, reading that artifact from the same run, since a `GITHUB_TOKEN` run starts no other workflow) and the switch of the GitHub Release asset to the Free build at the first directory release (ADR 0014). This supersedes the bearer-token fetch named in the story 54 inputs line above; story 58 is added to `after` at refinement. Added 2026-10-13 when story 58 was built: the SVN job runs in `release.yml` after the `package` job's step "Upload the Freemius Free build" (or as a job that `needs: package` and downloads that artifact).
- Input (2026-10-13): `post-quality-manager/readme.txt` exists as a minimal file (headers, tagline, a short Description), because Freemius refuses any zip without it. This story completes it (External services, Installation, FAQ, Screenshots, Changelog, `fs_premium_only` blocks) and keeps `release-header-harness.php` Row 7 green; the `no_plugin_readme` ignore is already gone from CI.
- Input (2026-10-16, story 70 filed by the owner): the Add Plugins card and its More Details view are previewed on Local from the real readme, header and assets and approved by the owner before this story submits; story 70 also rewrites the plugin header `Description` to the tagline rules. `/prioritize` decides whether 70 joins `after`.
- Decision (owner, 2026-10-24, the directory-practices audit beside this file): the audit's gaps that belong here are in AC1, AC2, AC3, AC5 and AC7 above; story 88 (prefix rename) and chore 89 (Freemius SDK 2.13.5) join `after`. The owner took the audit's recommendations:
  - D2: the `Domain Path` header and the `load_plugin_textdomain()` loader go in this story. Directory installs get language packs from translate.wordpress.org. This reverses the PHP half of epic-delivery-pipeline entry 6.
  - D3: the one-time key check to the Anthropic API stays, as an accepted Plugin Check warning. The suggested `wp_ai_client_prompt()` needs WordPress 7.0, above the 6.6 floor, and the call checks a key; it sends no prompt.
  - D4: no workflow that updates the readme or assets between releases at launch. A new WordPress major gets a patch release.
  - D5: no Live Preview at launch. A Playground preview cannot reach an n8n instance.
  - D6: `Contributors` is `postquality` only; the API upload is the SVN source, so Freemius adds nothing.
  - D8: the first listing's Changelog is one short entry with a link to GitHub Releases; no Upgrade Notice. Story 15's upgrade notes speak to 3.x upgraders, who do not exist in the directory.
- Assumption (audit, unverified): the Pro build also receives the language packs through the Freemius SDK's WordPress.org translation fetch, which only org-compliant freemium plugins get. Verify it when D2 is built; if it does not hold, the Pro build's translations need another path.
- Decision (owner, 2026-10-27): story 9 is done with its tooling (PR #199); the pre-production pass it carried runs inside this story, before submission. On a clean WordPress 6.6 site the owner creates in Local (hitl), the release that goes to WordPress.org installs and activates with `WP_DEBUG` on and no notices (delivery-pipeline entry 4.1's check), then an agent runs the walk in `docs/local-verification.md` and every story plan's manual checks on it. Story 9 stays in `after` for that tooling.
- Decision (owner, 2026-10-28; ADR 0014 amended): the switch of the GitHub Release asset to the Free build moves to entry 4.8 and happens at the first public release (4.0.0), not at the first directory release. This story keeps the SVN deploy (AC7), which reads the same checked Free build.
