---
id: 43
type: spike
title: "The commercial model is decided: seller, provider, plans, price and launch posture"
parent: none
covers: [O9, O2]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The commercial model is decided: seller, provider, plans, price and launch posture

## Description

ADR 0003 decided *that* there is a paid plan (Pro, per site, hosted licensing, Freemius by default) and ADR 0006 shipped the license check as a stub "until a paid product exists". The first public release (epic-delivery-pipeline entry 4.8, 4.0.0) is the next cut, and nothing says how a customer pays: no seller identity, no provider account, no plan, no price, no refund or support promise. Two consequences if the release ships as is: any visitor who types a word into Settings → License gets Pro for free, and the License section asks for a key nobody can buy. This spike makes the commercial decisions in one sitting with the owner, so stories 44 (real licensing), 45 (public home), 46 (policies) and 47 (data sheet) have their inputs. It produces an ADR, not code.

## Decisions to make

Each decision names a recommended default so the owner can answer "go with the defaults" or override one by one.

- **D1 Launch posture.** (a) Real licensing (story 44) lands before entry 4.8, so the first public release can be bought; or (b) 4.0.0 ships with the stub and no paid offer: the License section is hidden or relabelled "Pro is coming", the readme and the upgrade copy promise nothing, and 44 follows in a minor release. *Default: (a) — the public release is the moment "a paid product exists" (ADR 0006's trigger); (b) only if the provider setup cannot be done in time.*
- **D2 Seller identity.** Who sells: the owner as an individual or a company, in which country. This name goes on the readme (`Author`, `Contributors`), the provider account, the website and the policies. A merchant-of-record provider takes VAT and sales tax off the seller; a self-hosted shop does not. *Default: owner's call; nothing is inferred from the repository.*
- **D3 Provider.** Freemius (ADR 0003 default: merchant of record, checkout, license keys, a WordPress SDK with a directory-compliant opt-in screen, a percentage revenue share) vs. Lemon Squeezy or Paddle (merchant of record, license keys through an API, no WordPress SDK, so the plugin holds its own activation code) vs. a shop on the owner's site (Easy Digital Downloads or WooCommerce with a licensing add-on: full control, the seller handles taxes). *Default: Freemius, confirming ADR 0003; `PQM_License` stays the swap seam.*
- **D4 Plan structure.** One Pro plan, licensed per site (ADR 0003), yearly with auto-renewal. Site packs (1 / 5 / unlimited) and a lifetime option are provider features that can be added later without plugin changes. *Default: one plan, three site packs, yearly; no lifetime at launch.*
- **D5 Price and currencies.** The price covers the plugin only; the customer pays their own n8n hosting and AI provider (ADR 0003). *Default: the owner sets the number after checking the provider's current fees; USD and EUR offered.*
- **D6 Trial and refund.** The free tier is the trial. *Default: no card-less trial; a 14-day money-back guarantee handled by the provider.*
- **D7 Lapse.** When a license expires or is cancelled, the site returns to Free: paid commands are refused, scheduled reviews can be switched off (story 13), stored overrides and schedules survive (sanitizer carry rules, ADR 0006). *Default: confirm this behavior; no grace period beyond the provider's own.*
- **D8 Opt-in telemetry.** The provider's opt-in (install counts, versions, deactivation reasons) vs. the local signals planned in `epic-ebm-telemetry`. *Default: accept the provider's opt-in as the A1/A2 signal source; `epic-ebm-telemetry` stays local-only; both are disclosed in the privacy text (story 46).*
- **D9 Support promise.** *Default: Free = the WordPress.org support forum, best effort; Pro = email, answered within a stated number of business days (the owner picks the number).*
- **D10 Plan names.** The UI already says "Pro" and "Free". *Default: keep both.*

## Acceptance Criteria

Verify: an accepted ADR (via `/adr`) records D1–D10 with the chosen values and the alternatives weighed; the initiative's O9 line names the ADR; stories 44, 45, 46 and 47 carry the answers they depend on as `Decision (owner, date)` Notes lines; epic-delivery-pipeline's Notes say whether story 44 gates entry 4.8 (D1); `docs/brand.md` § Naming gains the seller name as the `Author` surface if D2 changes it.

## Boundaries

- Produces decisions and records only: no plugin code, no provider account yet (story 44), no website (story 45).
- Does not reopen the tiers (free = Review + Key Takeaways; ADR 0003) or the one-zip rule.

## References

- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (hosted licensing, Freemius by default, per-site license)
- decision — docs/adr/0006-license-provider-ships-as-stub.md (the stub and its trigger)
- deferred — _bmad-output/initiative-post-quality-manager/deferred-work.md, "Real Freemius licensing integration"
- requirements — docs/user-paths.md, A1–A2 (UV7) and the EBM summary
- release — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/epic-delivery-pipeline.md, entry 8 and Notes
- external — https://freemius.com/wordpress/ (provider terms, fees and SDK; read the current page before D3 and D5)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (guideline 7: opt-in only; guideline 6: a free plugin must stay functional)

## Notes

- Filed 2026-10-09 with stories 44–49 (the release-readiness pack): the owner asked to define how customers pay and what else the public release needs before the tickets exist. Recommended order of the pack: 47 (data sheet, can start now) → 43 → 44, 46, 48 in parallel → 45 → 49; `/prioritize` confirms it and ranks it against entry 4.8.
- Owner requirement O9 was added to the initiative for this pack so it ranks instead of reading as scope creep.
- Decision (owner, 2026-10-09, `/prioritize`): this spike is next, before story 48 and entry 4.8. The ranking's framing for D1: real licensing (44) gates story 38 rather than entry 4.8, because a GitHub-only release reaches almost nobody and the directory is the exposure; D1 confirms this or pulls 44 ahead of 4.8.
- Decision (owner, 2026-10-09, "go with defaults"): every default taken, recorded in ADR 0013. D1 = (a), so story 44 now gates entry 4.8 as well as story 38. The three inputs with no default were given the same day: D2 the owner as a private person (Vincent Heimann), D5 49 / 99 / 199 a year for 1 / 5 / unlimited sites in USD and EUR, D9 email at hello@postquality.com within 2 business days; domain postquality.com bought; landing page deferred to story 50 (Astro, after real buyers).
