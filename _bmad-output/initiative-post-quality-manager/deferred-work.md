# Deferred work — initiative post-quality-manager

Work consciously postponed during the initiative, with the decision that deferred it. Pick these up as backlog tickets when their trigger arrives.

## Real Freemius licensing integration (deferred 2026-10-02, story 3)

Story 3 shipped the license provider as a **stub**: `PQM_License::is_pro()` treats any non-empty `license_key` in `pqm_settings` as pro — owner decision of 2026-10-02 ("nothing to protect until a paid product exists; third parties at the end of the epic", ticket note of 2026-10-01). Still to do when the paid product becomes real:

- **Freemius SDK integration**, confined behind `PQM_License` — only that class's internals change; `PQM_Features::is_pro()`, the `pqm_is_pro` filter contract, gate callers, `why_not()`/`plan_allows()` all stay as they are.
- **Owner creates the Freemius product** (plans, pricing, license issuance) — plugin-side work cannot start before this exists.
- **Release notes / migration path** for installs the licensing flip turns free: any site that demoed pro via the stub (or the mu-plugin filter) and has no real license reverts to the free tier — the release notes must say so, and stored cron/override settings must keep surviving the flip (they do: sanitizer carry rules, story 5/6).

Trigger: end of `epic-freemium-and-roles`, when third-party distribution starts.
