---
id: 38
type: story
title: "The plugin is accepted in the WordPress.org plugin directory"
parent: none
covers: [UV7]
after: [10, 15]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The plugin is accepted in the WordPress.org plugin directory

## Description

UV7 proper (A1–A2: discoverability and the update channel). Once the plugin has an identity (story 10: name, slug, icon, banner, screenshots, copy) and CI-cut releases (`epic-delivery-pipeline`), it is submitted to the WordPress.org plugin directory and, after approval, deployed to its SVN from the GitHub release. The free tier (Review + Key Takeaways, ADR 0003) is what the directory hosts; Pro stays a hosted license. Everything the plugin review team checks is listed below so the gaps close before submission, not during a weeks-long review loop.

## Acceptance Criteria

1. **Plugin Check is clean**
   **Given** the release zip
   **When** the official Plugin Check (PCP) runs on it, in CI and in WP Admin
   **Then** it reports no errors (escaping, sanitizing, `$wpdb->prepare`, i18n, enqueueing, direct file access guards, prefixing)
2. **readme.txt in the directory format**
   **Given** `post-quality-manager/readme.txt`
   **When** validated with the WordPress.org readme validator
   **Then** it passes: short description ≤ 150 chars, `Stable tag` = the release version (never `trunk`), `Tested up to` = the current WordPress major, `Requires at least` 6.6, `Requires PHP` 8.0, Description, Installation, FAQ, Screenshots, Changelog (fed from the release notes), and the version appears in one more place CI keeps in step (epic 4 CAP-3)
3. **External services are disclosed**
   **Given** the readme
   **When** the review team reads it
   **Then** an "External services" section names n8n (the user's own instance) and the AI provider(s) the workflow calls, says exactly what is sent (post title, content, excerpt, Yoast meta, settings text), when, and links each service's terms and privacy policy; the plugin suggests privacy-policy text via `wp_add_privacy_policy_content()`
4. **GPL and human-readable code**
   **Given** the zip
   **When** audited
   **Then** every file and asset is GPL-compatible; the minified `admin/build/` is accompanied by the `src/` folder in the zip or by a readme link to the public repository and build steps
5. **Identity and trademarks**
   **Given** story 10's identity
   **When** the slug, name and assets are submitted
   **Then** the slug is `post-quality-manager`, the name contains no third-party trademark (no "WordPress", "Claude", "n8n" in the name), the copy claims no affiliation, and `assets/` holds `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png`, `banner-1544x500.png` and numbered screenshots matching the readme
6. **Freemium rules**
   **Given** a free site
   **When** the review team installs the plugin without a license
   **Then** Review and Key Takeaways work fully, Pro features are visible-but-locked without nagging notices, and no data leaves the site except the user-configured n8n call
7. **Deployed from the release**
   **Given** an approved listing
   **When** a GitHub Release is published
   **Then** a workflow deploys the zip contents to the SVN `trunk` + `tags/<version>` and the `assets/` folder, and installed sites see the update in Dashboard → Updates (this retires the parked "update channel" question)

## Boundaries

- Must not change: the feature gate (ADR 0003), the one-zip rule, the stored settings keys.
- Out of scope: translations content (language packs come from translate.wordpress.org once listed — bundled `languages/` becomes unnecessary for directory installs); Freemius SDK integration (deferred-work) unless the review requires the license flow to be visible.

## References

- requirements — docs/user-paths.md, A1–A2 (UV7) and A3 (update channel)
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (GitHub Releases first, WordPress.org afterwards, same gate)
- spec — _bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md (CAP-2 zip, CAP-3 versions; this story adds `readme.txt` to both)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (the 18 guidelines)
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/ and the readme validator https://wordpress.org/plugins/developers/readme-validator/
- external — Plugin Check: https://wordpress.org/plugins/plugin-check/ and the CI action https://github.com/WordPress/plugin-check-action
- external — SVN deploy action: https://github.com/10up/action-wordpress-plugin-deploy

## Notes

- Decision (owner, 2026-10-07): filed during the delivery-pipeline inception; blocked by story 10 (identity) and story 15 (release notes feed the readme changelog); also needs `epic-delivery-pipeline` done (the zip and the version check) — tickets.py cannot express a backlog → epic prerequisite, so this line carries it.
- Open question: whether to run Plugin Check in CI already inside `epic-delivery-pipeline` (recommended: yes, as a non-blocking report first, blocking once clean) — settle at that epic's inception.
- Open question: `src/` inside the zip or a readme link to the repo for the minified bundle — the review team accepts either; the link keeps the zip small.
