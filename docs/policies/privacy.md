# Privacy policy

_Post Quality Manager, a WordPress plugin by Vincent Heimann. Last updated 2026-11-04. Published at https://postquality.com/privacy; questions to hello@postquality.com._

This policy covers three things: the plugin running on your WordPress site, the Pro licensing service, and the website postquality.com. Short version: the plugin sends your post text to **your own** n8n instance and the AI provider **you** configured; the plugin's author receives nothing from your site unless you opt in to usage data or buy a Pro license, not even when you deactivate or delete the plugin. After an opt-out, Freemius may still receive the technical updates described under Optional usage data.

## 1. The plugin on your site

### What the plugin sends, and to whom

When you (or a schedule you set) run a command on a post, the plugin calls your n8n workflow's webhook address: the one you entered, or the one the plugin saved when it set the workflow up for you. Your n8n workflow then reads the post through the plugin's REST API and calls the AI provider you configured in n8n. The data that leaves your site is:

- **To your n8n instance:** the post's ID, title, slug, link, excerpt and content (text and HTML), the command, your site's addresses (the callback and, if you set one, a notification address), the settings text you wrote in the plugin (Editorial Standards or Brand Voice, Website Context, per-agent instructions, the chosen model and its options), the webhook token that lets n8n write results back, and, when Yoast SEO is active, the post's SEO score, readability score, focus keyphrase, meta description and SEO title. Your workflow can read the same post data again through the plugin's REST API. A test of the connection (Test on the n8n Workflow page, the setup guide's connect and set-up steps, a token rotation or a workflow update) sends your n8n instance the address of a one-time callback on this site's REST API and the webhook token, no post data; your workflow calls that address back once, so the test proves both directions.
- **To your n8n instance's API, if you connect it by key:** when you click Connect or Check again in the n8n Instance section of the n8n Workflow page, or in the setup guide's Connect your n8n step, the plugin calls the address you entered: it lists one workflow with your n8n API key and checks that two API endpoints exist, using IDs that match nothing, so nothing on n8n is created, changed or deleted. When you send a test ping with an n8n API key stored, the plugin also reads the workflow at your webhook address through n8n's API, to check which credentials its nodes use; nothing on n8n changes. When you let the setup guide set the workflow up, the plugin uses the same key to create the workflow and its credentials in n8n and to activate the workflow. The credentials hold the webhook token, your Anthropic API key and, on Pro, your WordPress username with a new Application Password that lets n8n save Rewrite results. If that setup fails, the plugin deactivates and removes the workflow and credentials it created (and, on Pro, revokes that Application Password); anything n8n would not let it remove is removed at your next setup. When n8n does not answer the workflow's creation in time, the plugin reads your workflows that carry that workflow's name, to find it and remove it if n8n created it all the same; if n8n does not list it yet, your next setup reads that list again first. These reads change nothing on n8n. When you let the plugin manage a workflow you set up by hand, it reads your active workflows with the same key to find the one at your webhook address and the credentials its nodes use (their names and IDs, never their contents), and changes nothing on n8n. When you rotate the webhook token, the plugin writes the new token into the webhook credential in n8n with the same key, and changes nothing else there. When you update the workflow to the plugin's newer template, the plugin reads that workflow and writes the new template onto it with the same key, keeping its credentials and its webhook address; on Pro, a workflow without a WordPress credential (one set up on the Free version, for example) first gets one, holding your WordPress username and a new Application Password, as the setup makes it, and that password is revoked again if n8n refuses the credential. It changes nothing else there. Every call names the plugin in its user agent. Nothing is called when the page loads.
- **To Anthropic, once, during setup:** when the setup guide sets the workflow up, the Anthropic API key you type is forwarded once: one request from your site to api.anthropic.com checks it (it lists one model and carries no post content), then the key goes into the n8n credential above. The plugin never stores it: not in your site's database, not in a log, not in WP Admin.
- **From your n8n instance to the AI provider:** whatever your workflow sends, by default the post text and the settings text above. The provider's own terms and privacy policy apply to that call; you hold the account and the API key, which lives in your n8n credential.
- **To your notification address, if you set one:** the workflow posts a short report there each time a command finishes: the post's title and link with the result, or the error. You choose the service behind that address, such as a chat webhook, and its own terms and privacy policy apply.

Nothing is sent before you configure a webhook address or connect an n8n instance, and nothing is sent to the plugin's author.

### What the plugin stores on your site

- A table of review results (post ID, command, score, status, the AI output, the date), kept until you delete the plugin.
- The plugin settings, including your n8n webhook address and token and your settings text.
- If you connect your n8n instance by key: its address, the n8n API key and the result of the last check; once the plugin has set the workflow up, the IDs of the workflow and credentials it created there or that you let it manage, so that rotating the webhook token writes the new one into that webhook credential and updating the workflow writes the new template onto that workflow. The key is kept in your site's database, as WordPress keeps other secrets, and is never sent anywhere but that address; only its last four characters are shown in WP Admin, so you can tell which key is stored. **Forget key** on the n8n Workflow page removes it. An n8n API key opens your whole n8n instance, so create one for this site alone, with an expiration date, and delete it in n8n when you stop using the plugin.
- A monthly counter of AI actions per user, used for the Author quota.
- Per-role plugin capabilities, stored in your site's roles.

Deleting the plugin (not just deactivating it) removes the table, the settings (the n8n address and API key included), the counters and the capabilities.

### Visitors to your site

The plugin runs in WP Admin only. It sets no cookies, loads nothing from third parties on your public pages and collects nothing about your visitors.

## 2. Pro licensing (Freemius)

Pro licenses are sold and managed by Freemius, Inc., which acts as the seller of record. When you buy, Freemius collects your name, email, billing address and payment details under its own privacy policy (https://freemius.com/privacy/). The plugin's author receives your name, email and the license details, used only to provide support and updates.

With a license key entered, the plugin checks the license with Freemius: your site's address, the plugin version and the license key are sent for that check. Activating a license also connects the site to your Freemius account, which sends the details listed below; only the plugins-and-themes list is optional there.

**Optional usage data.** On activation the plugin asks whether you want to share usage data: the first and last name and email address of the WordPress user who opts in (always included), the site address and title, WordPress, PHP and plugin versions, site language, active or inactive state, the reason you give if you deactivate, and a list of the site's plugins and themes (names, slugs, versions, active or not), which is on by default and can be unticked on that screen. This is off until you say yes, the screen can be skipped, you can change it at any time with the Opt In or Opt Out link under the plugin on the Plugins screen, and the free features work the same either way. The author uses it to count installs and understand why people leave. A site that did not opt in sees no feedback form when the plugin is deactivated and sends nothing then or when it is deleted. A site that no longer shares usage data sees no feedback form and sends no feedback answer when the plugin is deleted. What else stops depends on what you switch off in the opt-out window: Freemius may still receive technical updates about the plugin's installation on the site, such as its version, the site address and whether the plugin is active, and any of the details above that you left on. If the plugin's menu shows an Account page and the site has no Pro license, Disconnect there deletes the record Freemius holds for the site.

## 3. The website postquality.com

The website is static and hosted on GitHub Pages, a service of GitHub, Inc. It sets no cookies and runs no analytics. GitHub logs and stores each visitor's IP address for security purposes, under its own privacy statement (https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement). Writing to hello@postquality.com shares your email address and message, kept as long as needed to answer you. An email asking to hear about the launch is kept until that one message is sent, then deleted.

## 4. Your rights

You can see and delete the data the plugin stores on your site yourself; it is on your server. For data held by Freemius, use the customer portal or contact Freemius. For emails and license details held by the author, write to hello@postquality.com to ask what is held, to correct it or to have it deleted.

## 5. Changes

Changes to this policy are dated at the top and announced in the plugin's release notes.
