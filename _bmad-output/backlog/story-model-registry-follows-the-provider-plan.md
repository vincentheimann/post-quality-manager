---
title: "The AI Model section offers the provider's current models, and the registry follows their life cycle"
type: 'feature'
ticket: '101'
created: '2026-11-04'
status: 'built'
baseline_revision: '5c629554e27ed993b0c0e5ef38834cc7383ed20d'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Settings → AI offers four Claude models that the provider now lists as "legacy"; its current models (Opus 5.5, Sonnet 5.5, Haiku 5.5) are reachable only through the Custom model ID, and the default is Sonnet 4.6. The current models think by default, and their thinking counts against each agent's small output budget.

**Approach:** Add the three current models to the registry, listed first, with the four existing ones kept below as earlier models; make Sonnet 5.5 the default; send each model only the parameters it accepts, turning thinking off where the model allows it and giving output headroom where it does not; re-measure the cost example on the new default.

**Decisions (owner, 2026-11-04):**
- The list: Opus 5.5, Sonnet 5.5, Haiku 5.5 first ("Current models"), then Opus 4.8, Sonnet 5, Sonnet 4.6, Haiku 4.5 ("Earlier models"). No saved choice changes. Fable 5.1 is not listed (Custom model ID reaches it).
- The default: Sonnet 5.5. The cost example is re-measured, the release notes say it, and the site's AI-tab capture is refreshed.
- The real-call proof (Tasks) uses the key in `../.secrets/anthropic-key.txt`, read by the script and never printed: 14 calls, typically a few cents (owner, 2026-11-04, at the checkpoint).

## Boundaries & Constraints

**Always:** `anthropic-version: 2023-06-01` stays; the Custom model ID path and its conservative profile stay; the per-agent effort setting stays the user's; prices in labels are the provider's list prices read 2026-11-04 (Opus 5.5 $4/$20, Sonnet 5.5 $2/$10, Haiku 5.5 $0.10/$0.50 for prompts up to 100K tokens, Opus 4.8 $5/$25, Sonnet 5 $2/$10, Sonnet 4.6 $3/$15, Haiku 4.5 $1/$5); a site that saved a model keeps it.

**Never:** another provider (story 25); a new API version; `temperature` to any model that rejects it; a `thinking` value a model rejects at the effort sent; streaming.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Sonnet 5.5, effort ≤ high | agent `review`, effort `high` | `thinking: {type: between_tools}`, no `temperature`, `output_config.effort: high`, `max_tokens` = the agent budget | — |
| Haiku 5.5, effort ≤ high | effort `medium` | `thinking: {type: disabled}`, no `temperature`, effort `medium`, the agent budget | — |
| 5.5 model that must think | Opus 5.5 at any effort; Sonnet or Haiku 5.5 at `xhigh`/`max` | no `thinking` key (adaptive), no `temperature`, `max_tokens` = budget + the thinking reserve | — |
| Earlier models unchanged | Opus 4.8, Sonnet 5, Sonnet 4.6, Haiku 4.5 | exactly today's fragment (Sonnet 5 `disabled`; Sonnet 4.6 and Haiku 4.5 `temperature`; Haiku 4.5 no effort) | — |
| Fresh site | no stored `default_model` | Sonnet 5.5 | — |
| Saved earlier model | stored `claude-sonnet-4-6` | stays selected after a Settings save | — |
| Custom ID | `custom` + any ID | today's conservative fragment | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/pqm-functions.php` -- `postquality_default_settings()` L38 default → `claude-sonnet-5-5`; L137 the custom fallback stays `claude-sonnet-4-6`'s role → `claude-sonnet-5-5`. `postquality_model_registry()` L75: three new entries first, a `group` key (`current` / `earlier`), labels ("Claude Opus 5.5 — highest quality", "Claude Sonnet 5.5 — best quality/cost balance", "Claude Haiku 5.5 — fastest, lowest cost"; earlier ones lose the superlatives), Sonnet 5's price fixed to $2 / $10, and a thinking rule per model replacing the `'thinking' => 'disabled'|null` flag: how to turn thinking off (`disabled`, `between_tools`, or never) and up to which effort, and whether the model thinks when the key is omitted. Update the capability notes above it. `postquality_llm_params()` L170: apply the rule; when the request may think, `max_tokens` = budget + a `POSTQUALITY_THINKING_RESERVE` of 12,000 (rewrite's 8,000 + 12,000 stays under 21,333, the non-streaming ceiling).
- `post-quality-manager/admin/class-pqm-admin.php` -- the Default Model select (L871–890) and the per-agent model selects (around L1060): `<optgroup>`s "Current models" / "Earlier models" from `group`; the sanitizer (L363–388) keeps accepting every registry key. `wp_localize_script` (L193–203) passes the registry as today.
- `post-quality-manager/admin/js/pqm-admin.js` L207 -- reads caps from the localized registry; check it copes with the new thinking rule (it reads `supports_temperature` and `effort_levels`).
- `tests/harness/sanitize-matrix-harness.php` -- model IDs it pins (L86, L163, L368): keep valid.
- New harness rows (the existing LLM-params harness, or `tests/harness/model-registry-harness.php`): every matrix row, plus one row per listed model checking its fragment against a table of the provider's documented rules (no `temperature` where rejected; a `thinking` value accepted at the effort sent; effort in the model's levels).
- `docs/release-notes/unreleased.md` -- one line: the three current models; Sonnet 5.5 is the default for sites that never saved Settings; a site that saved one keeps it (pick Sonnet 5.5 in Settings → AI to switch).
- `bin/count-cost-example.py` L53 `DEFAULT_MODEL`; `docs/cost-example.md` and the `readme.txt` FAQ copy -- re-measured with `--model claude-sonnet-5-5 --prices 2,10 --figures "4 November 2026"`.
- `memory/llm-options-architecture.md`, `memory/lifecycle.md` (AI models row).
- (Change Log 1) `pqm-functions.php` `postquality_agent_max_tokens()`: sources 2,000 → 3,000, audit 5,000 → 8,000, rewrite 8,000 → 12,000. `templates/n8n-workflow.json`: node timeouts Claude - Takeaways Agent and Claude - Sources Agent 30 s → 60 s, Claude - Audit Agent and Claude - Rewrite Agent 120 s → 180 s; template version 9 (`POSTQUALITY_WORKFLOW_VERSION`, Parse Input's stamps, the trigger's reply); `n8n-workflow-free.json` re-derived (`php bin/derive-free-template.php`). Harness pins of version 8 → 9; `model-registry-harness.php` drops the 21,333 check (the SDKs' guard, not the workflow's bound). Release notes: one paragraph (version 9, the longer waits and larger budgets), and the earlier unreleased paragraphs that quote "this plugin needs 8" made true for 9. `docs/cost-example.md` "1,000 to 8,000 tokens" → "1,000 to 12,000". Memory: the node timeouts and budgets in `llm-options-architecture.md`, the version in `lifecycle.md` and wherever the version list is kept.

## Tasks & Acceptance

**Execution:**
- [x] `pqm-functions.php` -- registry, default, thinking rule, reserve -- the current models with valid requests
- [x] `class-pqm-admin.php`, `pqm-admin.js` -- grouped selects; JS caps unchanged in behaviour -- the list reads current first
- [x] harness rows -- the matrix and the per-model rule table -- AC2, AC5
- [x] `count-cost-example.py`, `docs/cost-example.md`, `readme.txt` -- re-measured (orchestrator runs the script: it reads `../.secrets/anthropic-key.txt` and never prints it; the token-count endpoint is free) -- the cost example matches the default
- [x] `unreleased.md`, memory -- the records
- [x] Real-call proof (orchestrator, owner's consent at the checkpoint): one Review and one Key Takeaways request per listed model with its exact fragment on a fixture post, recording status, `stop_reason`, output tokens and latency against the node timeouts (Takeaways and Sources 30 s, SEO 60 s, the others 120 s). A 400, a `max_tokens` stop or a latency over half its node's timeout is fixed in this story (a timeout change is a template version bump and a release-notes line)
- [x] (Change Log 1) budgets and template version 9 timeouts as in the Code Map -- every proof case above under half its new node timeout, no `max_tokens` stop
- [ ] postquality.com, after the plugin PR merges: the cost copies (its drift check reads the plugin's figures) and the AI-tab capture and alt text on the Features page -- a site PR

**Acceptance Criteria:**
- Given the real-call proof, when it ends, then every listed model answered 200 on both requests with `stop_reason: end_turn`, and the results are in Implementation Notes.

## Implementation Notes

**Build, 2026-11-04 (implementer):**
- Registry (`pqm-functions.php`): seven entries in select order, each with `group` (`current` / `earlier`) and a `thinking` rule `['by_default' => bool, 'off' => 'disabled'|'between_tools'|null, 'off_up_to' => effort|null]` in place of the flag. Opus 5.5 `[true, null, null]`; Sonnet 5.5 `[true, between_tools, high]`; Haiku 5.5 `[true, disabled, high]`; Sonnet 5 `[true, disabled, null]`; Opus 4.8, Sonnet 4.6, Haiku 4.5 `[false, disabled, null]` (never sent: they do not think when the key is omitted). The Custom profile is `[false, null, null]`, so it stays model + budget. `postquality_llm_params()` sends `off` when the model thinks by default and the effort sent is at or below `off_up_to`, otherwise adds `POSTQUALITY_THINKING_RESERVE` (12,000, defined in `pqm-functions.php` beside the budgets, as `POSTQUALITY_SETUP_OPTION` is). Key order of the fragment unchanged (model, max_tokens, temperature, output_config, thinking). Haiku 5.5's price label is `$0.10 / $0.50`; the docblock says it holds for prompts up to 100K tokens, which every agent request stays under (the post text is capped at 15,000 characters). The docblock points to `memory/llm-options-architecture.md` for the dated provider rules (lessons 2026-10-16).
- Admin (`class-pqm-admin.php`): one private helper, `render_model_options($registry, $selected)`, renders both selects' two optgroups, each model with its label and price (review fix 5) ("Current models", "Earlier models"); Custom stays a plain option after them, the per-agent Default option before them. The localized `defaultModel` (the JS fallback when the AI Model section is absent) resolved an empty Custom ID to `claude-sonnet-4-6`; it now says `claude-sonnet-5-5`, as `postquality_effective_model()` does. Sanitizer unchanged (reads the registry keys). `pqm-admin.js` unchanged: it reads only `temperature` / `effortLevels`, built from `supports_temperature` / `effort_levels`, which every entry keeps (a harness row checks it); optgroups do not change a select's `.value`.
- Template unchanged: the six Build nodes keep `claude-sonnet-4-6` as their fallback for a payload without an `llm` map, which this plugin never sends; changing it would be a template version bump (the ticket's open question; the Code Map names no template change).
- Harness: `tests/harness/model-registry-harness.php` (new, 41 rows): matrix rows 1–7, the earlier-models row against a transcription of the pre-101 `postquality_llm_params()` (5c62955) over every agent × effort (+ an unknown one) × three temperatures, and every listed model × agent × effort against a provider rule table written apart from the registry (temperature, effort levels, thinking type at the effort sent, reserve exactly when the request may think, ≤ 21,333). Mutation check: Sonnet 5.5 set to `disabled` fails 6 rows. `render-matrix-harness.php` (+10 rows): option order, both optgroups, labels and prices, fresh site → Sonnet 5.5 selected, a saved Sonnet 4.6 default and a saved Haiku 4.5 override stay selected, Custom selected.
- `bin/count-cost-example.py`: `DEFAULT_MODEL = "claude-sonnet-5-5"`. The re-measure, `docs/cost-example.md`, the `readme.txt` FAQ and the story-49 line of `memory/release-readiness.md` wait for the orchestrator's run (Tasks).
- Records: `docs/release-notes/unreleased.md` (one paragraph), `memory/llm-options-architecture.md` (a story-101 paragraph), `memory/lifecycle.md` (AI models row), `memory/MEMORY.md` (index line); `README.md`'s "Claude model choice" line, which listed the four old models (outside the Code Map, found by a grep for the old labels and IDs).
- Verification: `php -l` on the four touched PHP files, `node --check post-quality-manager/admin/js/pqm-admin.js`, the 22 harnesses green (0 failing), `npm run test:unit` 174/174; LF line endings in every touched file.

**Orchestrator, 2026-11-04:**
- Re-measure: `python -I bin/count-cost-example.py --model claude-sonnet-5-5 --prices 2,10 --figures "4 November 2026"` (token-count endpoint, key from `../.secrets/anthropic-key.txt`, never printed), exit 0, counts measured. Review input tokens 1,864 / 2,997 / 5,287 (short / average / long), so at most $0.0287 / $0.0310 / $0.0356 a review; 20 posts $0.58 to $0.72 a month, 80 posts $2.30 to $2.85. Sonnet 5.5's tokenizer counts about a third more tokens than Sonnet 4.6's for the same text (1,389 → 1,864 on the short post), and the lower prices still lower each review from at most $0.05 to at most $0.03–$0.04. Prices checked on the provider's pricing page the same day: Sonnet 5.5 $2 / $10, Opus 5.5 $4 / $20, Haiku 5.5 $0.10 / $0.50 up to 100,000-token prompts, Sonnet 5 $2 / $10 (footnote: the introductory price became the standard one; the $3 / $15 rise will not occur), Sonnet 4.6 $3 / $15, Opus 4.8 $5 / $25, Haiku 4.5 $1 / $5, so every label in the registry matches. Updated: `docs/cost-example.md` (paragraph, Method with the tokenizer and thinking notes, table, figures block, re-run command), the `readme.txt` FAQ paragraph, the story-49 line of `memory/release-readiness.md`. The site's `_tools/check_cost_copy.py` passes on the readme copy; its `docs.md` and `index.html` copies change in the site PR (Tasks).
- Real-call proof: `real-call-proof.py` (the implementer's script, read before running; `fragments.php` takes each fragment from `postquality_llm_params()` with the model as site default and default settings, the prompts from `count-cost-example.py`, checked against the template at start-up), on the `average` fixture, 14 calls, key never printed. **AC passed: 14 of 14 answered 200 with `stop_reason: end_turn`, every reply parsed as JSON, none over half its node timeout.**

  | Model | Review: output tokens, latency (/120 s) | Takeaways: output tokens, latency (/30 s) | Content blocks |
  |---|---|---|---|
  | Opus 5.5 (reserve: max_tokens 14,500 / 13,000) | 2,305, 24.8 s | 1,275, 10.7 s | thinking, text |
  | Sonnet 5.5 (`between_tools`) | 1,637, 13.0 s | 477, 3.9 s | text |
  | Haiku 5.5 (`disabled`) | 1,489, 8.4 s | 381, 2.4 s | text |
  | Opus 4.8 | 593, 9.7 s | 388, 5.9 s | text |
  | Sonnet 5 (`disabled`) | 1,219, 14.2 s | 374, 4.2 s | text |
  | Sonnet 4.6 (temperature 0.3) | 1,224, 30.0 s | 349, 9.4 s | text |
  | Haiku 4.5 (temperature 0.3) | 891, 10.6 s | 315, 4.5 s | text |

  What it shows: `between_tools` on Sonnet 5.5 and `disabled` on Haiku 5.5 return no thinking block. Opus 5.5 thinks on both agents, and its Takeaways reply took 1,275 output tokens, over the 1,000 budget, so without the reserve it would have stopped on `max_tokens`. The thinking reserve is needed. Its Takeaways latency, 10.7 s of 30 s (36 %), is the closest to the half-timeout line; one sample at default effort, not a timeout change (plan rule: over half). Not exercised: xhigh/max efforts (they think on every 5.5 model, with the reserve), the Pro agents (Rewrite, Sources, SEO, Audit).
- Plugin harness sweep on this branch, before the rebase: 22 of 22 green (the guard harness runs 63 rows here; 84 after the rebase onto 105's main).

**Pro-agent proof, 2026-11-04 (orchestrator; owner-approved 8 calls after review row 3b):**
- Script: `pro-proof.py`. Each body is what the template's own Build node makes (`build-body.js` runs the node's `jsCode`).
- Payload: the `average` fixture (plain `content`, block-editor `content_html`) and the cost example's profile and voice texts.
- Fragments come from `postquality_llm_params()`, the timeouts from the template.
- All 8 answered 200 with `end_turn`, and every reply had the shape its Parse node reads.

  | Agent (node timeout) | Opus 5.5: input, output tokens, latency | Sonnet 5.5: input, output tokens, latency |
  |---|---|---|
  | Sources (30 s) | 2,502, 2,653, **25.7 s (86 %, over half)** | 2,501, 1,527, 12.1 s |
  | SEO (60 s) | 2,556, 772, 8.3 s | 2,555, 314, 4.1 s |
  | Audit (120 s) | 7,190, 5,746, 53.7 s | 7,189, 4,617 (92 % of the 5,000 budget), 32.2 s |
  | Rewrite (120 s) | 4,518, 7,918, 59.8 s (at half) | 4,517, 4,464, 21.8 s |

- Opus 5.5 thinks on every agent: each reply carries a thinking block.
- **Fails the plan's rule:**
  - Opus 5.5 on Sources runs over half its node's timeout (25.7 s of 30 s). A longer post or a slower minute times it out.
  - Opus 5.5 on Rewrite sits at half (59.8 s of 120 s) on an average post.
- **Near the budget on the new default:** Sonnet 5.5 used 4,617 of Audit's 5,000 output tokens on an average post. Its tokenizer counts about a third more tokens than Sonnet 4.6's, so budgets sized on Sonnet 4.6 are tighter. Audit and Rewrite on the long post may stop on `max_tokens`; this is not measured yet.
- **Fix in this story** (Tasks rule: a timeout change is a template version bump and a release-notes line): the owner chose "Fix in 101" (2026-11-04). See Plan Change Log 1.

**Long-post proof and Change Log 1, 2026-11-04 (orchestrator; 5 calls, owner-approved with the fix):**
- `pro-proof.py --fixture long`: the `long` fixture, with `content_html` cut at 20,000 characters as the plugin does (18,496 here).
- Candidate budgets audit 8,000 and rewrite 12,000; these are the budgets Change Log 1 then adopted.
- All 5 answered 200 with `end_turn`, and every reply had its Parse node's shape.

  | Case | max_tokens | Input, output tokens | Latency |
  |---|---|---|---|
  | Opus 5.5 Sources | 14,000 | 4,767, 2,082 | 20.0 s |
  | Opus 5.5 Audit | 20,000 | 10,062, 7,782 | 71.1 s |
  | Opus 5.5 Rewrite | 24,000 | 7,557, 8,013 | 59.6 s |
  | Sonnet 5.5 Audit | 8,000 | 10,061, 3,032 | 22.2 s |
  | Sonnet 5.5 Rewrite | 12,000 | 7,556, 7,823 | 38.0 s |

- Sonnet 5.5 Rewrite's 7,823 output tokens would have stopped on `max_tokens` under the old 8,000 budget on a slightly longer post. Audit's output varied between posts: 4,617 on the average post, 3,032 on the long one.
- Built by the same Opus implementer:
  - budgets sources 3,000, audit 8,000, rewrite 12,000;
  - template version 9, with Takeaways and Sources at 60 s and Audit and Rewrite at 180 s; the Free template re-derived;
  - the version-8 pins updated in `rest-reviews-harness.php` and `template-failure-harness.php`;
  - `model-registry-harness.php` (42 rows): the 21,333 check dropped, the budgets pinned;
  - the release-notes paragraph "Longer waits for Claude's answer", the bug-97 paragraph's quote now "needs 9";
  - the records: cost example, memory, diagram.
- **The Change Log 1 task's check, against the new timeouts, over every call measured (30 calls in all):**
  - Highest share of the node timeout: Opus 5.5 Sources 25.7 s / 60 s (43 %), then Opus 5.5 Audit 71.1 s / 180 s (40 %), Opus 5.5 Takeaways 10.7 s / 60 s (18 %) and Sonnet 4.6 Review 30.0 s / 120 s (25 %).
  - Every call is under half its node's timeout.
  - Highest share of the output budget: Sonnet 5.5 Rewrite 7,823 / 12,000 (65 %), Review 1,637 / 2,500 (65 %), Audit 4,617 / 8,000 (58 %), Sources 1,527 / 3,000 (51 %).
  - No `max_tokens` stop.
- Verification: 22 harnesses with 0 failing, `npm run test:unit` 174 of 174, `php -l` clean, `git diff --check` clean.
- Not run: version 9 on a live n8n. Only the six Claude nodes' `options.timeout` values and the version stamps change; `template-guard-harness.php` (n8n 2.46.4's publish checks) passes on both templates. The walk at release 3.11.0 covers it.

## Plan Change Log

**1. 2026-11-04: the Pro-agent proof fails the Tasks rule; owner: "Fix in 101".**
- Trigger: review row 3b, settled by the owner-approved calls under Implementation Notes.
  - Opus 5.5 on Sources took 25.7 s of a 30 s node; on Audit 71.1 s of 120 s (long post); on Rewrite 59.8 s of 120 s.
  - Sonnet 5.5 used 4,617 of Audit's 5,000 tokens (average post) and 7,823 of Rewrite's 8,000 (long post, measured with a 12,000 candidate budget). Its tokenizer counts about a third more tokens than Sonnet 4.6's, so the budgets sized on 4.6 are tight.
- Amended (Code Map, Tasks):
  - Budgets: sources 3,000, audit 8,000, rewrite 12,000. Sources used 76 % on the average post, and Audit's output varied by half between two posts.
  - Node timeouts: Takeaways and Sources 60 s, Audit and Rewrite 180 s, each at least twice the slowest call measured. SEO stays 60 s and Review 120 s: their slowest calls were 8.3 s and 30.0 s.
  - Template version 9.
- Rule: the 21,333 bound is dropped, because it is the SDKs' guard and n8n applies none. The largest request is then Opus 5.5 Rewrite at 24,000.
- Known-bad state avoided: Opus 5.5 listed while its Sources and Audit calls ran close to n8n aborting them, and the new default's Rewrite cut off on a full-length post.
- KEEP: everything built and patched so far (the registry, the thinking rule, the reserve, the grouped and priced selects, the harness rows, the cost example, the records).
- Context: the plugin's own polling gives up at 3 minutes (`GIVE_UP_AFTER`) and says the run "is still running in n8n — results will appear when it finishes". A 180 s node therefore still lands its result.

## Review Triage Log

**Pass 1 (2026-11-04, Quick lens on Opus 5.5; diff since 5c62955, untracked files included):** high 0 · medium 0 · low 4 · false 0 · maybe-false 2. The lens re-ran the harnesses (green), recomputed every cost figure (all match) and checked the proof table against the run's output (it matches).

| # | Finding | Verdict | Route | Evidence / action |
|---|---------|---------|-------|-------------------|
| 1 | A Custom model ID that names a listed 5.5 model now gets that model's fragment; the help text and release notes don't say so | low | patch | Verified: caps are looked up by the effective ID, as before 101 (a Custom `claude-sonnet-4-6` already got Sonnet 4.6's fragment), so the Custom path is unchanged and only the list grew. The fragment those IDs now get is the valid one: the proof shows Opus 5.5 needs the reserve. What's wrong is the record. The Default Model help text (`class-pqm-admin.php` L882) says a Custom ID gets no temperature or effort, and the release notes don't tell a site that typed a 5.5 ID. Direct correction: the help text says "not in the list", and the release notes get one sentence. |
| 2 | Custom IDs of unlisted models that think by default (Fable 5.1) get no thinking reserve | maybe-false | defer | Pre-existing: the pre-101 Custom fragment is the same. The frozen matrix keeps it ("Custom ID → today's conservative fragment"). Unverified; medium if true. One Takeaways call on `claude-fable-5-1` with model + 1,000 settles it: a `max_tokens` stop means Custom needs the reserve, or Fable needs a registry entry. Appended to deferred-work. |
| 3a | 21,333 is called "the ceiling for a non-streaming request" (reserve docblock, memory, harness labels) | low | patch | Verified: 21,333 is the Anthropic SDKs' client-side guard, not an API limit. The SDKs refuse a non-streaming call whose `max_tokens` could run past 10 minutes at their assumed speed (3,600 s × max_tokens / 128,000 > 600 s). The API does not reject such a request, and n8n's HTTP Request node doesn't apply the guard; the real bound is each node's timeout. Direct correction of the wording. |
| 3b | The reserve lets thinking run past the n8n node timeouts; Sources (30 s), SEO (60 s), Audit and Rewrite (120 s) were not exercised | maybe-false | defer | The proof covered Review and Takeaways only (plan Tasks). Opus 5.5 ran at 93 to 119 output tokens per second, so a 30 s node ends near 2,800 to 3,600 tokens against `max_tokens` of 13,000 to 14,000. Open: whether a default-effort Pro agent on Opus 5.5 crosses half its node's timeout. Unverified; medium if true. One call per Pro agent on Opus 5.5 and Sonnet 5.5 settles it (8 calls; the owner's consent was asked 2026-11-04). Appended to deferred-work. |
| 4 | The new comments restate provider facts (lesson 2026-10-16) | low | patch | Verified: the registry docblock adds the 5.5 temperature rule and the 100K-token price tier, and the reserve docblock adds the 21,333 bound, while the dated facts live in `memory/llm-options-architecture.md`. The two copies already drift (row 3a). Fix is a deletion: the comments keep the local why and name the memory file. |
| 5 | The per-agent Model select shows no prices; ticket AC1 asks label and price for "default and per agent" | low | patch | Verified: `class-pqm-admin.php` L1071 passes `$priced = false`, and `render-matrix-harness.php` L1526 asserts there is no price. The pre-101 per-agent select had no prices either, and neither the plan nor a decision narrowed AC1. Direct correction: price both selects, drop the now-constant parameter, and flip the harness assertion. |

Patch round (2026-11-04, the same Opus implementer): rows 1, 3a, 4 and 5 were fixed.
- Row 1: the help text now reads "For a custom model ID that is not in the list…", and the release notes gained one sentence.
- Row 3a: memory now says the bound in practice is the node timeouts, and that 21,333 is the SDKs' client-side guard; the harness labels changed, the checks did not.
- Row 4: the provider facts left the docblocks for `memory/llm-options-architecture.md`, which now carries each removed fact.
- Row 5: `render_model_options()` lost `$priced`; both selects show prices, and the harness expects 7 priced options in the per-agent select.

The implementer also updated the helper's signature in this plan's Implementation Notes; that edit is accepted. Re-run after the patches:
- the 22 harnesses: 0 failing;
- `npm run test:unit`: 174 of 174;
- `php -l` on the 4 PHP files, and `node --check`;
- `git diff --check`: clean.

Rows 2 and 3b are in `deferred-work.md`. The owner then approved the 8 calls that settle row 3b (2026-11-04); results are under Implementation Notes.

## Design Notes

Thinking off where allowed: these agents return one JSON object; thinking adds cost and time and takes their small budgets. Sonnet 5.5 turns it off with `between_tools` and Haiku 5.5 with `disabled`, each only up to effort `high`; Opus 5.5 cannot, so it gets the reserve. A user who picks `xhigh`/`max` on Sonnet or Haiku 5.5 gets thinking with the reserve. The template already reads the text block by type, so thinking blocks in a reply break nothing.

## Verification

**Commands:**
- `for f in tests/harness/*-harness.php; do php "$f"; done` -- expected: 0 failing everywhere
- `npm run test:unit` -- expected: green
- `php -l` on every touched PHP file; `node --check post-quality-manager/admin/js/pqm-admin.js`
