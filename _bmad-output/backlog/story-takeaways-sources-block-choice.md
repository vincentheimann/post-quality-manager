---
id: 23
type: story
title: "Takeaways and Sources insert into the post through a pattern the site owner picks"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: medium
---

# Takeaways and Sources insert into the post through a pattern the site owner picks

## Description

Owner request 2026-10-05, refined 2026-10-06: in Settings → Agent-Specific Overrides, the **Key Takeaways Agent** and **Sources Agent** boxes each get a "Pattern" setting. It is a dropdown with "Plain list" (the default) plus the site's own patterns from Appearance → Patterns that contain a List, e.g. a styled Group with a "Key Takeaways" heading, a List and a Spacer. A custom block goes inside such a pattern. When the agent's output is inserted into the post, the pattern is copied and the items go into its first List, so the result matches the site's own design instead of a generic one.

## Acceptance Criteria

Verify: both agent boxes show a Pattern dropdown listing "Plain list" and the site's published patterns that contain a List, with "Plain list" preselected. The choice saves, survives reload and reaches n8n in the payload with no workflow re-import, like the other overrides. Output inserted into a post appears in the chosen pattern's design with the items in its List, and opens in the block editor with no "invalid content" warning. On a site with no such pattern, the plain list behaves the same.

## Boundaries

- Only the Takeaways and Sources agent boxes get the dropdown; other agents unchanged.
- Agent prompts and their JSON result contract stay the same; only the presentation used to insert the output changes.

## Notes

- Finding (2026-10-05): today neither agent writes into the post. Takeaways returns `rewritten_items` and Sources returns `rewritten_sources` as suggestions only, and the template builds no block (its only block markup is `wp:paragraph`, inside the Rewrite prompt). This story therefore either includes the "insert/replace the block" step or depends on a ticket that adds it. Settle this when refining.
- Settled 2026-10-06 (plan): the dropdown lists user patterns (published `wp_block` posts holding a `core/list`), replacing the typed block-name field of the first build. Insert is always an inline copy, never a synced reference; the first List gets the items. Tier follows the section: on a free site Takeaways insert as a plain list.
- The Takeaways prompt checks that "the block" exists and sits first. Pattern-aware detection follows with story 22.

## References

- settings UI — post-quality-manager/admin/class-pqm-admin.php (Agent-Specific Overrides section, `agent_labels()`)
- settings defaults — post-quality-manager/post-quality-manager.php (`agent_prompts` defaults, `pqm_n8n_payload()`)
- workflow — post-quality-manager/templates/n8n-workflow.json (Build/Parse Takeaways Body/Result, Build/Parse Sources Body/Result)
- adjacent backlog — story 22 (n8n workflow generator review)
- decision — docs/adr/0008-plugin-writes-agent-output-into-posts.md
