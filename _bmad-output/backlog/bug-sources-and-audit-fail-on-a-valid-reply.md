---
id: 117
type: bug
title: "Sources and Audit store \"returned invalid JSON\" when Claude's valid reply has a thinking passage or a closing sentence"
parent: none
covers: [UV3]
after: []
assignee: ""
refined: false
hitl: false
risk: medium
severity: P1
estimate: ""
---

# Sources and Audit store "returned invalid JSON" when Claude's valid reply has a thinking passage or a closing sentence

## Description

The licensed Pro-command walk (2026-11-06, template v9 on n8n 2.46.6, Claude Sonnet 5.5, the default since 3.11.0) ran each Pro command once on one demo post. SEO and Rewrite stored their results. Sources and Audit stored `{"error":"Sources Agent returned invalid JSON."}` and `{"error":"Audit Agent returned invalid JSON."}`, although both calls answered 200 with `stop_reason: end_turn` and each reply held a valid JSON object. A Pro buyer running Sources or Audit gets an error and pays for the call.

## Reproduction

1. The showcase, the Pro build of main 02af748 (3.12.0 + bug 99), licensed; a disposable n8n 2.46.6 provisioned with a real Anthropic key; default model Claude Sonnet 5.5.
2. `POST /trigger-review` with `command` `sources`, then `audit`, on a published demo post (about 60 words).
3. Actual, read from n8n's executions:
   - **Sources:** the text block is a fenced ```` ```json ```` object (172 characters), then 805 characters of prose after the closing fence. `Parse Sources Result` fails.
   - **Audit:** the text block opens with `<thinking>…</thinking>` (437 characters), then the fenced object running to the end of the reply. `Parse Audit Result` fails.
   - A balanced-brace read from the first `{` parses both objects.
4. Expected: the result is stored with its score and status, like SEO's (`needs_revision`, 55) and Rewrite's (`rewritten`).

## Cause Hypothesis

Every Parse node reads `content[].text`, trims it, strips a leading ```` ```json ```` and a trailing ```` ``` ```` by anchored regexes, then calls `JSON.parse` on what is left (`post-quality-manager/templates/n8n-workflow.json`: Parse Review Result L156, Parse Sources Result L292, Parse Audit Result L428, and the Takeaways and SEO nodes the same way).

- Text after the closing fence defeats the trailing regex (Sources).
- `Parse Audit Result` alone also removes `<thinking>…</thinking>`, but after the fence regexes: the leading fence is no longer at the start when they run, so it stays in. The Audit prompt (Build Audit Body) invites that block: "You may use a <thinking> block OUTSIDE the JSON to reason first if you want, but the final output MUST end with the JSON object".
- Review, Takeaways and SEO share the extraction, so a reply of either shape fails them too.

Story 101's proof (2026-11-04) judged each reply's shape with its own script ("every reply had the shape its Parse node reads"), not with the Parse nodes' code, and met neither shape.

## Acceptance Criteria

1. **A valid object in the reply is stored**
   **Given** a Claude reply whose text holds one JSON object, bare, fenced, after a `<thinking>…</thinking>` passage, or followed by prose
   **When** any of the five Parse nodes reads it
   **Then** the object is parsed and the result stored, as with a bare reply
2. **No object is still an error**
   **Given** a reply with no parsable object
   **When** the Parse node reads it
   **Then** it stores the same "<Agent> returned invalid JSON." error as today
3. **The fix ships as a template version**
   **Given** the template change
   **When** it is released
   **Then** the version is bumped, the Free template re-derived, the release notes say Update workflow is needed, and a harness runs each Parse node's code on the shapes of AC1 and AC2

## References

- found by — the licensed Pro-command walk, 2026-11-06 (`_bmad-output/backlog/story-model-registry-follows-the-provider-plan.md`, Implementation Notes)
- source — `post-quality-manager/templates/n8n-workflow.json` (the five Parse nodes; Build Audit Body's prompt), `bin/derive-free-template.php`

## Notes

- Fact (agent, 2026-11-06): n8n executions 2 (Sources, 332 output tokens, 5 s) and 4 (Audit, 2,042 output tokens, 15 s), model `claude-sonnet-5-5`, one text block each, no API thinking block. Reply content not kept: the walk's n8n was deleted with its data.
- Severity P1 proposed by the agent (two paid agents fail with the default model); the owner sets it.
- Filed 2026-11-06 in the licensed sitting's records PR, unrefined.
