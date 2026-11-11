# Distribution data sheet

_Every field a platform asks for before the plugin can be listed, sold or found, with its value or the owner who must supply it. One section per platform. Canon lives elsewhere: this sheet points at it and never copies a value that another file owns. Rows marked `OWNER:` are blanks only the owner can fill; rows marked `CHECK:` have a value to confirm at the moment of use. Created by backlog story 47 (2026-10-09); stories 38, 44, 45 and 46 read from it and write back what they settle._

## Plugin header (`post-quality-manager/post-quality-manager.php`)

| Field | Value | Source / owner |
|---|---|---|
| Plugin Name | Post Quality Manager | docs/brand.md § Naming |
| Plugin URI | https://postquality.com (set by story 45, 2026-10-09) | ADR 0013 |
| Description | The tagline in docs/brand.md (124 chars) | done (story 41, 2026-10-24): the header carries the tagline verbatim |
| Version | release-please owns it | AGENTS.md Policy |
| Requires at least | 6.6 | memory/admin-ui-rebuild.md (WordPress floor) |
| Requires PHP | 8.1 | ADR 0018, story 118 |
| Author | Vincent Heimann | ADR 0013 D2 (private person) |
| Author URI | https://postquality.com (set by story 45, 2026-10-09) | ADR 0013 |
| License / License URI | GPL-2.0-or-later / https://www.gnu.org/licenses/gpl-2.0.html | LICENSE, entry 4.9 |
| Text Domain / Domain Path | post-quality-manager / none since story 95 (2026-10-29): translations come from the WordPress.org language packs in `wp-content/languages/plugins/`, no bundled folder, no loader | docs/brand.md § Naming; entry 4.6; story 95 (audit D2) |

## WordPress.org plugin directory (story 38)

Account and submission:

| Field | Value | Source / owner |
|---|---|---|
| wordpress.org username (readme `Contributors`, the submitting account) | `postquality` (created 2026-10-09, https://profiles.wordpress.org/postquality/, login hello@postquality.com) | story 38 |
| Plugin slug | post-quality-manager (derived from the Plugin Name at submission, checked free 2026-10-08) | docs/brand.md § Naming |
| Support forum | https://wordpress.org/support/plugin/post-quality-manager/ once listed; Free support channel, best effort | ADR 0013 D9 |
| Display name of that account (the listing's "By" line) | OWNER sets it on the profile before submission; still `postquality` on 2026-11-10. The card and More Details show the submitting account's display name, linked to its profile, never the plugin header's Author | story 70 (2026-11-10); story 38 |
| SVN deploy credentials | the wordpress.org username and password as GitHub secrets `SVN_USERNAME` and `SVN_PASSWORD` (the names the 10up deploy action reads) | story 38 AC7 creates them; never in the repository |

`readme.txt` header (`post-quality-manager/readme.txt`, a minimal file since 2026-10-13 because Freemius refuses a zip without it; story 95 completed it on 2026-10-29, under the directory's 10 KB cap; `release-header-harness.php` Row 7 checks the rows below against the plugin header, and the size):

| Field | Value | Source / owner |
|---|---|---|
| Name (first line) | Post Quality Manager | docs/brand.md § Naming |
| Contributors | the wordpress.org username above | OWNER |
| Donate link | none at launch (Pro is the paid path) | ADR 0013 |
| Tags (max 5) | `content quality`, `ai`, `editorial`, `review` (2026-10-13: `seo` dropped from the proposal, the Free build the directory hosts has no SEO agent). CHECK at submission | story 38 settles against the directory's tag rules |
| Requires at least | 6.6 | as the header |
| Tested up to | 7.1 (WordPress 7.1.3 current on api.wordpress.org, 2026-10-13); major.minor only. CHECK: re-tested on each release; a new WordPress major turns CI's Plugin Check red (`outdated_tested_upto_header`) until it is raised | story 95; `/release` |
| Requires PHP | 8.1 | as the header |
| Stable tag | the release version, never `trunk`; release-please bumps it (a generic extra file, its block markers on two unknown `Release-please:` header lines the readme parser drops) | story 95; Plugin Check fails on a mismatch (`stable_tag_mismatch`) |
| License / License URI | GPL-2.0-or-later / https://www.gnu.org/licenses/gpl-2.0.html | as the header |
| Short description (≤ 150 chars) | the tagline in docs/brand.md, verbatim | docs/brand.md § Naming |

`readme.txt` sections and where their text comes from:

| Section | Source |
|---|---|
| Description | README.md "Why this exists" and "Features", to be rewritten for site owners by story 60 (the copywriting skill; handed over from story 41's copy sweep 2026-10-24, which checked the wording rules only); demo and cost FAQ from story 49 |
| Installation | README.md installation steps; the n8n Workflow page's ordered steps (story 40) |
| FAQ | README requirements (your own n8n, an AI provider key), single-site only, what Free includes, what the AI calls cost (story 49) |
| External services | story 46's draft, shortened by story 95 (2026-10-29), which ships it: the user's own n8n instance and its API, Anthropic (the AI provider the workflow calls, and the plugin's one-time key check during setup), the notification address the user sets (what the workflow posts there), Freemius opt-in and license check; what is sent, when, the terms and privacy links. The same facts go to WordPress's privacy policy guide (`PostQuality_Admin::privacy_policy_content()`) |
| Screenshots | `assets/screenshot-1.png`, `-2.png`, `-3.png` (story 41, 2026-10-24: the postquality.com captures), one caption each in the readme's order |
| Changelog | the current release only, in one or two lines, with a link to GitHub Releases, and no Upgrade Notice (audit D8; story 95 wrote `= 4.0.0 =`, "First release on WordPress.org"). The full notes stay in docs/release-notes/<version>.md and the release-please CHANGELOG (story 15); the entry for each later release is story 96's |

Assets (`assets/` in SVN, not in the zip; story 51 produced the icon, story 41 the banners and screenshots on 2026-10-24, story 122 re-laid the banners and recaptured screenshot 2 on 2026-11-10; story 38 uploads the eight directory files below (all but `icon-512x512.png`) from a `.wordpress-org/` folder, and nothing else from the repository's `assets/`):

| File | Status |
|---|---|
| icon-128x128.png, icon-256x256.png, icon.svg | done (story 51, 2026-10-12): `assets/icon.svg`, `icon-128x128.png`, `icon-256x256.png` |
| icon-512x512.png | uploaded as the Freemius product image 2026-10-12 (owner); the postquality.com favicon is `icon.svg` with `icon-128x128.png`, wired by story 85 (site PR, 2026-10-22) in the site's `_includes/head.html` |
| banner-772x250.png, banner-1544x500.png | produced (story 41, 2026-10-24): `assets/banner-772x250.png`, `assets/banner-1544x500.png`, rendered from `assets/src/banner.html` (docs/brand.md § Imagery); approved by the owner at the story 41 PR (#186, merged 2026-10-24). Re-laid by story 122 (2026-11-10): the content at the top, clear of the name label More Details lays over the lower left |
| screenshot-1.png, screenshot-2.png, screenshot-3.png | produced (story 41, 2026-10-24): `assets/screenshot-1.png` … `-3.png`, the postquality.com captures; numbers match the readme Screenshots section. `screenshot-2.png` recaptured by story 122 (2026-11-10) with the review tabs as the plugin shows them today, the site's `review.png` replaced by the same capture |

Card preview approved 2026-11-10 (story 70): the owner reviewed the Add Plugins card and More Details on the showcase, rendered from the readme, the plugin header and the eight directory files by `bin/directory-preview.php` (recipe: `docs/local-verification.md`, "Add Plugins preview (story 70)"). Approved: the card (icon, name, tagline, new-listing values) and More Details (banner, the Description, Installation, FAQ, Changelog and Screenshots tabs, the sidebar), with two readme fixes made then (caption 2 names what the image shows; the readme's URLs are links). Open before submission: the account's display name (the "By" line, row above).

Banner and screenshot 2 approved 2026-11-10 (story 122): the owner reviewed the re-laid banner pair and the recaptured screenshot 2 in the same preview on the showcase (WordPress 7.1.4, the Free build 3.13.2). In the real More Details dialog, 772 px wide, the banner shows its 772 file at device ratio 1 and its 1544 file at ratio 2. The name label's box runs from 26, 174 to 363, 224 (CSS px), and the banner's text ends at y 168. Screenshot 2 reads "✓ Review" and "✓ Takeaways".

## Licensing provider: Freemius (story 44)

| Field | Value | Source / owner |
|---|---|---|
| Account | created 2026-10-09 on hello@postquality.com (personal address as recovery) | ADR 0013 D2 |
| Product ID / public key | 41144 / `pk_ca2d449d0ee2b01bbdbdeb9bf3789` (the public key ships in the plugin; the secret key and API token stay in the Freemius dashboard, never in the repository) | story 44 |
| Plan IDs | Free 71338 · Pro 71340, unique name `pro` | story 44: the plugin reads no plan name, any license that unlocks premium code is Pro (one paid plan) |
| Pricing IDs | USD: 1 site 97893, 5 sites 97894, unlimited 97895 · EUR: 1 site 97896, 5 sites 97897, unlimited 97898 · CHF: 1 site 97905, 5 sites 97906, unlimited 97907 (story 52) | set 2026-10-09 |
| Release plans | ON since 2026-10-28 ("Release plans to users", Plans; toast "Paid plans are now visible to your users."; owner, through Claude in Chrome): moved ahead of story 44's Local pass, because Freemius refuses every license activation while the plans are unreleased ("The paid plans are not yet released…", the API's answer to a sandbox license on the showcase), and the SDK reaches Freemius's sandbox only when `is_live` is false, which the plugin's init sets to true, so no `wp-config.php` constant avoids it. Read at the same time on Pro: "Unlimited localhost license activations (recommended)" on (the SDK's `FS_Site::is_localhost_by_address()` counts, among other host patterns, `localhost`, `127.0.0.1`, addresses ending `.local`, `.test`, `.dev`, `.staging`, `.example` and starting `local.`, `dev.`, `test.`, `stage.`, and hosts starting with `staging`, with or without a dot, SDK 2.13.5), "Block features after expiry" selected, Free and Pro not hidden. No buy button shows until postquality.com's `launched: true` | story 44 |
| Pro plan expiration behaviour | "Block features after expiry" (internal value `block_features_annually`; Plans → Pro → Features section), read back after a full reload 2026-10-09: an expired or cancelled license stops unlocking Pro (`can_use_premium_code()` false), the site returns to Free. The other two choices ("…only when paying monthly", "Keep features, only block updates and support") are not used | ADR 0013 D7; set (as the plan default) 2026-10-09 |
| Sandbox checkout | https://checkout.freemius.com/plugin/41144/plan/71340/?sandbox=true; the test purchase ran 2026-10-28 from the dashboard's own "Sandbox / Testing" link (Pro, 1 site, USD, hello@postquality.com, "Prefill Form" test card) | story 44 AC1 |
| Hosted checkout links (the site's buy buttons) | One plain link per pricing ID, no checkout script: `https://checkout.freemius.com/product/41144/plan/71340/?pricing_id=<pricing ID>&currency=<usd\|eur\|chf>`, e.g. 5 sites in CHF `…/?pricing_id=97906&currency=chf`; postquality.com keeps the nine IDs in `_data/pricing.yml` and renders the links only when `launched: true`. What Freemius's pages say (read 2026-10-23): Hosted Checkout documents the path form `https://checkout.freemius.com/product/{product_id}/plan/{plan_id}/[licenses/{number\|unlimited}/][currency/{usd\|eur\|gbp}/]` (CHF is not among its examples) and says every Buy Button API option can be passed as a URL query parameter; the Buy Button API lists `pricing_id` as the exact multi-site prices to load, marked "Use licenses param instead", and `currency` with chf among its values; its sandbox needs a `{token, ctx}` from the dashboard. Checked 2026-10-28 (Claude in Chrome for the owner, read only, plans not yet released): all nine links load Pro with the expected sites, annual price and currency, and the dashboard holds the same prices and pricing IDs. The dashboard's own "Checkout Link" (Production / Live, Sandbox / Testing) uses the path form `…/plugin/41144/plan/71340/licenses/{1\|5\|unlimited}/`, the currency as a path part for EUR and CHF and none for USD, `?sandbox=true` for testing; both forms open the same checkout, so the site's links stay. The 5-site checkout shows "49.00 x 5 sites" struck through at 245.00 and a "Multi-Site license discount (Save 59%)" of −146.00 before 99.00: kept (owner, 2026-10-28), since the struck-through amount is the real price of five single-site licenses and the line shows the pack's saving at the moment of payment. Freemius documents no way to edit the checkout's text, only `title`, `language` and CSS; its option `multisite_discount` (`true` | `false` | `'auto'`, default `'auto'`) would hide the line, passed as a URL parameter on the site's links (Freemius Checkout docs, read 2026-10-28). The test purchase through the dashboard's sandbox link ran the same day (row Sandbox checkout) | story 86 build 2 |
| Store settings | title Post Quality, URL https://postquality.com, terms https://postquality.com/terms; emails From and Support = hello@postquality.com as "Post Quality"; no privacy-URL field exists (the privacy page is linked from the site and the readme instead) | set 2026-10-09 |
| DKIM | setup started 2026-10-09; three CNAMEs to add at the registrar, then Verify in Store → Emails: `em1587` → `u1589727.wl228.sendgrid.net`, `s1._domainkey` → `s1.domainkey.u1589727.wl228.sendgrid.net`, `s2._domainkey` → `s2.domainkey.u1589727.wl228.sendgrid.net` (hosts shown without the domain suffix; add it if the registrar wants the full name) | verified on Freemius (owner, 2026-10-12) |
| Seller of record | Freemius (remits VAT and sales tax); payouts to the owner as a private person | ADR 0013 D2, D3 |
| Product name | Post Quality Manager | docs/brand.md § Naming |
| Plan name | Pro (the free tier is "Free") | ADR 0013 D10 |
| Billing | yearly, auto-renew at the same price, no renewal discount, no coupon | ADR 0013 D4 |
| Site packs and prices | 1 site 49 · 5 sites 99 · unlimited 199, per year | ADR 0013 D5 |
| Currencies | USD, EUR and CHF at the same figures (49 / 99 / 199, set 2026-10-09); CHF converts to USD at processing and is paid out from the USD balance; PayPal is not offered in CHF, card only | ADR 0013 D5, amended 2026-10-09; story 52 |
| Trial | none (the free tier is the trial) | ADR 0013 D4 |
| Money-back guarantee | 14 days, Freemius "Flexible – Double Guarantee" (no questions asked) | ADR 0013 D6 |
| Support contact | hello@postquality.com, Pro answered within 5 business days, Monday to Friday (owner, 2026-10-23; was 2) | ADR 0013 D9, amended 2026-10-23 |
| Terms / refund / privacy URLs | https://postquality.com/terms, /refund, /privacy, /support; drafts in `docs/policies/` (story 46, 2026-10-09) | story 45 publishes them |
| Third-party legal links in the readme | CHECK: the n8n, Anthropic (commercial terms, the API's) and Freemius terms and privacy URLs in `post-quality-manager/readme.txt` § External services (OpenAI and Google dropped: neither template calls them) | story 95 opened each (2026-10-29); story 38 re-checks them at submission |
| Opt-in screen | Freemius's standard screen at activation, plugin name only, skippable; nothing leaves the site before the choice (`is_org_compliant`); the license key is entered on the SDK's activation screen, or after Skip through the "Activate License" link on the Plugins screen; Account, the one SDK submenu (pricing, contact, support, affiliation and add-ons off), appears only once the install is registered (opt-in or license activation) | ADR 0013 D8; shipped by story 44 |
| SDK version and vendor folder | 2.13.5, from https://github.com/Freemius/wordpress-sdk/archive/refs/tags/2.13.5.zip, committed unmodified in `post-quality-manager/vendor/freemius/` (byte for byte: `.gitattributes` turns off line-ending normalization there); CI skips it in `php -l` and Plugin Check | story 44; 2.13.5 since chore 89 (2026-10-24) |
| Test purchase | sandbox checkout exists (row above) | story 44 AC1 |
| Fees (read 2026-10-09) | 3.8% + 50¢ plus gateway fees, plus 2.3% for WordPress products; no setup or monthly fee | ADR 0013 D3; re-read before launch |

## Release order (ADR 0014)

One source, two builds, the same version number in both: the Pro build is the CI zip, the Free build is what Freemius generates from it. The order is tag → Freemius upload of the CI zip → its generated Free build to WordPress.org SVN (story 38 AC7) → release in Freemius. Since story 58 the upload, the fetch of both builds and the Free-build checks run in `release.yml`'s `package` job, and since entry 4.8 before the GitHub Release is published (a red step leaves it a draft); the owner keeps one secret and one step (Released). `/release` steps 7–9 (`.claude/skills/release/SKILL.md`) say what to read. GitHub Releases attach the full (Pro) zip until the first public release (entry 4.8, 4.0.0), then the Free build Freemius generated (ADR 0014, amended 2026-10-28). CI's `free-build` job strips the same markers with `bin/build-free.php` on every PR, and every release compares Freemius's Free build with it (`bin/compare-php-trees.php` for PHP).

| Field | Value | Source / owner |
|---|---|---|
| Upload | automatic: the `package` job sends the CI zip to `POST /products/41144/tags.json` (multipart `file`) when the latest deployment of any release mode (`GET …/tags/latest.json?type=all`) is not that version, then `PUT …/tags/{tag_id}.json` with `release_mode: pending`. A re-run of the newest release finds its version there and uploads nothing; a re-run of an older one already on Freemius stops on its refusal (`duplicate_plugin_version`), uploading nothing either. The deployment list (`GET …/tags.json`) returned none of the product's deployments, all pending, with or without `fields` (owner's reads, 2026-11-03), so it cannot tell. By hand (only if the job cannot run): Freemius → Deployment → Add New Version, the CI zip as downloaded; a re-zip only with the OS zip or `zip -r`, never PowerShell's `Compress-Archive` (backslash paths) | story 58 (by hand: story 54) |
| Free and Pro builds | artifacts `post-quality-manager-<version>-free.zip` and `-pro.zip` of the release run (90-day retention), from `GET /products/41144/tags/{tag_id}.zip?is_premium=false` / `true`. The Free one is checked (no `__premium_only` outside `vendor/`, header `Version`, `templates/` = the Free template) and compared with `bin/build-free.php`'s output: PHP as token streams through `bin/compare-php-trees.php` (Freemius re-prints every PHP file: spacing, trailing commas, parentheses, `if (…):` → braces, string escaping, comments inside argument lists dropped — story 54's diff of 3.5.1, 2026-10-13; a variable property `->$x` printed `->{$x}`, so the source writes it that way — release 3.8.0, 2026-10-14; a close tag followed by an open tag with nothing between is dropped, so the comparer reads such a pair as the `;` it stands for — release 3.10.0, 2026-11-02), everything else byte for byte except the readme's `Contributors` line, which Freemius extended with `freemius` on 3.5.1's dashboard upload and left alone on 3.7.0's API upload (the stripper mimics the SDK's README.md and composer.json left out; the source carries the SDK init keys as Freemius writes them); a non-PHP difference is printed as a unified diff. Any difference fails the job. The Pro zip's root folder is logged | story 58; story 38's SVN deploy reads the Free artifact |
| API | `https://api.freemius.com/v1/products/41144/…`, bearer token, product scope (the only scope bearer tokens cover); endpoints confirmed in Freemius's OpenAPI spec (`https://api.freemius.com/v1/openapi.json`, read 2026-10-13) | story 58 |
| Secret | `FREEMIUS_API_TOKEN` (GitHub repository secret): the "API Bearer Authorization Token" from the product's Settings → API & Keys. Regenerating the product's secret key invalidates it; then replace the secret. Never in the repository or a log | OWNER |
| Release to customers | Freemius → Deployment → the version → Released; the job never sets `released` or `beta` | OWNER |

## GitHub repository (story 48)

| Field | Value (read 2026-10-09) | Source / owner |
|---|---|---|
| Repository | github.com/vincentheimann/post-quality-manager, public since 2026-11-11 (entry 4.8) | epic-delivery-pipeline |
| Description | the tagline in docs/brand.md (set 2026-10-09) | story 48 |
| Topics | `wordpress-plugin`, `content-quality`, `n8n`, `ai` (set 2026-10-09; topics may name integrations, the product name may not) | story 48 |
| Website | https://postquality.com (set 2026-10-09) | story 48 |
| Community files | `SECURITY.md`, `CONTRIBUTING.md`, `.github/ISSUE_TEMPLATE/` (bug, feature, config), `.github/PULL_REQUEST_TEMPLATE.md` | story 48 |
| Default branch | main, under two rulesets applied by the owner on 2026-11-11 (read back through the API): "main: no force push" (deletion and force pushes blocked, no bypass, so admins cannot force push either) and "main: CI checks" (the four checks from GitHub Actions, not strict; bypass: the repository admin role, always, for release PRs); no classic branch protection, which exempts admins by default (GitHub's docs) | story 48, applied at entry 4.8 |
| Discussions | off, and they stay off (owner, 2026-11-11) | story 48; owner decision at entry 4.8 |
| Private vulnerability reporting | on since 2026-11-11, with secret scanning and push protection (owner; read back through the API) | story 48, applied at entry 4.8; the address is hello@postquality.com (story 46) |
| Release conventions | release-please on Conventional Commits, rebase merges, `post-quality-manager-<version>.zip` per release; public since 4.0.0 (entry 4.8), the zip is the Free build (pre-releases before it) | memory/delivery-pipeline.md |

## Website: postquality.com (story 45; story 57 improves it)

| Field | Value | Source / owner |
|---|---|---|
| Domain | postquality.com, bought 2026-10-09 in the owner's name | ADR 0013 |
| Mailbox | hello@postquality.com, hosted at Infomaniak (MX, SPF, DKIM, DMARC set); receives mail, confirmed by the owner 2026-10-12 | ADR 0013 D9 |
| Hosting at launch | GitHub Pages from the public repository github.com/vincentheimann/postquality.com (Jekyll 3.10 through `github-pages` with the site's own layouts and stylesheet, no theme: `theme: null`, story 86; built by GitHub Pages on push, "deploy from a branch"; local build per the site README), custom domain set, Let's Encrypt certificate issued and HTTPS enforced 2026-10-12. Kept as the MVP; the Netlify plan of 2026-10-09 was withdrawn | story 45 (done 2026-10-12); story 57 improves it, tooling chosen then |
| Pages at launch | /, /pricing/, /docs/, /privacy/, /terms/, /refund/, /support/ — policies copied from `docs/policies/`, which stays the source of truth | stories 45 and 46 |
| DNS for the site | at Infomaniak, live 2026-10-12: apex `A` 185.199.108.153 / .109.153 / .110.153 / .111.153, `AAAA` 2606:50c0:8000::153 / 8001 / 8002 / 8003, `CNAME www → vincentheimann.github.io`; MX, SPF, DKIM, DMARC, SendGrid and Infomaniak autoconfig records untouched | story 45 |
| Site as built (story 86, live 2026-10-23) | Direction "Navy and check" (`ux-postquality-website/DESIGN.md`, `EXPERIENCE.md`, `build-brief.md`): the site's own layouts and stylesheet, lockup B as live text on the band strip, one grey family, light only, 320 px reflow floor; lean copy from the brief's copy deck; pricing with packs before the table and a CSS-only currency switch (USD first, EUR, CHF); `/docs/` with the setup guide; four real captures from a fictional demo site (Dashboard, a review, the setup guide's step 2, Post Reviews; PNG + WebP under 250 KB, captions say fictional); dark sharing card `assets/og-card.png`, per-page descriptions, canonical URLs, `SoftwareApplication` JSON-LD on `/`; both launch states behind `launched: false` in `_config.yml` (pre-launch: the interest mailto everywhere, no install or buy link; launched: install and the nine hosted checkout links); Lighthouse accessibility 100 on every page in both states; no third-party request. Site PRs #3, #4, #5, #6 (main `dba2ce6`); plugin records #176, #177, #178 (rename), #179. Still open on the site: story 49 (demo, cost example), 53/60 (messaging), 63 (interest tool), `launched: true` at the first public release | story 86 (done 2026-10-24); story 57 closed with it |

## Launch plan (story 49)

_Written 2026-10-24. Days are relative to day 0 = entry 4.8, the first public release (`epic-delivery-pipeline`), not dates: the release day is the owner's. "Day L" is the day the WordPress.org listing goes live, which the directory's review queue decides (story 38 submits on day 0; the queue has taken days to weeks). Every row names an owner and a state. Communities hold the owner's picks only; none is named or posted to until the owner names it._

| Day | Channel | What | Owner | Source | State |
|---|---|---|---|---|---|
| 0 | GitHub | Entry 4.8: the repository turns public (branch protection, private vulnerability reporting, story 48's deferred steps), the launch PR drops `prerelease`, the GitHub Release carries the release notes and the Free build as its zip (ADR 0014, amended 2026-10-28); add the public issues link to the Dashboard feedback block (story 79) | owner merges, release-please and `release.yml` do the rest | memory/delivery-pipeline.md; `/release` | planned |
| 0 | Freemius | Plans released (story 44's last step), the Free build generated from the CI zip (story 54), the Pro build served to buyers | owner (Released, the plans) | Release order above; ADR 0013, 0014 | plans released 2026-10-28, ahead of day 0 (row Release plans); the builds planned for day 0 |
| 0 | WordPress.org | Story 38 submits the Free build; the SVN deploy runs once the slug is approved | owner (the account), story 38 | WordPress.org section above | planned, after story 44 and 54 |
| L | postquality.com | `launched: true` and the five values in `_config.yml`: the hero's version line and What's new → the changelog, Install from WordPress.org, the Pro checkout links, the JSON-LD offers (story 86). No separate blog post: the site has no blog; the release notes (`docs/release-notes/<version>.md`) are the launch text | agent prepares the PR, owner merges | site README "Launch flag" | planned |
| L | Interest emails | The one message to every address that wrote "Tell me when it's available", then the addresses deleted (privacy policy, website section) | owner, from hello@postquality.com | docs/policies/privacy.md | planned; list grows until L |
| L | n8n template library | **Declined for the first release:** the shipped template carries site placeholders (`__PQM_SITE_HOST__`, per-site credential names, the plugin's webhook token header), which the library's rules exclude; the plugin's setup guide is the distribution channel for the workflow. Revisit after launch, with a placeholder-free variant if the library's rules allow a plugin-specific template | owner decides at the revisit | https://n8n.io/creators/; story 49 | declined (2026-10-24) |
| L + 1 → | Communities | The owner's picks, posted by the owner with the release notes' text and the site link; one post per community, no paid promotion (story 49 boundary) | owner | story 49 Notes; docs/messaging.md (story 53) for the pitch lines | OWNER: to name; empty until then |
| L + 7, then monthly | Signals | Read A1/A2: Freemius opt-in installs and sales (the dashboard), WordPress.org downloads and active installs (the listing), interest emails received; write the reading to docs/user-paths.md | owner reads, agent records | docs/user-paths.md A1–A2; ADR 0013 D8 | planned |

## Open markers on 2026-10-09

- OWNER: approve story 41's banners (`assets/banner-772x250.png`, `assets/banner-1544x500.png`) and screenshots (`assets/screenshot-1..3.png`), produced 2026-10-24, at the story 41 PR; story 38 uploads them. Confirm the CHF figures (story 52).
- OWNER: Freemius account; Discussions on or off. (Mail at hello@postquality.com confirmed 2026-10-12.)
- CHECK at use: `Tested up to`, readme tags, Freemius SDK version and sandbox, Freemius fees.
