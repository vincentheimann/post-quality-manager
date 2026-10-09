---
title: "CHF is offered as a third checkout currency on Freemius"
ticket: 52
status: done
---

Dashboard setting, no plugin code. Applied 2026-10-09 by the Chrome agent on the owner's prompt: CHF added to the Pro plan (ID 71340) with the three annual site-pack prices at the same figures as USD and EUR (49 / 99 / 199; Freemius had pre-filled converted figures 41 / 83 / 166, corrected). Pricing IDs 97905 (1 site), 97906 (5 sites), 97907 (unlimited). Read back after reload; the sandbox checkout shows Fr49.00 / Fr99.00 / Fr199.00 with the currency switched to CHF. Plans still unreleased.

Documents: `docs/distribution.md` (Currencies and Pricing IDs rows), the site's pricing page (`postquality.com/pricing.md`, CHF column), this ticket's Notes. ADR 0013 D5 was amended by the ticket's author (another session); the refund and terms texts name no currency and need no change.

Observed, no action: CHF is converted to USD at processing and paid out from the USD balance; PayPal is not offered in CHF, card only; the checkout displays its own "multi-site discount" line on the 5-site pack.

Status `built`; done is the owner's mark.
