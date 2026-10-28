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

- Must not change: the tiers and the command registry, the stored settings keys, the free-tier behavior.
- Out of scope: a self-hosted license server; the stripping markers, the Free template and the CI check on the Free build (story 54, ADR 0014); site packs or lifetime plans beyond what the provider offers natively.

## References

- decision — docs/adr/0006-license-provider-ships-as-stub.md (the seam this story fills)
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (per-site license)
- decision — docs/adr/0014-two-builds-from-one-source.md (the SDK is initialised for two builds; the stripping itself is story 54)
- code — post-quality-manager/includes/class-pqm-license.php (the only class whose internals change)
- code — post-quality-manager/admin/class-pqm-admin.php, License section (the free-state copy and the key field)
- deferred — _bmad-output/initiative-post-quality-manager/deferred-work.md, "Real Freemius licensing integration"
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC6: the freemium rules the review team checks)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). Whether it gates epic-delivery-pipeline entry 4.8 is story 43's D1.
- The provider account, plans and prices are owner work and external (hitl); the plugin-side build starts once a sandbox product exists.
- Finding (agent, 2026-10-09, read at https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/): guideline 5 forbids trialware: "Plugins may not contain functionality that is restricted or locked, only to be made available by payment or upgrade", and guideline 6 refuses a service that "exists for the sole purpose of validating licenses or keys while all functional aspects of the plugin are included locally"; the team recommends excluding the premium code from the directory build. The one-zip, visible-but-locked model (ADR 0003, story 38 AC6) is therefore not listable as is. Owner decision needed before this story is planned; the standard answer is Freemius's two builds from one source (free build for the directory without the paid code and template nodes, Pro build served by Freemius with the license), which supersedes "one zip" by a new ADR.
- Plan-time choice: the provider's own activation UI (if it ships one) or the existing key field; the existing field is the minimal-code default.
- Decision (owner, 2026-10-09, spike 43, ADR 0013): provider Freemius; one yearly Pro plan in 1 / 5 / unlimited site packs; USD and EUR; no card-less trial; 14-day money-back guarantee; lapse returns the site to Free and keeps stored settings; the Freemius opt-in is allowed as the telemetry source, opt-in only. This story now gates epic-delivery-pipeline entry 4.8 (D1) as well as story 38.
- Decision (owner, 2026-10-09, ADR 0013): seller = the owner as a private person (Vincent Heimann); prices 49 / 99 / 199 a year for 1 / 5 / unlimited sites, USD and EUR; support hello@postquality.com within 2 business days; policy URLs on postquality.com (story 45). The Freemius product can be created with these.
- Freemius product created 2026-10-09 (IDs, public key, sandbox checkout link and plan unique name `pro` in `docs/distribution.md`); plans stay unreleased until this story's last step. AC1 uses the sandbox checkout. The SDK is vendored at a tagged release, pinned in the plan.
- Amended (owner, 2026-10-09): CHF joins USD and EUR as a checkout currency (story 52, ADR 0013 D5 amended); set it in the same product-setup sitting.
- Decision (owner, 2026-10-09, ADR 0014): two builds from one source. This story integrates the SDK with the two-build settings (`is_premium`, the Pro suffix, the Free build's update source) and keeps AC3's single "Get Pro" place; the markers that strip the paid code, the Free template and the CI check on the Free build are story 54, which sits between this story and story 38. AC5 is run on the Pro build here; story 54 runs it on the Free build.
- Built 2026-10-09 (plan beside this file): SDK 2.13.4 vendored, `PQM_License` reads `can_use_premium_code()`, License section shows Pro / lapsed / Free + "Get Pro", uninstall behind the SDK's `after_uninstall` hook. Pro plan expiration confirmed as "Block features after expiry" through the Chrome agent the same day (AC2's dashboard half). Open: the owner's Local pass (AC1, AC2 opt-in skip, lapse, delete) and the final step, releasing the plans.
- Decision (owner, 2026-10-28): the plans are released ahead of this story's Local pass, because Freemius refuses every license activation while they are unreleased (`docs/distribution.md`, Release plans). The setting checked at the same time: "Unlimited localhost license activations (recommended)" on, "Block features after expiry" selected.
- Local pass on the showcase (agent walks, owner hitl, 2026-10-28; the Pro build of main plus story 65's branch): AC1 passed. A sandbox license bought with hello@postquality.com, typed on the SDK screen, unlocks Pro (`is_pro()` true, the License section "Current plan: Pro — all agents, per-agent overrides, scheduled reviews and role settings are unlocked.", the Automation and Roles tabs, paid agents allowed); a made-up key gets "Invalid license key." and the site stays unlicensed. Skipping the opt-in leaves the plugin on Free (story 65's walks).
- Finding (agent, 2026-10-28, AC2 walked): with the test license's expiration moved to 2026-10-27 in the dashboard and the SDK's daily sync run (`_sync_cron_method()`), the site is Free (`is_pro()` false, paid agents refused) and the override and weekly schedule saved through Settings survive (same settings fingerprint, the cron event still scheduled). But the SDK detaches the expired license from the site (`license_id` null, plan free), so `PostQuality_License::has_lapsed()`, which reads this site's own license, answers false: the License section shows "Current plan: Free — Review and Key Takeaways are included. Get Pro", not the lapse copy with a renew link AC2 asks for. The SDK adds its own notice, "Hmm... Your license has expired. You can still continue using the free plugin forever.", with no link seen. A lapsed site keeping its weekly `review` runs is by design (ADR 0013 D7: "scheduled reviews can be switched off"; `tests/harness/cron-gate-harness.php` Row 1). The missing lapse copy is bug 93 (owner, 2026-10-28). Deletion (the manual check) not walked yet.
- Deletion (the manual check) passed 2026-10-28 on both builds (agent, WP-CLI as user 1, the deactivation first so the SDK registers its uninstall hook). Pro, from the lapsed state with the weekly schedule: the deactivation cleared the schedule; the deletion removed the reviews table, the 4 options and the 11 role capabilities, and Freemius received one request (`is_premium`, `uid`, no reason). Free, in bug 90's walks: the same, nothing left of the plugin. AC2's lapse copy and renew link: bug 93.
