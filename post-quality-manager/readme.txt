=== Post Quality Manager ===
Contributors: postquality
Tags: content quality, ai, editorial, review
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Release-please: x-release-please-start-version
Stable tag: 3.9.0
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

== Frequently Asked Questions ==

= What does it cost me in AI calls? =

You pay Anthropic per call, on your own API key, and your n8n hosting separately. A review sends the post text plus your settings text once. Measured 24 October 2026 on claude-sonnet-4-6, the default model, at Anthropic's list prices ($3 per million input tokens, $15 per million output tokens): one review costs at most $0.05 for a 600-word, a 1,300-word or a 2,500-word post, about 4 to 5 cents. One review per post per month: 20 posts cost $0.84 to $0.99, 80 posts $3.34 to $3.95. Prices change; check Anthropic's pricing page. The posts measured are fictional.

To cap what you spend, set a spend limit in your Anthropic account: the plugin's Monthly Quota only limits how many AI actions each Author runs (Editors and Administrators have no limit).

== Screenshots ==

1. Post Reviews: every post with its latest score. Click Review on a post to send it to your workflow. Demo posts, fictional scores.
2. The review: the score on four pillars, the issues and the top edits, back in WP Admin. Fictional post.
3. The Dashboard: Fix next ranks the posts to work on first. Demo posts, fictional scores.
