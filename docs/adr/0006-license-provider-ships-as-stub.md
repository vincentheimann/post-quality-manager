# 0006. The license provider ships as a stub until Freemius is set up

- Status: accepted
- Date: 2026-10-02

## Context

ADR 0003 picked Freemius as the hosted licensing provider behind `is_pro()`. When story 1.3 of epic-freemium-and-roles came up, no Freemius product existed and the owner wanted plugin-side development finished before any third-party setup ("third parties at the end", ticket note 2026-10-01). The epic retrospective flagged that this deviation was recorded in code, memory and deferred-work, but no ADR said it — leaving ADR 0003 reading as if Freemius shipped. Alternatives: block the epic on Freemius setup (serializes plugin work behind an external account), or ship a format-checked fake key (invented complexity protecting nothing).

## Decision

`PQM_License::is_pro()` ships as a stub: **any non-empty `license_key` flips the site to pro** (owner, 2026-10-02). There is nothing to protect until a paid product exists; the gate architecture, the key UI and the sanitizer carry rules are real and final — only the validation is placeholder. Real Freemius integration replaces the internals of `PQM_License` alone (`deferred-work.md`, trigger: end of epic / start of third-party distribution).

## Consequences

- Easier: the epic completed without external dependencies; the same zip and UI will serve real licensing unchanged.
- Harder: until Freemius lands, "pro" is self-service for anyone who can edit settings — acceptable while distribution is GitHub Releases and there is no paid product; the release that ships real licensing must carry the migration/release notes already listed in `deferred-work.md`.
- Does not supersede ADR 0003 — Freemius remains the chosen provider; this records the interim state ADR 0003 left implicit.
