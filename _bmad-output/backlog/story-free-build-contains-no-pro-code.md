---
id: 54
type: story
title: "The Free build contains no Pro code"
parent: none
covers: [UV7, O9]
after: [44]
assignee: ""
refined: false
hitl: true
risk: medium
---

# The Free build contains no Pro code

## Description

ADR 0014: WordPress.org hosts a Free build with no paid functionality in it, Freemius serves the Pro build, both come from one source. Story 44 integrates the SDK; this story marks the paid code so Freemius's deployment strips it, ships a Free n8n template without the paid branches, teaches the workflow-version handshake both variants, and makes CI prove the Free build is clean before any reviewer sees it. The Free build is judged as a product on its own: Review and Key Takeaways complete, nothing greyed out, one "Get Pro" section.

## Acceptance Criteria

1. **The paid code is marked, the Free build has none of it**
   **Given** the zip uploaded to Freemius
   **When** its deployment generates the Free build
   **Then** `PQM_Features::registry()` in that build holds only `tier: free` entries, no paid command, override, Automated Reviews schedule of a paid command, or Roles-tab save code is present, and `grep` for the paid command keys and the `__premium_only` markers in the Free zip's PHP files and n8n template finds nothing (the compiled React bundle is the same in both builds and is not grepped: its paid labels render only from server data the Free build never sends; decision D1, 2026-10-12)
2. **The Free template has no paid branch**
   **Given** the Free build's n8n Workflow page
   **When** the template is downloaded and imported
   **Then** it carries the Review and Takeaways branches, ping and the result POST only; the Route Command node knows no paid command; the Pro build's template is unchanged
3. **The handshake knows both variants**
   **Given** a site on either build with the matching template imported
   **When** the ping runs
   **Then** the page reports the version as current; a Pro site with the Free template imported is told the template lacks the paid branches and offered the Pro download
4. **Free is a complete product**
   **Given** the Free build activated fresh
   **When** an Administrator opens every plugin screen
   **Then** Review and Key Takeaways work end to end, no control is disabled or labelled "Pro", the Settings page has one "Get Pro" section linking to the pricing page or checkout (story 44 AC3), and nothing else upsells
5. **CI proves it**
   **Given** a pull request
   **When** CI runs
   **Then** a job builds the Free zip (reproducing the stripping, or fetching Freemius's generated build in the release job), runs Plugin Check on it with no new error, and a harness boots it and asserts the paid commands, the paid template branches and the markers are absent; a deliberately unwrapped paid call turns CI red
6. **Review checklist**
   **Given** `_bmad/custom/bmad-code-review.toml` and AGENTS.md
   **When** a paid feature is added or changed
   **Then** the reviewer checks the three leak patterns (inline condition with a premium call, assignment from a premium method, unwrapped caller of a suffixed function) and that the change is wrapped
7. **Release order is written down**
   **Given** `docs/distribution.md` and the `/release` skill
   **When** a release is cut
   **Then** the steps say: Freemius upload first, generated Free build to SVN (story 38 AC7), same version in both builds; the GitHub Release attaches the full zip until the first directory release

## Boundaries

- Must not change: the runtime gate and `why_not()`, the capabilities, the quota, the sanitizer carry rules, the stored settings keys, the Pro build's behaviour.
- Out of scope: the SDK integration and license checks (story 44); the directory submission itself (story 38); hiding or obfuscating Pro code in the public repository (ADR 0014: it stays GPL and public).

## References

- decision — docs/adr/0014-two-builds-from-one-source.md
- decision — docs/adr/0003-freemium-plan-and-role-based-access.md (gate, tiers; its one-zip point is superseded)
- code — post-quality-manager/includes/class-pqm-features.php (`registry()`, the tier list)
- code — post-quality-manager/templates/n8n-workflow.json (the Pro template; the Free one is derived)
- code — post-quality-manager/admin/class-pqm-admin.php (`PQM_WORKFLOW_VERSION` handshake, the Settings page sections)
- code — .github/workflows/release.yml, ci.yml (the zip job; the Free-build check joins them)
- spec — _bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md (the "no build flavours" constraint, superseded)
- external — https://freemius.com/help/documentation/wordpress-sdk/integration/software-licensing/ (markers, the three leak patterns)
- external — https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ (guidelines 5, 6, 8, 11)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (AC6, AC7 read from this story)

## Notes

- Filed 2026-10-09 by the owner's decision on ADR 0014 (option A: two builds from one source). Sits between story 44 (SDK) and story 38 (listing); gates 38, not entry 4.8 (GitHub Releases keep the full zip).
- Plan-time choices: whether the Free template is a second checked-in file or generated by a build script from the Pro one (fewer files to keep in step favours generating); whether CI reproduces Freemius's stripping locally (a small script over the same markers) or only checks the generated build at release time (both is the safe default: local on every PR, generated at release).
- The Roles tab, Automated Reviews knobs and Agent-Specific Overrides are Pro-only sections today rendered locked on Free (ADR 0003 presentation); in the Free build they are absent, so the locked-section pattern survives only in the Pro build for a lapsed license (story 44 AC2).
- hitl: the owner runs a Freemius deployment with a test zip and reads the generated Free build once before CI is trusted.
- Built 2026-10-12 on `feat/story-54-free-build` (plan `story-free-build-contains-no-pro-code-plan.md`, its Plan Change Log lists the deviations). D1–D4 answered by the owner as recommended (React bundle untouched, Free template generated and checked in, stale cron ignored, harness expectations change only with a moved call). Slice 3b (same day, after the code review; owner approved the four recommendations): the React command list follows the registry print, Settings on Free needs `pqm_manage_settings`, a license on the Free build points to the Pro download, and Freemius's dual-activation (`set_basename()`) pattern is in the main file. Open: the HITL step (a CI zip through a Freemius deployment, `diff -r` of its Free build against `bin/build-free.php`, the Free build walked on Local) before CI is trusted and the ticket is done.
- Hand-off (2026-10-12): story 58 (filed by the owner) automates the release-time half of AC5 (fetching Freemius's generated build in the release job and checking it) and rewrites the `docs/distribution.md` § Release order and `/release` steps this story wrote (AC7) once the upload is automatic. During this story's HITL step Freemius refused the 3.5.0 zip: it needs `readme.txt` on every upload, a file the plan had left to story 38.
