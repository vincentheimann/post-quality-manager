---
title: 'EXPERIENCE — n8n Workflow page'
status: final
created: 2026-10-14
updated: 2026-11-05
sources: ['_bmad-output/backlog/story-n8n-workflow-page-lean-ux-rework.md', 'docs/brand.md', 'docs/user-paths.md', '_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/spec-n8n-provisioning.md', '_bmad-output/initiative-post-quality-manager/spec-n8n-provisioning/failure-modes.md']
---

# n8n Workflow page — Experience Spine

Story 67, design-only pass, done before epic entry 6.6; the page itself is rebuilt after entry 6.12. The page as it ships today is inventoried in [audit.md](audit.md). This spine is the target: what the page shows in each state of the site. Visual rules live in [DESIGN.md](DESIGN.md). The mock [mockups/key-workflow-page.html](mockups/key-workflow-page.html) shows every state; this spine wins on conflict. Approved by the owner on 2026-10-14 (story 67 AC1).

## Outcome and hypothesis

The page answers one question in every state: **what do I do next?**

- **Hypothesis:** if the page shows one next step per site state, a blogger new to n8n reaches a passing ping without reading sections that do not apply to them.
- **Signals:** story 67 AC6, where the owner names the next step in each state without reading the other sections; and the epic's final walk (entry 12), which measures the time from opening the page to the first passing ping, with no visit to the n8n UI on the key path.

## Foundation

A WordPress admin page under the plugin menu. It is rendered in PHP with vanilla JS (`admin/js/pqm-admin.js`) and mounts two React islands: the setup wizard (`src/setup/`) and the n8n connection (`src/n8n-connection/`). The UI system is the WordPress admin (wp-admin CSS classes); `@wordpress/components` stays inside the islands. Desktop web admin; wp-admin's own responsive rules apply. Capability: `pqm_manage_settings`.

Terms used below: the **connection check** is the plugin's check of the n8n address and API key (entry 6.1); the **setup map** is the plugin's record of what it created in n8n (entry 6.4); a **test** pings the saved webhook URL.

### Where each epic entry lands

| Entry | What it adds | Where |
|-------|--------------|-------|
| 6.6 | The wizard: connect → provision → first review; "Set up by hand instead" | The primary action of states 1 and 2; the link to state 3 |
| 6.7 | Mis-wiring diagnosis | State 5's status sentence |
| 6.8 | Adopt a hand-made setup | **Let the plugin manage this workflow**, in the n8n connection disclosure of state 4 |
| 6.9 | One-click update | **Update workflow**, state 5's fix for a provisioned or adopted setup |
| 6.10 | Token rotation | **Rotate token**, in the Webhook disclosure |

Until story 67's rebuild, each entry places its control where this table says, as far as the current page allows.

## Information Architecture

One page, the same anatomy in every state, top to bottom:

1. **H1** "n8n Workflow".
2. **Status block.** One sentence saying where the site stands, plus the workflow version and when and how it was last checked. **Test again** sits here once a webhook URL exists. This is the one place for the connection state, the version and the test.
3. **The state's primary action.** Exactly one `.button-primary` on the page.
4. **The state's body.** Only what this state needs.
5. **Disclosures.** Native `<details>`, collapsed by default, one per topic the state does not lead with:
   - **Set up by hand.** The four manual steps of entry 6.2.
   - **n8n connection.** Address, API key, **Check again**, **Forget key**; adoption (6.8).
   - **Webhook.** URL view and change, token, rotation (6.10).

### States

State numbers follow the site's lifecycle (1 = first visit). The table rows follow the resolution order: the state is resolved from the top, and the first match wins.

| # | State | Resolved when | Status sentence (draft) | Primary action | Body | Disclosures shown |
|---|-------|---------------|-------------------------|----------------|------|-------------------|
| 5 | **Needs attention** | A webhook URL is saved and the last check failed, found an older version, found the Free workflow variant on a Pro build, or (6.7) found mis-wiring | The problem in one sentence, with its fix | The fix: see *State 5 fixes* below | The diagnosis, when there is one | n8n connection, Webhook, Set up by hand (a hand-made setup only) |
| 4 | **Set up** | A webhook URL is saved and the last check passed (variant *not checked yet*: a URL but no report) | "Your workflow answers. Version 6 · checked {date} by {a test / a command}." Not checked yet: "Saved, not checked yet." | No first review yet → **Run your first review** (the wizard's review step). Reviews exist → **Open Post Reviews**. Not checked yet → **Test the connection** | None | n8n connection (on a hand-made setup with a key stored, it offers **Let the plugin manage this workflow**, 6.8), Webhook, Set up by hand (a hand-made setup only) |
| 3 | **Manual path** | No webhook URL, and the site admin chose "Set up by hand instead" or the last connection check failed with a cause that offers the manual path ([failure-modes.md](../spec-n8n-provisioning/failure-modes.md)) | "Setting up by hand." plus the reason: "You chose to set up n8n by hand." or "On {date}, the n8n connection check did not pass: {cause}." By choice only, a link-styled "Use the guided setup instead" follows on its own line (story 106): the wizard removes the choice and opens on its first open step, the page following behind it | **Save and test**, under step 4 (the webhook URL field moves here from Settings; see Build notes) | The four steps, open | n8n connection (to try the key path again) |
| 2 | **Connected, not set up** | An n8n API key is stored with a passing connection check, and there is no setup map and no webhook URL | "Connected to n8n at {host}. The workflow is not set up yet." | **Set up n8n**: the wizard at its provisioning step (6.6), which asks for the Anthropic key once | Variant *leftovers waiting*: an info notice, "An earlier setup left a workflow or credentials in n8n. Setting up removes them first." | n8n connection, Set up by hand (a link: "Set up by hand instead") |
| 1 | **Not set up** | None of the above | "Not connected to n8n yet." | **Set up n8n**: the wizard at its connect step (6.6) | One paragraph naming the three values the setup asks for (the n8n address, an n8n API key with an expiry date, an Anthropic API key) and saying that the plugin creates everything in n8n | None: a "Set up by hand instead" link stores the choice and opens state 3 |

**State 5 fixes**, in this order (the first that applies is the primary action):

1. The ping fails → **Test again**, with the diagnosis below the status.
2. Mis-wired (6.7) → **Check again**, after the fix the status names.
3. Outdated, or the Free variant on Pro:
   - provisioned or adopted setup → **Update workflow** (6.9);
   - hand-made setup → **Re-import the workflow**, which opens Set up by hand at step 1, with the unpublish-first note.

### One place for each thing (AC3)

| What | Its one place | Elsewhere |
|------|---------------|-----------|
| Webhook URL (edit) | This page: the step 4 field (state 3), the Webhook disclosure (states 4–5). Saved through the existing `POST /setup/connect` (see Build notes) | Settings → Connection shows it read-only, with "Change it on the n8n Workflow page" |
| Connection test | The status block, **Test again** | Settings' **Send test ping** and the page's Connection Test section are removed. The wizard's own check is part of its flow |
| Workflow version | The status block | Ping results render in the status block, not as a separate result line |
| Webhook token | Hand-made setup: the manual step 2 copy row. Provisioned or adopted setup: the Webhook disclosure (rotation, 6.10) | Settings shows a link instead of the read-only field |
| Manual steps | This page, Set up by hand | 6.6 replaces the wizard's manual n8n steps with connect → provision → first review; "Set up by hand instead" in the wizard closes it and opens state 3; "Use the guided setup instead" (state 3 by choice, and the wizard's Set up by hand step by choice while no URL is saved) removes the choice and leads back to the key path (story 106) |
| Per-agent table | Settings → AI, where its values are edited (AC4) | Removed from this page |
| "Set up the workflow" entry points on other pages (welcome notice, Dashboard card, Post Reviews notice) | Stay; they link here | — |

## Voice and Tone

Microcopy, per `docs/brand.md` Copy Rules. The wording here is draft; each entry's build writes the final copy.

| Do | Don't |
|----|-------|
| "Not connected to n8n yet." | "n8n is not configured!" |
| "Your workflow answers. Version 6 · checked today by a test." | "Success! Connection established successfully." |
| "Your workflow is version 5; this plugin needs 6. Update workflow." | "Outdated workflow detected. Please re-import the JSON file and reconnect all credentials." |
| "You chose to set up n8n by hand." | "API setup skipped." |
| A state sentence followed by its action. | Every section's instructions on the page at once. |

## Component Patterns

| Component | Use | Behavioural rules |
|-----------|-----|-------------------|
| Status block | Every state | Per Information Architecture. It re-renders after a test, after the wizard closes, and on window focus (as today) |
| Primary action | Every state | Per Information Architecture. Secondary actions are `.button` or links |
| Disclosure | States 2–5 | Native `<details><summary>`, collapsed, no remembered state. A link that leads into a disclosure ("Set up by hand instead", "Re-import the workflow") opens it (focus: Accessibility Floor) |
| Manual steps | Set up by hand | Entry 6.2's four steps, unchanged in content, including their copy rows and Free/Pro variants. Step 4 gains the webhook URL field and **Save and test** |
| Wizard | Opened by the primary action of states 1–2 and 4, and by state 3's "Use the guided setup instead" (story 106) | The one guided entry point (story 55). Opens at the step the state names (see Build notes). On close, the page re-renders its state |

## State Patterns

| State | Treatment |
|-------|-----------|
| Built assets missing | The existing "build missing" warning replaces each island; the PHP parts still render |
| A test in progress | **Test again** disabled with a busy label; the status sentence stays until the answer replaces it |
| Free build | Same layout and five states; the manual steps have no WordPress credential or Rewrite node; no locked or empty section (AC5) |
| Pro without Application Passwords | State 4 (Set up): a warning line under the status block says Rewrite stays off until a WordPress credential is added (entry 4, D1) |

## Interaction Primitives

- Copy rows (existing `initCopyRows()`).
- `<details>` disclosures.
- One async test, from the status block.
- The wizard Modal (existing).
- No new primitives.

## Accessibility Floor

- The status block is an `aria-live="polite"` region; a test result is announced once.
- One H2 per disclosure summary; the H1 stays the page title.
- Notices carry text, never colour alone (WordPress notice classes).
- Disclosures are native `<details>`, keyboard- and screen-reader-operable without script.
- Focus: opening a disclosure from a link moves focus to the disclosure's heading; closing the wizard returns focus to the primary action.
- Tab order: status block, primary action, body, disclosures.

## Key Flows

### Flow 1 — Evaluation

*Lena, solo tech blogger, self-hosts WordPress and n8n, first evening with the plugin.*

1. Lena activates the plugin and follows the welcome notice to the n8n Workflow page.
2. State 1. The status says "Not connected to n8n yet."; one button, **Set up n8n**; the paragraph names the three values they will need.
3. They create an n8n API key with an expiry date, as the paragraph suggests, and open the wizard.
4. The wizard connects, and the page behind it moves to state 2; the wizard asks for the Anthropic key once.
5. **Climax:** the wizard reports the workflow set up and answering; the page behind it reads "Your workflow answers. Version 6 · checked today by a test." Lena never opened n8n's editor.
6. The primary action is now **Run your first review**.

### Flow 2 — By hand, by choice

*Lena again, on a shared n8n instance whose admin will not hand over an unscoped key.*

1. In state 1, Lena picks "Set up by hand instead".
2. State 3: "You chose to set up n8n by hand." The four steps are open.
3. They import the workflow, create the credentials, link the nodes, publish it and paste the production URL into step 4's field.
4. **Climax:** **Save and test** turns the page to state 4, "Your workflow answers."

*Changing her mind (story 106):* before step 3's URL is saved, "Use the guided setup instead" under the reason opens the wizard on Connect your n8n, and the page behind it reads "Not connected to n8n yet." again; when a key already passes, the wizard opens on Set up the workflow and the page reads "Connected to n8n at {host}. The workflow is not set up yet." Picking "Set up by hand instead" once more returns to state 3.

### Flow 3 — The silent mis-wiring

*The owner, production site, 2026-10-13, as recorded.*

1. Reviews return "could not get an answer from the model"; the page, as it shipped then, showed nothing wrong.
2. With 6.7, the page is in state 5. The status names the Claude nodes, the credential they share and the fix.
3. The owner assigns the Anthropic credential in n8n and presses **Check again**.
4. **Climax:** the status turns to "Your workflow answers.", and the next review lands.

### Flow 4 — A plugin release raises the template version

*Any provisioned site.*

1. After the update, the next check finds version 6 against the plugin's 7: state 5.
2. One button, **Update workflow** (6.9).
3. **Climax:** one click later, "Your workflow answers. Version 7." No re-import, no credential touched.

## Build notes

- `[ASSUMPTION]` The persona's name (Lena) and Flows 1, 2 and 4 are drawn from `docs/user-paths.md` and the spec, not from a recorded session.
- **Build note:** moving the webhook URL off the Settings form. `sanitize_settings()` rebuilds `pqm_settings` from the posted form, so the build must keep the stored URL when the field is absent (AGENTS.md "Disabled form controls don't POST").
- **Build note:** the wizard opening at a named step needs 6.6 to accept a start step; today it resumes at the first open step.
