---
id: 138
type: bug
title: "A successful update reports a failed ping when n8n's first run is slow"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P2
estimate: ""
---

# A successful update reports a failed ping when n8n's first run is slow

## Description

When Update workflow replaces the workflow in n8n, the plugin pings the new version and waits 8 seconds for n8n to call the site back. If that first run of the updated workflow takes longer, the update is reported as a failure: the reply says n8n "did not call this site back within 8 seconds" and blames the site's address ("a local, private or password-protected site cannot be called back"), and the n8n Workflow page turns to its failing state. The update itself worked, and a Test again a minute later passes. The user should see the update succeed, or at least not be told that their site cannot be reached.

## Reproduction

1. Local WordPress 6.6, Free 4.0.5 provisioned by key against a disposable n8n 2.48.5 (Free template v11); then the Pro 4.0.5 build activated over it and licensed (story 38's N4 frame, 2026-11-19).
2. n8n Workflow page → Update workflow.
3. Actual: the update passes (the 31-node Pro template, the WordPress credential created and linked, the setup map gains `wordpress` and `application_password`), but the reply is `updated` true, `ping_ok` false: "The workflow was updated in n8n. n8n accepted the ping but did not call this site back within 8 seconds. n8n must reach http://wordpress66.local/wp-json/ (this site's REST API): a local, private or password-protected site cannot be called back. Check the ping's execution in n8n, then test again." The page reads "The last test just now did not pass…" and the Dashboard keeps its "needs attention" notice until a Test again.
4. n8n's executions: the replaced Free version answered first in 0.09 s and was retried (bug 99, as intended); the first run of the updated version took 8.2 s; every later ping took 0.1–0.2 s, and so did the first ping of a freshly created Pro workflow. Test again about a minute later passed in 0.7 s; a Review and a Rewrite then landed.
5. Expected: the update reports success with the new version (or bug 99's "n8n is still switching…"), as plan L125 of bug 113 says: "updated, ping OK".
6. Seen once; repeating it needs a new Free setup. The code path makes it repeatable whenever n8n's first run after an update outlasts the wait.

## Cause Hypothesis

`PostQuality_Rest_API::ping_after_activation()` (`includes/class-pqm-rest-api.php` around L1694) retries a ping right after a publish only on a 404 or an answer from the older template (`answered_older()`); a ping the new version accepted but whose callback came after `PostQuality_Admin::PING_WAIT` (8 s, `admin/class-pqm-admin.php` around L2299) counts the first time. n8n's first run after a public-API PUT is slower than later runs (8.2 s against 0.1–0.2 s on 2.48.5), so the ping that follows an update meets it. Provisioning calls the same function, so a slow first run of a freshly created workflow would fail the same way (it took 0.19 s on 2.48.5). The timeout sentence (`admin/class-pqm-admin.php` around L2370) names reachability as the cause even when the callback arrived a moment later.

## Acceptance Criteria

1. **A slow first run after an update does not fail the update**
   **Given** an Update workflow whose first ping is accepted by the new version and whose callback comes after the wait
   **When** the update replies
   **Then** it does not report the update or the round trip as failed while the callback is only late, and the page does not turn to its failing state
2. **The same holds after provisioning**
   **Given** a provisioning whose first ping meets the same slow first run
   **When** the setup step replies
   **Then** it behaves as criterion 1 says
3. **A site n8n really cannot call back still fails, with a bounded wait**
   **Given** a site n8n cannot call back (local, private or password-protected)
   **When** the update or provisioning pings it
   **Then** the reply still says the callback did not arrive, and the total wait stays within a stated ceiling
4. **The Test button is unchanged**
   **Given** the Test again button, `POST /setup/connect` and the token rotation
   **When** they ping
   **Then** they ping once, as in 4.0.5
5. **Tests cover the condition found and fixed**
   **Given** the harnesses
   **When** they run
   **Then** a test that fails on the defect and passes on the fix covers a late callback right after an update and after provisioning
6. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** the update already reports success, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 5

## References

- parent — none
- source — `_bmad-output/backlog/story-wordpress-org-directory-listing-plan.md`, pass list row P030 (and P031) and the N4 entry (2026-11-19)
- related — bug 80 (ping retried on a 404 after activation), bug 99 (the replaced version's answer retried), bug 113 (Update workflow after Free → Pro; its plan L125 is the check that failed)
- logs — the N4 frame's stage B report, session scratchpad (not committed): n8n executions of the update's pings, 0.09 s (replaced version) and 8.2 s (first run of the new one)

## Notes

- Decision (owner, 2026-11-19): found in story 38's pre-production pass; it blocks submission: it joins story 38's `after` and ships in a 4.0.x release, as bugs 132, 135 and 137 did; P030 is re-walked on that release.
- Fact: n8n 2.48.5's first run after a public-API PUT took 8.2 s, later runs 0.1–0.2 s (one observation, 2026-11-19); it belongs in `memory/n8n-provisioning.md` with the frame's other n8n findings.
- Assumption: severity P2 — the update works and Test again recovers it, but the first thing a Free → Pro upgrader sees is a red failure that blames their site's address. Risk medium — the ping that provisioning and the update share.
- Open question: whether the fix waits longer for the callback, retries the ping on a late callback, or reports a late callback as "still switching"; the plan weighs each against criterion 3's ceiling.
