---
id: 4
type: story
title: "Any chat tool can drive the workflow from a documented webhook contract"
parent: none
covers: [O4]
after: []
assignee: ""
refined: false
hitl: false
risk: low
---

# Any chat tool can drive the workflow from a documented webhook contract

## Description

Document the webhook in/out JSON contract — command, post reference, options, the callback payload `save-review` expects, the token header — so a Slack, kChat or Teams bot can drive the workflow without reading the n8n nodes. Lives beside `n8n/workflow-diagram.md`.

## Acceptance Criteria

Verify: a reader can craft a valid `trigger` request and interpret the callback from the document alone, checked against the template's Code nodes.

## References

- diagram — n8n/workflow-diagram.md
- template — post-quality-manager/templates/n8n-workflow.json
- rest — README.md, section REST API

## Notes

- Docs only; no code change expected. If the contract turns out inconsistent, that is a bug ticket, not scope here.
