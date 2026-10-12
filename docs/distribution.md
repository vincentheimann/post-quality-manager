# Distribution data sheet

_Every field a platform asks for before the plugin can be listed, sold or found, with its value or the owner who must supply it. One section per platform. Canon lives elsewhere: this sheet points at it and never copies a value that another file owns. Rows marked `OWNER:` are blanks only the owner can fill; rows marked `CHECK:` have a value to confirm at the moment of use. Created by backlog story 47 (2026-10-09); stories 38, 44, 45 and 46 read from it and write back what they settle._

## Plugin header (`post-quality-manager/post-quality-manager.php`)

| Field | Value | Source / owner |
|---|---|---|
| Plugin Name | Post Quality Manager | docs/brand.md § Naming |
| Plugin URI | https://postquality.com (set by story 45, 2026-10-09) | ADR 0013 |
| Description | The tagline in docs/brand.md (124 chars) | story 41 replaces the current header text with it |
| Version | release-please owns it | AGENTS.md Policy |
| Requires at least | 6.6 | memory/admin-ui-rebuild.md (WordPress floor) |
| Requires PHP | 8.0 | epic-delivery-pipeline entry 4.2 |
| Author | Vincent Heimann | ADR 0013 D2 (private person) |
| Author URI | https://postquality.com (set by story 45, 2026-10-09) | ADR 0013 |
| License / License URI | GPL-2.0-or-later / https://www.gnu.org/licenses/gpl-2.0.html | LICENSE, entry 4.9 |
| Text Domain / Domain Path | post-quality-manager / /languages | docs/brand.md § Naming; entry 4.6 |

## WordPress.org plugin directory (story 38)

Account and submission:

| Field | Value | Source / owner |
|---|---|---|
| wordpress.org username (readme `Contributors`, the submitting account) | `postquality` (created 2026-10-09, https://profiles.wordpress.org/postquality/, login hello@postquality.com) | story 38 |
| Plugin slug | post-quality-manager (derived from the Plugin Name at submission, checked free 2026-10-08) | docs/brand.md § Naming |
| Support forum | https://wordpress.org/support/plugin/post-quality-manager/ once listed; Free support channel, best effort | ADR 0013 D9 |
| SVN deploy credentials | the wordpress.org username and password as GitHub secrets `SVN_USERNAME` and `SVN_PASSWORD` (the names the 10up deploy action reads) | story 38 AC7 creates them; never in the repository |

`readme.txt` header (story 38 creates the file):

| Field | Value | Source / owner |
|---|---|---|
| Name (first line) | Post Quality Manager | docs/brand.md § Naming |
| Contributors | the wordpress.org username above | OWNER |
| Donate link | none at launch (Pro is the paid path) | ADR 0013 |
| Tags (max 5) | CHECK: proposal `content quality`, `ai`, `editorial`, `review`, `seo` | story 38 settles against the directory's tag rules |
| Requires at least | 6.6 | as the header |
| Tested up to | CHECK: the current WordPress major at submission, re-tested on each release | story 38 AC2; `/release` |
| Requires PHP | 8.0 | as the header |
| Stable tag | the release version, never `trunk` | story 38 AC2; CI keeps it in step |
| License / License URI | GPL-2.0-or-later / https://www.gnu.org/licenses/gpl-2.0.html | as the header |
| Short description (≤ 150 chars) | the tagline in docs/brand.md, verbatim | docs/brand.md § Naming |

`readme.txt` sections and where their text comes from:

| Section | Source |
|---|---|
| Description | README.md "Why this exists" and "Features", rewritten for site owners by story 41's copy sweep; demo and cost FAQ from story 49 |
| Installation | README.md installation steps; the n8n Workflow page's ordered steps (story 40) |
| FAQ | README requirements (your own n8n, an AI provider key), single-site only, what Free includes, what the AI calls cost (story 49) |
| External services | story 46: the user's own n8n instance and the AI provider the workflow calls (what is sent, when, their terms and privacy links); Freemius opt-in and license check |
| Screenshots | numbered files from story 41, one caption each |
| Changelog | docs/release-notes/<version>.md and the release-please CHANGELOG (story 15) |

Assets (`assets/` in SVN, not in the zip; story 51 produces the icon, story 41 the banners and screenshots):

| File | Status |
|---|---|
| icon-128x128.png, icon-256x256.png, icon.svg | done (story 51, 2026-10-12): `assets/icon.svg`, `icon-128x128.png`, `icon-256x256.png` |
| icon-512x512.png | favicon for postquality.com (story 57 wires it, the site has no custom head yet); uploaded as the Freemius product image 2026-10-12 (owner) |
| banner-772x250.png, banner-1544x500.png | OWNER: approve story 41's direction, then produced (story 41, after 51) |
| screenshot-1.png … screenshot-n.png | same; numbers match the readme Screenshots section |

## Licensing provider: Freemius (story 44)

| Field | Value | Source / owner |
|---|---|---|
| Account | created 2026-10-09 on hello@postquality.com (personal address as recovery) | ADR 0013 D2 |
| Product ID / public key | 41144 / `pk_ca2d449d0ee2b01bbdbdeb9bf3789` (the public key ships in the plugin; the secret key and API token stay in the Freemius dashboard, never in the repository) | story 44 |
| Plan IDs | Free 71338 · Pro 71340, unique name `pro` | story 44: the plugin reads no plan name, any license that unlocks premium code is Pro (one paid plan) |
| Pricing IDs | USD: 1 site 97893, 5 sites 97894, unlimited 97895 · EUR: 1 site 97896, 5 sites 97897, unlimited 97898 · CHF: 1 site 97905, 5 sites 97906, unlimited 97907 (story 52) | set 2026-10-09 |
| Release plans | OFF until story 44 ships; releasing is its last step | story 44 |
| Pro plan expiration behaviour | "Block features after expiry" (internal value `block_features_annually`; Plans → Pro → Features section), read back after a full reload 2026-10-09: an expired or cancelled license stops unlocking Pro (`can_use_premium_code()` false), the site returns to Free. The other two choices ("…only when paying monthly", "Keep features, only block updates and support") are not used | ADR 0013 D7; set (as the plan default) 2026-10-09 |
| Sandbox checkout | https://checkout.freemius.com/plugin/41144/plan/71340/?sandbox=true (not yet opened) | story 44 AC1 |
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
| Support contact | hello@postquality.com, 2 business days | ADR 0013 D9 |
| Terms / refund / privacy URLs | https://postquality.com/terms, /refund, /privacy, /support; drafts in `docs/policies/` (story 46, 2026-10-09) | story 45 publishes them |
| Third-party legal links in the readme | CHECK: n8n, Anthropic, OpenAI, Google and Freemius terms and privacy URLs named in `docs/policies/readme-external-services.md` | story 38 opens each before pasting |
| Opt-in screen | Freemius's standard screen at activation, plugin name only, skippable; nothing leaves the site before the choice (`is_org_compliant`); the license key is entered on the SDK's activation screen, or after Skip through the "Activate License" link on the Plugins screen; Account, the one SDK submenu (pricing, contact, support, affiliation and add-ons off), appears only once the install is registered (opt-in or license activation) | ADR 0013 D8; shipped by story 44 |
| SDK version and vendor folder | 2.13.4, from https://github.com/Freemius/wordpress-sdk/archive/refs/tags/2.13.4.zip, committed unmodified in `post-quality-manager/vendor/freemius/` (byte for byte: `.gitattributes` turns off line-ending normalization there); CI skips it in `php -l` and Plugin Check | story 44 |
| Test purchase | sandbox checkout exists (row above) | story 44 AC1 |
| Fees (read 2026-10-09) | 3.8% + 50¢ plus gateway fees, plus 2.3% for WordPress products; no setup or monthly fee | ADR 0013 D3; re-read before launch |

## Release order (ADR 0014)

One source, two builds, the same version number in both: the Pro build is the CI zip, the Free build is what Freemius generates from it. The order is tag → Freemius upload of the CI zip → its generated Free build to WordPress.org SVN (story 38 AC7) → release in Freemius; the checks at each step are `/release` steps 6–9 (`.claude/skills/release/SKILL.md`). GitHub Releases attach the full (Pro) zip until the first directory release, then the Free build. CI's `free-build` job strips the same markers with `bin/build-free.php` on every PR; story 54's one-off `diff -r` against Freemius's output is what makes that check trustworthy.

| Field | Value | Source / owner |
|---|---|---|
| Upload | Freemius → Deployment → Add New Version, the CI zip as downloaded; a re-zip only with the OS zip or `zip -r`, never PowerShell's `Compress-Archive` (backslash paths) | story 54 |
| Free build download | the version's row in Deployment, or `GET /products/41144/tags/{tag_id}.zip?is_premium=false` with a product-scoped bearer token from Settings → API & Keys (in the dashboard or a GitHub secret, never in the repository). CHECK: confirm in Freemius's API reference before automating | story 54; story 38 automates |
| Automation endpoints | `POST /products/{id}/tags.json` (multipart `file`) adds a version; `PUT /products/{id}/tags/{tag_id}.json` with `release_mode` releases it. CHECK as above | story 38 |

## GitHub repository (story 48)

| Field | Value (read 2026-10-09) | Source / owner |
|---|---|---|
| Repository | github.com/vincentheimann/post-quality-manager, private until entry 4.8 | epic-delivery-pipeline |
| Description | the tagline in docs/brand.md (set 2026-10-09) | story 48 |
| Topics | `wordpress-plugin`, `content-quality`, `n8n`, `ai` (set 2026-10-09; topics may name integrations, the product name may not) | story 48 |
| Website | https://postquality.com (set 2026-10-09) | story 48 |
| Community files | `SECURITY.md`, `CONTRIBUTING.md`, `.github/ISSUE_TEMPLATE/` (bug, feature, config), `.github/PULL_REQUEST_TEMPLATE.md` | story 48 |
| Default branch | main; protection to add (CI checks required, no force push), only possible once the repository is public on the free plan (read 2026-10-09) | story 48, applied at entry 4.8 |
| Discussions | off today | OWNER: keep off or turn on (story 48) |
| Private vulnerability reporting | to enable; only available on a public repository | story 48, applied at entry 4.8; the address is hello@postquality.com (story 46) |
| Release conventions | release-please on Conventional Commits, rebase merges, `post-quality-manager-<version>.zip` per release, pre-releases until entry 4.8 | memory/delivery-pipeline.md |

## Website: postquality.com (story 45, later story 50)

| Field | Value | Source / owner |
|---|---|---|
| Domain | postquality.com, bought 2026-10-09 in the owner's name | ADR 0013 |
| Mailbox | hello@postquality.com, hosted at Infomaniak (MX, SPF, DKIM, DMARC set); receives mail, confirmed by the owner 2026-10-12 | ADR 0013 D9 |
| Hosting at launch | GitHub Pages from the public repository github.com/vincentheimann/postquality.com (Jekyll, minima, no build step), custom domain set, Let's Encrypt certificate issued and HTTPS enforced 2026-10-12. Kept as the MVP; the Netlify plan of 2026-10-09 was withdrawn | story 45 (done 2026-10-12); story 57 improves it, tooling chosen then |
| Pages at launch | /, /pricing/, /docs/, /privacy/, /terms/, /refund/, /support/ — policies copied from `docs/policies/`, which stays the source of truth | stories 45 and 46 |
| DNS for the site | at Infomaniak, live 2026-10-12: apex `A` 185.199.108.153 / .109.153 / .110.153 / .111.153, `AAAA` 2606:50c0:8000::153 / 8001 / 8002 / 8003, `CNAME www → vincentheimann.github.io`; MX, SPF, DKIM, DMARC, SendGrid and Infomaniak autoconfig records untouched | story 45 |
| Site improvement | content, design, Freemius checkout buttons, plugin-site best practices; depends on the plugin's success; tooling chosen at refinement | story 57 (story 50 dropped 2026-10-12) |

## Open markers on 2026-10-09

- OWNER: approve story 41's banner and screenshots; confirm the CHF figures (story 52).
- OWNER: Freemius account; Discussions on or off. (Mail at hello@postquality.com confirmed 2026-10-12.)
- CHECK at use: `Tested up to`, readme tags, Freemius SDK version and sandbox, Freemius fees.
