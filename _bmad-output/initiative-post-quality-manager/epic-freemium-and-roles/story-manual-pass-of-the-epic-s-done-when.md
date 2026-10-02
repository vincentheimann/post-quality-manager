---
id: 8
type: story
title: "Manual pass of the epic's Done when"
parent: epic-freemium-and-roles
covers: [CAP-1, CAP-2, CAP-3, CAP-4, CAP-5]
after: [7]
hitl: true
risk: low
---

# Manual pass of the epic's Done when

## Description

The owner walks Done when 1–4 on a real site, free then licensed, and records the result.

## Acceptance Criteria

Verify: Each Done when line is checked off, or a bug ticket exists for the failure.

## Walkthrough checklist (2026-10-02)

**Preconditions:** latest `main` on the real site; the `pqm_is_pro` mu-plugin file **removed** (the License key drives plan state since story 3); ideally `WP_DEBUG_LOG` on.

### Done when 1 — free site refuses the paid four before the webhook (UI, REST, AJAX)
- [x] License field empty → status line reads "Current plan: Free"; Review and Key Takeaways both reach n8n.
- [x] UI: Rewrite/Sources/SEO/Audit buttons render locked with upgrade copy; clicking fires no request.
- [x] REST: `POST /wp-json/post-quality/v1/trigger-review` with `command=seo` → 403 `pqm_feature_gated`, and n8n shows no execution.
- [x] AJAX: copy a trigger request as fetch in the browser console, swap the command to `rewrite` → 403 with the upgrade copy.

### Done when 2 — same zip, key entered, all six commands run; template/handshake unchanged
- [x] Enter any key, save → "Current plan: Pro", Overrides + Automated Reviews unlock.
- [x] Each of the six commands runs once and stores a result (SEO/Audit via their tabs).
- [x] n8n Workflow page: version handshake quiet, no re-import ever prompted.

### Done when 3 — role behavior (stay keyed)
- [x] Author: quota counter visible next to the buttons and decrements on a successful trigger; paid buttons absent (not locked); no Settings menu.
- [x] Editor: Settings shows exactly Agent-Specific Overrides + Automated Reviews, editable; after an Editor save, webhook URL/token, models, context, quota and the license key are unchanged.
- [x] Contributor: no PQM menu; direct REST call with their application password → 403.

### Done when 4 — capabilities remappable without code
- [x] Role editor grants Author `pqm_run_paid_agents` → paid buttons appear, quota counter gone; removing the grant restores the free set + counter.

**Recording:** tick the boxes here; any failure becomes a bug ticket in `_bmad-output/backlog/` instead of a tick.

**Result (2026-10-02):** the owner walked all four Done-when lines on the real site — **all green**, no bug tickets. Preconditions honored (mu-plugin override removed; License key drove plan state). Epic Done-when verified end to end.

## References

- parent — _bmad-output/initiative-post-quality-manager/epic-freemium-and-roles/epic-freemium-and-roles.md
