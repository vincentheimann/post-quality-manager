---
title: 'Launch content: the measured cost example, the loop in three frames, the announcement plan'
type: 'feature'
ticket: '49'
created: '2026-10-24'
status: 'built'
baseline_revision: '896a13832da332821bf4b3e96bd74f0c99dbd024'
site_baseline_revision: 'dba2ce6ab637c043c19dcc54b95884635ad5809c'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: ['{project-root}/docs/brand.md', '{project-root}/_bmad-output/initiative-post-quality-manager/ux-postquality-website/build-brief.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** postquality.com's cost FAQ promises "a worked cost example is coming", the readme has no FAQ, and nothing says what one review costs in AI spend; the loop (trigger → review → fix next) is told by three captures but not in that order, and no plan says which channel announces the first public release on which day (docs/user-paths.md A1–A2).

**Approach:** write one dated cost example in the plugin repository from measured token counts (Anthropic's free token-count endpoint, the owner's development key read from the private workspace folder at run time, never printed) on three fictional posts at short, average and long lengths, priced at Anthropic's published list prices on the day; copy its headline figures to the site FAQ and a new readme FAQ with a drift check; order the home page's How it works and the readme's Screenshots as the loop; add the announcement plan to docs/distribution.md as days relative to entry 4.8; rewrite the A1/A2 Signal lines.

**Decisions (owner delegation, 2026-10-24; story 49 Notes):** measured counts, not estimates, unless the key is unavailable (then an estimate from text length, said so); the demo stays static, no video or animated image; one source page copied with a drift check; days relative to day 0, not dates; the n8n template library recorded as declined for the first release (the shipped template carries site placeholders); the communities list holds the owner's picks only, empty until the owner names them. Deviation taken at planning: the demo site is offline (Local not running) and its seeded bodies are short generic paragraphs, so the three posts are written for the measurement and kept beside the example as fixtures, fictional tech-blog topics like the demo site's.

## Boundaries & Constraints

**Always:** the token-count request carries fictional text only and sends nothing else; the key is read at run time from the private folder and appears in no file, log, plan or commit; the example names the model, the date, the counts, the prices quoted and where to check them; every sentence about limits says run counts, never spend, and names no token limit and no second provider; the figures on the site and in the readme equal the source page's (the drift check proves it); captions say the content is fictional; `launched: false` and `launched: true` both build clean; docs/brand.md wording rules.

**Never:** plugin PHP or JS; the four captures; prices, plans, pricing IDs, URLs; n8n Cloud named; a KPI claim (story 53); a third-party request on the site; a merge or push by the agent in the site repository without the owner's standing rule; a community posted to.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Count | three fixture posts + the review system prompt + the default settings text, model `claude-sonnet-4-6` | input tokens per post (whole request, as the template builds it), output cap 2,500; figures written to the example | endpoint error or no key: the example states an estimate (chars ÷ 4) and says so |
| Price | the counts × Anthropic's list prices read that day | one review per post in USD to the cent; 20 and 80 posts a month | prices unreachable: the plan halts, the owner is asked |
| Site, pre-launch | `/docs/#cost` built with `launched: false` | headline figures + date, no "coming" sentence | — |
| Site, launched | built with `launched: true` | same figures, nothing else changes | — |
| Readme | `readme.txt` FAQ | one entry with the same figures and date; Screenshots section in loop order | — |
| Drift | `check_site.py`-style check comparing the three copies | NO PROBLEMS; a mutated figure in one copy raises | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/pqm-functions.php` -- `default_model` `claude-sonnet-4-6`; `pqm_agent_max_tokens()` review 2,500, takeaways 1,000 (Pro: sources 2,000, seo 1,000, audit 5,000); `pqm_voice_presets()` default instruction text; read only.
- `post-quality-manager/admin/class-pqm-admin.php` ~L278 -- `blog_profiles()` website_context texts (the default settings text); read only.
- `post-quality-manager/templates/n8n-workflow.json` -- node `Build Review Body` builds the request: `system` = reviewer prompt (~1,900 chars with the website_context and brand_voice), user message = title, excerpt, guidelines, full article; reproduce this shape in the counting script; do not change the template.
- `docs/cost-example.md` (new) -- the source page: method, model, date, the three posts' word and token counts, price per review, 20/80 posts a month, limits paragraph, "prices change" line. `docs/cost-example/` (new) -- three fixture posts as `.md` (short ~600, average ~1,300, long ~2,500 words), fictional.
- `bin/count-cost-example.py` (new, Python, stdlib only: `urllib`, `json`) -- builds the three requests as the template does, POSTs `https://api.anthropic.com/v1/messages/count_tokens` (headers `x-api-key`, `anthropic-version: 2023-06-01`), key from `../.secrets/anthropic-key.txt` relative to the repo or `$ANTHROPIC_API_KEY`; prints a table; `--prices in,out` turns counts into USD. Never prints the key. If `bin/` scripts are PHP-only by convention, write it in PHP with `wp_remote_post`-free `curl`; either way one file.
- `post-quality-manager/readme.txt` -- add `== Frequently Asked Questions ==` with "What does it cost me in AI calls?" (the figures + date, the limits sentences) and `== Screenshots ==` with three lines in loop order (1 Post Reviews: Review on a post, 2 the review, 3 the Dashboard's Fix next); story 38 completes the rest.
- `docs/distribution.md` -- new section `## Launch plan (story 49)` after the Website section: a table Day | Channel | What | Owner | Source | State; day 0 = entry 4.8.
- `docs/user-paths.md` L20, L27 -- A1 and A2 Signal lines rewritten to Freemius opt-in installs and sales, WordPress.org downloads and active installs, interest emails received; no site analytics (ADR 0013 D8).
- Site `docs.md` L102–108 -- the `#cost` FAQ: figures, date, limits sentences, the `unless site.launched` sentence removed. Site `index.html` L79 -- the "Your own AI bill" item gains the per-review figure. Site `index.html` L100–130 (How it works) -- check the three steps and their captures tell trigger → review → fix next; reorder captions only if they do not. Site `README.md` -- the Documentation drift paragraph names `docs/cost-example.md` as the FAQ's source.
- Drift check: extend the site's existing recipe (README "Documentation drift") with a small script `_tools/check_cost_copy.py` (stdlib) that reads the source page's figures block (a fenced block tagged `cost-figures`) and asserts the site FAQ and the readme carry the same figures and date.
- Scratch tools from story 86 (session 791539b7's scratchpad: `build.sh`, `check_site.py`, `lh.py`) -- reuse for the two-state build and the live-style checks; copy into this session's scratchpad, never into a repo.

## Tasks & Acceptance

**Execution:**
- [x] `docs/cost-example/*.md` -- write three fictional posts (short, average, long; tech-blog topics like the demo site's, no real person, product or domain) -- the measurement input, kept so the figures are reproducible
- [x] `bin/count-cost-example.py` -- the counting script as the Code Map says -- measured, repeatable, key never printed
- [x] read Anthropic's current pricing page for `claude-sonnet-4-6` (input and output per million tokens), note the date -- the prices quoted
- [x] `docs/cost-example.md` -- run the script, write the page: method, table, per-review price, 20 and 80 posts a month, the limits paragraph, the fenced `cost-figures` block -- the source of truth
- [x] `post-quality-manager/readme.txt` -- FAQ entry + Screenshots lines -- criterion 2 and 4
- [x] `docs/distribution.md` -- Launch plan table (channels per story 49 criterion 5; communities row "owner to name"; n8n template library "declined for the first release: the template carries site placeholders; revisit after launch") -- criterion 5
- [x] `docs/user-paths.md` -- A1/A2 Signal lines -- criterion 6
- [x] site `docs.md`, `index.html`, `README.md`, `_tools/check_cost_copy.py` -- the copies and the drift check -- criteria 2, 3, 4
- [x] `memory/release-readiness.md` -- one line: the cost example exists, measured on {date}, where the script is

**Acceptance Criteria:**
- Given the script and the key, when run, then it prints three token counts and exits 0, and `grep -r sk-ant` over both repositories finds nothing
- Given both site builds, when `check_site.py` and the drift check run, then NO PROBLEMS in both states and the word "coming" is absent from `/docs/`
- Given the readme, when read, then the FAQ figures equal the source page's and the Screenshots order is trigger → review → fix next
- Given the launch plan, when read, then every row has a day, an owner and a state, and no community is named without the owner's word

## Implementation Notes

- Plan written 2026-10-24 (Fable 5.1, bmad-build by hand from the workspace root per the owner's 2026-10-16 rule). Checkpoint 1 taken under the owner's advance approval of this session ("already approve and continue"): the plan is about 2,200 tokens, above the 1,600 guideline, kept whole because the cost example, the copies and the plan table are one user-facing goal (the launch content) and splitting them would separate the source page from its copies. Branch `feat/launch-content` in both repositories. Implementer subagent on Fable 5.1 (owner's 2026-10-16 decision).

- Built 2026-10-24 (implementer subagent, Fable 5.1). Counts measured through `count_tokens` with the owner's development key read from `../.secrets/anthropic-key.txt` (never printed): review input 1,389 / 2,304 / 3,941 tokens for 626 / 1,320 / 2,518 words; takeaways 1,184 / 2,393 / 4,144. Prices $3 / $15 per MTok for `claude-sonnet-4-6`, read 2026-10-24 at platform.claude.com/docs/en/about-claude/pricing. Per review $0.0417 / $0.0444 / $0.0493 (the 2,500-token output cap is $0.0375 of each), quoted rounded up to the cent so "at most" holds: $0.05 for every length; 20 posts $0.84–0.99, 80 posts $3.34–3.95 (review fix 2026-10-24). The script checks at start-up that its transcribed prompt text is still in the template's two nodes. Fixtures written at the three lengths (plan deviation already recorded in Decisions). How it works on the home page: the three steps already read connect → review (Click Review) → fix next (the Dashboard ranks); no caption reordered. The readme Screenshots section lists the loop order (Post Reviews' Review → the review → the Dashboard's Fix next); story 38 numbers the files. The drift check also refuses any dollar figure in a copy that is not in the source block and a `coming` in either FAQ. The counting script is Python (bin/ has PHP and JS; no PHP-only convention).

- Review patches verified by the orchestrator 2026-10-24 after the six patches: `python -I bin/count-cost-example.py --prices 3,15 --figures "24 October 2026"` exit 0, counts measured (review 1,389 / 2,304 / 3,941), figures block short/average/long 0.05, month 20 0.84–0.99, month 80 3.34–3.95; site `_tools/check_cost_copy.py` NO PROBLEMS; site built in both launch states (story 86's `build.sh` + `launched.yml` overlay) and `check_site.py` NO PROBLEMS in both; `coming` absent from both built `/docs/`; outbound hosts in the built pages: postquality.com, heimvin.me, docs.n8n.io only; `php tests/harness/release-header-harness.php` 31 passed, 0 failed; strict-shape key grep (`sk-ant-` + 30 chars) finds nothing outside the pre-existing test fixtures. Criterion 2 of the plan is thereby verified (triage row 6). Nothing deferred. Owner hitl left: the figures' wording, the communities row, the three readme Screenshots as the loop.

## Plan Change Log

## Review Triage Log

**Pass 1 (quick lens, Fable 5.1, 2026-10-24): 9 findings — high 0, medium 2, low 6, false 0, maybe-false 0, rejected 1; 6 → patch (the step-03 implementer re-engaged), 1 → intent gap resolved under the owner's delegation, 1 → verification re-run by the orchestrator. Matrix audit before review: Count and Price rows measured (script exit 0, prices quoted from Anthropic's pricing page 2026-10-24); Drift row covered by `check_cost_copy.py` with two mutation runs; Site rows built in both states by the implementer with story 86's `build.sh` + `check_site.py` (scratchpad copies); Readme row by the release-header harness (31/31) and the drift check.**

1. low → patch — `docs/cost-example.md` Skipped paragraph says the Pro caps run 2,000–8,000; `pqm_agent_max_tokens()` sets seo 1,000. Verified in `pqm-functions.php` L159–170.
2. medium → patch — "at most $0.04" with upper bounds of $0.0417 and $0.0444 in the same page's table: rounding down under "at most" makes the sentence false by its own arithmetic, in the source and the three copies. Verified. Fix: round up, regenerate the figures block, re-check the copies.
3. rejected by rule — acceptance criterion "grep sk-ant finds nothing" cannot pass because of pre-existing fake keys in `tests/harness/` and `tests/js/`, and the plan's own text; the only fix edits this plan. The intent (no real key) holds: a strict-shape grep finds no match (implementer and orchestrator).
4. low → patch — site `README.md` Launch flag paragraph still says the launched state drops the "coming" sentence; `docs.md` now carries the example in both states. Verified in the site diff.
5. medium → intent gap, resolved by the owner's delegation of 2026-10-24 ("already approve and continue") — the Intent says the home page's captures should be ordered as the loop; the home page's captures run Dashboard (hero) → review → setup by the owner's decision D10 of 2026-10-23 (value before requirements, build brief), and How it works holds one capture with step text already in loop order. Reordering would undo D10, a later and more specific owner decision. Resolution: the home page stays value-first; the loop order is carried by the readme's Screenshots section and the How it works step text. No code change; the plan's manual check and Implementation Notes corrected. Flagged for the owner in the hand-over.
6. low → verification by the orchestrator — criterion 2 (two-state build, `check_site.py`) ran on the implementer's side but was not recorded in the plan; the orchestrator re-runs it after the patches and records the result below.
7. low → patch — `memory/release-readiness.md` records "uncommitted" in the durable store; stale at the first commit (lesson 2026-10-12). Verified.
8. low → patch — `docs/user-paths.md` A2 UV line still says no cost guidance exists; the diff adds it. A plan gap (the Code Map named the Signal lines only), fixed as a one-sentence patch because it adds no surface; Definition of Done asks for the UV → CV adjustment.
9. low → patch — `bin/count-cost-example.py` transcribes the template's two system prompts with no check that they still match; a silent drift would make the next measurement wrong. Verified identical today by the reviewer. Fix: a containment check against the template's `jsCode` at start-up, and the prompt text named among the re-run triggers.

## Design Notes

The request the token count must mirror: `system` = the template's reviewer prompt with the default `website_context` (the "technical blog" profile, since the fixtures are tech posts) and the "professional" voice preset instruction; user message = `Title: … Excerpt: … Guidelines: … Full article: …` as `Build Review Body` concatenates them. Output cost uses the review cap of 2,500 tokens as the upper bound and says "at most". The monthly figures assume one review per post per month, stated as the assumption. Takeaways (free tier) can be a second, shorter line if the counts come cheaply from the same run; otherwise skipped: `Skipped: per-agent prices for the Pro agents — add when story 29 reports real usage.`

## Verification

**Commands:**
- `python -I bin/count-cost-example.py --prices <in>,<out>` -- expected: a table with three rows, non-zero counts, exit 0
- `python -I <site>/_tools/check_cost_copy.py --source <plugin>/docs/cost-example.md` -- expected: `NO PROBLEMS`
- site: `build.sh` both states, then `check_site.py` against each `_site` -- expected: NO PROBLEMS, no third-party host
- `grep -rn "sk-ant" "C:/Git/Post Quality/post-quality-manager" "C:/Git/Post Quality/postquality.com" --exclude-dir=node_modules --exclude-dir=vendor` -- expected: no match

**Manual checks (if no CLI):**
- Owner (hitl): the figures read right and the channel list is theirs; the readme's Screenshots and the How it works steps read as the loop (the home page's captures stay value-first per brief D10).
