---
title: 'The workflow template is guarded in CI'
type: 'chore'
ticket: '3'
created: '2026-10-08'
status: done
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'auto'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '3c10d204c247ac7ed01b92f33aef7fef6dc9cc75'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/spec-delivery-pipeline.md', '{project-root}/_bmad-output/initiative-post-quality-manager/spec-delivery-pipeline/brownfield.md', '{project-root}/memory/delivery-pipeline.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** Nothing in CI guards `post-quality-manager/templates/n8n-workflow.json` as a whole (the 2026-07-23 prototype was never committed), so a broken or site-specific template could ship in a release.

**Approach:** One new self-contained harness, picked up by the existing CI sweep, that fails naming the cause on invalid JSON, a placeholder `pqm_workflow_json()` does not fill, a site-specific string, or a Code node whose `jsCode` does not parse. Code nodes are compiled by Node.js inside an `AsyncFunction` wrapper (top-level `return` and n8n globals rule out `node --check`), never run.

**Site-specific-string rule (the ticket's open unknown, settled 2026-10-08, owner-approved plan):** every literal `http(s)://` host is on an allow-list (`api.anthropic.com` today); no `localhost` or IPv4 address; top-level keys are exactly a bare export (`name`, `nodes`, `connections`, `active`, `settings`), so an instance export's `id`, `versionId`, `meta.instanceId`, `pinData`, `tags` fail; every node `id` is `pqm-node-N`; no node carries a `webhookId`; every credential block carries `name` only, never an `id`.

## Boundaries & Constraints

**Always:** the placeholder list mirrors the `str_replace()` in `pqm_workflow_json()` (deliberate duplication; the failure message says to update both). The harness iterates over Code nodes, never hardcodes their count.

**Never:** no CI workflow edit, no plugin or template edit, no new dependency. The version-handshake agreement checks stay in `rest-reviews-harness.php`.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Current template | `main` as is | 26 checks pass | CI green |
| Invalid JSON | a stray comma | `FATAL: template is not valid JSON (Syntax error)` | CI red |
| Unknown placeholder | `__PQM_API_KEY__` in the name | fails, names the placeholder and both places to update | CI red |
| Site host | `https://my-blog.local/wp-json/x` | fails, names `my-blog.local` | CI red |
| Local address | `localhost` (or an IPv4) | fails, names what it found | CI red |
| Instance export key | top-level `versionId` | fails, names the key | CI red |
| Credential id | `id` in a credential block | fails, names node and credential type | CI red |
| Webhook id | `webhookId` on a node | fails, names the node | CI red |
| `jsCode` syntax error | `const x = ;` in Parse Review Result | fails, names the node and Node's error | CI red |
| No Node.js | `node` not on PATH | `FATAL` naming Node.js | CI red |

</frozen-after-approval>

## Code Map

- `tests/harness/template-guard-harness.php` -- new. Constants `FILLED_PLACEHOLDERS`, `ALLOWED_HOSTS`, `TOP_LEVEL_KEYS` at the top; sections Valid JSON, Placeholders, Site-specific strings, Code nodes parse; one `node` process compiles every Code node (temp runner + shutdown cleanup, the `template-failure-harness.php` pattern).
- `memory/delivery-pipeline.md` -- record the guard and its rule.

## Tasks & Acceptance

**Execution:**
- [x] `tests/harness/template-guard-harness.php` -- the harness.
- [x] `memory/delivery-pipeline.md` -- built facts.

**Acceptance Criteria:**
- Given the current template, when the sweep runs, then the harness passes (26/26) and every other harness stays green.
- Given each seeded breakage of the matrix, when the harness runs, then it exits 1 with exactly the matching check failing and naming the cause.

## Implementation Notes

- Deferred to the refactor sweep (entry 7): `rest-reviews-harness.php` "Template contract" keeps its narrower URL and placeholder checks (exact URL list, exact placeholder set); consolidate with this harness's rules there.
- The multi-provider backlog story (`story-ai-model-provider-and-model-picker.md`) will need to extend `ALLOWED_HOSTS`.
- `spec-delivery-pipeline/brownfield.md` L17 is stale (`PQM_WORKFLOW_VERSION` "currently 4", now 5); left as is, the spec is a record of 2026-10-07.

- Built 2026-10-08. Local results: harness 26/26 on the current template; each of the 8 seeded breakages (invalid JSON, unknown placeholder, site host, localhost, `versionId`, credential `id`, `webhookId`, `jsCode` syntax error) exits 1 with only the matching check failing and the cause named (template restored from git after each); full sweep 15/15 green.
- CI (PR #76): the branch is green on push and pull_request. The red proof ran on throwaway branch `chore/template-guard-ci-seed` (rebase merging would land a seed + revert pair on `main`). A first seed in Parse Review Result was stopped earlier by `template-failure-harness.php`, which executes that node; the second seed in Build SEO Body, a node no other harness runs, failed the 8.3 sweep with `FAIL Build SEO Body jsCode parses — Unexpected token ';'` while the older template harnesses stayed green (run 37781135834). The other seeds were proven locally only.

## Review Triage Log

- Quick self-review: 1 finding, fixed before the first run — `$errors[$name] ?? …` treated a successful (`null`) compile as missing; replaced with `array_key_exists`.

## Verification

**Commands:**
- `php tests/harness/template-guard-harness.php` -- expected: 26 passed, 0 failed.
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: all green.

**Manual checks:**
- On the pushed branch, one throwaway commit seeding a breakage turns the CI `php` 8.3 job red naming the cause; reverted before merge.
