---
title: 'The Set up the workflow button lines up with the Anthropic key field'
type: 'bugfix'
ticket: '115'
created: '2026-11-06'
status: done
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
baseline_revision: '94a1aaf38ca3538118d7cd03c508d49ad2078a50'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** On step 2 of the setup dialog, `.pqm-setup-connect` (`src/setup/style.css` L53–60) lines its items up at the bottom (`align-items: flex-end`), and the key field's `TextControl` carries its help line inside the control, so the **Set up the workflow** button meets the help line's bottom, about 30 px below the field; on Pro the Application Password sentence is a third item of the same row.

**Approach:** In step 2's form only, the help line leaves the `TextControl` and follows the button as its own paragraph, still named by the field's `aria-describedby`, and the Pro sentence follows it; one rule in `src/setup/style.css` gives a paragraph inside `.pqm-setup-connect` the full row, so the button meets the input's bottom and the text sits under the row. Step 1 and the first review's row carry no paragraph and keep their layout.

</frozen-after-approval>

## Implementation Notes

Oneshot: two source files and one test file (`src/setup/steps.js` L350–396, `src/setup/style.css`, `tests/js/setup-provision.test.js`), about 30 lines, no PHP, schema, REST or template change.

- WordPress 7.1.4's bundled `TextControl` (`wp-includes/js/dist/components.js`, `UnforwardedTextControl`) sets `"aria-describedby": !!help ? id + "__help" : void 0` and then spreads `...additionalProps` onto the `<input>`, so an `aria-describedby` passed in names the help paragraph outside the control. `@wordpress/components` is an external (not in `node_modules`); the Jest mock of `TextControl` (`setup-provision.test.js` L69–83) forwards only named props.
- DOM order is field, button, help, Pro sentence, the same as what a sighted user sees on a phone, where the row wraps (focus reaches the help's link after the button, as it is drawn); no CSS `order`.
- The help paragraph gets core's help look from the plugin's own class `pqm-setup-help` (12px, gray 700 `#757575`, as BaseControl's styled help in WordPress 7.1.4; the class `components-base-control__help` carries no CSS of its own, review pass 1); the row gap gives core's 8px above it. The Pro sentence keeps `pqm-muted`.
- Skipped: a CSS-only fix (aligning the button to the input needs the label's height in pixels) — add when a second form with a help line joins `.pqm-setup-connect`.
- 2026-11-06, built by the orchestrator (Opus 5.5; oneshot, no implementer subagent), commit ad25a84. Files: `src/setup/steps.js` (the help line from `TextControl`'s `help` to a `<p id="pqm-setup-key-help" class="components-base-control__help">` after the button, named by the input's `aria-describedby`; the Pro sentence after it; the button moved up), `src/setup/style.css` (`.pqm-setup-connect > p { flex-basis: 100%; margin: 0; }`; after review pass 1 also `.pqm-setup-help`), `tests/js/setup-provision.test.js` (the mock forwards `aria-describedby`; a new test checks the link and the form's order INPUT, BUTTON, P, P). The new test fails on 94a1aaf's `steps.js` and passes on the fix. Jest 182 passed, `npm run lint:js` clean, `npm run build` compiled, harnesses 23 files 0 failed.
- Showcase check (2026-11-06, Playwright driving Edge, a WP-CLI session for user 1). Backup first (3.9.0 Free, 13 tables, 30 rows, 1 user). Step 2 staged with a stored n8n connection (placeholder key, a passing probe record, never called), so the dialog opened on Set up the workflow. Measured in CSS px, top-bottom:

  | Build, window | Input | Button | Help line | Pro sentence |
  |---|---|---|---|---|
  | Free, 1280 | 507-547 | 511-547, beside, bottoms equal | 555-574 | none |
  | Free, 400 | 764-804 | 812-848, under the input | 856-876 | none |
  | Pro, 1280 | 483-523 | 487-523, beside, bottoms equal | 531-551 | 559-598 |
  | Pro, 400 | 698-738 | 746-782, under the input | 790-809 | 817-876 |

  The button is 36 px tall and the input 40 px, so bottoms level means tops 4 px apart (the components' default heights). `aria-describedby` named the help line in every run, and there were no console errors. `debug.log` held only WP-CLI's own lines. Restore: 3× "same".
- Not measured, and why: step 1's form (Connect by URL, L105) and the first review's row (`.pqm-setup-run`) hold no `<p>` child, and the new rule matches only `.pqm-setup-connect > p`; their markup is unchanged. Before the fix, the owner's 2026-11-04 screenshot is the evidence: about 30 px lower, the button's bottom on the help line's.
- Released 2026-11-06 in **3.12.4** (#260, release commit 16a83a2; pre-release). Release run 42241927319 green. Zip `post-quality-manager-3.12.4.zip` sha256 `f5fd0fce…a462`: one root folder, `admin/build/` inside, its `style-index.css` with `.pqm-setup-connect>p` and `.pqm-setup-help`. Uploaded to Freemius as **deployment 171447**, release mode pending; the Free build check said "PHP identical: 11 files."; the -free and -pro artifacts were uploaded. Release check 2026-11-09: the zip's 218 files equal the plugin folder on main, which chore 103's showcase walk installed that day on WordPress 7.1.4; the clean WordPress 6.6 install waits for story 38's pre-production site.

## Plan Change Log

## Review Triage Log

- 2026-11-06, Quick lens pass 1 (Opus 5.5): 2 findings, 0 high, 0 medium, 2 low, 0 false, 0 maybe-false; 1 patched, 1 rejected.

| # | Finding | Verdict | Route | Evidence |
|---|---|---|---|---|
| 1 | The moved help line lost core's help look (13px, the default colour; the showcase's help lines measured 19px tall) and the notes claimed `components-base-control__help` kept it | low | patch | Confirmed: the class has no rule in `wp-includes/css/dist/components/style.css`. Core's look is emotion's `StyledHelp` (margin-top `space(2)`, `helpText.fontSize`, `COLORS.gray[700]`). Patched with `.pqm-setup-help { font-size: 12px; font-style: normal; color: #757575; }`. The built CSS on the step's markup computes 12px and rgb(117, 117, 117). The showcase layout check was not re-run for a font-and-colour change: the help sits on its own line. |
| 2 | AC2 and AC3: step 1 and the first review's row were not seen at either width; the notes call `steps.js` L105 "step 1's form" | low | reject | No behaviour is wrong. `.pqm-setup-connect` occurs only at `steps.js` L105 and L350. Step 1 (Connect your n8n) is `ConnectN8n`, the `N8nConnection` island (`src/n8n-connection/`), and the first review's row is `.pqm-setup-run`, so neither can match `.pqm-setup-connect > p`; neither file changed. L105 is `Connect`, the paste-URL body of Set up by hand and of a failed ping, and holds no `<p>` child. The ticket's References carry the same L104 label. The fix would edit this build's plan, so this row records the correction instead. |

## Verification

**Commands:**
- `npm run test:unit` -- expected: every suite passes, with one new test: step 2's key input names, by `aria-describedby`, a paragraph holding "Create one on the API keys page of the Anthropic Console.", and that paragraph and the Pro sentence come after the submit button inside the form.
- `npm run lint:js`, `node --check` is not applicable to JSX (the build checks it): `npm run build` -- expected: compiled successfully.
- `for f in tests/harness/*.php; do php "$f" || break; done` -- expected: every file 0 failed (no PHP touched; a guard).

**Manual checks (if no CLI):**
- The showcase, Free build and Pro build of the branch, setup dialog step 2 at 1280 and 400 px window widths (Playwright, Edge): the button's bottom equals the input's bottom when beside it (1280), or the button sits under the input when the row wraps (400); the help line and, on Pro, the Application Password sentence sit under the row; step 1 (Connect your n8n) and the first review's row (Run Review) measure as on main.
