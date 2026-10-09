# 0013. Pro is sold through Freemius, per site and yearly, before the first public release

- Status: accepted
- Date: 2026-10-09

## Context

ADR 0003 decided there is a paid plan (Pro, per site, hosted licensing, Freemius by default). ADR 0006 shipped the license check as a stub, any non-empty key unlocks Pro, "until a paid product exists". The first public release (epic-delivery-pipeline entry 4.8, 4.0.0) is the next cut, and nothing said how a customer pays. As built, 4.0.0 would hand Pro to anyone who types a word into Settings → License, and ask for a key nobody can buy. Backlog spike 43 listed ten decisions, each with a recommended default; the owner took every default and supplied the three inputs that had none (2026-10-09). The `/prioritize` pass the same day had framed the launch posture the other way, with real licensing gating only the WordPress.org listing (story 38); the spike's default and the owner's answer pull it ahead of entry 4.8.

Alternatives weighed for the provider: Freemius (merchant of record, checkout, license keys, a WordPress SDK with an opt-in screen, a revenue share); Lemon Squeezy or Paddle (merchant of record, license keys through an API, no WordPress SDK, so the plugin would own its activation code); a shop on the owner's site with Easy Digital Downloads or WooCommerce plus a licensing add-on (full control, the seller handles VAT and sales tax). For the launch posture: real licensing before the first public release, or 4.0.0 with the stub and the License section relabelled "Pro is coming". For the public home: a dedicated domain with a site, the WordPress.org listing plus GitHub Pages as the only home, or a full marketing site from day one.

## Decision

Pro can be bought from the first public release on, through Freemius, as one yearly plan licensed per site, sold by the owner as a private person under the domain postquality.com.

- **D1 Launch posture:** real licensing (backlog story 44) lands before entry 4.8; the stub never ships in a public release.
- **D2 Seller:** the owner as a private person, Vincent Heimann, the name already in the plugin header. Freemius is the seller of record and remits VAT and sales tax; income tax on payouts stays with the owner.
- **D3 Provider:** Freemius, confirming ADR 0003. `PQM_License` stays the only seam (ADR 0006). Fees checked 2026-10-09 on freemius.com/pricing: 3.8% + 50¢ plus gateway fees, plus 2.3% for WordPress products; no setup or monthly fee.
- **D4 Plans:** one Pro plan, yearly with auto-renewal at the same price, in three site packs (1, 5, unlimited sites). No lifetime plan, no trial beyond the free tier, no launch coupon, no renewal discount.
- **D5 Prices:** 49 / 99 / 199 a year for 1 / 5 / unlimited sites, the same figures in USD and EUR. Anchored below the leading content plugins because the customer also pays their own AI and n8n bills (ADR 0003); Freemius keeps existing customers on the price they bought at, so raising later is free.
- **D6 Refund:** a 14-day money-back guarantee handled by Freemius.
- **D7 Lapse:** an expired or cancelled license returns the site to Free; paid commands are refused, scheduled reviews can be switched off, stored overrides, schedules and role rights survive; no grace period beyond Freemius's own.
- **D8 Telemetry:** the Freemius opt-in (its standard activation screen, installs, versions, deactivation reasons) is the A1/A2 signal source; `epic-ebm-telemetry` stays local-only; both are disclosed in the privacy text (story 46). Nothing leaves a site before consent (WordPress.org guideline 7).
- **D9 Support:** Free gets the WordPress.org support forum, best effort; Pro gets email at hello@postquality.com, answered within 2 business days.
- **D10 Plan names:** "Free" and "Pro", as the UI already says.
- **Public home:** the domain postquality.com, bought 2026-10-09, carries the plugin's URLs (Plugin URI, Author URI, policies, pricing, "Get Pro") and the support mailbox as a forwarding address. At launch the site is a few static pages on GitHub Pages (story 45); a proper landing page in Astro is built only after real users have bought the plugin (story 50). The WordPress.org listing and the Freemius checkout and customer portal do the rest.

## Consequences

- Easier: no public release ever ships a free-for-all Pro; Freemius takes checkout, invoices, VAT and refunds off the seller; the gate, the key field and the carry rules are reused unchanged; every public URL has a stable home from day one.
- Harder: entry 4.8 now waits on story 44, which waits on a Freemius account and a working test purchase, so the public release moves later than the 2026-10-09 ranking assumed. The Freemius SDK adds a vendor folder to the zip that Plugin Check must accept, and a revenue share applies to every sale. The domain uses the short form of the name, so the brand canon gains a surface row for it.
- Committed to: stories 44 (licensing), 45 (static pages), 46 (policies), 47 (data sheet) and 50 (landing page) read their values from this ADR.
- Extends ADR 0003 and closes ADR 0006's stub era at story 44; supersedes neither.
