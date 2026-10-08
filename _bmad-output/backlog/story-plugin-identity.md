---
id: 10
type: story
title: "The plugin's name, slug, tagline and wording rules are decided"
parent: none
covers: [UV7]
after: []
assignee: ""
refined: true
hitl: true
risk: low
---

# The plugin's name, slug, tagline and wording rules are decided

> **Delivered 2026-10-08:** the owner decided in session and the agent recorded it as the **Naming** section of `docs/brand.md` — full name *Post Quality Manager*, short form *Post Quality* (menu, cross-references), slug and text domain `post-quality-manager` kept (checked free on WordPress.org 2026-10-08, auto-derived from the header at submission), provider-neutral tagline (124 chars), page headings name the screen not the product, "Content Quality" retired as a name, "PQM" code-only. No code changed; story 41 applies the rules.

## Description

Owner request (2026-10-02); resliced 2026-10-08 (`/prioritize`, high priority — owner). The product calls itself two things: "Post Quality Manager" (plugin header, repo, slug, text domain) and "Content Quality" (admin page titles). The WordPress.org slug is permanent at submission, the text domain *is* the slug (pipeline entry 4.6 wires translations to it), and `readme.txt` carries the name and short description — so the naming is a one-way door the delivery pipeline is about to cement. This story is the decision sitting: the owner locks name usage, the slug, a tagline, and the wording rules (which surface uses which name), recorded as an owner-approved extension of `docs/brand.md`. Applying them — assets, imagery, the copywriting sweep — is story 41.

## Acceptance Criteria

Verify: `docs/brand.md` (or an extension it links) records, owner-approved: the name, the "Post Quality Manager" vs "Content Quality" resolution with the rule per surface (plugin header, admin menu, page titles, readme short description), the slug confirmation (expected: `post-quality-manager` stays — repo, text domain and releases already use it), and a tagline usable as the readme short description (≤ 150 chars). Decided before epic-delivery-pipeline entry 4.6 builds and before any `readme.txt` work.

## References

- docs/brand.md — the existing tone/copy canon this extends, never contradicts
- docs/user-paths.md — A1–A2 (discoverability), UV7 (distribution)
- applied by — _bmad-output/backlog/story-visual-identity-and-copy-sweep.md (story 41) and story 38 AC2/AC5 (readme, slug, trademark rules)
- cemented by — _bmad-output/initiative-post-quality-manager/epic-delivery-pipeline/tickets.toml entry 6 (text domain / languages folder)

## Notes

- Decision (owner, 2026-10-08, `/prioritize` D6): high priority — the naming kernel lands before pipeline entry 4.6 builds and before any readme work; the assets and the copy sweep move to new story 41 (after this one, blocking only story 38).
- Assumption: `docs/brand.md` stays the canonical brand home and is extended, not replaced.
- HITL: the owner decides name, slug, tagline; the agent drafts options and records the rules.
