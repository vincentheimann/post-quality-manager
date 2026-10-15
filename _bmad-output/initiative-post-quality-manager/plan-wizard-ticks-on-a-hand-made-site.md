---
title: 'The wizard ticks only what the plugin did: a hand-made site takes the manual path'
type: 'bugfix'
ticket: ''
created: '2026-10-15'
status: 'built'
route: 'full'
route_source: 'pinned'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 1
baseline_revision: '4dcecb47a8a1e93e5b9fa8631603901f45ddf872'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/ux-n8n-workflow-page/EXPERIENCE.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On a site set up by hand (a saved webhook URL, no n8n API key), the setup guide shows **Connect your n8n ✓** and **Set up the workflow ✓**, although the plugin never connected to n8n nor set anything up. Entry 6.6's "Already connected" rule ticked both from the saved URL alone. The owner saw it on 2026-10-15.

**Approach:** A step is ticked only for what the plugin did, and what the plugin did decides the path. A site with a saved webhook URL follows how its workflow was made: set up by the plugin (the setup map exists) → the key path; made by hand → the manual path, whose one step **Set up by hand** is ticked by the saved URL. A site without a URL follows today's rule: a passing key → the key path; a stored choice or a failed check → the manual path. **Connect your n8n** is ticked only while a stored key passes; **Set up the workflow** only by the plugin's own setup. Configuration steps are unchanged.

## Boundaries & Constraints

**Always:**
- A saved webhook URL never ticks **Connect your n8n** or **Set up the workflow**.
- A passing key outranks a stored choice or a failed check, never a workflow made by hand: a hand-made site stays on the manual path with a key, which is kept for adoption (entry 6.8).
- The dialog never offers a second setup over a live workflow.
- A completed site keeps today's behaviour: its n8n steps count as done and the guide opens on the first open configuration step or the finish panel.
- The dialog's steps always match GET /setup: after the n8n connection changes in the dialog, it re-reads the state instead of patching it.
- A re-walk moves nothing; no option is written by this change.

**Never:**
- A change to `pqm_manual_reason()`'s stored reasons or to the Workflow File section's reason line (entry 6.2 D1/D2): "made by hand" is a path in the wizard, not a stored reason.
- A new REST field (`provisioned` already exists) or a change to `POST /setup`.
- The Workflow page's button or status block (story 67 / plan C).

**Decisions (owner, 2026-10-15, review loop 1):**
- **D1:** option A. What the plugin did decides the path: a provisioned site always takes the key path, a hand-made site stays on the manual path even with a passing key (adoption, 6.8, takes it over later). This replaces the first draft's "a passing key outranks every manual signal".
- **D2:** the first draft's row "key stored, check failed, URL saved" is dropped: a later failed check keeps the last passing probe (entry 2 D1), so that state never occurs.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Hand-made site | URL saved, no setup map, no key | Manual path: **Set up by hand ✓**, then the review step; the key-path steps are not shown; the step says the workflow was set up by hand | — |
| Hand-made site, key passes | as above, a passing key | Manual path, **Set up by hand ✓**; no "Connect by key instead" (the key is already connected); no setup offered | — |
| Hand-made, complete | as above, `complete` | Manual path, every n8n step done; opens on the first open config step or finish | — |
| Provisioned site | setup map, URL, key passes | Key path: both steps ✓ | — |
| Provisioned, key forgotten | setup map, URL, no key | Key path: **Connect your n8n** open, **Set up the workflow ✓**; no "set up by hand" sentence anywhere | — |
| Provisioned, ping failed | setup map, URL, `steps.connect` false | Key path, **Set up the workflow** open with the paste-URL body (entry 6.6 finding 9 rule) | — |
| Key passes, no URL | no setup map | Key path: **Connect your n8n ✓**, **Set up the workflow** open | — |
| Key passes, choice stored, no URL | both | Key path (the key outranks the choice) | — |
| Choice stored, no key, no URL | `manual` | Manual path, **Set up by hand** open (unchanged) | — |
| Check failed, no key, no URL | `failed` recorded | Manual path, **Set up by hand** open, the reason line says the check failed (unchanged) | — |
| Fresh site | nothing | Key path, nothing ticked (unchanged) | — |
| Ping failed after a by-hand paste | URL saved, no setup map, `steps.connect` false | Manual path, **Set up by hand** open with the paste-URL body | — |
| Forget key in the dialog | any state with a passing key | The dialog re-reads GET /setup; path and ticks follow the rows above | Load error shown in the dialog |

</frozen-after-approval>

## Code Map

- `post-quality-manager/includes/class-pqm-rest-api.php` -- `setup_state()` ~L1030–1055. Today: `$derived['n8n'] = $connected || key passes`, `'manual' => null !== $reason`, `$url_done` = connected and no recorded failed ping, `'provisioned' => (bool) get_option( PQM_N8n::SETUP_OPTION )` already in the reply. New:
  - `$key_ok` = key set with a passing probe; `$provisioned` = the setup map exists.
  - `'manual'` (the path `stepsFor()` reads) = `$connected ? ! $provisioned : null !== $reason`. `pqm_manual_reason()` already returns null when a key passes, so a key outranks a choice or a failure only while no URL is saved.
  - steps: `n8n` = `$key_ok`; `provision` = `$provisioned && $url_done`; `manual` = `$url_done`.
  - The `SETUP_STEPS` docblock states these rules.
- `src/setup/index.js` -- `stepDone()` ~L65: the `n8n` case returns the passing-key test only; every other n8n step reads `state.steps`. `onN8nChange` (the island's `onChange`) today patches local state: replace the patch with a re-read of GET /setup (the same call the dialog makes on open), keeping the current step. `resumeAt()` unchanged.
- `src/setup/steps.js` -- `stepsFor()` ~L456 reads `state.manual`, unchanged. `Manual` body: when `manual_reason` is null, `state.connected` is true and `state.provisioned` is false, say the workflow was set up by hand (draft: "This site's workflow was set up by hand."); hide "Connect by key instead" when the key already passes. `ConnectN8n`'s "Set up by hand instead" link stays tied to a passing key (unchanged).
- `post-quality-manager/includes/pqm-functions.php` -- `pqm_manual_reason()` unchanged (Never).
- `docs/release-notes/unreleased.md` -- the setup-guide paragraph ends "A site already connected sees its n8n steps done.": replace it with the rule for a site connected by hand (its guide shows one step, Set up by hand, done).
- `tests/harness/setup-harness.php` -- GET /setup rows (the derived-steps and `manual` rows, ~L259 onwards; the "legacy URL without a connect record" row): one row per server-decided matrix line, with fixtures `save()` can produce (a key always with a passing probe).
- `tests/harness/free-build-harness.php` -- the Free GET /setup row for a hand-made site.
- `tests/js/setup-provision.test.js` -- the rows only the dialog shows: which path and step open, which steps are ticked, the sentence, the hidden "Connect by key instead", and the re-read after Forget key (`apiFetch` called for GET /setup).
- `memory/n8n-provisioning.md` -- the entry 6 line's n8n-step rule.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-rest-api.php` -- the derivation in `setup_state()` and its docblock.
- [x] `src/setup/index.js`, `src/setup/steps.js` -- `stepDone()`, the re-read on `onN8nChange`, the `Manual` body's sentence and hidden link.
- [x] `tests/harness/setup-harness.php`, `tests/harness/free-build-harness.php` -- the server rows.
- [x] `tests/js/setup-provision.test.js` -- the dialog rows.
- [x] `docs/release-notes/unreleased.md`, `memory/n8n-provisioning.md` -- the corrected lines.

**Acceptance Criteria:**
- Given a site with a saved webhook URL and no setup map, when the setup guide opens, with or without a key, then no step claims the plugin connected to or set up n8n, and no step offers to set the workflow up.
- Given every matrix row, when GET /setup is read, then `steps.n8n` is true only with a passing key and `steps.provision` only with the setup map.

Skipped: writing "by hand" into `pqm_setup_progress` for such sites — the derivation covers it without a migration.

## Review Triage Log

Pass 1 (2026-10-15, lens `quick`, diff against main 4dcecb4 after #128 merged; code reverted for the loopback, the diff kept in the session scratchpad): 0 high, 3 medium, 2 low, 0 false, 0 maybe-false. Routed intent_gap (findings 1 and 5), so the lower entries are moot until re-derivation; their fixes are folded into the amended tasks.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | A hand-made URL ticks "Set up the workflow" once a key passes; leaving it open instead would offer a second provisioning on the same webhook path | medium | intent_gap | `'provision' => $key_ok && $url_done` and the client mirror; the frozen Always forbids the tick, and "a passing key outranks every manual signal" puts the site on the key path, where provisioning collides with the live hand-made workflow (n8n refuses a second active workflow on a registered path, entry 6.3 deferred item). The frozen rules cannot both hold. |
| 2 | "Forget key" in the dialog leaves `manual`/`steps` stale | medium | folded into tasks | `onN8nChange` re-derives only on `passes`; the server derivation now depends on the key. Fix: the dialog re-reads GET /setup after the island reports a change, one source of truth. |
| 3 | "This site's workflow was connected by hand." is false on a provisioned site whose key was forgotten | medium | folded into tasks | `forget()` clears `probe`/`failed`; the branch tests `state.connected` only, not `state.provisioned`. |
| 4 | Release notes still say a connected site sees its n8n steps done | low | folded into tasks | `docs/release-notes/unreleased.md`, the setup-guide paragraph. |
| 5 | Matrix row "key stored, check failed, URL saved" is unreachable: a later failed check keeps the last passing probe, so the key still passes | low | intent_gap | `record_failure()` keeps the passing probe (entry 2 D1); the harness row passes only on a key without a probe, a state `save()` never writes. |

Pass 2 (2026-10-15, lens `quick`, the rebuild of the amended plan against main 4dcecb4): 0 high, 3 medium, 3 low, 0 false, 0 maybe-false. All six patched; harnesses, Jest 76/76, lint and the build were green before the pass.

| # | Finding | Verdict | Route | Evidence |
|---|---------|---------|-------|----------|
| 1 | The manual step of a hand-made site still offers "Connect by key instead"; connecting there stores the key and silently unmounts the island, the site staying on the manual path | medium | patch | Server: `'manual' => $connected ? ! $provisioned : …`; the offer contradicts AC1 and D1. Fix: offer it only while no URL is saved. |
| 2 | `setup-config.test.js` `completedState()` is a GET /setup answer the server can no longer produce (connected, no key, not provisioned, `manual: false`) | medium | patch | The suite's step headings rest on it. Fix: a provisioned fixture with a passing key. |
| 3 | `onConnected` patches `steps` but not `manual`, which now depends on `connected` | medium | patch | Reachable on a provisioned site whose URL was cleared; contradicts "the dialog's steps always match GET /setup". Fix: re-read after a save, as after a key change. |
| 4 | Memory says "Set up by hand instead" shows while `n8n` is open; it is hidden whenever a URL is saved or a key passes | low | patch | `ConnectN8n`; memory is what is true now. |
| 5 | `SETUP_STEPS` docblock: one line past the wrap width | low | patch | Cosmetic, direct correction. |
| 6 | `ConnectN8n` writes the passing-key test inline next to the exported `keyPasses()` | low | patch | Duplicate rule in one file; direct correction. |

## Verification

**Commands:**
- `php -l` on each touched PHP file -- expected: clean
- `for h in tests/harness/*-harness.php; do php "$h" || echo "FAIL $h"; done` -- expected: no FAIL
- `npm run test:unit` and `npm run lint:js` -- expected: green
