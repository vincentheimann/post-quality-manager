## Upgrading from an earlier version

These changes reach a site that ran an earlier version of Post Quality Manager without any prompt in WP Admin.

**Authors have a monthly limit.** Users without the "Run paid agents" right get 20 AI actions a month; 0 means no limit. Change it under Settings → Usage & Limits → Monthly Quota → AI Actions per User.

**Role rights stay as you set them.** Each right goes to its default roles once; what you change afterwards survives deactivation, reactivation and updates. Set them under Settings → Roles (Pro) or with a role editor plugin. The "View as" toolbar switch of the 3.0 and 3.1 test builds is gone; the Roles tab replaces it.

**Re-import your n8n workflow.** Send a test ping from the n8n Workflow page; it says "Workflow update available" when yours is older. Until you re-import, an old workflow's trigger does not check that a call comes from your site, and a workflow imported before July 2026 still needs its WordPress user to hold "View reviews". The new import uses the Post Quality webhook credential (Header Auth with your webhook token), as the setup guide shows.

**Pro needs a license bought at postquality.com.** A key typed under Settings → License during the test builds no longer unlocks Pro, and the License Key field is gone. Buy Pro at https://postquality.com/pricing, install the Pro version you download after checkout and activate your key on the license screen it shows, or later with the "Activate License" link under the plugin on the Plugins screen (an Account page joins the plugin's menu once you opt in or activate a license). On activation, Post Quality asks whether to share your name, email address and site details with Freemius, the licensing service; skip it and the plugin works the same.

**The setup guide now continues past the first review.** Reopen it from the n8n Workflow page ("Setup guide"): after the first review it offers your Blog Profile, your Editorial Standards and scheduled reviews (Pro), one step each, every one skippable. A site set up earlier sees these three steps open; nothing changes until you save one, and skipping leaves your settings as they are.
