## What and why

<!-- One paragraph. Link the issue or the ticket in _bmad-output/. -->

## Definition of Done

- [ ] KISS: the smallest change that solves the problem
- [ ] `php -l` on every touched PHP file and `node --check` on every touched JS file are clean
- [ ] Every harness in `tests/harness/` and `npm run test:unit` are green
- [ ] No WordPress debug errors or warnings
- [ ] REST endpoints answer correctly and every route has a `permission_callback`
- [ ] UI loading and error states handled
- [ ] A change existing sites meet without being told adds one line to `docs/release-notes/unreleased.md`
- [ ] A REST or payload change is mirrored in `post-quality-manager/templates/n8n-workflow.json`, placeholders only
- [ ] Durable facts updated in `memory/`; significant decisions recorded in `docs/adr/`
- [ ] The version was not bumped by hand (release-please owns it)
