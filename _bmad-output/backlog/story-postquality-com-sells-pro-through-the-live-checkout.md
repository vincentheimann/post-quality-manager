---
id: 92
type: story
title: "postquality.com sells Pro: every buy link opens Freemius's live checkout"
parent: none
covers: [UV7, O9]
after: [38]
assignee: ""
refined: true
hitl: true
risk: high
---

# postquality.com sells Pro: every buy link opens Freemius's live checkout

## Description

On the day the WordPress.org listing goes live (day L in the launch plan), postquality.com switches to its launched state: the pricing page offers the three Pro packs with a buy link per currency, and the home page carries the version line and the link to the directory listing. The checkout itself stays Freemius's hosted page, one plain link per pack and currency, as story 86 built it behind the launch flag. The agent prepares the site's pull request; the owner merges it and proves one live purchase before the launch is announced.

## Acceptance Criteria

1. **The pricing page sells the three packs**
   **Given** the launched site
   **When** a visitor opens the pricing page
   **Then** it shows the 1, 5 and unlimited site packs at 49, 99 and 199 a year, each with one buy link in the currency shown (USD by default, EUR and CHF on choice), and no "Tell me when it's available" button
2. **Every buy link opens the live checkout of its pack and currency**
   **Given** the nine buy links
   **When** each is opened
   **Then** Freemius's checkout shows Post Quality Manager Pro, that pack's number of sites, annual billing and that price in that currency, in live mode (no sandbox marker such as "Prefill Form (Only visible in Sandbox mode)")
3. **The prices agree everywhere they are stated**
   **Given** the launched site and the Freemius dashboard
   **When** the pricing page, the structured data offers and the dashboard's Pro pricing are compared
   **Then** every pack has the same price and currencies in all three
4. **The home page points to the release and the listing**
   **Given** the launched site
   **When** a visitor opens the home page
   **Then** the version line names the released version, What's new opens its changelog, and Install from WordPress.org opens the plugin's directory listing
5. **One live purchase completes before the launch is announced**
   **Given** the launched site
   **When** the owner buys a pack from a buy link in live mode
   **Then** Freemius emails the license key and the Pro download, the license activates Pro on a test site, and the purchase, license and any coupon are removed or refunded afterwards
6. **The site can go back in one change**
   **Given** a buy link that fails criterion 2 or 5
   **When** the launch flag is set back to false
   **Then** the site shows its pre-launch pricing again, with no buy link

## Boundaries

- Must not change: the hosted-link checkout (no Freemius checkout script, no cookie, no other third-party code on any page, ADR 0013 D8); the prices and pricing IDs (`docs/distribution.md`); the pre-launch state of every page.
- Out of scope: the interest emails of day L (owner, launch plan); the wording of Freemius's own checkout, emails and customer portal (story 62); an email capture for visitors not ready to buy (story 63).

## References

- launch plan — docs/distribution.md, § Launch plan (story 49), row L "postquality.com"
- data — docs/distribution.md, rows Hosted checkout links, Release plans
- decision — _bmad-output/backlog/story-site-content-style-checkout-best-practices.md, Notes (owner, 2026-10-22: plain hosted links, no checkout script)
- audit — _bmad-output/backlog/story-site-content-style-checkout-best-practices-audit.md, open question 3 (links, overlay, or links now and overlay later)
- build — _bmad-output/backlog/story-site-navy-and-check-plan-2.md (the pricing page's launched state)
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8: no tracking)
- decision — docs/adr/0016-funnel-read-from-existing-sources-site-unmeasured.md (the funnel read from Freemius, the site unmeasured)

## Notes

- Decision (owner, 2026-10-22): plain hosted checkout links, one per pack and currency, no checkout script on the site. The audit's option (c), the overlay later if conversion data argues for it, stays open; Freemius's carts report checkout visits and abandoned carts for hosted links too (spike 64 findings), so that data exists after launch without a script.
- Decision (owner, 2026-10-28): the plans are released (`docs/distribution.md`, Release plans); the nine links were checked in the dashboard the same day, plans then unreleased (row Hosted checkout links).
- Decision (owner, 2026-10-28, ticket approved): this runs on day L, after story 38's listing goes live, as the launch plan says; Pro does not go on sale on day 0 (GitHub release only).
- Open question: how criterion 5 is proved without a real charge: a single-use 100% coupon, if Freemius completes a free order through the hosted checkout, or a real purchase refunded within the 14-day window. Either way the license, the coupon and the payment are listed and removed (owner rule 2026-10-20, chore 81).
- Risk high (payments): the check outside the criteria is the owner's live purchase in criterion 5, done by a person before the launch is announced.
- Path for the builder (recorded at the owner's convention, not a reference): the site repository's `_config.yml` (`launched` and the five launch values) and its README section "Launch flag".
