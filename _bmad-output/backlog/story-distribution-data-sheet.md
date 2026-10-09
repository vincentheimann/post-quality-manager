---
id: 47
type: story
title: "A distribution data sheet lists every field each platform needs, with its value or its owner"
parent: none
covers: [UV7, O9]
after: []
assignee: ""
refined: false
hitl: true
risk: low
---

# A distribution data sheet lists every field each platform needs, with its value or its owner

## Description

The public release touches four platforms, and each asks for a set of fields: the WordPress.org directory (account and readme header), the licensing provider (product setup), GitHub (the public repository's front) and the website. Today those fields are spread across the plugin header, `docs/brand.md`, story 38 and the owner's head, and nobody can say which are still blank. This story writes one file, `docs/distribution.md`, listing every field per platform with its current value or an `OWNER:` marker naming what the owner must supply or decide (most of them through story 43). Stories 38, 44, 45 and 46 read their values from it and write back what they settle; `docs/brand.md` § Naming stays the canon for the name, slug and tagline, and the sheet points at it rather than copying.

## Acceptance Criteria

Verify: `docs/distribution.md` exists with one section per platform and one line per field, each with a value, a pointer to the canon that holds it, or an `OWNER:` marker; at minimum it covers — WordPress.org: the owner's wordpress.org username (readme `Contributors`), `Plugin Name`, slug, short description, up to five tags, `Requires at least`, `Tested up to`, `Requires PHP`, `Stable tag`, `License` and `License URI`, `Donate link`, the sources of Description / Installation / FAQ / Screenshots / Changelog, the External services text, the asset file list, the support forum, and the SVN deploy credentials as a GitHub secret name — Licensing provider: account, product name, plans, prices, currencies, trial and refund setting, support contact, terms / privacy / refund URLs, SDK version, opt-in wording — GitHub: repository description, topics, website URL, release-note conventions, branch protection — Website: domain, pages — Plugin header: `Plugin URI`, `Author`, `Author URI`, `Description`; the README's layout section lists the file; an `OWNER:` marker is never silently replaced by an invented value.

## Boundaries

- A document only; no plugin code, no accounts created.
- Does not decide anything: blanks stay blanks until story 43, 45 or 46 fills them.

## References

- naming — docs/brand.md § Naming (the canon the sheet points at)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC2 and AC5: the readme fields and the asset list)
- external — https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/ (the readme header fields)
- external — https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/ (icon, banner and screenshot names)

## Notes

- Filed 2026-10-09 (release-readiness pack, see story 43). First of the pack to run: it surfaces the blanks the owner must fill, and needs nothing before it. HITL only for the values the owner supplies.
- Decision (owner, 2026-10-09, spike 43): the licensing-provider section takes its settled values from ADR 0013 (Freemius, plans, currencies, refund, support channels) and takes the seller (Vincent Heimann, private person), the prices (49 / 99 / 199 a year), the mailbox (hello@postquality.com, 2 business days) and the domain (postquality.com) from the same ADR. The one `OWNER:` marker still open on 2026-10-09: the wordpress.org username for `Contributors`.
