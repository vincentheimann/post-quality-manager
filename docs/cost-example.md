# What one review costs in AI calls

_The worked cost example behind the FAQ on postquality.com (`/docs/#cost`), the home page's "Your own AI bill" item and the `readme.txt` FAQ. Written by backlog story 49 on 2026-10-24 from measured token counts, re-measured by story 101 on 2026-11-04 for the new default model; this file is the source, the copies carry its figures and the site's drift check (`_tools/check_cost_copy.py` in the site repository) proves they match. Re-run the script and update this page when the default model, the prices, the review's output cap or the template's prompt text change (the script stops when its transcription of the prompt is no longer in the template)._

## In one paragraph

You pay Anthropic per call, on your own API key, and your n8n hosting separately. A review sends the post text plus your settings text once. Measured 4 November 2026 on claude-sonnet-5-5, the default model, at Anthropic's list prices ($2 per million input tokens, $10 per million output tokens): one review costs at most $0.03 for a 600-word post and at most $0.04 for a 1,300-word or a 2,500-word post, about 3 to 4 cents. One review per post per month: 20 posts cost $0.58 to $0.72, 80 posts $2.30 to $2.85. Prices change; check Anthropic's pricing page. The posts measured are fictional.

## Method

- **Model:** `claude-sonnet-5-5`, the plugin's default (`postquality_default_settings()['default_model']`). A site that picks another model in Settings pays that model's prices.
- **Requests:** the exact request the n8n template sends. The `Build Review Body` node of `post-quality-manager/templates/n8n-workflow.json` builds the system prompt (the reviewer instructions with the site's Website Context and Editorial Standards texts) and the user message (`Title`, `Excerpt`, `Guidelines`, `Full article`). The measurement uses the "Technical / IT Blog" profile text (the fixtures are tech posts) and the "Professional & Authoritative" preset instruction, no additional per-agent instructions. `bin/count-cost-example.py` reproduces that shape; it does not change the template.
- **Counts:** Anthropic's token-count endpoint (`POST /v1/messages/count_tokens`, free, no generation), which returns the input tokens a request would consume. Measured, not estimated. The script falls back to an estimate (characters ÷ 4) only when no key is found or the endpoint fails, and says so in its first line. Sonnet 5.5 uses Anthropic's newer tokenizer, which counts the same text as about a third more tokens than Sonnet 4.6 did (1,864 against 1,389 for the short post's review); its lower prices still make each review cheaper.
- **Output:** a review's output is capped at 2,500 tokens (`postquality_agent_max_tokens()['review']`). The price per review below charges the full cap, so it is an upper bound: a review's JSON is usually shorter, and the real cost is lower. Key Takeaways, the other free-tier agent, is capped at 1,000 tokens. The cap holds because the plugin turns thinking off on the default model at the default effort. A site that sets an agent's effort to `xhigh` or `max`, or picks Claude Opus 5.5 (which cannot turn thinking off), lets the model think: that agent's output budget grows by 12,000 tokens (`POSTQUALITY_THINKING_RESERVE`), and a review can cost more than these figures.
- **Posts:** three fictional tech-blog posts in [`cost-example/`](cost-example/), written for this measurement at the three lengths (about 600, 1,300 and 2,500 words); no real person, product or domain. The plugin sends the first 15,000 characters of a post's text; the long post, at 14,181 characters, is under that.
- **Prices:** Anthropic's list prices for Claude Sonnet 5.5, read on 4 November 2026 at https://platform.claude.com/docs/en/about-claude/pricing: $2 per million input tokens, $10 per million output tokens (the row "Claude Sonnet 5.5 | $2 / MTok | … | $10 / MTok"). Cache pricing, batch pricing and partner platforms are not applied; the workflow sends plain requests.

## The measurement (4 November 2026)

| Post | Words | Review: input tokens | Review: price, at most | Key Takeaways: input tokens | Key Takeaways: price, at most |
|---|---|---|---|---|---|
| Short ([`short.md`](cost-example/short.md)) | 626 | 1,864 | $0.0287 | 1,533 | $0.0131 |
| Average ([`average.md`](cost-example/average.md)) | 1,320 | 2,997 | $0.0310 | 2,997 | $0.0160 |
| Long ([`long.md`](cost-example/long.md)) | 2,518 | 5,287 | $0.0356 | 5,471 | $0.0209 |

Price per review = input tokens × $2 / 1,000,000 + 2,500 output tokens × $10 / 1,000,000. The output cap is most of the price ($0.025 of each figure), which is why a post four times longer costs less than a cent more. Quoted figures are rounded up to the cent, so "at most" holds: the short post is at most $0.03 a review, the average and long posts at most $0.04.

**Per month, one review per post:** 20 posts cost $0.58 to $0.72, 80 posts $2.30 to $2.85 (the short post's unrounded price × the count to the long post's unrounded price × the count, each rounded up to the cent). A Key Takeaways run on top adds $0.02 to $0.03 a post. Automated Reviews (Pro) re-review posts on a schedule; each run is one more review at these prices.

**Skipped:** per-agent prices for the Pro agents (Rewrite, Sources, SEO, Audit): their output caps differ (1,000 to 8,000 tokens) and Rewrite sends the post's HTML. Add them when story 29 reports real usage.

## Limits

The plugin's **Monthly Quota** limits how many AI actions each Author runs a month; Editors and Administrators have no limit. It counts runs, not spend. To cap what you spend, set a spend limit in your Anthropic account: Anthropic stops serving requests at the limit, whatever the plugin asks for. Your n8n hosting is billed by its host, independent of the number of reviews.

## Prices change

Anthropic sets the prices and changes them without notice to this page. Before quoting the figures, re-read https://platform.claude.com/docs/en/about-claude/pricing (prices are also on claude.com/pricing) and, if a figure moved, re-run the script with the new prices and update the block below and the copies:

```
python -I bin/count-cost-example.py --prices 2,10 --figures "4 November 2026"
```

The script reads the key from `$ANTHROPIC_API_KEY` or from `../.secrets/anthropic-key.txt` beside the repository, never prints it, and sends the fixture text only.

## Figures

The copies (the site FAQ, the home page item, the readme FAQ) carry these values; `_tools/check_cost_copy.py` in the site repository reads this block and fails when a copy differs.

```cost-figures
model: claude-sonnet-5-5
measured: 4 November 2026
price_input: 2
price_output: 10
short: 0.03
average: 0.04
long: 0.04
month_20_low: 0.58
month_20_high: 0.72
month_80_low: 2.30
month_80_high: 2.85
```
