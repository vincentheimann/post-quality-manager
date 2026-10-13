# Brownfield: what exists today, and what this epic reshapes

Read before slicing. Paths are relative to the repository root; the plugin lives in `post-quality-manager/`.

## The setup wizard (stories 36 and 55, done)

- A React island on the PHP n8n Workflow page (`src/setup/`), one entry point: the welcome notice and the "Set up the workflow" button. Steps in `src/setup/steps.js`: get an n8n instance, import the workflow, create the three credentials, link each credential to its node, activate and copy the production URL, paste the URL here, run the first review, then Blog Profile, Editorial Standards and scheduled reviews (story 55). On the Free build the Application Password and Schedule steps are omitted (`setup_state().build`).
- Server side: `GET /setup` (state and progress), `POST /setup/connect` (saves the webhook URL, pings), `POST /setup/settings` (the story 55 steps) in `includes/class-pqm-rest-api.php`. Progress is stored per site (`pqm_setup_progress`); setup counts as complete from the first review.
- This epic replaces the four n8n steps (import, credentials, link, activate and copy) with: connect the instance (CAP-1), provision (CAP-2) or adopt (CAP-4), then the existing "run your first review". The manual steps move to CAP-8's section and the wizard's fallback branch.

## The n8n Workflow page (`admin/class-pqm-admin.php`)

- Sections: the setup island, the workflow status (version handshake, `render_workflow_status()`), the Workflow File section with the Download button, the import URL row with its Copy button, `render_credentials_list()` and `render_credentials_hint()`. Story 40 (open) reorders it into journey steps with Copy buttons; CAP-8 absorbs that story.
- The handshake: `PQM_Admin::ping_webhook()` sends the `X-PQM-Token` header to the saved URL; the trigger answers `{ok, workflow_version, workflow_variant}`; `pqm_record_workflow_version()` stores it; a stale or Free-on-Pro template shows a "download and re-import" notice. CAP-5 turns that notice into an update action when a key is stored; CAP-7 adds the credential read.
- The webhook token (`webhook_token`) is one shared secret for both directions (ADR 0010); the Settings form shows it in a text field. CAP-6 adds rotation. The Settings form keeps re-echoing values, which is why the n8n key never goes there.

## The template and its contract

- `templates/n8n-workflow.json` is the canonical Pro template: placeholders `__PQM_SITE_HOST__` only, filled by `pqm_workflow_json()`; `tests/harness/template-guard-harness.php` fails on any other placeholder, literal host, or Code node that does not compile. The Free template is derived by `bin/derive-free-template.php` and checked in; the guard compares them.
- Credentialed nodes and their expected credential names: Webhook Trigger `Post Quality webhook - <host>` (Header Auth, `X-PQM-Token`); six `Claude - * Agent` HTTP Request nodes `Anthropic - Claude` (Header Auth, `x-api-key`); `Update WP Post (Rewrite)` `WordPress - <host>` (Basic Auth, Application Password). The Free template has the Review and Takeaways agents only and no WordPress node.
- The trigger path is the static `pqm-execute`; this epic adds a per-site path placeholder, which touches the guard's placeholder set, `pqm_workflow_json()`, the derivation script and the handshake.
- `PQM_WORKFLOW_VERSION` is 5; a template change that alters the contract bumps it (not the plugin version).

## Settings and storage

- `pqm_settings` holds `n8n_webhook_url`, `webhook_token`, `notification_url` and the rest; sanitized by `sanitize_settings()` with carry rules the Free build shares. The n8n API key and base URL are not among them: they get their own option with a page-local save path (not the Settings form, whose save rebuilds `pqm_settings` from the posted fields), masked, never echoed.
- Harnesses run without WordPress (`tests/harness/*.php`, WP stubs); every REST change extends one. n8n calls need a stub transport the harnesses can script (the `wp_remote_post` stub pattern exists in `rest-reviews-harness.php`).

## Policies and docs to change in this epic

- `docs/policies/privacy.md` and `docs/policies/readme-external-services.md`: the provider-key sentence becomes "forwarded once during setup, never stored"; the n8n API key is described as stored, with the dedicated-expiring-key advice.
- `docs/user-paths.md` A4/UV4: converts to Current Value once the success-signal walk passes.
- `memory/n8n-provisioning.md`, `memory/llm-options-architecture.md` (handshake) and ADR 0015's "Consequences" are updated as the stories land; ADR 0009 and 0010 are amended by reference, not superseded.

## Spike evidence

- `_bmad-output/backlog/spike-n8n-provisioning-api-findings.md` holds every confirmed call with its observed response shape on n8n 2.42.3, the round-trip results, and the six findings this spec's failure modes derive from. It is an adopted companion: read it before writing any n8n call.
