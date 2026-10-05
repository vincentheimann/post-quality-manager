---
title: "AJAX and GET triggers report success when n8n answers non-2xx"
ticket: 18
status: done
---

Closed 2026-10-05 by deletion, with no fix of its own: epic-ui-rebuild story 5.7 removed both affected paths, `ajax_trigger_command()` with its `wp_ajax_pqm_trigger_command` hook and the GET `pqm_action=trigger` path in `render_posts()`. The only remaining trigger path is `POST /trigger-review`, whose React callers already read `success:false` on an n8n non-2xx. The owner confirmed it after the 5.7 site check came back all green.
