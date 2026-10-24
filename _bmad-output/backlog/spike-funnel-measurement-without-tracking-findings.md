# Spike 64 — findings: measuring the funnel without tracking

Research run by an agent on 2026-10-24; every source fetched that day except those listed in section 4. Freemius SDK behaviour was read in the vendored SDK (2.13.4, `post-quality-manager/vendor/freemius/`) and checked against the 2.13.5 tag on github.com. The decision is ADR 0016.

## Verdict

The funnel is read from what WordPress.org, Freemius and GitHub already report, and postquality.com stays unmeasured: the promise "no cookies, no analytics" holds (owner, 2026-10-24).

- **Why not a cookieless counter:** none is free of Art. 5(3) of the ePrivacy Directive (EDPB 2/2023 ¶33 for scripts, ¶50–51 for pixels), "no banner needed" is the vendors' claim rather than settled law, and every option ends "no analytics". Switzerland would need a notice and an opt-out.
- **Attribution:** the interest email already names the page it was sent from (story 86, brief D16: the body ends "Sent from the pricing page"), visible to the visitor and deletable. No coupon per entry point: ADR 0013 D4 stays (no coupons). The in-plugin moments (story 61) are read as total upgrades in Freemius.
- **The figures and their cadence** (weekly; the GitHub traffic figures before their 14-day window closes):

| funnel step | figure | source |
|---|---|---|
| Visit | blind; indirect: postquality.com among the repository's top 10 referrers, interest emails per page | GitHub traffic API, the mailbox |
| Install | release downloads (before the listing, as running totals to snapshot); downloads per day, the active-install band, version shares (after) | GitHub release assets; WordPress.org stats |
| Activation / opt-in | new opt-ins, active installs, activations vs deactivations; the opt-in rate only as an estimate against WordPress.org's active installs | Freemius dashboard |
| First use (A5) | blind (the local-only `epic-ebm-telemetry` is the place to revisit it) | — |
| Purchase | payments, subscriptions, carts (visit, abandoned, completed, recovered) | Freemius |

- **How each waiting story reads its effect:** 57 (done) and 63 — interest emails per page, then confirmed subscribers with open and click tracking off (EDPB ¶48); 61 — total upgrades per period in Freemius, no per-moment figure; 62 — opt-ins against WordPress.org installs, and abandoned against completed carts, before and after the copy change.
- **Found on the way:** finding 8 below (deactivation feedback from a site that skipped the opt-in) is filed as bug 90; GitHub Pages' limits on commercial sites (end of section 4) are an aside for the owner.

The research follows, as delivered.

## 0. What decides

1. **After install, Freemius sees only opted-in sites.** The new dashboard shows uninstalls and deactivations, but "we only know this for websites that opted-in or activated a license" ([release notes](https://freemius.com/blog/release-notes-real-time-analytics-and-multi-store-dashboard/)). SDK 2.5.1 dropped the skip ping: "the Skip button is just for skipping the opt-in now" ([2.5.1 notes](https://freemius.com/blog/release-notes-user-focused-changes-wordpress-sdk-2-5-1/)). Freemius therefore has no skip count to divide by, and the dashboard dropped its conversion rate as inaccurate. The only outside count is WordPress.org's downloads and active installs, once the plugin is listed.
2. **A sale can be traced to its source without a site script in one way only: put a different coupon on each checkout link.** Hosted checkout links accept `?coupon=` ([hosted checkout](https://freemius.com/help/documentation/checkout/integration/hosted-checkout/)). Each payment records `coupon_id`, and the API can filter payments by it ([payments API](https://freemius.com/help/api/payments/list/)). Carts are recorded with the statuses `visit`, `abandoned`, `completed` and `recovered`, and a `url` field. Carts have no UTM or referrer field ([carts API](https://freemius.com/help/api/carts/list/)). Affiliate referral URLs set a cookie, and the site must get the visitor's consent for it ([Freemius affiliate terms](https://product.freemius.com/843/foogallery/legal/affiliate-program/)). Affiliate links would break the promise.
3. **GitHub Pages gives the owner no visit count.** The repository traffic API counts only views of the repository on github.com. It covers 14 days and the top 10 referrers and paths ([traffic API](https://docs.github.com/en/rest/metrics/traffic); [community answer](https://github.com/orgs/community/discussions/22090)).
4. **EU law: cookieless JavaScript is within Art. 5(3) of the ePrivacy Directive, and so is a no-JS pixel.** EDPB Guidelines 2/2023 say so for JavaScript in ¶33 and for pixels in ¶50–51. Whether consent is then needed depends on national exemptions, which the EDPB does not cover (¶4):
   - CNIL (France) exempts audience measurement that meets its conditions.
   - WP29 (2012) found first-party analytics "not exempt from consent" but low-risk.
   - The Commission's Digital Omnibus proposes an explicit exemption (Art. 88a(3)(c) GDPR). It is still a proposal.

   "No banner needed" is the vendors' claim, not settled EU law.
5. **Swiss law: inform the visitor and offer an opt-out. Consent is not required for low-risk use.** The Telecommunications Act (TCA) Art. 45c requires the information and a right to refuse. The data protection act (FADP) Art. 31(2)(e) justifies statistics when the data are anonymised as soon as possible, which for websites usually means immediately ([FDPIC guidelines v1.1](https://www.edoeb.admin.ch/dam/en/sd-web/brLL9rM3ny9d/Leitfaden%20des%20ED%C3%96B%20betreffend%20Datenbearbeitungen%20mittels%20Cookies%20und%20%C3%A4hnlichen%20Technologien%20V.%201.1%20vom%2006.10.2025_EN.pdf), §2.1, §3.7.2 and §3.8.2).
6. **The wording of the promise:**
   - "No cookies" stays true with every cookieless tool in Table B. Each vendor says it stores nothing on the device.
   - "No analytics" stops being true with any of them.
   - A consent banner has to remember the visitor's choice, usually in a cookie or localStorage, so "no cookies" would change as well.
7. **In-plugin nudges cannot be read today.** Every "Get Pro" link opens `https://postquality.com/pricing/` (`PQM_PRICING_URL`), and the site has no counting. The no-send option is described in section 3.
8. **The privacy text may be wrong (code read only, not seen on the network).** A site that skipped the opt-in can still send data when the admin fills in the deactivation feedback form and later deletes the plugin. What is sent depends on the form's "Anonymous feedback" checkbox:
   - **Ticked:** an anonymous `uninstall.json` call carrying the reason ID, a reason text of up to 128 characters, and an md5 site ID.
   - **Unticked (the default):** `opt_in()` runs at uninstall. It fills in the current WordPress user's first name, last name and email.

   `docs/policies/privacy.md` §1 says the author receives "nothing from your site unless you opt in". Sources: [SDK 2.13.5 L9733–9757](https://github.com/Freemius/wordpress-sdk/blob/2.13.5/includes/class-freemius.php#L9733-L9757), [L16994–16996](https://github.com/Freemius/wordpress-sdk/blob/2.13.5/includes/class-freemius.php#L16994-L16996), [form.php L65 and L572](https://github.com/Freemius/wordpress-sdk/blob/2.13.5/templates/forms/deactivation/form.php#L572). Two SDK filters change this: `show_deactivation_feedback_form` and `default_to_anonymous_feedback`. This should be confirmed on a sandbox site before the privacy text is changed.

## 1. Table A: sources

| source | figures | consent needed? | cost | retention | what it cannot tell | URL |
|---|---|---|---|---|---|---|
| Freemius Audience Analytics (dashboard) | Active and all-time installs; new opt-ins and new users; activations vs deactivations ("Effective Growth"); uninstalls and deactivations; versions of product, WordPress, PHP and SDK; location and language. Custom date range, with an optional comparison range. | Yes: the opt-in, or a license activation | No monthly fee. 3.8% + 50¢ per sale, plus 2.3% for WordPress products, plus gateway fees | Not stated. The date range can be moved over any past period. | Anything about sites that skipped: the skip counter and conversion rate are gone, so there is no opt-in rate. A plugin deleted over FTP or SSH still counts as active. No per-version breakdown of activations. | https://freemius.com/help/documentation/analytics/audience/ · https://freemius.com/blog/release-notes-real-time-analytics-and-multi-store-dashboard/ · https://freemius.com/blog/release-notes-user-focused-changes-wordpress-sdk-2-5-1/ · https://freemius.com/help/faq/analytics-insights/ · https://freemius.com/pricing/ |
| Freemius installs API | `installs.json` with `filter` = active / inactive / trial / paying / uninstalled / active_premium / active_free; `version`; `reason_id`; `search` over the reason text. Fields include `is_active`, `is_uninstalled`, `is_premium`, `version`, `created`, `country_code`, `plan_id`, `upgraded` and `last_seen_at`. | Yes: opted-in or licensed sites only | As above | Not stated | Same blind spots as the dashboard | https://freemius.com/help/api/installations/list-installs/ |
| Freemius deactivation and uninstall reasons | Dashboard: Users → Uninstalls → filter by reason. API: `reason_id` filter. | The user submits the form. Sites that skipped the opt-in: see finding 8. | As above | Not stated | Users who do not answer. The sources disagree: the 2024 release notes say "we still don't store reasons for uninstallation in the new dashboard", while the User Feedback guide still documents the reason filter. | https://freemius.com/help/documentation/analytics/user-feedback/ · https://freemius.com/blog/release-notes-real-time-analytics-and-multi-store-dashboard/ |
| Freemius events and webhooks | Install events: `install.installed`, `.activated`, `.deactivated`, `.uninstalled`, `.version.upgraded`, `.plan.changed`. Sales: `license.activated` (also fires after an upgrade), `payment.created`, `subscription.created`. Carts: `cart.created`, `.abandoned`, `.completed`, `.recovered`. Also `pricing.visit`, which fires only for the in-admin pricing table or the popup checkout; the plugin turns these off (`'pricing' => false`) and sends upsells to the site. No `install.skipped` event exists. | Install events: opted-in sites only. Cart and payment events: buyers. | As above | Not stated | Any channel or source | https://freemius.com/help/documentation/marketing-automation/events-webhooks/ |
| Freemius carts (checkout visits and abandoned carts) | `status` = pricing_visit / visit / abandoned / completed / email_1–3_sent / recovered. `visited` is "the first time the prospect visited the checkout". `url` is "the page URL containing the checkout". Also `mode`, `coupon_id`, `install_id`, `country_code`. Recovery emails go out after 60 minutes, 24 hours and 3 days. | Not the plugin's opt-in. Freemius runs the checkout as seller of record (privacy.md §2). | Recovery included ("no extra fee for cart recovery") | Not stated | No UTM or referrer field. Unknown whether a cart exists before an email is entered, and whether the query string of a hosted checkout link is kept in `url`. | https://freemius.com/help/api/carts/list/ · https://freemius.com/help/documentation/checkout/integration/freemius-checkout-buy-button/ |
| Freemius payments, upgrades and coupons | `payments.json` fields include `coupon_id`, `plan_id`, `install_id`, `country_code`, `created`, `gross`, `type` and `is_renewal`. Filters: `coupon_id`, `from` / `to`, `billing_cycle`. Hosted links take `?coupon=CODE`, and `&hide_coupon=true` hides the code. | Buyers (Freemius is seller of record) | Per-sale fee | Not stated | Where a sale came from, unless each entry point carries its own coupon. A coupon is a discount; whether a 0% coupon is allowed is unverified. | https://freemius.com/help/api/payments/list/ · https://freemius.com/help/documentation/checkout/features/coupons/ · https://freemius.com/help/documentation/checkout/integration/hosted-checkout/ |
| Freemius affiliates | `affiliate_user_id`; per affiliate: `visits`, `unique_visits`, `referrals`, `earnings`; coupon-based affiliate tracking | **A cookie on the visitor's browser; the site must obtain consent** | Commission | Cookie lifetime set by the vendor | Out under the current promise | https://product.freemius.com/843/foogallery/legal/affiliate-program/ · https://freemius.com/affiliate-platform/ |
| Freemius checkout JS callbacks (`track`, `purchaseCompleted`) | Checkout events, from load to complete | They need Freemius JS on postquality.com, which is a third-party script | — | — | Out under the current promise. Hosted links cannot use callbacks. | https://freemius.com/help/documentation/checkout/integration/freemius-checkout-buy-button/ |
| Freemius custom events (`track_event`) | Custom event per install | Opted-in sites only (`if ( ! $this->is_registered() ) return false;`) | Unknown | Unknown | The SDK says custom events are "currently only supported for specific clients"; for others, the API ignores the call | https://github.com/Freemius/wordpress-sdk/blob/2.13.5/includes/class-freemius.php#L9636-L9694 |
| WordPress.org plugin API `info/1.2` | `active_installs` as a rounded band (live: akismet = 5000000, shown as "5+ million"); `downloaded` total (live: 408018640) | No. WordPress.org counts through core update checks. | Free | Current value only | Who installed, from where, the exact install count; referrers and page views (none on the Advanced page) | https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=akismet&request[fields][active_installs]=1 · https://wordpress.org/plugins/akismet/advanced/ |
| WordPress.org stats endpoints | `stats/plugin/1.0/downloads.php?slug=` gives downloads per day (live: 179 days, 2026-04-28 to 2026-10-23; `limit=2000` gave the same); `stats/plugin/1.0/{slug}` gives active-version shares in %. The Advanced page shows "Active versions", "Downloads Per Day" and "Downloads history". | No | Free | About 6 months of daily downloads through the API | Installs vs downloads (whether downloads include updates is unverified); no referrer data; exact install counts are private | https://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=akismet&limit=5 · https://api.wordpress.org/stats/plugin/1.0/akismet · https://make.wordpress.org/plugins/2025/08/11/plugin-rollout-phased-releases/ · https://make.wordpress.org/plugins/2021/08/27/inaccurate-stats-have-been-corrected/ |
| GitHub repository traffic API | Views, unique visitors and clones per day or week; top 10 referrers; top 10 paths. Referrers exclude search engines and GitHub itself. | No | Free | **14 days.** Views and clones refresh hourly, referrers and paths daily. | Visits to the Pages site (only repository views count); anything older than 14 days unless someone snapshots it. Needs write/push access. | https://docs.github.com/en/rest/metrics/traffic · https://docs.github.com/en/repositories/viewing-activity-and-data-for-your-repository/viewing-traffic-to-a-repository |
| GitHub release assets | `download_count` per asset: a single integer, with no per-day breakdown | No | Free | Running total | When downloads happened (it needs periodic snapshots); downloads are not installs | https://docs.github.com/en/rest/releases/assets |
| GitHub stars | Stargazers with a timestamp per star (`Accept: application/vnd.github.star+json`). From July 2026 the list is limited to admins and collaborators. | No | Free | Permanent while starred | Intent; source | https://docs.github.com/en/rest/activity/starring |
| GitHub Pages | **No visit figure for the site owner.** GitHub logs visitor IPs "for security purposes" only. | — | — | — | Everything about site visits | https://docs.github.com/en/pages/getting-started-with-github-pages/about-github-pages · https://github.com/orgs/community/discussions/22090 |
| Email: a different prefilled `mailto:?subject=` per entry point | One subject per page or CTA, e.g. `?subject=Launch%20news%20(pricing)` | The sender's own email | Free | The owner's mailbox | Visits that send no email. The sender can edit the subject. Clients "MAY" build the message from only some fields, but RFC 6068 calls Subject and Body "safe and useful". No mail handler means nothing is sent. | https://www.rfc-editor.org/rfc/rfc6068 |

## 2. Table B: site-visit options

The legal analysis is the same for every (b) option that runs JavaScript, so it is stated once here.

- **EU, the legal frame common to all (b) options:**
  - Art. 5(3) of the ePrivacy Directive allows access to the device only with consent or where strictly necessary (EDPB 2/2023 ¶1).
  - JavaScript that tells the browser to send information "clearly falls within the scope" (¶33).
  - A pixel's "distribution to the user's terminal equipment does constitute storage, at the very least through the caching mechanism… Article 5(3) ePD is applicable" (¶50).
  - The EDPB leaves exemptions to national law and national authorities (¶4, ¶56).
  - **France (CNIL):** audience measurement is exempt when it serves only the site owner, produces anonymous statistics, involves no cross-site tracking and no transfer of non-anonymous data to third parties. The English sheet adds IP truncation, a way to object, a 13-month tracker lifetime, and says the rules "may be subject to national variation".
  - **WP29 Opinion 04/2012:** first-party analytics are "not exempt from consent but pose limited privacy risks".
  - **Digital Omnibus:** proposes Art. 88a(3)(c) GDPR, which would exempt "aggregated information… to measure the audience… by the controller… solely for its own use". It is a proposal: Commission FAQ of 20 Nov 2025, EDPB-EDPS Joint Opinion 2/2026 of 10 Feb 2026.
  - **GDPR:** a transiently processed IP is personal data, so the basis would be Art. 6(1)(f), legitimate interest (EDPB Guidelines 1/2024).
  - **Does the GDPR reach a Swiss owner?** Pricing in EUR is a targeting factor under Art. 3(2)(a). Analytics are not automatically "monitoring" (EDPB 3/2018).
- **Switzerland, the common frame:**
  - The TCA (SR 780.10) Art. 45c obliges the site to "inform website visitors" and remind them "that they may refuse".
  - Without high-risk profiling, an opt-out is enough; express consent is needed only for intrusive or unexpected use (FDPIC guide §3.7.2, §3.10).
  - FADP Art. 31(2)(e) justifies statistics if the data are anonymised "usually… immediately" and the results identify no one (§3.8.2).
  - The FDPIC guide counts "pixels" and stateless tracking among "similar technologies" (§1.1).

| option | runs JS / touches device? | EU consent position | CH position | cost | can the promise stay as worded? | URLs |
|---|---|---|---|---|---|---|
| (a) No measurement | No / no | Nothing new | Nothing new | 0 | **Yes** | — |
| (b1) GoatCounter, hosted, `count.js` | JS: yes. Device: "doesn't store any information in the browser with cookies, localStorage, cache, or any other method". The session key is site + User-Agent + IP, kept in memory for 8 h and never written to disk. | In scope of Art. 5(3). Consent unless a national exemption applies; it plausibly meets the CNIL conditions (single site, aggregate data, IP not stored), but no authority has assessed it. The vendor says it "probably doesn't require a GDPR consent notice". | Notice and an opt-out | Free "for reasonable public usage"; "small-to-medium business… is fine"; donation-funded | "No cookies": yes. "No analytics": **no**. "No tracking": risky (see Table B notes). | https://www.goatcounter.com/ · https://www.goatcounter.com/help/privacy · https://www.goatcounter.com/help/sessions · https://www.goatcounter.com/help/gdpr |
| (b2) GoatCounter no-JS pixel (`<img src=…/count?p=/path>`) | JS: no. Device: an image request, possibly cached. It loses the referrer and screen size, and the path must be set by hand (a Jekyll template can do it). | **Still in scope** (EDPB ¶50–51); otherwise as b1 | As b1 | Free, as b1 | As b1 | https://www.goatcounter.com/help/pixel |
| (b3) Plausible Cloud | JS: yes. Device: "We do not use cookies, browser cache or local storage." Visitors are counted with `hash(daily_salt + domain + IP + UA)`; the salt is deleted every 24 h. | As b1. Vendor: "you do not need cookie banners" | As b1 | $9/month for 10k pageviews; 30-day trial; no free plan. Community Edition is free (AGPL) but needs a server, which the site does not have. | As b1 | https://plausible.io/data-policy · https://plausible.io/#pricing · https://plausible.io/self-hosted-web-analytics |
| (b4) Simple Analytics | JS: yes, with a noscript pixel by default. Device: "No cookies… No local storage or session storage… No fingerprinting". Drops visitors who send Do Not Track by default (pixel docs). | As b1 (pixel included). Vendor: "no consent banners needed" | As b1. The Do Not Track drop is a ready-made opt-out. | Free "for hobby sites": 1 month history, badge required, commercial use not stated. €20/month from 100k pageviews. | As b1 | https://www.simpleanalytics.com/pricing · https://www.simpleanalytics.com/gdpr-compliance · https://docs.simpleanalytics.com/without-javascript |
| (b5) Umami Cloud | JS: yes. Device: "Umami does not use any cookies in the tracking code" (v2 docs) | As b1 | As b1 | Hobby plan free: 100k events/month, 1 site, 6 months retention. Pro $20/month. | As b1 | https://umami.is/pricing · https://v2.umami.is/docs |
| (b6) Cloudflare Web Analytics beacon | JS: yes. Device: "does not use any client-side state, such as cookies or localStorage". | As b1. A US company; data location not stated in the fetched pages. | As b1 | Free | As b1 | https://www.cloudflare.com/web-analytics/ · https://developers.cloudflare.com/web-analytics/about/ · https://developers.cloudflare.com/web-analytics/faq/ (unsampled for 7 days, then about a 10% sample; 6 months viewable) |
| (b7) Cloudflare proxy (edge analytics, no JS) | JS: no. Device: nothing beyond normal page delivery. It needs DNS proxied through Cloudflare in front of GitHub Pages, which adds a processor that sees every request. | No instruction is sent to the device, so this is the most defensible option. But EDPB ¶54–55 brings IP-based *tracking of navigation* within 5(3); aggregate request counts are arguably not that (interpretation). GDPR still applies to IPs. | No processing on the device, so Art. 45c is arguably not triggered (interpretation). The FADP still applies, and a notice is advisable. | The FAQ says edge analytics are "available to any customer who proxies traffic", but the landing page labels the proxy option "Sign up for a paid plan". Unclear. | "No cookies" and "no analytics script": yes. "No analytics": **no**; it would become "aggregate server-side counts". | https://developers.cloudflare.com/web-analytics/faq/ · https://www.cloudflare.com/web-analytics/ |
| (c) Consent banner + analytics | JS: yes. Device: storing the choice usually takes a cookie or localStorage; the FDPIC notes a cookie "to store the visitor's consent" may be necessary. | Compliant if consent comes before any collection | Compliant (it exceeds the opt-out the TCA requires) | Tool cost + CMP | **No**: "no cookies" and "no analytics" both change, and the data cover only visitors who consent | FDPIC guide §3.5.2 (link in Table B notes) |

**Table B notes**

- **Sources for the shared legal frame:**
  - EDPB 2/2023 final (adopted 7 Oct 2024): https://www.edpb.europa.eu/our-work-tools/our-documents/guidelines/guidelines-22023-technical-scope-art-53-eprivacy-directive_en (PDF: https://www.edpb.europa.eu/system/files/documents/2024-10/edpb_guidelines_202302_technical_scope_art_53_eprivacydirective_v2_en_0.pdf)
  - CNIL: https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience (4 Jul 2025) and https://www.cnil.fr/en/sheet-ndeg16-use-analytics-your-websites-and-applications
  - WP29 Opinion 04/2012: https://ec.europa.eu/justice/article-29/documentation/opinion-recommendation/files/2012/wp194_en.pdf
  - Digital Omnibus: https://digital-strategy.ec.europa.eu/en/faqs/digital-package and https://www.edpb.europa.eu/system/files/documents/2026-02/edpb_edps_jointopinion_202602_digitalomnibus_en.pdf ¶101–102
  - EDPB Guidelines 1/2024 (legitimate interest, v1.0, consultation): https://www.edpb.europa.eu/our-work-tools/documents/public-consultations/2024/guidelines-12024-processing-personal-data-based_en
  - EDPB 3/2018 (territorial scope): https://www.edpb.europa.eu/sites/default/files/files/file1/edpb_guidelines_3_2018_territorial_scope_after_public_consultation_en_1.pdf
  - FDPIC guidelines v1.1 (6 Oct 2025): link in finding 5
  - Revised FADP in force 1 Sep 2023: https://www.bj.admin.ch/en/new-data-protection-legislation
- **Why "no tracking" is risky wording for (b):** the EDPB treats pixels and JavaScript collection as Art. 5(3) operations, and the FDPIC's guide lists "pixels" and stateless tracking as tracking technologies. Wording that stays honest for b1–b6: "No cookies. We count visits in aggregate with <tool>, which stores nothing on your device and keeps no IP address. To opt out: <how>."
- **The opt-out problem:** an opt-out that needs nothing stored on the device exists only where the tool drops Do Not Track visitors (Simple Analytics, documented). For the other tools this is unverified.

## 3. Table C: funnel steps

| step | best source under the current promise | blind part | if the promise is amended | read cadence |
|---|---|---|---|---|
| **Visit** (postquality.com) | **Blind.** Indirect signals: postquality.com among the GitHub repo's top-10 referrers (repo clicks only; depends on the browser sending a Referer, unverified); one mailto subject per page (interest, not visits) | All page-level visits and conversion from each page | b1 or b2 (GoatCounter, free, EU) or b7 (edge counts, no JS), with a privacy text that names the tool and the opt-out | Weekly |
| **Install** | Before the listing: GitHub release `download_count` (snapshot it, because it is a running total). After the listing: WordPress.org downloads per day (about 6 months through the API), the active-install band and version shares. | Downloads are not installs; no source or referrer | Same | Weekly snapshot (GitHub traffic must be read at least every 14 days) |
| **Activation / opt-in** | Freemius new opt-ins, active installs and Effective Growth | Sites that skip. The opt-in rate is only an estimate: Freemius opt-ins ÷ WordPress.org active installs, and the two count differently (Freemius FAQ). | Same | Weekly |
| **First use** (A5: first stored review) | **Blind** | No Freemius event exists for it. `track_event` is limited to opted-in sites and "only supported for specific clients". | Opted-in sites only, if Freemius enables custom events and the privacy text names the event | — |
| **Purchase** | Freemius payments, subscriptions, `license.activated` and carts (visit / abandoned / completed / recovered). All buyers are covered, no opt-in needed. | Source or channel, unless each entry point carries its own coupon (`coupon_id`) | Same; the checkout JS `track` callback stays out (third-party script) | Weekly; per sale while sales are few |

**How each waiting story could read its effect:**
- **Story 57 (site CTAs):** visits are blind. Each page's buy CTA could carry its own coupon and read it on payments, if a discount is acceptable. A `mailto` subject per page could stand in until story 63.
- **Story 61 (in-plugin moments):** see below.
- **Story 62 (Freemius surfaces):** compare the opt-in count against WordPress.org installs before and after the change, and the checkout's abandoned vs completed carts.
- **Story 63 (interest capture):** count confirmed subscribers in the chosen tool. Switch off open and click tracking: EDPB ¶48 names email tracking pixels.

**Question 3, in-plugin nudges.** Three ways to read a moment's effect, none of which sends anything from the plugin:

- **Option 1, a coupon per moment:** point the moment's link straight at the hosted checkout with its own coupon (and `hide_coupon=true` if wanted). The sale then carries that coupon's `coupon_id`. Nothing is sent before the user clicks and buys.
  - WordPress.org guideline 11 forbids "tracking referrals via those ads (see guideline 7)". It is written about dashboard ads, while "upgrade prompts" are allowed if limited and dismissible. Whether a per-moment coupon counts as referral tracking is not ruled on anywhere I found.
- **Option 2, Freemius's own figures:** `pricing.visit` does not fire, because the in-admin pricing page is off. Opted-in sites still show `install.plan.changed` and `license.activated`. Those tell an upgrade happened, not which moment caused it.
- **Option 3, accept staying blind:** keep the site's pricing link and read only the aggregate upgrades in Freemius.

Sources: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ and the Freemius URLs in Table A.

## 4. Unverified claims and sources I could not fetch

- **EUR-Lex.** Directive 2002/58/EC, 2009/136/EC and the GDPR returned empty or HTTP 202 to automated fetches. The Art. 5(3) wording comes from EDPB 2/2023 ¶1. I did not read the GDPR article texts directly.
- **Fedlex.** The TCA Art. 45c text page needs JavaScript, so I could not read it. Its content comes from the FDPIC guide §2.1, which also gives SR 780.10.
- **meta.trac.wordpress.org.** Tickets #1155 and #6511 were blocked by a browser check. So I could not confirm the active-install rounding rule (first significant digit), the removal of the install-growth chart, or the statement that WordPress.org provides no raw data.
- **WordPress.org stats method.** Whether download counts include update downloads is unverified. Recount cadence: the 2021 post says daily; a commenter and the Freemius FAQ say weekly sampling.
- **Freemius, open points:**
  - Retention of analytics, events, carts and payments is not stated.
  - Unknown whether a hosted-checkout visit creates a cart before an email is entered.
  - Unknown whether extra query parameters on a hosted link survive into the cart's `url`.
  - Unknown whether 0% coupons are allowed, and whether the dashboard reports sales per coupon (the API filter exists).
  - Unknown whether anonymous uninstall events from sites that skipped appear anywhere. The release notes and the User Feedback guide disagree.
  - Finding 8 comes from reading code only.
  - The installations-count endpoint's own page did not render; the list endpoint did.
- **Simple Analytics.** The hosting providers (Worldstream, Leaseweb) appeared only in a search snippet; the fetched page says "Netherlands (EU)". Commercial use on the free plan is not stated.
- **Umami.** The current docs page was empty, so the cookie claim comes from the v2 docs. Whether a customer can choose the US or EU region is not stated.
- **Cloudflare.** Unclear whether edge analytics are on the free plan; the data location is not stated; how a proxy works with GitHub Pages HTTPS was not researched.
- **GoatCounter.** How long aggregate statistics are kept is not stated, and so is whether it honours Do Not Track.
- **National positions other than France** (for example Germany, the Netherlands) were not fetched.
- **Digital Omnibus.** Its status after Feb 2026 is not verified. Council documents up to Aug 2026 appeared in search results but were not fetched.
- **Whether ePrivacy reaches a Swiss controller** through member-state transpositions was not researched.
- **GitHub community discussion #22090.** The accepted answer's author shows no staff badge.
- **The Referer header** sent on a click from postquality.com to github.com depends on browser policy and was not verified.
- **Aside, hosting choice (out of scope):** GitHub Pages "is not intended for or allowed to be used as a free web-hosting service to run your online business, e-commerce site, or any other website that is primarily directed at either facilitating commercial transactions or providing commercial software as a service (SaaS)" (https://docs.github.com/en/pages/getting-started-with-github-pages/github-pages-limits).
