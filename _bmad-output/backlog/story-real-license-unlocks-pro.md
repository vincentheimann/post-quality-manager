---
id: 44
type: story
title: "A customer buys Pro and a real license unlocks it"
parent: none
covers: [O2, O9]
after: [43]
assignee: ""
refined: false
hitl: true
risk: medium
---

# A customer buys Pro and a real license unlocks it

## Description

The deferred-work item "Real Freemius licensing integration" (2026-10-02) becomes a ticket. The owner creates the product in the provider chosen by story 43 (plans, prices, currencies, refund setting, contact and policy links from story 46); an agent replaces the internals of `PQM_License` with the provider's SDK or API so that only a license the provider issued flips a site to Pro, and gives the free state a way to buy. Everything else stays as it is: `PQM_Features::is_pro()`, the `pqm_is_pro` filter, the gate callers, `why_not()` and the sanitizer carry rules (ADR 0006: only the class's internals change). The end-to-end check is a sandbox purchase activated on the Local site.

## Acceptance Criteria

1. **Only a real license unlocks Pro**
   **Given** a license bought through the provider's checkout (sandbox mode)
   **When** it is entered or activated in Settings → License and saved
   **Then** `is_pro()` is true and paid agents, overrides, Automated Reviews and the Roles tab unlock; a made-up key leaves the site on Free and the section says the key was not accepted
2. **A lapsed license returns the site to Free, losing nothing**
   **Given** a license the provider reports as expired or cancelled
   **When** the plugin next checks it
   **Then** the site behaves as Free (story 43 D7), stored overrides and schedules survive, and the License section says the license lapsed, with a renew link
3. **The way to buy is visible, nowhere else**
   **Given** a free site
   **When** an Administrator opens Settings → License or meets the upgrade copy on a gated command
   **Then** a "Get Pro" link leads to the pricing page (story 45) or the provider's checkout; no other screen, notice or toast nags
4. **The seam holds**
   **Given** the change
   **When** the harness sweep runs
   **Then** the caps, gate, cron and sanitize harnesses pass unchanged, and a new harness exercises `PQM_License` against a stubbed provider (valid, invalid, expired)
5. **Directory rules hold**
   **Given** the built zip
   **When** Plugin Check runs and the plugin is activated fresh
   **Then** the SDK is GPL-compatible, any opt-in screen is opt-in and skippable, nothing leaves the site before consent, and Plugin Check reports no new error (warnings on the vendor folder are triaged in the plan)
6. **Existing sites are told**
   **Given** `docs/release-notes/unreleased.md`
   **When** this story merges
   **Then** it says that a key typed during the test builds no longer unlocks Pro, and where to buy a license

## Boundaries

- Must not change: the tiers and the command registry, the one-zip rule, the stored settings keys, the free-tier behavior.
- Out of scope: a self-hosted license server; a separate Pro zip; site packs or lifetime plans beyond what the provider offers natively.

## References

- decision — docs/adr/0006-license-provider-ships-as-stub.md (the seam this story fills)
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (per-site license, one zip)
- code — post-quality-manager/includes/class-pqm-license.php (the only class whose internals change)
- code — post-quality-manager/admin/class-pqm-admin.php, License section (the free-state copy and the key field)
- deferred — _bmad-output/initiative-post-quality-manager/deferred-work.md, "Real Freemius licensing integration"
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC6: the freemium rules the review team checks)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). Whether it gates epic-delivery-pipeline entry 4.8 is story 43's D1.
- The provider account, plans and prices are owner work and external (hitl); the plugin-side build starts once a sandbox product exists.
- Plan-time choice: the provider's own activation UI (if it ships one) or the existing key field; the existing field is the minimal-code default.
- Decision (owner, 2026-10-09, spike 43, ADR 0013): provider Freemius; one yearly Pro plan in 1 / 5 / unlimited site packs; USD and EUR; no card-less trial; 14-day money-back guarantee; lapse returns the site to Free and keeps stored settings; the Freemius opt-in is allowed as the telemetry source, opt-in only. This story now gates epic-delivery-pipeline entry 4.8 (D1) as well as story 38.
- Decision (owner, 2026-10-09, ADR 0013): seller = the owner as a private person (Vincent Heimann); prices 49 / 99 / 199 a year for 1 / 5 / unlimited sites, USD and EUR; support hello@postquality.com within 2 business days; policy URLs on postquality.com (story 45). The Freemius product can be created with these.
- To verify at plan time: whether Freemius offers a sandbox or test mode for AC1 (its pricing page, read 2026-10-09, does not say); otherwise a real purchase refunded within the guarantee.
