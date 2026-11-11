=== Post Quality Manager ===
Contributors: postquality
Tags: content quality, ai, editorial, review
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Release-please: x-release-please-start-version
Stable tag: 4.0.0
Release-please: x-release-please-end
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Score every post, see what to fix next and keep your back-catalog sharp. AI content reviews, stored and tracked in WP Admin.

== Description ==

Post Quality Manager reviews your posts with AI and keeps the results in WP Admin: a score for every post, what to fix first, and the trend across your blog.

* Review: a score on four pillars (Structure, Readability, Engagement, Credibility) and the top edits to make.
* Key Takeaways: the post's main points as a list, ready to insert into the post.
* Dashboard: your average score, its trend and the posts to fix next.

The reviews run in your own n8n workflow with your own AI provider key. The plugin generates the workflow for your site, and a setup guide walks you through connecting it.

== Installation ==

You need your own n8n instance that can reach your site, and an Anthropic API key.

1. Install and activate the plugin.
2. Open Post Quality → n8n Workflow and click Set up the workflow. The setup guide asks for your n8n address and an n8n API key (create one for this site alone, with an expiration date), then for your Anthropic API key, once. It checks the key with Anthropic and passes it into an n8n credential without storing it, and creates the workflow and its credentials in n8n; then you run your first review from the guide. To set it up by hand instead, if your n8n's public API is off or out of reach or you prefer not to hand over a key, choose Set up by hand instead: the Set up by hand section of the same page walks you through importing the workflow, creating its credentials and publishing it, then takes the workflow's production webhook URL.
3. After the first review, the guide offers your Blog Profile and Editorial Standards. Change them any time under Post Quality → Settings.

== Frequently Asked Questions ==

= What does it cost me in AI calls? =

You pay Anthropic per call, on your own API key, and your n8n hosting separately. A review sends the post text plus your settings text once. Measured 4 November 2026 on claude-sonnet-5-5, the default model, at Anthropic's list prices ($2 per million input tokens, $10 per million output tokens): one review costs at most $0.03 for a 600-word post and at most $0.04 for a 1,300-word or a 2,500-word post, about 3 to 4 cents. One review per post per month: 20 posts cost $0.58 to $0.72, 80 posts $2.30 to $2.85. Prices change; check Anthropic's pricing page. The posts measured are fictional.

To cap what you spend, set a spend limit in your Anthropic account: the plugin's Monthly Quota only limits how many AI actions each Author runs (Editors and Administrators have no limit). You set the quota yourself under Post Quality → Settings → Usage & Limits: 20 a month by default, 0 for no limit.

= Does it work on a multisite network? =

No. The plugin is single-site only: multisite networks are not supported.

= What does Free include, and what does Pro add? =

Free includes Review and Key Takeaways, the Dashboard, Post Reviews and Insert into Post. Pro adds the Rewrite, Sources, SEO and Audit agents, per-agent model, effort and instructions, Automated Reviews on a schedule and the Roles tab. See the [plans and prices](https://postquality.com/pricing/).

= Where is the source code? =

The admin app ships built, in `admin/build/`. Its source and the build steps are in the [GitHub repository](https://github.com/vincentheimann/post-quality-manager).

== External services ==

Post content goes only to your n8n instance and, through it, to Anthropic; nothing goes to them before you enter a webhook address or connect an n8n instance. Pro licensing and the optional usage data go to Freemius (below); nothing goes there before you opt in or enter a license key.

**Your n8n instance.** When you run a command on a post, or a schedule you set on Pro runs one, the plugin calls your n8n webhook with the post's title, excerpt, content and link, the command, your site's callback address, your notification address if you set one, the settings text you wrote in the plugin (Editorial Standards, Website Context, per-agent instructions, model choice), the webhook token, and the post's Yoast SEO fields when Yoast is active. Your workflow can read the same post data through the plugin's REST API and writes the result back. A connection test sends the address of a one-time callback on this site and the webhook token, no post data, and your workflow calls that address back once. You run your own n8n instance; n8n's [terms](https://n8n.io/legal/) and [privacy policy](https://n8n.io/legal/privacy/).

**Your n8n instance's API, if you connect it by key.** With an n8n API key entered on the n8n Workflow page or in the setup guide, the plugin calls your instance's public API only when you act, and names itself in the user agent of every call. Connect and Check again list one workflow and check that two endpoints exist. A test ping reads the workflow at your webhook address to check which credentials its nodes use. Setting the workflow up creates it and its credentials (the webhook token, your Anthropic API key and, on Pro, your WordPress username with a new Application Password for Rewrite) and activates it; if that fails, the plugin removes what it created, and when n8n does not answer the workflow's creation in time, it lists the workflows of that name (a read, nothing changed) to find and remove the one it made. Managing a workflow you set up by hand reads your active workflows and stores the IDs of that workflow and its credentials. Rotating the webhook token writes the new token into the webhook credential. Updating the workflow writes the plugin's newer template onto it, keeping its credentials and webhook address; on Pro, a workflow without a WordPress credential (set up on the Free version) first gets one, with a new Application Password of the administrator who clicks. Nothing else on n8n changes. The key stays in your site's database until you click Forget key or delete the plugin, the address until the plugin is deleted; use a key created for this site alone, with an expiration date.

**Anthropic, your AI provider.** The workflow sends the post text and your settings text to Anthropic's API with the key stored in your n8n credential. When the setup guide sets the workflow up, the plugin forwards the Anthropic API key you type once: one request from your site to api.anthropic.com checks it (it lists one model, with no post content), then the key goes into the n8n credential. The plugin never stores it. Anthropic's [terms](https://www.anthropic.com/legal/commercial-terms) and [privacy policy](https://www.anthropic.com/legal/privacy).

**Your notification address (optional).** If you enter a Notification URL under Post Quality → Settings → Connection, the workflow posts a short report there each time a command finishes: the post's title and link with the result (scores, edits or takeaways), or the error. You choose the service behind that address, such as a chat webhook; its own terms and privacy policy apply.

**Freemius (Pro licenses and optional usage data).** Pro licenses are sold and checked through Freemius; with a license key entered, your site's address, the plugin version and the key are sent to verify it. On activation the plugin asks whether to share usage data with the author through Freemius: the first and last name and email address of the WordPress user who opts in (always included), the site address and title, WordPress, PHP and plugin versions, site language, activation state and deactivation reason, and a list of the site's plugins and themes (names, slugs, versions, active or not; on by default when opting in, optional when activating a license). This is off until you opt in, the screen can be skipped (a site that skipped it sees no feedback form at deactivation and sends nothing when the plugin is deactivated or deleted; a site that no longer shares usage data sees no form either and sends no feedback answer when the plugin is deleted, though Freemius may still receive technical updates about the plugin's installation and any details you left on in the opt-out window; if the plugin's menu shows an Account page and the site has no Pro license, Disconnect there deletes the record Freemius holds for the site), and the free features do not depend on it. Freemius's [terms](https://freemius.com/terms/) and [privacy policy](https://freemius.com/privacy/).

Read the full [privacy policy](https://postquality.com/privacy).

== Screenshots ==

1. Post Reviews: every post with its latest score. Click Review on a post to send it to your workflow. Demo posts, fictional scores.
2. The review: the score on four pillars and the top edits, back in WP Admin. Fictional post.
3. The Dashboard: Fix next ranks the posts to work on first. Demo posts, fictional scores.

== Changelog ==

= 4.0.0 =
* First release on WordPress.org: AI reviews and Key Takeaways for every post, scored and tracked in WP Admin through your own n8n workflow.

Every release with its notes is on [GitHub Releases](https://github.com/vincentheimann/post-quality-manager/releases).
