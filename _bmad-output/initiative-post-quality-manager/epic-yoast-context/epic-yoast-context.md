---
type: epic
title: "Yoast scores inform the SEO and Audit agents and are kept per review"
parent: initiative-post-quality-manager
covers: [O3]
after: []
assignee: ""
risk: low
---

# Yoast scores inform the SEO and Audit agents and are kept per review

<!-- Envelope, likely two stories at inception: (1) template change, (2) snapshot at save-review. -->

## Description

The numeric Yoast SEO and readability scores already travel in the n8n payload. Append them to the `Build SEO Body` and `Build Audit Body` user messages in the workflow template, framed as algorithmic signals rather than ground truth; the Review agent stays Yoast-free by design (side-by-side decision, avoids score anchoring). Separately, store `{seo_score, readability_score}` from post meta into `result_data` at save-review time, so past reviews keep the Yoast standing they were compared against (the Post Reviews column shows live values only).

## Outcome

SEO and Audit results reference the same numbers the blogger sees in Yoast, and review history stays interpretable after the post changes; the signal is a stored review whose snapshot differs from the live Yoast column after an edit.

## Done when

1. The SEO and Audit prompts in `templates/n8n-workflow.json` carry the Yoast scores with the "algorithmic signal" framing; the Review prompt does not.
2. `save-review` stores the snapshot when Yoast is active and omits it cleanly when it is not.
3. The detail panel shows the snapshot next to the live value.

## Boundaries

The two agent prompts and the save-review path. Not the Review agent, not a Yoast dependency (Yoast stays optional).

## References

- parent — _bmad-output/initiative-post-quality-manager/initiative-post-quality-manager.md, Requirement O3
- template — post-quality-manager/templates/n8n-workflow.json, Code nodes `Build SEO Body` and `Build Audit Body`
- facts — memory/llm-options-architecture.md (payload-driven request params, template version handshake)

## Notes

- Decision: Review agent stays Yoast-free (user, 2026-07-23).
- A template change bumps the template version handshake; the n8n Workflow page then shows the update notice.
