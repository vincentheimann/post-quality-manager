---
title: 'The Parse nodes read the JSON object out of any reply around it'
type: 'bugfix'
ticket: '117'
created: '2026-11-06'
status: 'done'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
context: []
baseline_revision: 'f11a5b0157343bb81f5140856814da69c201ed83'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The five JSON Parse nodes strip only an anchored leading ```` ```json ```` and trailing ```` ``` ````, so a valid reply with a closing sentence after the fence (Sources) or a `<thinking>` passage before it (Audit, whose prompt invites one, and whose node removes it only after the fence regexes ran) is stored as "<Agent> returned invalid JSON." — seen 2 of 2 on Claude Sonnet 5.5, the default.

**Approach:** One extraction, the same text in all five Parse nodes: drop the `<thinking>…</thinking>` passages that open the reply (one or more, before the object; owner decision 2026-11-06 at review pass 1: never inside the object, so a JSON string that quotes the tags stays whole), then parse the text from its first `{` to its last `}`. Ship it as template version 10 (Update workflow on every site), with harness rows running each node's real code on every reply shape.

## Boundaries & Constraints

**Always:** the five nodes (Review, Takeaways, Sources, SEO, Audit) carry the identical extraction lines; a reply with no parsable object keeps today's "<Agent> returned invalid JSON." message; template version 10 in every stamp, the Free template re-derived with `php bin/derive-free-template.php`; one release-notes paragraph (Update workflow needed); LF files; the prompts, budgets and timeouts unchanged.

**Never:** a prompt change (the Audit prompt's `<thinking>` invitation stays: the parser handles it); a fence-content regex (it stops at a ```` ``` ```` inside a JSON string, e.g. a code example in a rewritten item); touching Parse Rewrite Response (its delimiters parse) or the Build nodes; a new dependency.

## I/O & Edge-Case Matrix

| Scenario | Reply text (one `text` block) | Expected | Error Handling |
|----------|------------------------------|----------|----------------|
| Bare | `{…}` | stored as today (score, status, result) | — |
| Fenced | ```` ```json\n{…}\n``` ```` | the same as Bare | — |
| Thinking, then fenced (Audit, 2026-11-06) | `<thinking>…{…}…</thinking>\n\n```json\n{…}\n``` ` | the same as Bare; braces inside the passage ignored | — |
| Fenced, then prose (Sources, 2026-11-06) | ```` ```json\n{…}\n```\n\nOne closing sentence. ```` | the same as Bare | — |
| A brace inside a JSON string | `{"reason": "use {x}"}` with any wrapper above | parsed whole | — |
| Two passages, then fenced | `<thinking>…</thinking>\n<thinking>…{…}…</thinking>\n```json\n{…}\n``` ` | the same as Bare | — |
| A JSON string quoting the tags (owner, review pass 1) | `{"edit": "Explain <thinking>…</thinking> in a sentence."}`, bare or after a passage | parsed whole, the string as written | — |
| A passage after the object | ```` ```json\n{…}\n```\n<thinking>no braces</thinking> ```` | the same as Bare (outside the slice) | with a brace inside it: "<Agent> returned invalid JSON." (the Audit prompt asks the object last) |
| No object | `Sorry, I cannot help with that.` | — | "<Agent> returned invalid JSON." (today's row) |

</frozen-after-approval>

## Code Map

- `post-quality-manager/templates/n8n-workflow.json` -- Parse Review Result (L156), Parse Takeaways Result, Parse Sources Result (L292), Parse SEO Result, Parse Audit Result (L428): each builds `raw` in one line inside `try { … r = JSON.parse(raw); } catch(e) { return … 'returned invalid JSON.' }`; replace that line only (Audit's carries the late `<thinking>` strip). Version stamps: the Webhook Trigger's `responseData` `{"ok":true,"workflow_version":9}` (L10) and Parse Input's `workflow_version: 9` (jsCode, L29).
- `post-quality-manager/templates/n8n-workflow-free.json` -- derived; never edited by hand.
- `post-quality-manager/post-quality-manager.php` L95 -- `POSTQUALITY_WORKFLOW_VERSION` 9 → 10.
- `tests/harness/template-failure-harness.php` -- runs the nodes' real `jsCode` through `node` (`run_code()`, `parse_refs()`, `post_body()`); version pins L104, L113, L116, L119–120, L163; the Unparseable output row (L183–189) is AC2's; `$review_json` (L248) is the valid Review object to reuse; the other four need minimal valid objects holding the fields their nodes read (Takeaways: `block_found`, `position_correct`, `count`, `count_ok`, `items_are_specific`, `items_within_word_limit`, `issues`, `rewritten_items`; Sources: `total_claims`, `sourced`, `unsourced_critical`, `claims`, `overall_verdict`, `rewritten_sources`; SEO: `keyphrase_assessment`, `keyphrase_reason`, `keyphrase_changed`, `proposed_keyphrase`, `metadesc_assessment`, `metadesc_reason`, `metadesc_changed`, `proposed_metadesc`, `char_count`; Audit: `review`, `takeaways`, `sources`, `seo` as those shapes).
- `tests/harness/rest-reviews-harness.php` L312 -- the `POSTQUALITY_WORKFLOW_VERSION is 9` pin.
- `tests/harness/free-build-harness.php` L337 -- matches `workflow_version: \d+`: no change expected; check it passes.
- `docs/release-notes/unreleased.md` -- a new paragraph; every quote of the page's "needs <n>" message names 10 (the v9 paragraph L61 keeps describing what v9 changed). The paragraph says that on an older workflow any of the five agents can still fail that way, not only Sources and Audit (review pass 1, row 1): v9's Review, Takeaways and SEO carry the same anchored-fence line.
- `n8n/workflow-diagram.md` L50, L79 (version 9 → 10) and one bullet beside L68 for the parse; the bullet says a v9 workflow can fail on any of the five, and that v9's Parse Audit Result removed `<thinking>` only after the fence regexes.
- `memory/lifecycle.md` -- the n8n row names template version 10 as in use (harness-run, not yet on a live n8n), version 9's walk kept beside it.
- `memory/llm-options-architecture.md` -- the version handshake list (L15) gains v10; the reply-shapes paragraph (L13) says bug 117 fixed it in v10.
- KEEP from pass 1 (reverted at review pass 1, intent_gap; its harness design and records were sound): the "Reply shapes (bug 117)" section ran each node on its bare object (score and status checked, the object stored whole), then compared every wrapped shape's item with the bare run's whole item; one string the node reads in every object held a brace pair and a ```` ```js ```` fence; the Audit object was built from the four others; the pins and the ping label ("stamps 8" while it checked 9) moved to 10. Run the new rows once against the baseline template: they must fail there.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/templates/n8n-workflow.json` -- the extraction line in the five Parse nodes; version 10 in both stamps -- AC1, AC3
- [x] `post-quality-manager/templates/n8n-workflow-free.json` -- `php bin/derive-free-template.php` -- AC3
- [x] `post-quality-manager/post-quality-manager.php` -- version 10 -- AC3
- [x] `tests/harness/template-failure-harness.php`, `tests/harness/rest-reviews-harness.php` -- pins to 10; a row per Parse node × each matrix shape, the stored score, status and result equal to the Bare run's -- AC1, AC2
- [x] `docs/release-notes/unreleased.md`, `n8n/workflow-diagram.md`, `memory/llm-options-architecture.md` -- the records -- AC3

**Acceptance Criteria:**
- Given each of the five Parse nodes and each reply shape of the matrix with an object, when the harness runs the node's code, then it returns `error: false` and the same callback body as for the bare object.
- Given a reply with no object, when any of the five nodes reads it, then the error message is today's.
- Given the change, when the harnesses run, then every file passes, the Free template is the derived one, and both templates stamp version 10.

## Implementation Notes

- **Pass 1, reverted after review pass 1 (intent_gap, see the Change Log):** 2026-11-06 (implementer subagent, Opus): the Design Notes' two lines replace each node's `raw` line inside its `try`, identical in all five (one distinct text, checked by script); `.trim()` dropped since the slice starts at `{`. Only Webhook Trigger, Parse Input and the five Parse … Result nodes differ from the baseline (node set, connections and the rest unchanged); the Free template re-derived (its Webhook Trigger, Parse Input, Parse Review Result and Parse Takeaways Result).
- Harness: "Reply shapes (bug 117)" runs each of the five nodes on its bare object (score and status checked: Review 72 ship, Takeaways 80 ship, Sources 55 and SEO 55 needs_revision, Audit 72 ship, the object stored whole), then on fenced, thinking-then-fenced (braces inside the passage) and fenced-then-prose, each compared with the bare run's whole item (callback body and report). Matrix row 5 rides every object: one string the node reads holds a brace pair and a ```` ```js ```` fence. AC2 stays the Unparseable output row (comment names bug 117). The Audit object is built from the four others. Against the baseline template the new rows fail 10 of 20 (thinking-then-fenced and fenced-then-prose on all five nodes; bare and fenced pass), so they reproduce the bug.
- The ping check's label said "stamps 8" while it checked 9; it now says 10 with the pin.
- Beyond the Code Map: `memory/lifecycle.md`'s n8n row named "template version 9" as in use; it now names version 10 (harness-run, not yet on a live n8n), version 9's walk kept beside it.
- Verification: every `tests/harness/*.php` 0 failed (template-failure 111, rest-reviews 79, free-build 98, template-guard 84); `npm run test:unit` 181 passed; `php -l` clean on the three touched PHP files; `node --check` clean on the seven Parse node codes (Pro and Free); `git diff --check` clean; every touched file LF (the two templates keep their baseline's missing final newline).
- **Pass 2, 2026-11-06 (implementer subagent, Opus 5.5), after the Change Log's amendment:** the Design Notes' two lines (passages removed only when they open the reply, `+` for several) replace each node's `raw` line inside its `try`, one distinct text in the five nodes of both templates (checked by script); `.trim()` dropped since the slice starts at `{`. Only Webhook Trigger, Parse Input and the five Parse … Result nodes differ from the baseline (node set, connections and the rest unchanged); the Free template re-derived (its Webhook Trigger, Parse Input, Parse Review Result and Parse Takeaways Result).
- Harness, pass 2: the KEEP design, rebuilt. "Reply shapes (bug 117)" runs each node on its bare object (Review 72 ship, Takeaways 80 ship, Sources 55 and SEO 55 needs_revision, Audit 72 ship; the object stored whole), then on six wrapped shapes, each compared with the bare run's whole item and callback body: fenced; a passage holding braces, then fenced; fenced, then prose; two passages, then fenced (braces in the second); a passage, then the bare object; fenced, then a passage without braces. One more row per node: fenced, then a passage holding a brace → "<Agent> returned invalid JSON." (matrix row 8's error column). Rows 5 and 7 ride every object: one string the node reads holds a brace pair, a ```` ```js ```` fence and a quoted `<thinking>...</thinking>` pair, encoded with literal slashes (`JSON_UNESCAPED_SLASHES`) so the text carries `</thinking>` as Claude writes it. The Audit object is built from the four others. AC2 stays the Unparseable output row (comment names bug 117).
- Against the baseline template the 40 new rows fail 26: five of the six wrapped shapes on every node (fenced passes), and Audit's bare row (v9's late strip cut the quoted pair out of the string); the version pins fail 10 more. Mutation check (the template swapped and restored byte for byte): pass 1's global strip fails 10 rows (every bare row and every brace-after row), one passage only 5 (two passages), a greedy passage 15, no removal 10.
- Beyond the Code Map: `memory/llm-options-architecture.md`'s version-handshake paragraph, which names each version's change, gains v10's sentence; `memory/lifecycle.md`'s n8n row as listed.
- Verification, pass 2: every `tests/harness/*.php` 0 failed (template-failure 131, rest-reviews 79, free-build 98, template-guard 84); `npm run test:unit` 181 passed; `php -l` clean on the three touched PHP files; `node --check` clean on the seven Parse node codes (Pro and Free); `git diff --check` clean; every touched file LF (the two templates keep their baseline's missing final newline).
- Review pass 2 patches (2026-11-06, the same implementer, records only): the reply-shapes paragraph of `memory/llm-options-architecture.md` and the diagram's v10 bullet say a passage after the object that holds a brace fails on v10, where v9's Parse Audit Result parsed the bare form; the release-note paragraph names what v10 reads (a closing sentence after the answer, reasoning before it) and says the Audit prompt allows the reasoning. The orchestrator narrowed the diagram bullet's title ("out of the reply around it"). Verification re-run on the final tree: harness files 23, 0 failed; `npm run test:unit` 181; `php -l` and `git diff --check` clean; LF.
- Owner decisions (2026-11-06): severity P1 (the ticket proposed it), branch `fix/117-parse-json-in-reply`, the plan kept whole at both checkpoints.
- bmad-build ran from the workspace root through the Skill tool (registered mid-session); implementers and both Quick lenses on Opus 5.5. Not run live: real calls on a disposable n8n go with the next release's walk.
- **Walked live, 2026-11-06 afternoon (Opus 5.5 session; #253 merged as 4e7b87f):** the showcase on the Pro build of main 4e7b87f (template v10), licensed by the owner, a fresh disposable n8n 2.46.6 provisioned with the owner's key from `.secrets/` (never printed): ping OK, version 10 Pro variant. On the demo post where v9 failed in the morning, Claude Sonnet 5.5, one run each:

  | Agent | Reply shape (n8n execution) | Stored |
  |---|---|---|
  | Sources | fenced JSON, then 942 characters of prose | `ship`, 85 (6 s; v9: "returned invalid JSON.") |
  | Audit | `<thinking>` passage, then fenced JSON | `needs_revision`, 42 (15 s; v9: "returned invalid JSON.") |
  | Review | bare object | `major_revision`, 22 (12 s) |
  | Takeaways | fenced JSON | `needs_revision`, 50 (6 s) |
  | SEO | bare object | `needs_revision`, 55 (6 s) |

  All `end_turn`; `debug.log` only WP-CLI's own lines. License deactivated on the Account page before the restore; restore 3× "same"; n8n stopped by its PID, its folder and the walk files deleted. With the walk-recipe fix of the morning (wait for the editor's log line) the owner step took at the first form send.

## Plan Change Log

**1. 2026-11-06, review pass 1, intent_gap (row 2): "drop every `<thinking>` passage" cut into JSON strings; owner: "Only before the object".**
- Trigger: the shared line removed `<thinking>…</thinking>` over the whole text, so a string quoting the tags lost text silently, and a pair split across two strings deleted a key (reproduced: `{"a":"open <thinking>","b":"close </thinking> here"}` → `{"a":"open  here"}`).
- Amended: the frozen Approach (the owner's answer, written in as a decision); three matrix rows (two leading passages, a string quoting the tags, a passage after the object); Design Notes; the Code Map's release-note and diagram lines (row 1); `memory/lifecycle.md` added to the Code Map; tasks unticked.
- Known-bad state avoided: a review or rewrite of a post about prompting stored with a key or a quote missing, as a success.
- KEEP: everything in the Code Map's KEEP line; the two extraction lines' shape (one text in all five nodes, the slice first `{` to last `}`); the version bump and records list.

## Review Triage Log

- 2026-11-06, Quick lens pass 1 (Opus 5.5): 4 findings, 0 high, 0 medium, 4 low, 0 false, 0 maybe-false. One intent_gap, so this pass loops back to the owner.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | `unreleased.md` (new paragraph) and `workflow-diagram.md` (v10 bullet) say an older workflow's Sources and Audit can still fail; all five can | low | patch (after the loopback) | Baseline Review, Takeaways and SEO carry the same anchored-fence line; the new rows run on f11a5b0 fail on all five nodes. A Free site, which has only Review and Takeaways, is told it has nothing to fix. |
| 2 | Dropping every `<thinking>…</thinking>` over the whole text cuts into JSON strings; a pair split across two strings deletes the JSON between them | low | intent_gap | Reproduced: `{"a":"open <thinking>","b":"close </thinking> here"}` parses as `{"a":"open  here"}`, stored as a success. Four nodes never stripped the tag before. The smallest fix, dropping only the passages before the object, changes the frozen Approach ("drop every passage"), which only the owner can do. |
| 3 | A prose reply holding a small object (`… {} …`) now parses, then Takeaways (`r.rewritten_items.forEach`, `r.issues.forEach`) and Audit (`rv.status`) throw outside the `try`; no `onError`, Error Fallback only behind Route Command, so the run ends with no callback | low | defer (entry written in `deferred-work.md` now: pre-existing, so the loopback does not re-derive it) | Confirmed in the template. The root is older than this change: v9 crashed the same way on a bare reply missing those fields. This change widens it only to prose replies that hold braces. Fixing it needs shape guards in five nodes. |
| 4 | No harness row covers two passages, or a passage after the object | low | moot until 2 is settled | The single passage sits before the object, so the rows would pass with the `g` flag dropped. Re-derived with the owner's answer to 2. |

- 2026-11-06, Quick lens pass 2 (Opus 5.5, on the re-derived code): 3 findings, 0 high, 0 medium, 3 low, 0 false, 0 maybe-false; none carried from pass 1. Rows 1 and 4 of pass 1 are met in this code: the release note and diagram name all five agents, and the harness has the two-passage and after-the-object rows.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 5 | A bare Audit object followed by a passage holding a brace (`{…}` then `<thinking>One more {thought}.</thinking>`) parsed on v9 and fails on v10; the records call the after-the-object failure a limit v9 already had | low | patch (records) | v9's Parse Audit Result removed the first passage anywhere after its fence regexes, so the bare form parsed; v10's anchored removal leaves it and the slice ends inside it. Matrix row 8 (owner, review pass 1) accepts that error, so the code stays. Prompted replies end with the object. |
| 6 | The release note says v10 reads the answer "whatever surrounds it" and that the Audit agent "asks for" its reasoning | low | patch (records) | A brace in prose outside the object, or a passage after a preamble sentence, still fails (probed on v10). Build Audit Body's prompt allows a `<thinking>` block ("if you want"), it does not ask for one. |
| 7 | A prose reply holding a small object (`I cannot review an empty post, so I return {}.`) now stores a success in Review, Sources and SEO (scores 0, 50, 55), where v9 stored the invalid-JSON error | low | defer (new entry) | Same root as row 3: nothing checks the parsed object against the fields the node reads. v9 already stored a bare `{"error": …}` as a success. The fix is a shape check in five nodes, more than a direct correction; the plan's triage rows are not edited. |

## Design Notes

First `{` to last `}` needs no knowledge of the wrapper: fences, a closing sentence, a preamble all fall outside the braces, and a fence inside a JSON string stays inside them. The opening `<thinking>` passages go first because they may hold braces; anchored at the start, the removal never reaches into the object (owner, review pass 1). Skipped: a string-aware brace scan — add when a reply's prose outside the object carries a brace.

```js
const text = ((resp.content || []).find(b => b.type === 'text')?.text || '').replace(/^\s*(?:<thinking>[\s\S]*?<\/thinking>\s*)+/i, '');
const raw = text.slice(text.indexOf('{'), text.lastIndexOf('}') + 1);
```

## Verification

**Commands:**
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every file 0 failed
- `npm run test:unit` -- expected: 181 passed
- `php -l post-quality-manager/post-quality-manager.php`; `git diff --check` -- expected: clean

**Manual checks (if no CLI):**
- Not run live: real calls on a disposable n8n go with the next release's walk (the harness runs each node's real code on the observed shapes).
