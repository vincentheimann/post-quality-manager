---
id: 93
type: bug
title: "A lapsed Pro license shows the site as Free, with no way to renew from WP Admin"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
severity: P2
---

# A lapsed Pro license shows the site as Free, with no way to renew from WP Admin

## Description

When a customer's Pro license expires, Settings → License says "Current plan: Free — Review and Key Takeaways are included. Get Pro", as on a site that never bought Pro. Story 44's criterion 2 asks for the lapse copy with a renew link instead. The Freemius notice shown beside it, "Hmm... Your license has expired. You can still continue using the free plugin forever.", carries no link either, so a customer who wants to keep Pro finds no renewal path in WP Admin. The person's part: expiring a test license in the Freemius dashboard.

## Reproduction

Walked 2026-10-28 on the showcase site (Pro build of main 8502d84 plus story 65, Freemius SDK 2.13.5):
1. The site opted in and a sandbox Pro license activated on it: the License section reads "Current plan: Pro — all agents, per-agent overrides, scheduled reviews and role settings are unlocked."
2. In the Freemius dashboard, the license's expiration set to the day before (Licenses → the license → Extend → an earlier date; the dashboard then lists it under Expired).
3. The SDK's daily sync run at once through WP-CLI: `postquality_fs()->_sync_cron_method( array( get_current_blog_id() ) )` (otherwise it runs on its own within a day).
4. Settings → License, and any admin page.

What happens: `PostQuality_Features::is_pro()` false (correct); the License section shows the Free copy with "Get Pro"; the SDK's notice above says the license expired, with no link. The site's own license is gone from the SDK's install record (`license_id` null, plan `free`); `has_any_license()` is true.
What should happen: the License section says the license lapsed and offers a renew link (story 44 criterion 2, ADR 0013 D7).

## Cause Hypothesis

On expiry the SDK's sync detaches the license from the install, so `PostQuality_License::has_lapsed()`, which looks for this site's own license (`_get_license()`) and finds none, answers false, and the section falls through to the Free copy. The licenses the SDK still knows for the customer cover other sites too (the reason `has_any_license()` was not used, `memory/freemium-and-roles.md`, real licensing), so which license lapsed on this site has to be read from what the SDK keeps after the detach.

## Acceptance Criteria

1. **A lapsed site is told so, with a way to renew**
   **Given** a Pro site whose license expired or was cancelled, after the SDK's next sync
   **When** an Administrator opens Settings → License
   **Then** the section says the license lapsed and links to where it can be renewed, and the site still behaves as Free
2. **A site that never had a license is unchanged**
   **Given** a Pro or Free site that never activated a license, and a site whose customer holds a license on another site only
   **When** an Administrator opens Settings → License
   **Then** the section shows the Free copy with the one "Get Pro" link, as today
3. **Scheduled reviews can be switched off while lapsed**
   **Given** a lapsed site with scheduled reviews on
   **When** an Administrator turns them off and saves
   **Then** no scheduled review runs afterwards (ADR 0013 D7), and the other stored settings, overrides and schedules survive as today
4. **Tests cover the condition found and fixed**
   **Given** the harness sweep
   **When** it runs
   **Then** a row fails on the detached-license state and passes on the fix, and the rows for criterion 2's states pass on both
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the lapse copy and renew link already show, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- found by — _bmad-output/backlog/story-real-license-unlocks-pro.md, Notes (AC2 walked, 2026-10-28)
- decision — docs/adr/0013-pro-sold-through-freemius-before-public-release.md (D7 Lapse)
- memory — memory/freemium-and-roles.md (real licensing: `has_lapsed()`, `_get_license()` versus `has_any_license()`)
- walk — docs/local-verification.md and memory/local-verification.md (Freemius on Local)

## Notes

- Decision (owner, 2026-10-28): filed as the one defect of story 44's lapse walk. A lapsed site keeping its scheduled reviews is by design (ADR 0013 D7, `tests/harness/cron-gate-harness.php` Row 1), not part of this bug; criterion 3 checks only that they can be switched off.
- Open question: whether the Automation section of a lapsed Pro site lets an Administrator switch scheduled reviews off (D7) or renders locked; criterion 3 settles it.
- Open question: where the renew link points: the SDK's Account page (`get_account_url()`, the lapse link story 44 built) or Freemius's renewal checkout; the builder reads what the SDK offers for an expired license.
- Fixture (owner rule 2026-10-20, chore 81): the Freemius test user for hello@postquality.com and its expired sandbox license and subscription, created by story 65's walks on 2026-10-28, are kept for this bug's walk (extend the license, activate it on the showcase after a fresh opt-in, expire it again) and removed by the owner in the dashboard when this bug is done. Story 65's install record of the showcase is orphaned (the walk's dumps are deleted) and goes in the same cleanup.
- Risk medium: license state is read on every admin load and gates the upsell copy; the owner's walk with a real expiry is the check outside the harness.
- Decision (owner delegation, best practice, 2026-10-28): built before entry 4.8, because story 44, whose AC2 it carries, gates 4.8 (ADR 0013 D1); story 44 is marked done after this bug's walk. If this bug is the last open gate, 4.8 goes without it: plans are yearly with no trial (ADR 0013 D4), so no license can lapse within a year of the first sale, and the fix ships in the next release.
