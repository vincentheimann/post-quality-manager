---
id: 59
type: bug
title: "Every Upgrade link Freemius renders inside WP Admin leads to the Pro checkout"
parent: none
covers: [O9]
after: []
assignee: ""
refined: true
hitl: false
risk: low
severity: P2
---

# Every Upgrade link Freemius renders inside WP Admin leads to the Pro checkout

## Description

On a site without a Pro license, the Upgrade tab and buttons of the plugin's Account page, and the "Upgrade now" link of the expired-license notice, open a WordPress "Sorry, you are not allowed to access this page" screen instead of a place to buy Pro. The one working upsell is the "Get Pro" link in Settings, which goes to the public pricing page. Every Upgrade link Freemius renders should reach the Pro checkout, in WP Admin or on postquality.com.

## Reproduction

1. Activate the Free build (or the Pro build without a license key) as an Administrator.
2. Open Content Quality → Account, the page Freemius adds.
3. Click the Upgrade tab, or the Upgrade button beside the plan.
4. Observed: WordPress answers "Sorry, you are not allowed to access this page." The address is the plugin's dashboard slug with `-pricing` appended, a page nothing registers.
5. Expected: a page where Pro can be bought at the ADR 0013 prices.

The same link sits in the SDK's expired-license and expired-trial notices ("Upgrade now"). Owner report 2026-10-13: "the Upgrade button within the plugin menu is not working"; the exact button and screen the owner met are confirmed at refinement (Open question below).

## Cause Hypothesis

The plugin's Freemius init turns the pricing submenu off, which also removes the SDK's Upgrade action link on the Plugins screen. The Account page and the lapse notices do not check that switch: they build their Upgrade link from the SDK's pricing URL, which points at the in-admin pricing page the plugin never registers. The plugin applies no SDK filter for the pricing URL, so the link has nowhere to go.

## Acceptance Criteria

1. **An Upgrade link always reaches the checkout**
   **Given** a site without a Pro license
   **When** any Upgrade link Freemius renders is followed (the Account page's tab and buttons, the expired-license and expired-trial notices)
   **Then** the reader lands on a page where Pro can be bought at the ADR 0013 prices (the Freemius pricing page in WP Admin, or the public pricing page), never on a "not allowed" screen
2. **A licensed site shows no dead link**
   **Given** a site with an active Pro license
   **When** the Account page renders
   **Then** every link it shows resolves to a page that exists
3. **Tests cover the condition found and fixed**
   **Given** the harness sweep
   **When** it runs
   **Then** a check proves that every admin URL the plugin's Freemius configuration exposes points at a registered admin page or an external address, and fails on the defect
4. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the expected behavior already holds, with the evidence recorded in Notes; this supersedes 1 to 3

## References

- parent — none
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (prices, plans, the public home)
- licensing — _bmad-output/backlog/story-real-license-unlocks-pro.md (story 44: its last step releases the Freemius plans and adds the live checkout link)
- builds — _bmad-output/backlog/story-free-build-contains-no-pro-code.md (story 54: the Free build this reproduces on)
- data — docs/distribution.md, section Licensing provider: Freemius (product and plan ids, checkout links)
- memory — memory/freemium-and-roles.md (one Get Pro link on Free, locked-section copy)

## Notes

- Filed 2026-10-13 (owner). Severity P2: Pro cannot be bought from these links, but the Settings "Get Pro" link works. Risk low: a wrong link shows at once.
- Two ways to settle at the plan checkpoint, the owner decides: (a) point the SDK's pricing URL at the public pricing page through the SDK's own filter, consistent with the Settings link; the SDK then also renders a plain external submenu item when the pricing item is switched on; (b) switch the SDK's pricing page on inside WP Admin, which renders Freemius's plan table and checkout without leaving the site, and needs the Freemius plans released (story 44's last step) to show anything.
- Open question: which Upgrade the owner clicked and what the screen said; the reproduction above is the agent's reading of the SDK on 2026-10-13, not a walk.
- Assumption: the report concerns the Free build; the Pro build without a license shows the same links.
- Path for the builder: the Freemius init lives in the main plugin file (`menu.pricing` false); the SDK's `pricing_url` and `is_pricing_page_visible` filters are the hooks.
- Related: story 61 (in-plugin nudges) starts from this link working; story 44's owner pass walks the lapse copy whose link this fixes.
- Refined 2026-10-24 (Opus 5.5, by hand from the workspace root; the owner delegated the choices: "make choices based on best practices"). Reproduction confirmed from SDK 2.13.4 source instead of a walk: the Account page's Upgrade tab and plan buttons (`templates/account.php`), the expired-license and expired-trial notices, the updater's renewal line and the version-upgrade notice all call `get_upgrade_url()` / `pricing_url()`, which build `admin.php?page=pqm-dashboard-pricing`. Corrected by the build's review (same day, SDK source): `menu.pricing` false only hides the menu entry; once the SDK adds its submenu items it still registers that page hidden, so outside activation mode the links opened Freemius's in-admin pricing page, empty until story 44 releases the plans. The "not allowed" screen appears in activation mode (Pro waiting for its key, Free before the opt-in choice), where the SDK registers no page. The open question (which Upgrade the owner clicked) no longer changes the fix: every one of them routes through the same URL builder. Details in `memory/freemium-and-roles.md`.
- Decision (owner delegation, 2026-10-24): option (a). The SDK's `pricing_url` filter returns the public pricing page, the one place prices live (ADR 0013) and the target of the Settings "Get Pro" link; both now read one constant. Option (b) would put Freemius's plan table inside WP Admin, which shows nothing until story 44 releases the plans and gives the site and the plugin two pricing pages to keep in step. Before launch the pricing page carries the interest email instead of Buy links; that is the honest state of a product not yet on sale, and the same page the Settings link opens today.
- Story 65 (the opt-in notice after Free → Pro) is not part of this build: its criteria need the owner's Local walks with the Freemius sandbox; bundle them with story 44's open owner pass.
