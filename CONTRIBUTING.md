# Contributing

Thanks for taking an interest in Post Quality Manager.

## Issues

Bug reports and feature requests are welcome as GitHub issues; the templates ask for what a fix needs (versions, steps, the WP_DEBUG output). Security problems go through [SECURITY.md](SECURITY.md), never a public issue.

## Pull requests

Pull requests from outside are accepted by prior agreement: open an issue first, say what you want to change and why, and wait for a go before writing code. This keeps the work aligned with the product's priorities ([docs/user-paths.md](docs/user-paths.md)) and avoids wasted effort.

Once agreed:

- Branch from `main` as `feat/…`, `fix/…` or `chore/…`; commit with [Conventional Commits](https://www.conventionalcommits.org/) (`fix(rest): refuse unknown commands`). Releases are cut from those messages by release-please, so the type and scope matter.
- Never bump the version: the `Version:` header, `PQM_VERSION` and `package.json` belong to release-please.
- Before pushing, run what CI runs:

  ```sh
  php -l <each touched PHP file>
  for f in tests/harness/*.php; do php "$f" || break; done
  npm run lint:js && npm run test:unit && npm run build
  ```

- Follow [AGENTS.md](AGENTS.md): `PQM_` / `pqm_` prefixes, WordPress APIs over raw SQL, sanitize and escape everything, nonces on forms and AJAX, a `permission_callback` on every REST route, native WP admin components. The Definition of Done there is the pull-request checklist.
- A change existing sites meet without being told (a new default, a new permission, a removed screen, a workflow re-import) adds one line to `docs/release-notes/unreleased.md` in the same pull request.
- When a REST route or the webhook payload changes, change `post-quality-manager/templates/n8n-workflow.json` to match, with `__PQM_*__` placeholders and no site-specific values.

Pull requests are merged by rebase, so keep every commit meaningful on its own.

## License

By contributing you agree that your contribution is licensed under GPL-2.0-or-later, like the plugin.
