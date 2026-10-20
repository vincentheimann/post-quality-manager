---
id: 82
type: bug
title: "Two provisioning requests sent at the same instant both pass the provisioning lock"
parent: none
covers: [UV4]
after: [72]
assignee: ""
refined: true
hitl: false
risk: medium
severity: P3
---

# Two provisioning requests sent at the same instant both pass the provisioning lock

## Description

Bug 72 gave provisioning a lock so that two runs started together make one workflow and one set of credentials. The lock holds for runs seconds apart (a second tab, a repeated click after the first answer), but two requests that reach the server at the same instant both claim it and both run. With a real Anthropic key the second run is stopped by n8n at activation (another workflow already listens on the site's webhook path, bug 75's sentence) and rolled back, so nothing is orphaned; the user still reads a refusal that names the wrong cause for what happened. The lock should refuse the second of two simultaneous runs the way it refuses a later one.

## Reproduction

Observed 2026-10-20 on the owner's Local sandbox (Pro build of main 57766d6, WordPress 6.x on MySQL, no persistent object cache), against the owner's n8n instance:

1. Site connected to n8n with a key, no setup map, no lock option.
2. Send two `POST /n8n/provision` requests with the same bogus Anthropic key from two processes started in the same millisecond.
3. Actual: both answer 400 `pqm_provider_key_invalid` after the same 6.3 s, so both passed the lock and both asked Anthropic. Expected: one answers 400 after asking Anthropic, the other 409 `pqm_already_provisioned` at once with no outgoing call.
4. Afterwards the lock option is gone and no map exists, as expected.

The harness row for two runs passes because its `add_option()` stub refuses a key that exists; the real one does not behave that way under this timing.

## Cause Hypothesis

WordPress core's `add_option()` is a read followed by a write: it reads the option, and when none is stored it runs an `INSERT … ON DUPLICATE KEY UPDATE`, which also succeeds when the row was inserted by another request between the read and the write (and answers "no change" only when the stored value is byte-identical). Two requests inside that gap both get `true`. The stale-lock takeover has the same shape (read the old `at`, then `update_option()`). The Options API offers no compare-and-set; the one atomic claim the database offers is a plain insert that fails on a duplicate key, or a conditional update, with the affected-row count read. Recorded in `memory/n8n-provisioning.md` (add_option finding, 2026-10-20).

## Acceptance Criteria

1. **The second simultaneous run is refused**
   **Given** a connected site without a setup map and without a lock
   **When** two provisioning requests reach the server at the same instant
   **Then** exactly one proceeds and the other answers 409 `pqm_already_provisioned` with no outgoing call, nothing written and nothing created, whatever the two requests' timing inside the claim
2. **A stale lock is taken over by one run only**
   **Given** a lock older than the ceiling (five minutes)
   **When** two runs start at the same instant
   **Then** one takes the lock over and proceeds, the other is refused
3. **Everything else as bug 72 left it**
   **Given** a run that finished, failed or was refused
   **When** the lock is read afterwards
   **Then** it is gone after a finished or failed run, untouched after a refused one, and every existing provisioning row still passes
4. **Tests cover the condition found and fixed**
   **Given** the n8n API harness
   **When** it runs
   **Then** its options stand-in behaves like core (a claim racing another claim is modelled, not idealised), a row drives two claims inside the race window and asserts one refusal, a row drives two takeovers of a stale lock and asserts one winner, and the existing lock rows still pass
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current code
   **Then** one of the two requests is refused before any outgoing call, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- predecessor — _bmad-output/backlog/bug-two-provisioning-runs-orphan-the-first.md (bug 72) and its plan's Implementation Notes (the walk of 2026-10-20 where this was observed, and its Review Triage Log, pass 1, second row)
- finding — memory/n8n-provisioning.md, "WordPress `add_option()` is not an atomic claim" (2026-10-20)
- the walk's backstop — _bmad-output/backlog/bug-activation-refusal-says-try-again.md (bug 75: the 409 at activation that stops the second run today)

## Notes

- Filed 2026-10-20 from the small-fixes walk. Severity P3: no data is lost or orphaned (n8n's path conflict stops the second run and the rollback cleans it); the user reads a misleading refusal for a race only two simultaneous requests can start. Risk medium: shared option code on the setup path, a mistake slips past review until two requests race.
- Decision (owner, 2026-10-20): build the follow-up rather than accept the backstop; the claim becomes atomic at the database, best practices applied (the Options API cache kept consistent after a raw write, the harness models the race honestly).
- Assumption: the fix stays inside `PQM_N8n` and the n8n harness; no new option, no REST or template change.
