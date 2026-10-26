# What one review costs in AI calls

_The worked cost example behind the FAQ on postquality.com (`/docs/#cost`), the home page's "Your own AI bill" item and the `readme.txt` FAQ. Written by backlog story 49 on 2026-10-24 from measured token counts; this file is the source, the copies carry its figures and the site's drift check (`_tools/check_cost_copy.py` in the site repository) proves they match. Re-run the script and update this page when the default model, the prices, the review's output cap or the template's prompt text change (the script stops when its transcription of the prompt is no longer in the template)._

## In one paragraph

You pay Anthropic per call, on your own API key, and your n8n hosting separately. A review sends the post text plus your settings text once. Measured 24 October 2026 on claude-sonnet-4-6, the default model, at Anthropic's list prices ($3 per million input tokens, $15 per million output tokens): one review costs at most $0.05 for a 600-word, a 1,300-word or a 2,500-word post, about 4 to 5 cents. One review per post per month: 20 posts cost $0.84 to $0.99, 80 posts $3.34 to $3.95. Prices change; check Anthropic's pricing page. The posts measured are fictional.

## Method

- **Model:** `claude-sonnet-4-6`, the plugin's default (`postquality_default_settings()['default_model']`). A site that picks another model in Settings pays that model's prices.
- **Requests:** the exact request the n8n template sends. The `Build Review Body` node of `post-quality-manager/templates/n8n-workflow.json` builds the system prompt (the reviewer instructions with the site's Website Context and Editorial Standards texts) and the user message (`Title`, `Excerpt`, `Guidelines`, `Full article`). The measurement uses the "Technical / IT Blog" profile text (the fixtures are tech posts) and the "Professional & Authoritative" preset instruction, no additional per-agent instructions. `bin/count-cost-example.py` reproduces that shape; it does not change the template.
- **Counts:** Anthropic's token-count endpoint (`POST /v1/messages/count_tokens`, free, no generation), which returns the input tokens a request would consume. Measured, not estimated. The script falls back to an estimate (characters ÷ 4) only when no key is found or the endpoint fails, and says so in its first line.
- **Output:** a review's output is capped at 2,500 tokens (`postquality_agent_max_tokens()['review']`). The price per review below charges the full cap, so it is an upper bound: a review's JSON is usually shorter, and the real cost is lower. Key Takeaways, the other free-tier agent, is capped at 1,000 tokens.
- **Posts:** three fictional tech-blog posts in [`cost-example/`](cost-example/), written for this measurement at the three lengths (about 600, 1,300 and 2,500 words); no real person, product or domain. The plugin sends the first 15,000 characters of a post's text; the long post, at 14,181 characters, is under that.
- **Prices:** Anthropic's list prices for Claude Sonnet 4.6, read on 24 October 2026 at https://platform.claude.com/docs/en/about-claude/pricing: $3 per million input tokens, $15 per million output tokens (the row "Claude Sonnet 4.6 | $3 / MTok | … | $15 / MTok"). Cache pricing, batch pricing and partner platforms are not applied; the workflow sends plain requests.

## The measurement (24 October 2026)

| Post | Words | Review: input tokens | Review: price, at most | Key Takeaways: input tokens | Key Takeaways: price, at most |
|---|---|---|---|---|---|
| Short ([`short.md`](cost-example/short.md)) | 626 | 1,389 | $0.0417 | 1,184 | $0.0186 |
| Average ([`average.md`](cost-example/average.md)) | 1,320 | 2,304 | $0.0444 | 2,393 | $0.0222 |
| Long ([`long.md`](cost-example/long.md)) | 2,518 | 3,941 | $0.0493 | 4,144 | $0.0274 |

Price per review = input tokens × $3 / 1,000,000 + 2,500 output tokens × $15 / 1,000,000. The output cap is most of the price ($0.0375 of each figure), which is why a post four times longer costs less than a cent more. Quoted figures are rounded up to the cent, so "at most" holds: every length is at most $0.05 a review.

**Per month, one review per post:** 20 posts cost $0.84 to $0.99, 80 posts $3.34 to $3.95 (the short post's unrounded price × the count to the long post's unrounded price × the count, each rounded up to the cent). A Key Takeaways run on top adds $0.02 to $0.03 a post. Automated Reviews (Pro) re-review posts on a schedule; each run is one more review at these prices.

**Skipped:** per-agent prices for the Pro agents (Rewrite, Sources, SEO, Audit): their output caps differ (1,000 to 8,000 tokens) and Rewrite sends the post's HTML. Add them when story 29 reports real usage.

## Limits

The plugin's **Monthly Quota** limits how many AI actions each Author runs a month; Editors and Administrators have no limit. It counts runs, not spend. To cap what you spend, set a spend limit in your Anthropic account: Anthropic stops serving requests at the limit, whatever the plugin asks for. Your n8n hosting is billed by its host, independent of the number of reviews.

## Prices change

Anthropic sets the prices and changes them without notice to this page. Before quoting the figures, re-read https://platform.claude.com/docs/en/about-claude/pricing (prices are also on claude.com/pricing) and, if a figure moved, re-run the script with the new prices and update the block below and the copies:

```
python -I bin/count-cost-example.py --prices 3,15 --figures "24 October 2026"
```

The script reads the key from `$ANTHROPIC_API_KEY` or from `../.secrets/anthropic-key.txt` beside the repository, never prints it, and sends the fixture text only.

## Figures

The copies (the site FAQ, the home page item, the readme FAQ) carry these values; `_tools/check_cost_copy.py` in the site repository reads this block and fails when a copy differs.

```cost-figures
model: claude-sonnet-4-6
measured: 24 October 2026
price_input: 3
price_output: 15
short: 0.05
average: 0.05
long: 0.05
month_20_low: 0.84
month_20_high: 0.99
month_80_low: 3.34
month_80_high: 3.95
```
