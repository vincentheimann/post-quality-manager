---
id: 121
type: bug
title: "A reply without results leaves Takeaways and Audit running forever, and gives Review, Sources and SEO a made-up score"
parent: none
covers: [UV1, UV3]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
severity: P2
estimate: ""
---

# A reply without results leaves Takeaways and Audit running forever, and gives Review, Sources and SEO a made-up score

## Description

When Claude answers a command with an object that holds none of the results the command asks for (`{}`, a sentence that mentions `{}`, or `{"error": "no content"}`), the site never learns what happened. Takeaways and Audit stop inside n8n and never call the site back: the post shows the command running, Post Reviews stops waiting after three minutes, and nothing is stored. Review, Sources and SEO store a success with a score nobody gave (0, 50 and 55) and the reply's object as the result. Instead, the site should store an error for that post and command, as it does for a reply that is not JSON. One person step: the live run of the Pro commands on the new workflow needs the owner to activate the license on the showcase (Notes).

## Reproduction

1. Workflow template version 10, Pro and Free (main 5288cb3, release 3.13.1). Each `Parse … Result` node's code was run in Node.js on 2026-11-10 with n8n's `$input` and `$()` stood in for, on three replies with `stop_reason` `end_turn`: a bare `{}`, `I cannot review an empty post, so I return {}.`, and `{"error": "no content"}`.
2. Actual, the same for all three replies and on both templates where the node exists:
   - Parse Review Result: success, score 0, status `needs_revision`, result `{}` (or the error object).
   - Parse Takeaways Result: throws `TypeError: Cannot read properties of undefined (reading 'forEach')`.
   - Parse Sources Result: success, score 50, status `needs_revision`.
   - Parse SEO Result: success, score 55, status `needs_revision`.
   - Parse Audit Result: throws `TypeError: Cannot read properties of undefined (reading 'status')`.
3. A throwing Code node ends the run (n8n's default for a node without `onError`; not run in n8n): the Parse nodes have no error output and no error path leads from them, so `POST Result to WordPress` never runs and the post's row stays `processing`.
4. Expected: for each of the five commands, the site stores an error naming the agent, and the post stops showing the command as running.

## Cause Hypothesis

Each Parse node's `try` covers only the JSON parse. The node reads the parsed object's fields after the `try`, assuming the object has the shape its prompt asks for. A missing field either throws there (Takeaways' suggested rewrite list, Audit's editorial review) or falls back to a default (Review's `|| 0`, Sources' score map default, SEO's "not both ok" branch), so an object without results passes as a result. Version 10's extraction, from the first `{` to the last `}` (bug 117), makes this more common: prose that mentions `{}` now parses, where version 9 stored the invalid-JSON error for it.

## Acceptance Criteria

1. **A reply without results is stored as an error**
   **Given** Review, Takeaways, Sources, SEO or Audit, on each workflow (Pro or Free) that carries it
   **When** Claude's reply parses as JSON but lacks a field the command's score or status is computed from
   **Then** the result is stored the way a reply that is not JSON is: an error for that post and command, whose message names the agent and says what to do
   **And** a reply holding those fields is stored as before, its score, status and result unchanged
2. **Every run reports back**
   **Given** a reply to any of these commands that parses as JSON, whatever fields it holds
   **When** the command runs
   **Then** the site receives the outcome, the post stops showing the command as running, and the notification is sent when one was asked for
3. **Existing sites learn they must update the workflow**
   **Given** a site whose workflow reports version 10
   **When** the release carrying the fix is installed
   **Then** the n8n Workflow page offers the workflow update
4. **Tests cover the condition found and fixed**
   **Given** the test suite
   **When** it runs
   **Then** tests that fail on version 10 and pass on the fix cover each of the five commands on the Pro template, and Review and Takeaways on the Free one, with a bare `{}`, a sentence holding `{}`, and an object holding only an error field; one covers a Takeaways reply with its score fields and no suggested rewrite; and the tests for replies with results stay green
5. **Or: no change is needed, with proof**
   **Given** the reproduction
   **When** it is run on the current template
   **Then** each command already stores an error and reports back, or the report was mistaken, with the evidence recorded in Notes — this supersedes 1 to 4

## References

- parent — none
- source — _bmad-output/initiative-post-quality-manager/deferred-work.md, the two entries from bug 117's plan
- source — _bmad-output/backlog/bug-sources-and-audit-fail-on-a-valid-reply-plan.md, Review Triage Log (pass 1 row 3, pass 2 row 7)
- source — _bmad-output/backlog/bug-sources-and-audit-fail-on-a-valid-reply.md (bug 117, version 10's extraction)
- record — memory/llm-options-architecture.md (the failure contract and the workflow version handshake)
- verification — docs/local-verification.md, "A disposable n8n"
- copy — docs/brand.md

## Notes

- Fact (template version 10, read 2026-11-10): the fields each node reads after its `try`. Review: `overall_score`, `status`, the four dimension scores, `top_edits`. Takeaways: `block_found`, `position_correct`, `count_ok`, `count`, `items_within_word_limit`, `items_are_specific`, `issues` (guarded), `rewritten_items` (unguarded). Sources: `overall_verdict` (the score), `total_claims`, `sourced`, `unsourced_critical`, `claims`, `rewritten_sources`. SEO: `keyphrase_assessment` and `metadesc_assessment` (the score), `keyphrase_reason`, `metadesc_reason`, `keyphrase_changed`, `metadesc_changed`, `proposed_keyphrase`, `proposed_metadesc`, `char_count`. Audit: `review` (unguarded; its `overall_score` and `status` are the stored ones), then `takeaways`, `sources`, `seo`, each guarded.
- Fact (2026-11-10): the Rewrite branch parses delimiters, not JSON, and already stores an error when its content block is missing; it is outside this bug.
- Fact (code, 2026-11-10): Post Reviews and the meta box await a run for three minutes, then stop waiting; nothing on the server moves a `processing` row on.
- Fact: a template change bumps the workflow version, so every site must update its workflow once. Before the public launch only the owner's sites do.
- Decision (owner, 2026-11-10, `/prioritize`): first pick under the hardening-before-launch rule of 2026-11-04, before the launch path.
- Assumption: severity P2 (running the command again is the workaround; the trigger never refuses an in-flight run); risk medium (a check stricter than Claude's real replies passes review and shows only in a live run; every site imports the new workflow).
- Assumption: the error message is a new sentence per agent, not "returned invalid JSON." (the reply was valid JSON): it names the agent, says the reply held no usable result and says what to do, per `docs/brand.md` ("Explain errors gently with actionable solutions").
- Assumption: "a field the score or status is computed from" is the minimum the check requires. A missing list the report only displays (`top_edits`, `issues`, `rewritten_items`, `rewritten_sources`) does not make the reply unusable, but reading it must not end the run (criterion 2).
- Assumption: out of this bug: a score field that is present with a value outside the prompt's set (a Sources verdict outside its list scores 50, an SEO assessment of "good" scores 55) keeps today's fallback. It is not a missing result, and a strict check there risks turning near-miss replies into errors.
- Decision (owner, 2026-11-10, hitl): the build is verified by the tests, then by a live run of all five commands on the new version, on the showcase against a disposable n8n, as on 2026-11-06. The agent provisions with the owner's Anthropic key read from its file, never printed. The owner activates the license on the showcase before the run (Sources, SEO and Audit need it) and deactivates it after. `docs/local-verification.md` ("A disposable n8n"), which still has the owner type the key in the setup dialog, is brought in line with this way.
