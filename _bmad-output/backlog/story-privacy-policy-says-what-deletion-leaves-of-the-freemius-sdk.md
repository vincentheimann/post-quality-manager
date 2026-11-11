---
id: 127
type: story
title: "The privacy policy says what deleting the plugin leaves of Freemius's record"
parent: none
covers: []
after: []
assignee: ""
refined: false
hitl: false
risk: low
estimate: ""
---

# The privacy policy says what deleting the plugin leaves of Freemius's record

## Description

Deleting the plugin removes its own data, as the privacy policy says, but Freemius's SDK keeps its own record on the site, the `fs_accounts` option, shared by every Freemius-powered plugin or theme there (story 38's site gate, 2026-11-11). `docs/policies/privacy.md` gains one sentence after its deletion paragraph: that this record stays, what it can hold, and that the site owner can delete it once no other Freemius-powered product is installed. The plugin keeps leaving Freemius's record alone; the copy on postquality.com follows in the site repository as the owner's follow-up.

## Acceptance Criteria

1. **The policy says what deletion leaves, and claims no more than Freemius stores**
   **Given** `docs/policies/privacy.md`
   **When** a reader reaches the sentence on what deleting the plugin removes
   **Then** the sentence after it says that Freemius's record (`fs_accounts`) stays, that every Freemius-powered plugin or theme on the site shares it, what it can hold, and that the site owner can delete it once no other such product is installed
   **And** each thing it says the record holds is written by the vendored SDK in the case it names (personal data only for a site that opted in or activated a license), and nothing says the plugin or the SDK deletes it
2. **Dated and announced**
   **Given** the change
   **When** the policy and `docs/release-notes/unreleased.md` are read
   **Then** the policy's "Last updated" date is the day of the change, and `unreleased.md` carries one line announcing it, as the policy's section 5 promises

## Boundaries

- Must not change: what the plugin does at deletion (it does not touch Freemius's record: owner decision below); the rest of the policy; `readme.txt`'s privacy text and the in-plugin privacy guide (see the assumption below); any file in the postquality.com repository.

## References

- found by — _bmad-output/backlog/story-wordpress-org-directory-listing-plan.md, Implementation Notes "Site gate" (the six SDK options that stayed; the owner's 5a and 6b; why the SDK keeps its state)
- facts — memory/freemium-and-roles.md, "What a deletion leaves on the site"
- the policy — docs/policies/privacy.md (L29 the deletion sentence, L41 what an opt-in sends, § 5 Changes)
- copy rules — docs/brand.md (§ Copy Rules, § Naming)
- the published copy — _bmad-output/backlog/story-privacy-page-matches-the-policy.md (story 68: the page at postquality.com/privacy is a verbatim copy, "Last updated" date included)

## Notes

- Decision (owner, 2026-11-11, 6b): one sentence in the privacy policy says that deleting the plugin leaves the Freemius SDK's own record (`fs_accounts`, shared by every Freemius-powered product on the site), which the site owner can delete when no other such product is installed. Best practice: the plugin does not touch the SDK's store.
- Fact (SDK 2.13.5, `post-quality-manager/vendor/freemius/start.php` L18; read 2026-11-11; the paths below are relative to `post-quality-manager/vendor/freemius/`). What `fs_accounts` holds after an opt-in or a license activation:
  - `users`: `opt_in()` calls `install_with_new_user()` (`includes/class-freemius.php` L17307, L17684), which reads the Freemius user from the API (L17712); `handle_account_connection()` (L18330) stores it through `_set_account()` → `_store_account()` → `_store_user()` (L16767, L20211, L19953). `setup_user()` (L17617) does the same on the multi-install paths. That user carries `email`, `first` and `last` (`includes/entities/class-fs-user.php` L20, L24, L28), `is_verified`, `customer_id`, `gross`, and the account's API keys `public_key` and `secret_key` (`includes/entities/class-fs-scope-entity.php` L17, L21). It is the record Freemius's API returns for the account the site connected to (privacy policy L41 lists what an opt-in sends).
  - `sites`: the install record, stored by `_store_site()` (`includes/class-freemius.php` L19776): site address and title, versions, language, the user's id, plan and license ids, the install's keys (`includes/entities/class-fs-site.php` L17–L114).
  - Licenses: `_store_account()` also stores the site's licenses (L20217), each with its key (`includes/entities/class-fs-plugin-license.php` L83).
  - The deactivation answer: on a site that shares usage data, the reason chosen and up to 128 typed characters are stored (`includes/class-freemius.php` L2661–2672) and sent at deletion (L9742–9745), never removed by the SDK. The plugin removes them only on a site that does not share.
  - With the opt-in skipped (observed at story 38's site gate): only this plugin's entries (`plugin_data`, `plugins`, `file_slug_map`, the product id), no user and no install.
- Fact (same SDK): at deletion, `_uninstall_plugin_event()` sends the uninstall event of a connected site and deletes nothing (`includes/class-freemius.php` L9733, the `@todo` at L9773).
- Fact (same SDK): `fs_api_cache` (`config.php` L253) caches read replies (`FS_Api::get()`, `includes/class-fs-api.php` L311, L359), the user record included. Deactivation empties it (`includes/class-freemius.php` L8261, in the deactivation hook registered at L1513), and WordPress deletes only an inactive plugin, which matches the empty cache at story 38's site gate. It keeps data only when that hook returns early, for a user who is not an administrator (L8189). So the sentence need not name it.
- Fact (same SDK): when another Freemius-powered product stays active, its SDK's garbage collector, on an admin page load at most once a day, removes this plugin's entries from `fs_accounts` four weeks after the plugin's last load, then each user no install refers to (`includes/class-fs-garbage-collector.php` L217–233, L298–330; started from `includes/class-freemius.php` L1358–1378, L1490). With no other such product, nothing loads the SDK and the record stays.
- Assumption (wording, for the owner; the policy says "Freemius", never "SDK"): one sentence after L29: "Deleting the plugin leaves `fs_accounts`, the record Freemius keeps for every Freemius-powered plugin or theme on the site; after an opt-in or a license activation it holds the name and email address of the connected Freemius account, and you can delete it once no other Freemius-powered plugin or theme is installed."
- Open question: one sentence or two. The proposal runs about 55 words. Two sentences read easier (docs/brand.md: be concise): "Deleting the plugin leaves `fs_accounts`, the record Freemius keeps for every Freemius-powered plugin or theme on the site; after an opt-in or a license activation it holds the name and email address of the connected Freemius account. You can delete it once no other Freemius-powered plugin or theme is installed."
- Open question: whether the sentence also names the deactivation answer a sharing site gave (the policy's L41 already names "the reason you give"), the license key, and the account's API keys. The proposal names only the personal data.
- Open question: what `gross` and `customer_id` hold in a real record. The SDK does not show whether the API fills them; read one record of a connected site (story 38's sandbox license, on its clean site, within a walk's frame) before the wording is final.
- Assumption: the sentence does not describe the garbage collector. It depends on another vendor's product being active, and the site owner's deletion covers both cases.
- Assumption: `readme.txt`'s privacy text and the in-plugin privacy guide stay as they are. Neither says what deletion leaves of Freemius's record (the guide: results "until the plugin is deleted"; the readme: the n8n key "until you click Forget key or delete the plugin"), and a change to either ships only in a release.
- Assumption: `docs/release-notes/unreleased.md` is created if missing, so the line rides with the next release, 4.0.1 when this merges before its release PR.
- Handoff (owner, follow-up): the copy at postquality.com/privacy, in the postquality.com repository, takes the same sentence and the same "Last updated" date in a site pull request the owner publishes, as story 68 did. Policy pages change in this repository first (workspace rule).
- Assumption: risk low. One sentence in a document, reverted cleanly; criterion 1's second half is the check on its truth.
