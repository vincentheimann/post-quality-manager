---
title: 'The stored n8n API key shows its last four characters'
type: 'feature'
ticket: ''
created: '2026-10-15'
status: 'built'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: 'f9dcd82961a457b14256e6d51e7ca9fb21a3d0a8'
context: ['{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** After an Administrator enters an n8n API key and connects, the key field is empty and the only feedback is "API key: set". This happens on the n8n Workflow page and in the setup guide's Connect your n8n step, which reuse the same island. Nothing shows which key is stored, so after a new key is created in n8n the owner cannot tell whether the site holds the old or the new one. The owner reported the empty field as poor feedback on 2026-10-15.

**Approach:** Apply the common practice for stored API secrets (GitHub, Stripe, AWS): show only the stored key's last four characters, never the key. The REST state gains a `key_hint`. The island shows it in the status line and as the masked placeholder of the key field, so after Connect the field reads `••••••••ab12` instead of blank. The privacy policy states what is shown. The Anthropic key, which is never stored, has nothing to hint: when a refused setup clears its field, the error says to enter the key again.

## Boundaries & Constraints

**Always:**
- The hint is computed from the stored key at read time and never stored separately.
- It is at most the last 4 characters, and empty when the key is shorter than 16 characters (a short key would give away too large a share).
- The full key appears in no REST reply, page source or log. The harness scans keep proving it.
- The key field stays write-only: the hint is a placeholder, never a value, so nothing can be copied out of it and submitting the empty field keeps the stored key (today's rule).
- The same island serves the page and the setup guide, so both show the hint.

**Never:**
- Showing more than 4 characters, or a "reveal" toggle.
- A change to how the key is stored, sent to n8n or forgotten.
- Changes to the Anthropic key's handling beyond the error sentence.

**Decision (owner, 2026-10-15):** apply the best practice: the last 4 characters, with the privacy wording "only its last four characters are shown".

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Key stored | 40-character key ending `ab12` | `GET /n8n/connection` → `key_hint: "ab12"`; status line "API key: set, ending in ab12"; empty field with placeholder `••••••••ab12` and help "Type a new key to replace it." | — |
| No key | nothing stored | `key_hint: ""`; "API key: not set"; today's placeholder and help | — |
| Short key | stored key under 16 characters | `key_hint: ""`; "API key: set" without an ending | — |
| Connect with a new key | POST passes | The field clears and the placeholder shows the new key's last 4 at once (the reply carries the hint) | — |
| Connect fails | probe refused | The typed key stays in the field (unchanged behaviour); the stored hint is unchanged | The island's error notice |
| Forget key | DELETE | `key_hint: ""`; "API key: not set" | — |
| Setup guide | island in Connect your n8n | Same hint and placeholder | — |
| GET /setup | `n8n` object | Carries `key_hint` (it is `public_state()`) | — |
| Anthropic key refused | provision error | The field clears (unchanged); the error ends with "Enter the key again." | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-n8n.php` -- `public_state()` L76–84 returns `{base_url, key_set, probe}`. Add `key_hint`: `strlen( $key ) >= 16 ? substr( $key, -4 ) : ''`. Its callers are `GET|POST|DELETE /n8n/connection` (`class-pqm-rest-api.php` ~L1309, L1345, L1352) and `setup_state()` (~L1043), so all get the hint with no other change. Update the class docblock line "The key never leaves the server".
- `src/n8n-connection/index.js`:
  - Status line ~L145: `key_set` ? "API key: set" : "not set". Add the ending when `key_hint` is not empty: `sprintf( __( 'API key: set, ending in %s' ), hint )`.
  - Key `TextControl` ~L163–178: when `key_set` and the hint is not empty, `placeholder={ '••••••••' + hint }`. The help becomes "Type a new key to replace it.", or "A key is stored. Type a new key to replace it." when there is no hint.
- `src/setup/steps.js` -- provisioning error ~L297–307: append "Enter the key again." to the shown message.
- `docs/policies/privacy.md` L24: "never shown again in WP Admin or sent anywhere but that address" → "only its last four characters are shown in WP Admin, so you can tell which key is stored, and it is never sent anywhere but that address". `docs/policies/readme-external-services.md`: the same wording if it says the key is never shown (grep).
- `tests/harness/n8n-api-harness.php`:
  - The `public_state()` shape asserts at L246, L264, L273, L373 and L400 gain `key_hint`.
  - New rows: hint = the last 4 characters; empty for a key under 16 characters; empty after Forget.
  - The leak scans (`!str_contains(..., KEY)`) stay as they are: the full key never appears.
- `tests/harness/setup-harness.php` -- the GET /setup `n8n` shape at ~L208 and ~L308.
- `tests/js/n8n-connection.test.js` -- rows for the status line with the ending, the placeholder after a passing connect, no hint for a short key, and none after Forget.
- `tests/js/setup-provision.test.js` -- the refused-key row checks the "Enter the key again." sentence.
- `memory/n8n-provisioning.md` -- the entry 1 line: the public state carries the last-4 hint.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php` -- `key_hint` in `public_state()`.
- [x] `src/n8n-connection/index.js` -- the status line, placeholder and help.
- [x] `src/setup/steps.js` -- the Anthropic error sentence.
- [x] `docs/policies/privacy.md`, `docs/policies/readme-external-services.md` -- the wording.
- [x] `tests/harness/n8n-api-harness.php`, `tests/harness/setup-harness.php`, `tests/js/n8n-connection.test.js`, `tests/js/setup-provision.test.js` -- the rows above.
- [x] `memory/n8n-provisioning.md`, and `docs/release-notes/unreleased.md` only if it describes the key field (grep).

**Acceptance Criteria:**
- Given any REST reply or rendered page after a key is stored, when it is searched for the full key, then the key is not found, and only its last 4 characters appear.
- Given the privacy policy, when read, then it says only the last four characters are shown.

Skipped: a hint for the Anthropic key (it is never stored). Story 68 syncs the published privacy page with every policy change, this one included.

## Review Triage Log

Pass 1 (2026-10-15, lens `quick`): 0 high, 1 medium, 5 low, 1 false, 0 maybe-false. Five patched; one rejected (false). Both acceptance criteria were met before the pass; harnesses, Jest 77/77, lint and the build were green.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | "Enter the key again." is appended to every provisioning error, including 409, the missing template and the network fallback, where retyping cannot help | medium | patch | `steps.js` rejection handler appends to any `err.message`. Fix keeps the frozen row (a refused key) and limits the sentence to errors a retyped key can retry: `pqm_provider_key_invalid`, `pqm_provider_unreachable`, and `pqm_provision_failed` outside the `template`/`link` steps. The test fixture used a shortened refused-key message. |
| 2 | `get_n8n_connection_callback()` docblock still states `{ base_url, key_set, probe }` | low | patch | The reply is `public_state()`, now with `key_hint`. |
| 3a | `spec-n8n-provisioning.md` L53 still says the key field shows "set / not set" | low | patch | The spec is the capability contract; one clause added. |
| 3b | `ux-n8n-workflow-page/audit.md` L15 still says "API key: set / not set" | false | reject | The audit is a dated snapshot of the page as shipped on 2026-10-14; it is correct for its date. |
| 4 | A dated 2026-10-12 decision bullet in `memory/n8n-provisioning.md` was rewritten to the new behaviour | low | patch | Restored; the entry 1 line records the hint with its date. |
| 5 | The harness check "the hint is never stored" reads the option the test just seeded and cannot fail | low | patch | Deleted; the exact-shape check after Connect proves it. |
| 6 | Style: a section header broken across lines, a constant declared mid-class, an overlong docblock line, a ragged header comment | low | patch | Direct corrections. |

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: clean
- `for h in tests/harness/*-harness.php; do php "$h" || echo "FAIL $h"; done` -- expected: no FAIL
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- expected: green
