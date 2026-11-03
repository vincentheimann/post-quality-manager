# Life cycle record

_What the plugin uses from each tracked part, and when it was last checked against its source (ADR 0017). The `/lcm` skill updates this file; add or drop a row when a part is added or dropped._

| Part | In use (2026-11-03) | Source of truth | Last checked | Latest seen / ticket |
|---|---|---|---|---|
| AI models (Anthropic) | the registry in `postquality_model_registry()`: Claude Sonnet 5, Opus 4.8, Sonnet 4.6, Haiku 4.5 (`memory/llm-options-architecture.md`) | the provider's models and deprecations pages; the `claude-api` skill's model reference | not yet | a newer generation exists (seen 2026-11-03): first `/lcm` run files the ticket |
| Freemius SDK | 2.13.5, vendored unmodified (chore 89) | github.com/Freemius/wordpress-sdk tags | not yet | — |
| WordPress | `Tested up to: 7.1`, floor 6.6 | api.wordpress.org core version check | not yet | — |
| PHP | `Requires PHP: 8.0` (past upstream end of life) | php.net supported versions; WordPress requirements | not yet | — |
| n8n | template version 8; owner's instance 2.42.x (`memory/n8n-provisioning.md`) | github.com/n8n-io/n8n releases | not yet | — |
| Build | `@wordpress/scripts` ^30.7.0 | npm registry (`npm outdated`) | not yet | — |
| CI | `actions/checkout`, `setup-node`, `upload-artifact`, `download-artifact` @v4; `shivammathur/setup-php@v2`; `wordpress/plugin-check-action` v1.1.9 (pinned by SHA); `googleapis/release-please-action@v5`; runner `ubuntu-latest` (Ubuntu 26 since 2026-10-19) | each action's releases; run annotations | not yet | — |
