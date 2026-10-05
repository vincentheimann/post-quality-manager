# User Paths — Post Quality Manager

_A Product Owner tool: the two journeys a solo professional blogger takes through the product, with the Current Value (CV) delivered and the Unrealized Value (UV) still on the table at each stage, following the Evidence-Based Management (EBM) approach. Agents: read this before any product or UX decision — effort goes where UV is highest, not where code is easiest._

- **Persona:** solo professional blogger, any niche (marketing, technical/IT, lifestyle). Self-hosts or controls their WordPress; comfortable following a setup guide; not necessarily an n8n or API expert.
- **Evidence status:** grounded in real usage by user #1 (the project owner, 2026-07); no telemetry exists. Every "Signal" below is a *candidate* measure — today the evidence is self-observed. Treat CV claims as validated only where user #1's behavior confirms them.
- **Maintenance:** update this file when a stage's friction is removed (move it from UV to CV) or when real usage contradicts a claim. Backlog references point to the BMad ticket tree in `_bmad-output/` (the ranked UV table below is the initiative's requirement source, ids UV1–UV7; since 2026-10-01, ADR 0004).

---

## Path A — New user: from problem to habit

The path the setup wizard must eventually orchestrate. The critical moment is **A4→A5**: everything before the first successful review is cost; everything after is value.

### A1. Problem recognition
The blogger notices the back-catalog is aging — older posts drift out of date, quality varies — and realizes SEO plugins score keywords, not editorial quality.

- **CV:** the README articulates this gap well ("which posts need editorial attention, and why").
- **UV:** discoverability is near zero — GitHub Releases only, WordPress.org deferred, no content presence. A blogger with this problem will not find this plugin.
- **Signal:** repo traffic, stars, release downloads.

### A2. Evaluation
They read the README and weigh the requirements: an n8n instance **and** a Claude API key.

- **CV:** honest requirements list; niche-agnostic pitch widens the audience.
- **UV:** the n8n + API-key prerequisite is the single biggest adoption filter — no demo, no screenshots, no "what will this cost me in API spend" guidance. Many evaluators bounce here without the plugin ever getting a chance.
- **Signal:** README views vs. release downloads (drop-off ratio).

### A3. Install & activate
Copy the plugin folder from a GitHub release into `wp-content/plugins/`, activate.

- **CV:** standard WP install, clean activation.
- **UV:** no welcome screen — after activation nothing points to the 3-step checklist on the n8n Workflow page; no update channel (GitHub releases don't surface in WP's updater). *(2026-07-24, pending validation: every in-plugin signpost — welcome notice, dashboard setup CTA, error notices — now routes to that checklist; a real welcome screen / activation redirect and the update channel remain open.)*
- **Signal:** activation → n8n Workflow page visit rate.

### A4. Connect the chain (the friction wall)
Follow the 3-step checklist: download/import the pre-configured workflow, connect two credentials in n8n (WP Application Password + Claude API key), paste the production webhook URL back into Settings.

- **CV:** this is already a proto-wizard — pre-filled template, import-from-URL, test ping, per-agent request preview. User #1 confirms it's a **one-time cost**, not recurring pain.
- **UV:** the test ping only proves WP → n8n; the return path (n8n → `/save-review` with the token) is unverified until a real review succeeds (backlog: *full round-trip connection test*). Two-system context switching remains inherently confusing for a non-n8n user. *(2026-07-24, pending validation: setup now completes on one page — wizard step 3 saves the webhook URL inline and pings; ping copy states exactly what is and isn't verified. The stale token-persistence claim was removed — the activator has persisted it since activation.)*
- **Signal:** % of activations reaching a first successful `/save-review` callback — **the activation metric**.

### A5. First review (the aha moment)
Run a review on one post from the Post Reviews page; see the score on four pillars plus top edits.

- **CV:** async trigger (UI never blocks), stored result, score + issues + suggested edits visible.
- **UV:** nothing prompts it — after setup completes, the user must find the Post Reviews page themselves; the dashboard is empty and silent before the first result. The wizard's last step should *be* "run your first review" and celebrate the result. *(2026-07-24, pending validation: shipped — wizard step 4 "First review" routes into Post Reviews, and the dashboard shows a "Run your first review" CTA until the first result lands.)*
- **Signal:** time from activation to first stored review.

### A6. Habit formation
Pick a Blog Profile, adjust context, enable scheduled reviews.

- **CV:** profile presets pre-fill voice/context (progressive disclosure works); cron with batch size, minimum score, and post-age knobs turns the plugin from a tool into a system.
- **UV:** nothing pulls the blogger back — no digest, no notification, no "3 posts dropped below 60 this week". Results wait silently in wp-admin. This is where new users silently churn. *(2026-07-24: small step — after the first review the dashboard nudges enabling scheduled reviews; the actual pull-back mechanism (digest/notifications) remains open.)*
- **Signal:** % of installs with cron enabled; dashboard visits per week after week 2.

---

## Path B — Existing user: the ongoing quality loop

User #1's real usage today: cron batches do the routine inflow, manual per-post triggers happen ad hoc; the agents actually used are **review, takeaways/sources, seo/audit** — rewrite is not part of the habit.

### B1. Review inflow
WP-Cron reviews batches on schedule; manual triggers fill the gaps.

- **CV:** the scheduler works and is configured (validated by real usage); since 2026-07-23 cron sends the same full payload as manual triggers (brand voice, prompts, LLM map).
- **UV:** cron is locked to the `review` command — the seo/audit agents user #1 actually uses still require manual triggering (backlog: *cron command choice*).
- **Signal:** share of reviews originating from cron vs. manual.

### B2. Triage in the dashboard
Scan scores, statuses, and top edits, with Yoast's SEO and readability scores alongside while Yoast is active. *(2026-10-05, pending validation: the Yoast column and the "— Pending" title marker, dropped with the legacy list in epic-ui-rebuild story 5.7, are back in the React table since story 5.10.)*

- **CV:** the global overview exists and informs — this is the plugin's core delivered value.
- **UV:** the dashboard reports, but doesn't *prioritize* — no "fix next" queue ordered by impact (low score × high traffic × post age); Yoast standing at review time isn't snapshotted, so past reviews lose their context (backlog). *(2026-07-24: stat cards now link into the filtered Post Reviews list — routing, not yet prioritization. Also: takeaways/sources/seo/audit results — the agents user #1 actually uses — are now visible and triggerable per post; progress chips show what actually ran instead of a fixed Review/Rewrite pair.)* *(2026-10-05, pending validation: the rebuilt Dashboard (epic-ui-rebuild story 5.6) prioritizes — a "Fix next" queue of 5 ordered by status severity, then score, then staleness, each row with Review + Open in Editor; tiles open the matching Post Reviews chip; an 8-week average-score sparkline. Traffic and Yoast inputs to the priority remain open — no analytics source yet.)*
- **Signal:** which sort/filter actions get used; % of dashboard sessions that lead to opening a post.

### B3. Act on a post
Edit the flagged post guided by the top edits (or, in theory, run rewrite).

- **CV:** suggested edits are concrete enough to guide manual editing.
- **UV — the biggest gap in the whole product:** the loop from insight to action is unproven. User #1's honest answer is "varies / too early to say", and the rewrite agent goes unused. There is no way to mark an edit applied, no tracking of whether reviews cause edits at all. *(2026-07-24, pending validation: the deep-link now exists — "Open in Editor" is the primary action on the Review tab.)* **If reviews don't lead to edits, the product's core promise is unrealized regardless of how good the scores are.**
- **Signal:** % of reviewed posts edited within 14 days; score delta on the following review.

### B4. Verify improvement
Re-review an edited post; compare against history.

- **CV:** full review history is stored per post.
- **UV:** history is stored but not *told* — no before/after comparison, no automatic re-review after a post update, no "your blog average moved from 62 → 71" trend. The motivational payoff of the loop is invisible.
- **Signal:** re-review rate after edits; blog-level average score over time.

### B5. Maintain the system
Tune models, effort, prompts; keep the n8n workflow current.

- **CV:** since the 2026-07-23 LLM-options pivot, model/effort/prompt changes ride the webhook payload — **no re-import needed**; the version handshake shows an "update available" notice when the template itself changes.
- **UV:** a perception gap — user #1 still experiences settings changes as "export/import the whole workflow again", the exact pain the pivot removed. Either the manual validation pass (open as `_bmad-output/backlog/story-manual-pass-lean-ux-and-locked-knobs.md`) hasn't yet built trust, or the UI doesn't say clearly enough *"saved — applies on the next command, no re-import"*. *(2026-07-24, pending validation: the UI now says it — custom save notice on Settings plus inline reassurance in the AI Model and Agent Overrides sections.)* Shipped value that isn't perceived is unrealized value. Genuine re-imports (template structure changes) also still mean manual n8n round-trips, and the handshake only reports after a real command runs.
- **Signal:** re-imports performed per template version bump (should approach exactly 1).

---

## EBM summary — where the effort should go

Unrealized Value ranked by size of the gap between the current and the desired experience:

| # | Unrealized Value | Path stage | Why it ranks here |
|---|---|---|---|
| 1 | Insight → action loop (edit tracking, deep-links, proof that reviews cause edits) | B3–B4 | The core promise; currently unmeasured and unproven even for user #1 |
| 2 | Perceived no-re-import (validate the pivot, say it in the UI) | B5 | Value already built but not realized — cheapest UV to convert to CV |
| 3 | Pull-back mechanism (digest / notifications) | A6, B1 | Retention; silent results are unrealized value by definition |
| 4 | Guided activation (welcome screen → checklist → first review) | A3–A5 | The future wizard; converts evaluators into activated users |
| 5 | Round-trip connection test | A4 | Removes the scariest silent failure for new users (backlog item exists) |
| 6 | Cron for seo/audit commands | B1 | Direct ask from real usage (backlog item exists) |
| 7 | Distribution (WordPress.org, updater) | A1–A2 | Largest long-term UV, deliberately deferred post-MVP |

**Current Value in one line:** an installed, configured system that continuously scores the back-catalog and shows where to look — validated by real daily-adjacent usage of review, takeaways/sources, and seo/audit.

**Unrealized Value in one line:** proof and reinforcement that those scores change the blog — acting, verifying, and being pulled back.

### Notes for the future setup wizard (Path A)
Ordered by what the path shows: (1) welcome screen on activation linking straight into the checklist; (2) the existing n8n Workflow page steps, ending with a **full round-trip test** rather than a one-way ping; (3) "run your first review now" on a real post as the final step, landing on the populated dashboard; (4) offer Blog Profile choice and cron enablement immediately after the first result, while motivation is highest.
