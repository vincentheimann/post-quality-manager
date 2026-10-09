# Privacy policy

_Post Quality Manager, a WordPress plugin by Vincent Heimann. Last updated 2026-10-09. Published at https://postquality.com/privacy; questions to hello@postquality.com._

This policy covers three things: the plugin running on your WordPress site, the Pro licensing service, and the website postquality.com. Short version: the plugin sends your post text to **your own** n8n instance and the AI provider **you** configured; the plugin's author receives nothing from your site unless you opt in to usage data or buy a Pro license.

## 1. The plugin on your site

### What the plugin sends, and to whom

When you (or a schedule you set) run a command on a post, the plugin calls the n8n webhook address you entered in its settings. Your n8n workflow then reads the post through the plugin's REST API and calls the AI provider you configured in n8n. The data that leaves your site is:

- **To your n8n instance:** the post's ID, title, slug, link, excerpt and content (text and HTML), the command, your site's addresses (the callback and, if you set one, a notification address), the settings text you wrote in the plugin (Editorial Standards or Brand Voice, Website Context, per-agent instructions, the chosen model and its options), the webhook token that lets n8n write results back, and, when Yoast SEO is active, the post's SEO score, readability score, focus keyphrase, meta description and SEO title. Your workflow can read the same post data again through the plugin's REST API.
- **From your n8n instance to the AI provider:** whatever your workflow sends, by default the post text and the settings text above. The provider's own terms and privacy policy apply to that call; you hold the account and the API key, the plugin never sees the key.

Nothing is sent before you configure a webhook address, and nothing is sent to the plugin's author.

### What the plugin stores on your site

- A table of review results (post ID, command, score, status, the AI output, the date), kept until you delete the plugin.
- The plugin settings, including your n8n webhook address and token and your settings text.
- A monthly counter of AI actions per user, used for the Author quota.
- Per-role plugin capabilities, stored in your site's roles.

Deleting the plugin (not just deactivating it) removes the table, the settings, the counters and the capabilities.

### Visitors to your site

The plugin runs in WP Admin only. It sets no cookies, loads nothing from third parties on your public pages and collects nothing about your visitors.

## 2. Pro licensing (Freemius)

Pro licenses are sold and managed by Freemius, Inc., which acts as the seller of record. When you buy, Freemius collects your name, email, billing address and payment details under its own privacy policy (https://freemius.com/privacy/). The plugin's author receives your name, email and the license details, used only to provide support and updates.

With a license key entered, the plugin checks the license with Freemius: your site's address, the plugin version and the license key are sent for that check. Activating a license also connects the site to your Freemius account, which sends the details listed below; only the plugins-and-themes list is optional there.

**Optional usage data.** On activation the plugin asks whether you want to share usage data: the first and last name and email address of the WordPress user who opts in (always included), the site address and title, WordPress, PHP and plugin versions, site language, active or inactive state, the reason you give if you deactivate, and a list of the site's plugins and themes (names, slugs, versions, active or not), which is on by default and can be unticked on that screen. This is off until you say yes, the screen can be skipped, you can change it at any time under the plugin's settings, and the free features work the same either way. The author uses it to count installs and understand why people leave.

## 3. The website postquality.com

The website is static. It sets no cookies and runs no analytics. Writing to hello@postquality.com shares your email address and message, kept as long as needed to answer you.

## 4. Your rights

You can see and delete the data the plugin stores on your site yourself; it is on your server. For data held by Freemius, use the customer portal or contact Freemius. For emails and license details held by the author, write to hello@postquality.com to ask what is held, to correct it or to have it deleted.

## 5. Changes

Changes to this policy are dated at the top and announced in the plugin's release notes.
