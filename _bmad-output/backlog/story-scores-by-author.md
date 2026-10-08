---
id: 39
type: story
title: "An Administrator sees scores by author and spots who needs help"
parent: none
covers: [O8]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# An Administrator sees scores by author and spots who needs help

## Description

Owner request (2026-10-08). On a site with several authors — a small team or a company (the same persona split as ADR 0011) — the Dashboard shows one blog-wide average, a Fix-next queue of posts and a trend, but nothing per person. The Administrator cannot tell whether the weak posts belong to one author who needs coaching or are spread across the team. This story adds a **By author** view for Administrators: one row per author with the quality of their posts, sorted so the author who needs help most comes first, with a way to open that author's posts in Post Reviews. The goal is to identify who needs help improving their content, not to grade people: the view gives the Administrator the numbers and the drill-down; judging and acting stay human. Pro feature.

## Acceptance Criteria

1. **The by-author table**
   **Given** a site with reviewed posts by at least two authors
   **When** an Administrator opens the Dashboard
   **Then** a "By author" section lists every author who has at least one listable post (published or pending), with their display name, the number of posts, how many have a review score, the average of their posts' latest review scores, and how many sit below the site's low-score threshold; authors with no scored post show "—" for the average and are listed last
2. **Who needs help first**
   **Given** the table
   **When** it renders
   **Then** rows are ordered by average score ascending (lowest first), ties by the number of low-scoring posts descending; the Administrator can re-sort by any column
3. **Drill-down**
   **Given** a row
   **When** the Administrator clicks the author's name or post count
   **Then** Post Reviews opens filtered to that author's posts, sorted by score lowest first, with the author filter visible and clearable
4. **Same numbers as the rest of the Dashboard**
   **Given** the blog-wide Average score tile
   **When** the by-author averages are compared with it
   **Then** both use the same rule — each post's latest `review` result, scored statuses only, listable posts only (ticket 3: the Dashboard stays review-only) — so the per-author numbers reconcile with the tile
5. **Single-author site**
   **Given** a site where every listable post has the same author
   **When** the Dashboard renders
   **Then** the section is absent — a solo blogger never sees a one-row table about themselves
6. **Access and plan**
   **Given** an Editor or an Author on a Pro site
   **When** they open the Dashboard
   **Then** the section is absent — an Author does not see their own row either — and the data is not in the REST response for them; *View as* (story 34) shows the Administrator exactly that
   **Given** a free site
   **When** an Administrator opens the Dashboard
   **Then** the section is locked with the upgrade copy, like the other Pro sections

## Boundaries

- Must not change: the `pqm_reviews` table (the author comes from `wp_posts.post_author` by join, never stored on the review), the n8n template, the Fix-next queue and tiles.
- Out of scope: a per-author trend over time, a per-author page or report, e-mailing an author their numbers, a per-author quota view (the Monthly Quota already counts per user), coaching copy or suggestions per author, any automatic "needs help" flag or threshold on an author, Yoast columns.

## References

- requirement — docs/user-paths.md, B2 "Triage in the dashboard" (the view extends the triage overview to people) and B4 (blog-level averages)
- decision — docs/adr/0011-editorial-standards-and-author-voice.md (the multi-author persona: solo blogger, small team, company)
- decision — docs/adr/0005 (Dashboard on @wordpress/components; the section is a React component in `src/dashboard/`)
- code — post-quality-manager/includes/class-pqm-db.php, `get_score_trend()` and `get_all_posts_with_scores()` (the latest-review-per-post rule and `SCORED_STATUSES` the averages must reuse)
- code — post-quality-manager/includes/class-pqm-rest-api.php, `dashboard_callback` (`GET /dashboard`) and the `GET /posts` args (no `author` arg today)
- code — src/dashboard.js, src/dashboard/stat-tiles.js, src/post-reviews/filter-bar.js
- code — post-quality-manager/includes/class-pqm-features.php (capability map; `pqm_manage_settings` = Administrator only, `pqm_manage_agents` = Administrator and Editor)

## Notes

- Assumption: "scoring" means the Review agent's overall score, as everywhere else on the Dashboard; seo/audit scores stay per post.
- Assumption: the low-score threshold is the same number the Automated Reviews "minimum score" setting uses (default 60), so "needs help" and "needs a review" agree. Confirm at refinement.
- Assumption: the table is a section on the existing Dashboard, not a new admin page — the minimal-code ladder (ADR 0007) and the Dashboard's role as the single overview both point there.
- Assumption: the data rides a new field on `GET /dashboard` rather than a new route, computed in PHP from the same rows `get_score_trend()` already loads; the drill-down needs an `author` filter arg on `GET /posts` and in the Post Reviews filter bar.
- Assumption: a post counts for its current author (`post_author` at render time), so reassigning a post moves its score to the new author.
- Decision (owner, 2026-10-08): Administrators only (`pqm_manage_settings`). Editors do not see the section; an Author does not see their own row on their Dashboard.
- Decision (owner, 2026-10-08): Pro plan, like the other multi-author feature (author voice, ADR 0011).
- Decision (owner, 2026-10-08): the plugin ranks by the numbers and stops there — no trend per author, no flag, no coaching copy. Deciding who needs help is a human decision; a human judges and reviews.
- Decision (owner, 2026-10-08): an author with few scored posts ranks with the others; the post count in the row is the context the Administrator needs.
- Covers O8, added 2026-10-08 (`/prioritize` D3): "quality by author on multi-author sites" — the same move as O6 and O7, so this story ranks instead of reading as scope creep. Ranked right after story 37 (the other multi-author story, same persona).
