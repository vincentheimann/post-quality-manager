---
id: 62
type: story
title: "The Freemius surfaces a buyer meets carry the brand's copy"
parent: none
covers: [O9]
after: [53, 60]
assignee: ""
refined: false
hitl: true
risk: low
---

# The Freemius surfaces a buyer meets carry the brand's copy

## Description

Between "Get Pro" and a working license, a buyer reads text that Freemius renders and the plugin never wrote on purpose: the plan name and feature bullets in the checkout, the opt-in screen's message at activation, the Account page, the license-activation screen, and the emails Freemius sends (purchase, license key, renewal, refund). Most are fields in the Freemius dashboard; a few are SDK filters. This story writes each with the copywriting skill (story 60) and sets them, so what the buyer reads there matches the site and the plugin. The dashboard fields are the owner's to fill (hitl).

## Acceptance Criteria

1. **The checkout says what Pro is**
   **Given** the Freemius checkout for any site pack
   **When** it opens
   **Then** the plan name, its feature bullets and the guarantee line match `docs/brand.md`, the messaging file and the refund policy, name the four paid agents and Automated Reviews, and name no product the buyer does not have
2. **The opt-in screen is the plugin's voice**
   **Given** the first activation
   **When** the Freemius opt-in screen shows
   **Then** its message says in the plugin's words what is shared and that skipping keeps the plugin working, consistent with `docs/policies/privacy.md` (ADR 0013 D8), and the plugin name is the only product named
3. **The emails read as ours**
   **Given** a purchase, a license delivery, a renewal reminder and a refund in the Freemius sandbox
   **When** each email arrives
   **Then** its sender name, subject and body follow the Copy Rules, link to the right public pages, and the support promise matches `docs/policies/support.md`
4. **The in-admin screens match**
   **Given** the Account and license-activation screens Freemius renders in WP Admin
   **When** they show
   **Then** every customizable string the SDK exposes follows the Copy Rules, and the ones it does not expose are listed in Notes as accepted
5. **Recorded where the next person finds it**
   **Given** `docs/distribution.md`
   **When** this story is done
   **Then** its Freemius section lists each field set, its text's source file and the date

## Boundaries

- Must not change: prices, plans, currencies and the guarantee (ADR 0013); what is shared through the opt-in (ADR 0013 D8); the policy texts (edit `docs/policies/` first).
- Out of scope: the plugin's own screens (story 61); the public site (story 57); releasing the plans and the live checkout link (story 44's last step).

## References

- parent — none
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D8 telemetry, prices, guarantee)
- data — docs/distribution.md, section Licensing provider: Freemius
- copy — docs/brand.md; the copywriting skill of story 60; _bmad-output/backlog/story-value-messaging-kpis.md (53)
- policies — docs/policies/privacy.md; docs/policies/refund.md; docs/policies/support.md
- licensing — _bmad-output/backlog/story-real-license-unlocks-pro.md (44: the sandbox and the owner pass this reuses)

## Notes

- Filed 2026-10-13 (owner).
- Assumption: Freemius lets the seller edit the email templates and the plan feature list from its dashboard; which strings the SDK exposes through filters is checked at refinement against the SDK source in `vendor/freemius`.
- Open question: whether the opt-in screen keeps Freemius's standard wording with the plugin name only (story 44's decision) or gets a custom message; the owner decides, and the privacy text follows.
- Risk low: dashboard text and filtered strings, visible at once.
- Input (2026-10-24, spike 64, ADR 0016): the effect is read as Freemius opt-ins against WordPress.org active installs, and abandoned against completed checkout carts, before and after the copy change.
