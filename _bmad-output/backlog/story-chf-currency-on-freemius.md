---
id: 52
type: story
title: "CHF is offered as a third checkout currency on Freemius"
parent: none
covers: [O9]
after: [43]
assignee: ""
refined: false
hitl: true
risk: low
---

# CHF is offered as a third checkout currency on Freemius

## Description

Owner request (2026-10-09): the Pro plan is also priced in Swiss francs. ADR 0013 D5 set USD and EUR; this story adds CHF as a third currency on the Freemius product. Freemius supports CHF (its multi-currency page, read 2026-10-09, lists USD, EUR, GBP, CAD, AUD, ILS, PLN, CHF and RSD); prices are entered per plan and per currency by hand ("+ Add Currency" copies the USD figures, which are then edited); CHF revenue is converted to USD at purchase time and paid out from the USD balance, since only USD, EUR and GBP have a payout balance of their own. Nothing in the plugin changes.

## Acceptance Criteria

Verify: the Freemius Pro plan lists CHF beside USD and EUR with the three site-pack prices (default: the same figures, 49 / 99 / 199, unless the owner sets others at the sitting); a test checkout with the currency switched to CHF shows the CHF figures; ADR 0013 D5, `docs/distribution.md` (Currencies row) and the pricing page content of story 45 name the three currencies; the refund and terms texts of story 46 are checked and need no change if they name no currency.

## Boundaries

- No plugin code; a Freemius dashboard setting plus the documents that quote it.
- No other currency (GBP and the rest wait for demand); no CHF-specific pricing beyond the figures the owner sets.

## References

- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D5, amended 2026-10-09)
- product setup — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44: the sitting where the Freemius product is created)
- sheet — docs/distribution.md § Licensing provider
- external — https://freemius.com/help/documentation/selling-with-freemius/multi-currency/

## Notes

- Filed 2026-10-09 (owner). Done in the same Freemius sitting as story 44's product setup; filed apart so the ADR amendment and the CHF figures have a ticket of their own.
- Assumption: the same figures in CHF as in USD and EUR, following D5's "same figures" rule; the owner can set other CHF figures at the sitting.
- Payout: CHF sales land in the USD balance after conversion at purchase; there is no separate CHF payout.
- Not ranked by `/prioritize` yet.
