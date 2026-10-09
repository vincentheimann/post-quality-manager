# Distribution data sheet

_Every field a platform asks for before the plugin can be listed, sold or found, with its value or the owner who must supply it. One section per platform. Canon lives elsewhere: this sheet points at it and never copies a value that another file owns. Rows marked `OWNER:` are blanks only the owner can fill; rows marked `CHECK:` have a value to confirm at the moment of use. Created by backlog story 47 (2026-10-09); stories 38, 44, 45 and 46 read from it and write back what they settle._

## Plugin header (`post-quality-manager/post-quality-manager.php`)

| Field | Value | Source / owner |
|---|---|---|
| Plugin Name | Post Quality Manager | docs/brand.md § Naming |
| Plugin URI | https://postquality.com | ADR 0013; story 45 changes it from the GitHub URL |
| Description | The tagline in docs/brand.md (124 chars) | story 41 replaces the current header text with it |
| Version | release-please owns it | AGENTS.md Policy |
| Requires at least | 6.6 | memory/admin-ui-rebuild.md (WordPress floor) |
| Requires PHP | 8.0 | epic-delivery-pipeline entry 4.2 |
| Author | Vincent Heimann | ADR 0013 D2 (private person) |
| Author URI | https://postquality.com | ADR 0013; story 45 |
| License / License URI | GPL-2.0-or-later / https://www.gnu.org/licenses/gpl-2.0.html | LICENSE, entry 4.9 |
| Text Domain / Domain Path | post-quality-manager / /languages | docs/brand.md § Naming; entry 4.6 |

## WordPress.org plugin directory (story 38)

Account and submission:

| Field | Value | Source / owner |
|---|---|---|
| wordpress.org username (readme `Contributors`, the submitting account) | OWNER: create or name the account | story 38 cannot submit without it |
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

Assets (`assets/` in SVN, not in the zip; story 41 produces them):

| File | Status |
|---|---|
| icon-128x128.png, icon-256x256.png | OWNER: approve story 41's direction, then produced |
| banner-772x250.png, banner-1544x500.png | same |
| screenshot-1.png … screenshot-n.png | same; numbers match the readme Screenshots section |

## Licensing provider: Freemius (story 44)

| Field | Value | Source / owner |
|---|---|---|
| Account | OWNER: create at freemius.com in the seller's name | ADR 0013 D2 |
| Seller of record | Freemius (remits VAT and sales tax); payouts to the owner as a private person | ADR 0013 D2, D3 |
| Product name | Post Quality Manager | docs/brand.md § Naming |
| Plan name | Pro (the free tier is "Free") | ADR 0013 D10 |
| Billing | yearly, auto-renew at the same price, no renewal discount, no coupon | ADR 0013 D4 |
| Site packs and prices | 1 site 49 · 5 sites 99 · unlimited 199, per year | ADR 0013 D5 |
| Currencies | USD and EUR, same figures | ADR 0013 D5 |
| Trial | none (the free tier is the trial) | ADR 0013 D4 |
| Money-back guarantee | 14 days | ADR 0013 D6 |
| Support contact | hello@postquality.com, 2 business days | ADR 0013 D9 |
| Terms / refund / privacy URLs | on https://postquality.com | story 46 writes them, story 45 publishes them |
| Opt-in screen | Freemius's standard screen, plugin name only | ADR 0013 D8 |
| SDK version and vendor folder | CHECK: the current Freemius WordPress SDK at build time | story 44 plan |
| Test purchase | CHECK: whether Freemius offers a sandbox; otherwise a real purchase refunded under the guarantee | story 44 Notes |
| Fees (read 2026-10-09) | 3.8% + 50¢ plus gateway fees, plus 2.3% for WordPress products; no setup or monthly fee | ADR 0013 D3; re-read before launch |

## GitHub repository (story 48)

| Field | Value (read 2026-10-09) | Source / owner |
|---|---|---|
| Repository | github.com/vincentheimann/post-quality-manager, private until entry 4.8 | epic-delivery-pipeline |
| Description | empty today → the tagline in docs/brand.md | story 48 |
| Topics | none today → proposal `wordpress-plugin`, `content-quality`, `n8n`, `ai` | story 48; topics may name integrations, the product name may not |
| Website | empty today → https://postquality.com | story 48 |
| Default branch | main; protection to add (CI checks required, no force push), only possible once the repository is public on the free plan (read 2026-10-09) | story 48, applied at entry 4.8 |
| Discussions | off today | OWNER: keep off or turn on (story 48) |
| Private vulnerability reporting | to enable; only available on a public repository | story 48, applied at entry 4.8; the address is hello@postquality.com (story 46) |
| Release conventions | release-please on Conventional Commits, rebase merges, `post-quality-manager-<version>.zip` per release, pre-releases until entry 4.8 | memory/delivery-pipeline.md |

## Website: postquality.com (story 45, later story 50)

| Field | Value | Source / owner |
|---|---|---|
| Domain | postquality.com, bought 2026-10-09 in the owner's name | ADR 0013 |
| Mailbox | hello@postquality.com, a forwarding address on the domain | ADR 0013 D9; OWNER: set up the forwarding at the registrar |
| Hosting at launch | GitHub Pages with the custom domain, HTTPS | story 45 |
| Pages at launch | front page (link to the listing), pricing, docs, privacy, terms, refund, support | stories 45 and 46 |
| Landing page | Astro, after real buyers | story 50 |

## Open markers on 2026-10-09

- OWNER: wordpress.org username (`Contributors`, the submitting account).
- OWNER: approve story 41's icon, banner and screenshots.
- OWNER: Freemius account; mail forwarding at the registrar; Discussions on or off.
- CHECK at use: `Tested up to`, readme tags, Freemius SDK version and sandbox, Freemius fees.
