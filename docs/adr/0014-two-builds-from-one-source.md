# 0014. Two builds from one source: a Free build for the directory, a Pro build served by Freemius

- Status: accepted
- Date: 2026-10-09
- Supersedes the "one zip, one build" and "one n8n template" points of [0003](0003-freemium-plan-and-role-based-access.md)

## Context

ADR 0003 chose one zip for free and paid sites: every paid feature ships in the plugin and a license key flips the gate; paid commands render visible-but-locked with upgrade copy (backlog story 38 AC6). While preparing the WordPress.org listing (story 44 Notes, 2026-10-09) the directory's guideline 5 turned out to forbid exactly that: "Plugins may not contain functionality that is restricted or locked, only to be made available by payment or upgrade", with the review team's own advice to exclude the premium code from the directory build; guideline 6 refuses a remote service "for the sole purpose of validating licenses or keys" while the functionality is local, and guideline 8 forbids a directory plugin installing a premium version of itself from elsewhere. Upselling stays allowed, within guideline 11 (sparing, on the plugin's own pages, dismissible, untracked). The directory is where real users come from (A1); a rejection costs a review-queue cycle.

Alternatives weighed: (A) two builds from one source, Freemius's designed model: `__premium_only` markers tell its deployment which code to strip, the generated Free build goes to the directory, the Pro build is served by Freemius to licensed sites; (B) a separate Pro add-on plugin hooking into the Free one, two plugins to version and keep compatible; (C) staying off the directory and selling only from postquality.com, giving up the main free acquisition channel.

## Decision

One codebase, one runtime gate, two build outputs. The Free build contains no Pro code and is what WordPress.org hosts; the Pro build contains everything and is what Freemius serves.

- **Stripping is a build concern, not a source concern.** Paid code is marked with Freemius's conventions (`*__premium_only` methods and files, `@fs_premium_only` header list, `fs_premium_only` comment blocks in JS and CSS). `PQM_Features::registry()` stays the one command list; the Free build simply has no `tier: paid` entries, so the gate code, `why_not()`, the carry rules and `pqm_is_pro` are reused unchanged.
- **The n8n template is Pro code too.** The Free build ships a template without the Sources, SEO, Audit and Rewrite branches. The workflow-version handshake (`PQM_WORKFLOW_VERSION`) must recognise both variants.
- **Free is a complete product, judged alone.** Review and Key Takeaways, nothing greyed out or "coming in Pro". One "Get Pro" section on the Settings page links to the pricing page or the checkout; no notice, toast or dashboard widget.
- **Pro is never installed by the Free build.** After checkout the customer downloads the Pro build from Freemius and replaces the plugin; updates come from Freemius while the license is valid, from WordPress.org for Free. Both builds carry the same version number.
- **Release order:** one zip to Freemius first; its generated Free build is what the SVN deploy pushes to WordPress.org (story 38 AC7). GitHub Releases keep attaching the full zip until the first directory release, then the Free build.
- **Leaks are caught before the reviewers see them.** CI builds the Free zip, runs Plugin Check on it, and a harness asserts the paid commands and template branches are absent. Freemius's three leak patterns (inline conditions, assignments from premium methods, unwrapped callers of a suffixed function) go into the code-review checklist.
- **The Pro code stays public and GPL.** Entry 4.8 opens the repository with the Pro code in it; the license sells updates, support and convenience, never secrecy. Nothing is obfuscated or moved to a server to hide it.

## Consequences

- Easier: the listing is possible at all; the gate, capabilities, quota and settings carry rules are untouched; Freemius does the stripping and the Pro hosting that ADR 0003 wanted to avoid building.
- Harder: every paid feature now has two guards, the runtime gate and the build marker, and a missed marker is a directory rejection, so CI must check the Free build, not the source. The release pipeline gains a Freemius upload before the SVN deploy, and the delivery-pipeline spec's "no build flavours" constraint no longer holds. The template exists in two variants, and the handshake grows a variant check. The build step couples to Freemius's marker syntax; the runtime seam (`PQM_License`, ADR 0006) stays provider-neutral.
- Committed to: backlog story 54 (the Free build contains no Pro code) between stories 44 and 38; story 38 AC6 rewritten from "visible-but-locked" to "absent, one upsell"; stories 44 and 38 drop the one-zip boundary.
- Supersedes ADR 0003's "one zip, one build" and "one n8n template"; its gate, roles, quota and per-site licensing stand. Extends ADR 0013.
