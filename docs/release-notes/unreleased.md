## Upgrading from an earlier version

These changes reach a site that ran an earlier version of Post Quality Manager without any prompt in WP Admin.

**Authors have a monthly limit.** Users without the "Run paid agents" right get 20 AI actions a month; 0 means no limit. Change it under Settings → Usage & Limits → Monthly Quota → AI Actions per User.

**Role rights stay as you set them.** Each right goes to its default roles once; what you change afterwards survives deactivation, reactivation and updates. Set them under Settings → Roles (Pro) or with a role editor plugin. The "View as" toolbar switch of the 3.0 and 3.1 test builds is gone; the Roles tab replaces it.

**Re-import your n8n workflow.** Send a test ping from the n8n Workflow page; it says "Workflow update available" when yours is older. Until you re-import, an old workflow's trigger does not check that a call comes from your site, and a workflow imported before July 2026 still needs its WordPress user to hold "View reviews". The new import uses the Post Quality webhook credential (Header Auth with your webhook token), as the Workflow File section of the n8n Workflow page shows.

**A re-imported workflow has a new production URL.** Each site's workflow now listens on its own webhook path, so several sites can share one n8n instance. When the n8n Workflow page says "Workflow update available", re-import the workflow, then copy the production URL from its Webhook Trigger and paste it as the n8n Webhook URL in Settings. Until you do, the plugin keeps calling your current workflow.

**Pro needs a license bought at postquality.com.** A key typed under Settings → License during the test builds no longer unlocks Pro, and the License Key field is gone. Buy Pro at https://postquality.com/pricing, install the Pro version you download after checkout and activate your key on the license screen it shows, or later with the "Activate License" link under the plugin on the Plugins screen (an Account page joins the plugin's menu once you opt in or activate a license). On activation, Post Quality asks whether to share your name, email address and site details with Freemius, the licensing service; skip it and the plugin works the same.

**The setup guide now continues past the first review.** Reopen it from the n8n Workflow page ("Setup guide"): after the first review it offers your Blog Profile, your Editorial Standards and scheduled reviews (Pro), one step each, every one skippable. A site set up earlier sees these three steps open; nothing changes until you save one, and skipping leaves your settings as they are.

**The setup guide now sets n8n up for you.** It connects your n8n with an n8n API key, asks once for your Anthropic API key and creates the workflow and its credentials in n8n itself; the key is passed on, never stored. The step-by-step manual setup now lives in the Workflow File section of the n8n Workflow page, where "Set up by hand instead" in the guide leads. A site whose workflow was set up by hand keeps that path: its guide shows one n8n step, Set up by hand, done.

**A test ping also checks your workflow's credentials.** With an n8n API key stored on the n8n Workflow page, each test ping also reads your workflow in n8n, without changing it. When a Claude node carries the webhook credential, or a node has no credential, the page says which nodes and how to fix them; fix them in n8n, publish the workflow, then press Test again.

**The plugin can take over a workflow you set up by hand.** With an n8n API key connected and your webhook URL saved, the n8n Instance section of the n8n Workflow page offers Let the plugin manage this workflow: it finds the workflow at your webhook URL and shows the credentials its nodes use, by name. Once you confirm, the plugin remembers that workflow and its credentials, and the setup guide shows its n8n steps done. Nothing in n8n changes.

**Rotate the webhook token in one click.** On a site whose workflow the plugin set up or manages, the n8n Instance section of the n8n Workflow page offers Rotate the webhook token: the plugin writes a new token into the webhook credential in n8n, saves it here and sends a test ping, so the old token stops working at once. A workflow set up by hand, without the plugin managing it, keeps its token; let the plugin manage that workflow to rotate it from here.

**Update the workflow in one click.** On a site whose workflow the plugin set up or manages, "Workflow update available" on the n8n Workflow page now ends with Update workflow: the plugin writes the newer template onto your workflow in n8n, keeps its credentials and its production URL, sends a test ping and shows the new version. No re-import, nothing to paste. A workflow set up by hand, without the plugin managing it, keeps the Re-import the workflow steps (and the paragraph above on its new production URL); let the plugin manage that workflow to update it from here.

**The menu shows the plugin's own icon.** The plugin's entry in the admin menu carries the Q-and-check mark instead of the generic chart icon; nothing else changes.

**Post Quality Manager now comes as a Free and a Pro build.** The Free version, the one WordPress.org will host, contains Review and Key Takeaways and nothing else: no Pro settings, and an n8n workflow without the Sources, SEO, Audit and Rewrite branches. Pro is the version you download after checkout and holds everything, as before. If a Pro site imported the Free workflow, the n8n Workflow page says so: download the workflow there and re-import it. To move from Free to Pro, upload the Pro version you downloaded after checkout as a new plugin and activate it; the Free copy is switched off for you, and your settings and reviews stay.
