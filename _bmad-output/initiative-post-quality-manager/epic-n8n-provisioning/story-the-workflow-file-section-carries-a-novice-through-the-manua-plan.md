---
title: 'The Workflow File section carries a novice through the manual setup'
type: 'feature'
ticket: '2'
created: '2026-10-14'
status: 'built'
route: 'full'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
baseline_revision: '8d27f807557f51e194ab74d467d40d74fbed8c2c'
context: ['{project-root}/_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md', '{project-root}/_bmad-output/backlog/story-workflow-file-novice-path.md', '{project-root}/docs/brand.md']
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The Workflow File section lists the credentials before the import, makes the reader retype the token and the header names, links nowhere, says nothing about n8n filling every node with the first credential of a type (the 2026-10-13 production mis-wiring), nothing about a re-import onto a path an active workflow holds, and nothing about why the reader is on the manual path.

**Approach:** Rebuild the section as four numbered steps in journey order (story 40's acceptance criteria), with a Copy row for every value typed into n8n, accurate links only, the first-credential warning before the import, the unpublish-first note and this site's webhook path in the publish step, and one line saying why the reader is here: the stored cause of a failed CAP-1 probe, or the owner's own choice, read through one helper entry 6.6 writes to.

## Boundaries & Constraints

**Always:** story 40's five acceptance criteria hold on both builds; the Free build names two credentials and the two Claude nodes, the Pro one three and the six Claude nodes plus Update WP Post (Rewrite), behind `pqm_fs()->is__premium_only()` blocks only (ADR 0014); copy per `docs/brand.md`, every new string translatable, translator comments on parameterised ones, link texts name their destination; links only to addressable places (profile page Application Passwords section, the Anthropic key page, the n8n origin when known, Settings' connection tab), exact menu words for n8n screens; the n8n key never appears in the page or in the stored failure; a failed probe still leaves the stored URL, key and passing probe as they were (entry 1).

**Never:** a change to the setup dialog (`src/setup/`), the n8n Instance island (`src/n8n-connection/`), the template, the credential names, the import URL contract, `sanitize_settings()` or the probe's order and causes; the wizard side of the choice (no button, no route writes `manual`; entry 6.6); screenshots of n8n; a live refresh of the section after a probe in the same page view.

**Decisions (owner, 2026-10-14, best-practice answers):**
- D1 Failed probe's cause: `connect_n8n_callback()` stores it in `pqm_n8n_api['failed']` = {cause, the cause sentence without the manual-path sentence, at}, for the five causes whose message offers the manual path (`api_disabled`, `proxy`, `unreachable`, `missing_endpoint`, `unexpected`); `key`, `insecure` and `invalid_url` store nothing. A passing probe and Forget clear it (`save()` and `forget()` rewrite the option without it). The section shows the dated line whether or not setup is complete: it states a fact, so it stays true.
- D2 The choice's store: `pqm_setup_progress['manual']` (the wizard's per-site memory a re-walk reads; Forget keeps it; uninstall already deletes it). This entry makes `setup_progress()` carry the key, so a step tick no longer drops it; entry 6.6 writes it. A stored failure line wins over the choice line; a passing probe hides both.
- D3 The n8n link: the stored base URL when there is one (the address the owner opens n8n at), else the saved webhook URL's origin (story 40's rule), else no link.
- D4 Release notes: no line; the section explains itself and no behaviour changes for an existing site.
- D5 Plan size: keep the full plan (one goal; the failure record and the choice hook exist only to feed the reason line, and the length is the Code Map).

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|---|---|---|---|
| Fresh site | setup not complete, nothing stored | First line recommends "Set up the workflow"; steps 1–4; no reason line; no n8n link | — |
| Setup complete | `pqm_setup_complete()` | No recommendation line; steps unchanged | — |
| Probe failed, manual cause | POST /n8n/connection → `api_disabled`, `proxy`, `unreachable`, `missing_endpoint` or `unexpected` | Stored as `failed` {cause, message without the manual-path sentence, at}; section: dated line with that sentence | Stored URL, key, probe unchanged |
| Probe failed, other cause | `key`, `insecure`, `invalid_url` | Nothing stored; no reason line | Island shows it, as today |
| Probe passes / Forget | after a stored failure | `failed` gone; no reason line | — |
| By choice | `pqm_setup_progress['manual']` true, no failure, no passing probe | "You chose to set up n8n by hand" line | A failure line wins over it |
| Choice, then connected | `manual` true, key + passing probe | No reason line | — |
| Step tick | POST /setup with `manual` stored | `manual` survives the write | — |
| n8n link | base URL stored / only webhook URL saved / neither | Link to the base URL / to the webhook URL's origin / no link | — |
| Copy | click Copy on any row | Value copied, row's `aria-live` says so; clipboard refused → value selected, Ctrl+C hint | — |

</frozen-after-approval>

## Code Map

- `post-quality-manager/admin/class-pqm-admin.php:1379-1396` -- the section in `render_workflow()`; `:1525-1609` `render_import_url()`, `render_credentials_list()`, `render_credentials_hint()` become one steps renderer plus `render_copy_value( $label, $value )` (visible label, readonly input with an `aria-label`, Copy button, `aria-live` feedback; classes only, no IDs). Reuse `pqm_site_host()` for the credential names, `pqm_webhook_path()` for "the production URL ends in /webhook/<path>", `pqm_setup_complete()` for the recommendation line. Links: `admin_url( 'profile.php#application-passwords-section' )`, `https://platform.claude.com/settings/keys` (console.anthropic.com 301s there, checked 2026-10-14), `admin_url( 'admin.php?page=pqm-settings#tab-connection' )`. `:203-204` `pqmAdmin.i18n.copied` is import-specific: add a generic "Copied." key.
- `post-quality-manager/includes/pqm-functions.php:501` -- beside `pqm_setup_complete()`: `pqm_manual_reason(): ?array` (`{kind: 'probe', message, at}` | `{kind: 'choice'}` | null), the one reader entry 6.6 reuses; the date through `wp_date( get_option( 'date_format' ), $at )`.
- `post-quality-manager/includes/class-pqm-n8n.php:41-73,211-261` -- `connection()` default `failed => null`; `record_failure( $probe )` (manual causes only); `failure()` builds each cause sentence once, `message` keeps the manual-path sentence appended (the island is unchanged) and the result carries the bare sentence for the record.
- `post-quality-manager/includes/class-pqm-rest-api.php:1275-1278` -- record a failed probe; `:988-995` `setup_progress()` carries `manual`.
- `post-quality-manager/admin/js/pqm-admin.js:375-405` -- `initImportUrlCopy()` becomes one binding over `.pqm-copy-row` (the import URL row included); `admin/css/pqm-admin.css:74-78` the row selectors follow.
- Tests: `tests/harness/render-matrix-harness.php:412-464,544-546,707` (asserted copy and classes; it loads the whole plugin, so `PQM_N8n` is there, `$GLOBALS['tw_options']` seeds `pqm_n8n_api` and `pqm_setup_progress`, and `$wpdb->has_result` at `:746` drives `pqm_setup_complete()`), `tests/harness/free-build-harness.php:342-344`, `tests/harness/n8n-api-harness.php` (failure record), `tests/harness/setup-harness.php` (`manual` survives a tick); `tests/js/workflow-status.test.js` is the jsdom pattern for a new copy test.
- Do not change: `PQM_N8n::probe()`'s calls and causes, `public_state()`, `src/setup/`, `src/n8n-connection/`, the template.

## Tasks & Acceptance

**Execution:**
- [x] `post-quality-manager/includes/class-pqm-n8n.php`, `class-pqm-rest-api.php` -- failure record, `manual` carried -- the cause survives a reload
- [x] `post-quality-manager/includes/pqm-functions.php` -- `pqm_manual_reason()` -- one reader for the section and entry 6.6
- [x] `post-quality-manager/admin/class-pqm-admin.php` -- the four steps, Copy rows, links, warning, reason and recommendation lines -- story 40 + CAP-8
- [x] `post-quality-manager/admin/js/pqm-admin.js`, `admin/css/pqm-admin.css` -- one Copy binding -- every row behaves like the import URL row
- [x] `tests/harness/*` above, `tests/js/workflow-file-copy.test.js` (new) -- every matrix row -- proof
- [x] `memory/n8n-provisioning.md` -- entry 2 line: the `failed` record, the `manual` hook

**Acceptance Criteria:**
- Given either build, when the section renders, then step 1 imports (Download, import URL), step 2 creates the credentials, step 3 opens each flagged node, step 4 saves, publishes, copies the production URL and pastes it, in that order, and no credential is listed above the import.
- Given step 1, when it renders, then before the import it warns that n8n fills every node needing a credential with the first one of that type it has, so each node's credential name must be checked in step 3.
- Given step 4, when it renders, then it says a re-import onto this site's path can be published only after the old workflow is unpublished, and that the production URL ends in `/webhook/` plus `pqm_webhook_path()`.

## Implementation Notes

- Built 2026-10-14 by the implementation subagent; verified on the orchestrator side: `php -l` clean on the 8 touched PHP files, `node --check` clean, 16 harnesses green (render-matrix 337, n8n-api 121, setup 108, free-build 51), Jest 50/50 (new `workflow-file-copy.test.js`), `lint:js` clean, build compiles, `git diff --check` clean, LF only.
- `pqm_manual_reason()['at']` is the date already formatted in the site's date format, not a timestamp; entry 6.6 reads it as text.
- A failed probe's result carries `reason` (the cause sentence without the manual-path one) beside `message`, so the POST /n8n/connection reply has it too; not secret, the island ignores it. The five manual-path cause strings lost their manual-sentence placeholder: new msgids, existing translations of them lapse.
- Copy feedback: the import URL row keeps its own "Copied! Paste it in n8n via "Import from URL"." line (the trailing "…" dropped, as the dialog writes it); every other row says "Copied." (new `copiedValue`); `copyFailed` now says "The value is selected".
- The harness stubs grew: `render-matrix-harness.php` defines `wp_get_current_user()` and `$wpdb` before the section, and its `add_query_arg` stub takes the three-argument form `workflow_import_url()` needs.
- Copy and n8n menu words ("Unpublish" from the arrow beside "Publish", "Create" → "Credential", "Import from File" / "Import from URL") come from the plan, docs.n8n.io and the dialog; not checked on a live n8n.

## Plan Change Log

## Review Triage Log

Pass 1 (quick lens): 6 findings — medium 3, low 3, false 0, maybe-false 0; 5 patched, 1 deferred.

| # | Finding | Verdict | Route | Evidence / action |
|---|---|---|---|---|
| 1 | The WordPress credential block always links to `profile.php#application-passwords-section`, a section core prints only when Application Passwords are available | medium | patch | Real: core renders the section only when `wp_is_application_passwords_available_for_user()` is true (HTTPS or `local`, no plugin turning it off); the dialog already branches on it (`setup_state()['app_passwords']`, `AppPassword` in `src/setup/steps.js:143`). Breaks the Always "links only to addressable places". Patch: branch on the same check, the unavailable case says so in words, no link. |
| 2 | Step 4's re-import sentence claims a path conflict for every re-import | low | patch | Real: before entry 3 (4e54a89) the trigger path was the literal `pqm-execute`, so a v5 site, the one the update notice sends here, re-imports onto a new path and n8n publishes it beside the old one. Direct copy correction: scope the sentence to an old workflow of version 6 or later. |
| 3 | Copy rows share accessible names (three "Copy Credential name", two "Copy Name") | medium | patch | Real: `render_copy_value()` derives the field's and button's names from the generic label only; the credential is in a nearby `<h4>` neither references. Story 40 AC5 (screen reader). Patch: the credential rows' aria-labels name their credential; the harness's label-equality assertion follows. |
| 4 | `.pqm-copy-feedback:empty { display: none; }` keeps each live region out of the accessibility tree until it is filled | medium | patch | Real: a `display: none` element is not in the accessibility tree, so the region appears with its content at once, which several screen-reader and browser pairs do not announce. Pre-existing for one row, extended by this diff to eight, and the matrix's Copy row relies on it. Patch: the empty region keeps no margin instead of being hidden. |
| 5 | `src/setup/steps.js:7` names the removed `PQM_Admin::render_credentials_list()` | low | defer | Real stale comment; the fix edits `src/setup/`, which this plan's frozen Never forbids. Deferred to entry 6.6, which reworks the dialog's n8n steps. |
| 6 | Docblocks: `PQM_SETUP_OPTION` lacks `manual`; `forget_n8n_callback()` omits `failed`; `probe()`'s `@return` omits `reason` | low | patch | Real: all three describe data the diff changed. Direct comment corrections. |

Patches 1–4 and 6 applied by the implementation subagent: the WordPress block branches on `wp_is_application_passwords_available_for_user()` (unavailable: no link, the reason in words); the re-import sentence opens "Re-importing over a workflow of version 6 or later?"; `render_copy_value()` takes an optional accessible label and every credential row passes `credential_label()` ("<credential>: <field>"), the harness asserting no two buttons share a name; the empty live region keeps `margin: 0` instead of `display: none`; the three docblocks match the data. Finding 5 written to `deferred-work.md`. Verification re-run on the orchestrator side: `php -l` clean, `node --check` clean, 16 harnesses green (render-matrix 339), Jest 50/50, `lint:js` clean, build compiles, `git diff --check` clean, LF only.

## Design Notes

- Steps (content, copy written at build): 1 import: Download or the import URL row; the first-credential warning; "in n8n" linked per D3. 2 credentials: one block each, Copy rows for the credential name, `X-PQM-Token`, the token, `x-api-key`; Anthropic key and Application Password links. 3 nodes: today's hint, plus "check the name". 4 publish: Save, Publish (older n8n: the Active toggle); a re-import: unpublish the old one first (the arrow beside Publish, docs.n8n.io checked 2026-10-14); the path; paste in Settings, then Send test ping below.
- Copy on "Anthropic - Claude" too, and (Pro) a User row with the current user's login in the WordPress credential block: consistent with the dialog, no extra code.
- Skipped: the failure in `public_state()` and the island — add when entry 6 needs it.

## Verification

**Commands:**
- `php -l` on every touched PHP file -- clean
- `for f in tests/harness/*.php; do php "$f" || exit 1; done` -- every harness passes
- `npm run test:unit`, `npm run lint:js`, `npm run build` -- pass
- `git diff --check`, no CR in touched files -- clean

**Manual checks (if no CLI):**
- On a site the owner or the agent opens: Free and Pro sections read as four steps, every Copy works with its announcement, each link opens its destination, and a stored failure shows its dated line.

## Code Review

### 2026-10-14

**Code review complete.** 1 `decision-needed`, 11 `patch`, 0 `defer`, 10 rejected. Lenses: blind-hunter (16), edge-case-hunter (5), verification-gap (2 + 1 note), intent-alignment (descriptive), overbuild (3), premium-leak (clean). Findings grouped by root cause; verdicts are the triager's, graded against the code.

- [x] [Review][Decision] Import order differs from the dialog — resolved (owner, 2026-10-14, best-practice answer): (a), the import URL first and the Download as the fallback, as the dialog does. the section leads with Download + "Import from File" and offers the import URL second; `src/setup/steps.js` leads with Import from URL and offers the file when n8n cannot reach the site. Story 40 names the dialog as the wording to stay consistent with. Options: (a) URL first here too, Download as the fallback; (b) keep file first and say why (the re-import path); (c) leave as is.
- [x] [Review][Patch] Step 4 drops the dialog's Test URL warning (a `/webhook-test/` URL only listens while the editor is open; the plugin refuses it) [post-quality-manager/admin/class-pqm-admin.php:1621]
- [x] [Review][Patch] "Claude Console" link text; story 40 AC3 and the dialog say "Anthropic Console" [post-quality-manager/admin/class-pqm-admin.php:1599-1601]
- [x] [Review][Patch] `MANUAL_CAUSES` duplicates what `manual()` already decides (`reason` is set only for manual-path causes); a cause routed through one and not the other would carry `reason` yet never be recorded [post-quality-manager/includes/class-pqm-n8n.php:36,88]
- [x] [Review][Patch] `credential_label()` exists only to feed `render_copy_value()`'s third argument and every caller repeats the field's `__()` twice; the "<credential>: <field>" convention lives outside the function that needs it [post-quality-manager/admin/class-pqm-admin.php:1676,1696]
- [x] [Review][Patch] `wp_date( get_option( 'date_format' ), … )` has no default; `class-pqm-features.php:282` uses `'F j, Y'` [post-quality-manager/includes/pqm-functions.php:534]
- [x] [Review][Patch] The import URL row's "where to paste" feedback keys on a `.pqm-import-url` wrapper no PHP test asserts [tests/harness/render-matrix-harness.php:472]
- [x] [Review][Patch] `n8n_editor_url()`'s origin branch is untested with a port (`http://192.168.1.20:5678/…`, accepted by `sanitize_settings()`) or a hostless/odd-scheme URL (the `''` branch) [tests/harness/render-matrix-harness.php:514]
- [x] [Review][Patch] The section's credential names are PHP literals; the harness ties step 3 to the template's nodes but not the names to the template's credential names [tests/harness/render-matrix-harness.php:530]
- [x] [Review][Patch] `wp_get_current_user()` and `wp_is_application_passwords_available_for_user()` stubs are unguarded while every neighbour uses `if (!function_exists(...))` [tests/harness/render-matrix-harness.php:423,426]
- [x] [Review][Patch] `echo "Failure record` + literal newline, the pattern this diff fixes in render-matrix [tests/harness/n8n-api-harness.php:292]
- [x] [Review][Patch] Story 22's reference still names `render_import_url()` / `render_credentials_list()` [_bmad-output/backlog/story-n8n-workflow-generator-review.md:27]

Rejected:
- overbuild `reuse:` `PQM_N8n::origin()` for `n8n_editor_url()` — false: `origin()` refuses `http://` off localhost, so a LAN webhook URL (`http://192.168.1.20:5678/…`, accepted by `sanitize_settings()`) would lose its link; the reviewer's own ceiling.
- blind + edge: the stored failure sentence is frozen in the probing user's locale and wording — low: needs two admins with different locales, or a copy change between probe and view; the fix restructures the store against D1.
- blind + edge: no `edit_posts` check beside the User row — low: the default map gives `pqm_manage_settings` to administrators only (`class-pqm-features.php:37`), a custom Roles map is the one way there, and the sentence states the requirement; fix adds a branch.
- edge: an empty `webhook_token` renders an empty Value row — low, pre-existing: activation generates one, only a blanked Settings field reaches it, and an empty token breaks the import URL and the trigger too; fix adds a guard.
- edge: `undefined` in the live region if `pqmAdmin.i18n` lacks the keys — low: PHP localizes them with the same release (harness-asserted); guards a state not shown reachable.
- blind: wrap the input in `<label>` — low: `aria-label` already names the field; the change runs through the harness regexes and the jest fixture.
- blind: add a manual-check line for the n8n menu words — fix edits the plan; surfaced to the owner instead.
- blind: the filled live region's margin makes the row jump — low, cosmetic, pre-existing on the import row; reserving height blanks eight rows.
- blind: story 40 carries no note that entry 6.2 absorbs it — false: its line 62 records the drop and the absorption.
- verification-gap note: a reverse-proxied n8n under a path prefix links to the host root — a consequence of D3, recorded, no action.

Intent alignment (descriptive): the diff implements the "section + minimum state plumbing" reading; sub-choices pinned by D1–D5. Surfaces no test reaches: the live n8n menu words, the three link targets resolving, the hand-off from the island (plain text, Never'd), the choice line (unreachable until 6.6 writes `manual`). Noted: `key` failures store no reason (D1); the dated line persists until a passing probe or Forget (D1); five probe msgids and the REST reply's new `reason` are release surfaces D4 leaves out of the notes.

Actions (2026-10-14, owner: "apply all, best-practice answers"): the decision resolved as (a) and the 11 patches applied by the orchestrator. Step 1 now leads with Import from URL, the file as the fallback (secondary button); step 4 carries the Test URL warning; the link text says "Anthropic Console"; `MANUAL_CAUSES` removed, `record_failure()` keys on `reason`; `credential_label()` folded into `render_copy_value()`'s third argument (the credential); `date_format` defaults to `F j, Y`; render-matrix gained the `.pqm-import-url` wrapper pin, the port and no-host link rows and the template credential-name tie, its two stubs are guarded; the n8n-api echo fixed; story 22's reference names `render_workflow_file()`. Verification re-run: `php -l` clean, `node --check` clean, 16 harnesses green (1728 checks, render-matrix 346), Jest 50/50, `lint:js` clean, build compiles, `git diff --check` clean, LF only.
