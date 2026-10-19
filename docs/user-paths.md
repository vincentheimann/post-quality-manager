# User Paths — Post Quality Manager

_A Product Owner tool: the two journeys a solo professional blogger takes through the product, with the Current Value (CV) delivered and the Unrealized Value (UV) still on the table at each stage, following the Evidence-Based Management (EBM) approach. Agents: read this before any product or UX decision — effort goes where UV is highest, not where code is easiest._

- **Persona:** solo professional blogger, any niche (marketing, technical/IT, lifestyle). Self-hosts or controls their WordPress; comfortable following a setup guide; not necessarily an n8n or API expert.
- **Evidence status:** no real users yet (owner, 2026-10-07) and no telemetry. CV claims are verified by the owner in development (manual passes, last one 2026-10-07: epic-ui-rebuild 5.9, backlog stories 1 and 6, stories 34–36), not observed in use. Every "Signal" below is a *candidate* measure. Treat CV as owner-verified until real usage confirms or contradicts it.
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

- **CV:** standard WP install, clean activation; a welcome notice offers "Set up the workflow" and opens the setup guide directly until the setup is complete, and every other signpost (dashboard setup CTA, error notices) routes to it (2026-07-24 and story 36, owner-verified 2026-10-07). No activation redirect by choice — the notice greets.
- **UV:** no update channel (GitHub releases don't surface in WP's updater).
- **Signal:** activation → n8n Workflow page visit rate.

### A4. Connect the chain (the friction wall)
Type three values into the plugin (the n8n URL, an n8n API key, the Anthropic key) and let it provision n8n; or, without the n8n API, follow the Workflow File section by hand (import the workflow, three credentials, paste the production webhook URL back).

- **CV (epic n8n-provisioning, ADR 0015, owner-verified 2026-10-16 on their Local sandbox against their own n8n instance):** the setup dialog provisions n8n itself — Connect your n8n (URL + API key, the instance probed), Set up the workflow (the Anthropic key typed once, validated against the provider, forwarded into an n8n credential, never stored), then the first review; three values typed, no n8n UI visit. A failed run rolls itself back and says which step stopped (verified with a forced activation refusal: nothing left in n8n). A hand-made setup is adopted by the plugin (nothing on n8n changes), a test ping names a mis-wired credential in one sentence with the fix, a template update is one click in place, and the webhook token rotates from the plugin. The manual path stays, by choice or when the API is unavailable: the Workflow File section carries a novice through it with a Copy on every value (agent-verified 2026-10-14 from the rendered page on the owner's production site, at the owner's request; the Copy click itself not walked in a browser). The owner expects setup to be a **one-time cost**, not recurring pain.
- **UV:** the first review landing on a *fresh* site is still unproven: the 2026-10-16 walk ran on a Local sandbox the n8n instance cannot call back (the review ran, the callback failed silently and the dialog waited without end — backlog story 2: *full round-trip connection test*; the landed reviews so far are the owner's production site's). The page the setup lands on now shows one next step per site state (story 67, built 2026-10-19: the status block, one primary action, the rest behind collapsed disclosures); the owner's walk through its five states (AC6) is still to come. The n8n Cloud path (sign-up step, Cloud URL) is untested — no Cloud instance.
- **Signal:** % of activations reaching a first successful `/save-review` callback — **the activation metric**.

### A5. First review (the aha moment)
Run a review on one post from the Post Reviews page; see the score on four pillars plus top edits.

- **CV:** async trigger (UI never blocks), stored result, score + issues + suggested edits visible.
- **CV (story 36, owner-verified 2026-10-07):** the setup guide's last step runs the first review on a published post from the dialog, waits for the score, then marks the setup complete and offers the Blog Profile and scheduled reviews; the dashboard shows a "Run your first review" CTA until the first result lands.
- **UV:** time to first review is not measured (UV1 telemetry).
- **Signal:** time from activation to first stored review.

### A6. Habit formation
Pick a Blog Profile and an Editorial Standards preset (or Custom), enable scheduled reviews.

- **CV:** a Blog Profile and an Editorial Standards preset are one click each, Custom opens the text field (progressive disclosure — story 26, 2026-10-07); cron with batch size, minimum score, and post-age knobs turns the plugin from a tool into a system.
- **CV (story 55, 2026-10-12, owner test on production, no bugs found):** the setup guide offers all three right after the first review, inside the same dialog — Blog Profile, Editorial Standards (preset read-only, Custom from the stored text) and a one-click switch for scheduled reviews with the stored knobs (Pro + "Manage agents"; a free site is told it is Pro). Each step is skippable and writes only its own keys.
- **UV:** nothing pulls the blogger back — no digest, no notification, no "3 posts dropped below 60 this week". Results wait silently in wp-admin. This is where new users silently churn. *(2026-07-24: small step — after the first review the dashboard nudges enabling scheduled reviews; the actual pull-back mechanism (digest/notifications) remains open.)*
- **Signal:** % of installs with cron enabled; dashboard visits per week after week 2.

---

## Path B — Existing user: the ongoing quality loop

The owner's intended usage (no real users yet): cron batches do the routine inflow, manual per-post triggers happen ad hoc; the agents expected to matter are **review, takeaways/sources, seo/audit** — rewrite is not part of the habit.

### B1. Review inflow
WP-Cron reviews batches on schedule; manual triggers fill the gaps.

- **CV:** the scheduler works (owner-verified); since 2026-07-23 cron sends the same full payload as manual triggers (brand voice, prompts, LLM map). A schedule can run Review, SEO or Audit — one command per schedule, posts picked by their latest result of that command; results land in the post's tab and row chips, the dashboard stays review-only (ticket 3, owner-verified 2026-10-06).
- **UV:** one command per schedule — running review and seo/audit both on a schedule is not possible yet; scheduled seo/audit results do not feed the dashboard counts or trend.
- **Signal:** share of reviews originating from cron vs. manual.

### B2. Triage in the dashboard
Scan scores, statuses, and top edits, with Yoast's SEO and readability scores alongside while Yoast is active (the Yoast column and the "— Pending" title marker, story 5.10).

- **CV:** the global overview exists and informs — this is the plugin's core delivered value.
- **CV (epic-ui-rebuild, owner-verified 2026-10-07 in the 5.9 pass):** the Dashboard prioritizes — a "Fix next" queue of 5 ordered by status severity, then score, then staleness, each row with Review + Open in Editor; tiles open the matching Post Reviews chip; an 8-week average-score sparkline. Takeaways/sources/seo/audit results are visible and triggerable per post; progress chips show what actually ran.
- **UV:** traffic and Yoast are not priority inputs — no analytics source yet; Yoast standing at review time isn't snapshotted, so past reviews lose their context (epic-yoast-context).
- **Signal:** which sort/filter actions get used; % of dashboard sessions that lead to opening a post.

### B3. Act on a post
Edit the flagged post guided by the top edits (or, in theory, run rewrite).

- **CV:** suggested edits are concrete enough to guide manual editing; "Open in Editor" is the primary action on the Review tab; "Insert into Post" puts Takeaways and Sources into the post in the owner's pattern (story 23) — owner-verified.
- **UV — the biggest gap in the whole product:** the loop from insight to action is unproven. The owner's answer is "varies / too early to say", and the rewrite agent goes unused. There is no way to mark an edit applied, no tracking of whether reviews cause edits at all. **If reviews don't lead to edits, the product's core promise is unrealized regardless of how good the scores are.**
- **Signal:** % of reviewed posts edited within 14 days; score delta on the following review.

### B4. Verify improvement
Re-review an edited post; compare against history.

- **CV:** full review history is stored per post.
- **UV:** history is stored but not *told* — no before/after comparison, no automatic re-review after a post update, no "your blog average moved from 62 → 71" trend. The motivational payoff of the loop is invisible.
- **Signal:** re-review rate after edits; blog-level average score over time.

### B5. Maintain the system
Tune models, effort, prompts; keep the n8n workflow current.

- **CV:** since the 2026-07-23 LLM-options pivot, model/effort/prompt changes ride the webhook payload — **no re-import needed**; the version handshake shows an "update available" notice when the template itself changes.
- **CV (UV2, owner-verified 2026-10-07 in backlog stories 1 and 6):** the UI says it — a custom save notice on Settings plus inline reassurance in the AI Model and Agent Overrides sections: *"saved — applies on the next command, no re-import"*.
- **UV:** genuine re-imports (template structure changes) still mean manual n8n round-trips, and the handshake only reports after a real command runs.
- **Signal:** re-imports performed per template version bump (should approach exactly 1).

---

## EBM summary — where the effort should go

Unrealized Value ranked by size of the gap between the current and the desired experience:

| # | Unrealized Value | Path stage | Why it ranks here |
|---|---|---|---|
| 1 | Insight → action loop (edit tracking, deep-links, proof that reviews cause edits) | B3–B4 | The core promise; currently unmeasured and unproven even for the owner |
| 2 | Perceived no-re-import (validate the pivot, say it in the UI) | B5 | *(2026-10-07: converted — owner-verified, backlog stories 1 and 6)* |
| 3 | Pull-back mechanism (digest / notifications) | A6, B1 | Retention; silent results are unrealized value by definition |
| 4 | Guided activation (welcome screen → checklist → first review) | A3–A5 | Converts evaluators into activated users *(2026-10-07: converted — the guided setup dialog, story 36, owner-verified)* |
| 5 | Round-trip connection test | A4 | Removes the scariest silent failure for new users (backlog item exists) |
| 6 | Cron for seo/audit commands | B1 | Owner ask *(2026-10-06: converted — one command per schedule, ticket 3; several commands per schedule remain open)* |
| 7 | Distribution (WordPress.org, updater) | A1–A2 | Largest long-term UV, deliberately deferred post-MVP |

**Current Value in one line:** an installed, configured system that continuously scores the back-catalog and shows where to look — verified by the owner in development; no real users yet, so none of it is observed in use.

**Unrealized Value in one line:** proof and reinforcement that those scores change the blog — acting, verifying, and being pulled back.

### Notes for the future setup wizard (Path A)
Ordered by what the path shows: (1) welcome screen on activation linking straight into the checklist; (2) the existing n8n Workflow page steps, ending with a **full round-trip test** rather than a one-way ping; (3) "run your first review now" on a real post as the final step, landing on the populated dashboard; (4) offer Blog Profile choice and cron enablement immediately after the first result, while motivation is highest. *(2026-10-07: (1) as the welcome notice opening the guide, (3) and (4) shipped in the setup dialog, story 36 — (4) as steps inside the dialog since story 55; (2)'s round-trip test stays with story 2 — the first review proves the return path meanwhile.)*
