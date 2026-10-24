---
id: 41
type: story
title: "The visual identity and the copy sweep are produced and applied"
parent: none
covers: [UV7]
after: [10, 51]
assignee: ""
refined: true
hitl: true
risk: low
---

# The visual identity and the copy sweep are produced and applied

## Description

Sliced from story 10 (`/prioritize` 2026-10-08). Story 10 locked the naming, story 51 produced the icon, story 57's UX pass decided the lockup and the site's palette, and story 86 applied both on postquality.com with live text. What is still missing is everything WordPress.org shows before a visitor installs: the two banners, the numbered screenshots the readme's Screenshots section already captions, and the one fixed brand asset the brand canon promises, the lockup with the wordmark as SVG outlines (source typeface Inter) for the places that cannot set text. This story produces those files at the repository root (`assets/`, never in the zip), writes the imagery direction into `docs/brand.md` so the "not decided yet" line goes, and records the copy sweep: README and admin copy checked against story 10's wording rules, with any drift fixed. The owner approves the banners and the lockup artwork by looking at the rendered files (hitl); an agent produces them from the decided tokens.

## Acceptance Criteria

1. **The WordPress.org banners exist**
   **Given** `docs/brand.md` § Lockup and the site's sharing card as the model
   **When** `assets/banner-772x250.png` and `assets/banner-1544x500.png` are opened
   **Then** both show the same composition at 1× and 2×: the band colour full-bleed, lockup B at the left with the tile never on navy, the tagline verbatim in white, no screenshot, no third-party mark, no text other than the lockup and the tagline; each file is PNG and under 4 MB; the 1544×500 file is the 2× rendering of the same source, not an upscale
2. **The screenshots match the readme**
   **Given** `post-quality-manager/readme.txt` § Screenshots (three captions, loop order)
   **When** `assets/screenshot-1.png`, `-2.png`, `-3.png` are opened
   **Then** each shows the capture its caption describes (Post Reviews, the review, the Dashboard), taken from the story 86 captures of the fictional demo site, lowercase file names, PNG, each under 10 MB, no admin bar, no real name or domain in any frame
3. **The fixed lockup artwork exists**
   **Given** lockup B's anatomy in `docs/brand.md` (tile, 0.63 T wordmark, 0.375 T gap, 600 and 400 weights, the light and the dark colours)
   **When** `assets/lockup-b.svg` (light) and `assets/lockup-b-dark.svg` are opened
   **Then** the wordmark is SVG path outlines from Inter with no `<text>` element and no font reference, the icon is the same artwork as `assets/icon.svg`, the proportions follow the T-units, the dark variant sits on a band-coloured rectangle, and the file names the source typeface and its licence in a comment
4. **The brand canon says what the imagery is**
   **Given** `docs/brand.md` § Visual Design
   **When** the section is read after the build
   **Then** the "not decided yet" line is gone and a subsection names the imagery direction (the brand's own palette and lockup, the tagline, product screenshots as the only pictures; no stock photography, no illustration, no third-party mark), the WordPress.org asset set with its files and how to re-render them, and the decision that the banners carry no localised variants at launch
5. **The copy sweep is recorded**
   **Given** story 10's wording rules in `docs/brand.md` § Naming
   **When** README.md, `readme.txt`, the admin strings (`post-quality-manager/admin`, `includes`, `src`) and `docs/` are checked
   **Then** no "Content Quality" remains as a name, "PQM" reaches no user-facing string, no sentence pairs the full name and the short form, the first mention in each file is the full name, and the check's commands and result are in the plan; anything found is fixed in the same build
6. **Nothing else changes**
   **Given** the boundaries below
   **When** the change is reviewed
   **Then** the icon files, `readme.txt`, the site and the readme captions are unchanged, nothing inside `post-quality-manager/` changes except the copy-sweep fixes AC5 names, none of the new assets is in the plugin zip, and the owner has approved the banners and the lockup artwork (hitl)

## Boundaries

- Must not change: the icon files `assets/icon*.{svg,png}` (story 51); `readme.txt` (its captions are story 49's, the rest story 38's); anything inside `post-quality-manager/` except the copy-sweep fixes AC5 names; postquality.com. The new assets never go into the zip.
- Out of scope: localised or RTL banners (English only at launch; revisit with the first translation); a video or animated asset; the Freemius store images beyond the product image already uploaded (story 51); the WordPress.org submission itself (story 38); the copywriting pass over promotional surfaces (stories 53 and 60).

## References

- docs/brand.md — the canon this extends, never contradicts (§ Icon, § Lockup, § Naming)
- sharing card — _bmad-output/initiative-post-quality-manager/ux-postquality-website/DESIGN.md § Components "Sharing card" (the composition the banners derive from) and the site's `_tools/og-card.html` + README "Sharing card" (the render recipe: headless Edge, throwaway profile)
- captures — postquality.com `assets/img/` (`review-list.png` 1440×911, `review.png` 960×824, `dashboard.png` 2048×1488) and the site README "Captures" (how they were made)
- naming — _bmad-output/backlog/story-plugin-identity.md (story 10, the rules this applies)
- listing — _bmad-output/backlog/story-wordpress-org-directory-listing.md (story 38 AC5: the asset list and the trademark rules); WordPress.org plugin assets handbook (banner 772×250 + 1544×500 PNG/JPG, icons 128/256 + SVG with PNG fallback, `screenshot-N.png` lowercase one per readme line, 4 MB / 1 MB / 10 MB per file, `assets/` beside `trunk` in SVN)
- captions — post-quality-manager/readme.txt § Screenshots (story 49)

## Notes

- Decision (owner, 2026-10-08, `/prioritize` D6): split from story 10 so the naming decision — a one-way door gating pipeline entry 4.6 and the readme work — is not held back by asset production. This story runs in parallel at the owner's pace and blocks only story 38.
- Sliced (owner, 2026-10-09): the icon (menu icon, WordPress.org icon files, favicon and Freemius product image) moves to story 51; this story keeps the imagery direction, the banners, the screenshots and the copy sweep, and now waits on 51 because the banners reuse the mark.
- Refined 2026-10-24 (Fable 5.1, by hand from the workspace root; the owner delegated the decisions in advance). Since filing, story 57's UX pass decided the lockup (B primary) and the palette, story 86 built the site and the four captures, the rename (#178) removed the "Content Quality" menu label, and story 49 wrote the readme's Screenshots captions; the criteria above are what is left. `/prioritize` 2026-10-24 ranks this second in the agent lane (launch pack toward entry 4.8).
- Decision (owner delegation, 2026-10-24): the banners derive from the sharing card (band colour, lockup B, the tagline), rendered from one HTML source by headless Edge at 1× and 2×, the same recipe as the sharing card; the typeface in the rendered artwork is Inter (installed on the author's machine; SIL OFL 1.1), the brand's artwork face, so the PNGs match the outlined wordmark.
- Decision (owner delegation, 2026-10-24): the screenshots are the story 86 captures copied as they are (sizes differ per frame; WordPress.org scales them), in the readme's loop order; a recapture at one common width is not worth a Local session now and would need the demo site, which is offline.
- Decision (owner delegation, 2026-10-24): the outlined wordmark is generated by a small script (`bin/outline-wordmark.py`, fontTools through `uv run --with`, no repository dependency) from the installed Inter variable font at weights 600 and 400, optical size matched to the tile; the script is kept so the artwork can be regenerated when the lockup spec changes.
- Decision (owner delegation, 2026-10-24): no localised or RTL banner at launch; the handbook says an unchanged copy under a locale name is ignored anyway.
- Assumption: Inter's SIL OFL 1.1 allows converting glyphs to outlines in a logo (it restricts selling the font and reusing the Reserved Font Name for derived fonts, not outlined artwork); the SVG comment credits the source. If the owner wants a legal read, it is theirs.
- Open question (owner, hitl at the PR): approve the banner composition and the lockup artwork from the rendered files; any tweak is a re-render.
- Decision (review, 2026-10-24, under the owner's delegation): copy-sweep fixes inside `post-quality-manager/` are in scope of AC5 (applied: the cron schedule label `Once Weekly (Post Quality)` in `includes/class-pqm-cron.php`, the plugin header `Description` set to the tagline as `docs/distribution.md` asked); the assets never go into the zip. Boundaries line 1 and AC6 reworded to say that; they had forbidden every change inside the plugin folder while AC5 asked for fixes there.
- Done 2026-10-24: PR #186 merged (954fd5f); the owner's merge is the hitl approval of the banners and the lockup artwork. Story 38 uploads the six files `docs/brand.md` lists; the README rewrite for site owners is story 60's.
