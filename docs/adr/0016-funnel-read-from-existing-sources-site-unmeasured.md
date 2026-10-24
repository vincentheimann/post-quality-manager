# 0016. The funnel is read from WordPress.org, Freemius and GitHub; postquality.com stays unmeasured

- Status: accepted
- Date: 2026-10-24

## Context

ADR 0013 D8 made the Freemius opt-in the A1/A2 signal source, and the privacy policy promises a site with no cookies and no analytics. Stories 61, 62 and 63 add nudges whose effect nobody could read: postquality.com is a static site on GitHub Pages, which gives its owner no visit figure, and every "Get Pro" link in the plugin opens the site's pricing page. Spike 64 asked how the funnel (visit → install → opt-in → first use → purchase) can be measured within the promise. Its findings are in `_bmad-output/backlog/spike-funnel-measurement-without-tracking-findings.md`.

Alternatives weighed for site visits:
- No measurement.
- A cookieless counter: GoatCounter (free, EU), Plausible, Simple Analytics, Umami, the Cloudflare beacon, or Cloudflare's server-side counts.
- A consent banner with analytics.

Every counter that runs a script or loads a pixel falls under Art. 5(3) of the ePrivacy Directive (EDPB Guidelines 2/2023), so it would need consent under some national laws. Swiss law asks for a notice and an opt-out. Any counter ends the "no analytics" promise, and a banner ends "no cookies" too.

Alternatives weighed for attribution: a distinct checkout coupon per entry point, which ADR 0013 D4 rules out (no coupons) and a 0% coupon is unverified; the page named in the interest email; or totals only.

## Decision

The funnel is read from the sources that already report it. postquality.com stays unmeasured, and the promise stays as worded.

- **Visit:** blind. Indirect signals are the repository's top referrers (GitHub traffic, 14 days) and the interest emails, whose body already names the page they were sent from (story 86).
- **Install:** GitHub release downloads until the listing (running totals, snapshotted), then WordPress.org downloads per day, the active-install band and version shares.
- **Opt-in and activation:** the Freemius dashboard. The opt-in rate is only an estimate against WordPress.org's active installs, since Freemius sees only opted-in sites.
- **First use:** blind; `epic-ebm-telemetry` (local-only) is where it is revisited.
- **Purchase:** Freemius payments, subscriptions and carts, read as totals per period. No coupon per entry point; ADR 0013 D4 stands.
- **Cadence:** weekly, and the GitHub traffic figures before their 14-day window closes.

## Consequences

- Easier: the privacy policy, the site and the plugin need no change; no new vendor, script, banner or cost.
- Harder: no nudge on the site or in the plugin can be read on its own. Stories 61, 62 and 63 judge their effect by before-and-after totals, which a small audience makes noisy. The GitHub traffic figures are lost unless someone reads them every two weeks.
- Committed to: `docs/user-paths.md`'s A1 and A2 Signal lines name these sources. A counter, a coupon per entry point, or a change to the promise needs a new ADR that supersedes this one.
- Extends ADR 0013 D8; supersedes nothing.
