#!/usr/bin/env python3
"""Measures the token counts behind docs/cost-example.md (backlog story 49).

    python -I bin/count-cost-example.py [--prices IN,OUT] [--model MODEL] [--estimate] [--figures DATE]

Builds, for each fixture post in docs/cost-example/, the same request the n8n
template's `Build Review Body` node sends to Anthropic (the reviewer system
prompt with the "Technical / IT Blog" profile text and the "Professional &
Authoritative" preset instruction, then the user message `Title / Excerpt /
Guidelines / Full article`), and the `Build Takeaways Body` request (the
takeaways system prompt, then `Title / Raw HTML`). It POSTs each one to
Anthropic's free token-count endpoint and prints the input token counts.

The prompt text is transcribed from the template. At start-up the script
checks that each transcribed segment is still in the two nodes' code
(post-quality-manager/templates/n8n-workflow.json) and stops with a message
when it is not: update the transcription, then re-measure.

The key comes from $ANTHROPIC_API_KEY or, failing that, from the private
workspace folder `../.secrets/anthropic-key.txt` beside this repository. It is
read at run time and never printed, logged or written anywhere. The request
carries the fictional fixture text and nothing else.

--prices IN,OUT turns the counts into USD at IN and OUT dollars per million
tokens: one review per post (input tokens at IN plus the review's output cap
of 2,500 tokens at OUT, so an upper bound), then 20 and 80 posts a month at
one review per post. Every quoted price is rounded up to the cent, so "at
most" holds. --figures DATE also prints the fenced `cost-figures` block that
docs/cost-example.md carries and the site's drift check reads. --estimate
skips the endpoint and estimates characters / 4; the script does the same,
and says so, when no key can be found or the endpoint fails.

Standard library only (urllib, json). Exit 0 when every count was measured
(or --estimate was asked for), 2 when it had to fall back to an estimate.
"""
from __future__ import annotations

import argparse
import json
import math
import os
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
FIXTURES = REPO / "docs" / "cost-example"
TEMPLATE = REPO / "post-quality-manager" / "templates" / "n8n-workflow.json"
ORDER = ("short", "average", "long")
ENDPOINT = "https://api.anthropic.com/v1/messages/count_tokens"
DEFAULT_MODEL = "claude-sonnet-4-6"  # pqm_default_settings()['default_model']
REVIEW_OUTPUT_CAP = 2500  # pqm_agent_max_tokens()['review']
TAKEAWAYS_OUTPUT_CAP = 1000  # pqm_agent_max_tokens()['takeaways']

# PQM_Admin::blog_profiles()['technical']['website_context']
WEBSITE_CONTEXT = (
    "A technical blog for software developers and IT professionals. Readers expect accurate, in-depth content: "
    "working code examples, references to official documentation, and honest trade-off discussions. Domain jargon "
    "is fine; marketing fluff is not."
)
# pqm_voice_presets()['professional']['instruction']
BRAND_VOICE = (
    "Professional and authoritative: precise, confident wording; active voice; back claims with evidence; no hype "
    "or filler."
)

# The static segments of the `system` string of the template's Build Review Body node, between its dynamic parts
# (website_context, brand_voice, the optional additional instructions). check_template() proves each one is still
# in the node's JS source before anything is counted.
REVIEW_SEGMENTS = (
    "You are a Senior Content Reviewer for ",
    ".\n\nTONE & VOICE RULES:\n",
    "\n\nAGNOSTIC SCORING FRAMEWORK (Score each 0-100):\n"
    "1. Structure: Logical flow, clear hierarchy (H2/H3s), and coherent pacing.\n"
    "2. Readability: Accessibility, concise sentences, avoiding heavy jargon.\n"
    "3. Engagement: The hook, emotional resonance, preventing drop-offs, and actionable value.\n"
    "4. Credibility (E-E-A-T): Trustworthiness, evidence-backed claims, and expert tone.\n\n"
    "SCORING CALIBRATION:\nRubric (apply to every 0-100 score):\n"
    "  90-100  Exceptional (publish-ready, top 1%)\n"
    "  75-89   Strong (great but needs minor tweaks)\n"
    "  60-74   Decent (average, needs better flow/hooks)\n"
    "  40-59   Weak (poorly structured, unsupported claims)\n"
    "  0-39    Poor (unpublishable draft quality)\n\n"
    "Status (must match overall_score):\n"
    '  "ship"            -> overall_score >= 71\n'
    '  "needs_revision"  -> overall_score 41-70\n'
    '  "major_revision"  -> overall_score <= 40\n\n\n',
    "Return ONLY valid JSON, no markdown, no explanation:\n{\n"
    '  "overall_score": 0-100,\n'
    '  "status": "ship | needs_revision | major_revision",\n'
    '  "structure": { "score": 0-100, "issues": [], "suggestions": [] },\n'
    '  "readability": { "score": 0-100, "issues": [], "suggestions": [] },\n'
    '  "engagement": { "score": 0-100, "issues": [], "suggestions": [] },\n'
    '  "credibility": { "score": 0-100, "issues": [], "suggestions": [] },\n'
    '  "top_edits": ["edit 1", "edit 2", "edit 3"]\n}\n'
    "Limit issues and suggestions to 3 per dimension. top_edits must contain exactly 3 items.",
)
# The node's template literal for the user message, as it reads in the JS source (escaped newlines).
REVIEW_USER_SOURCE = (
    "Title: ${d.title}\\n\\nExcerpt: ${d.excerpt}\\n\\nGuidelines:\\n${guidelines}\\n\\nFull article:\\n${d.content}"
)

# The same for Build Takeaways Body: two static segments around the optional additional instructions.
TAKEAWAYS_SEGMENTS = (
    "You are a Key Takeaways reviewer for a blog.\n\nKEY TAKEAWAYS BLOCK RULES:\n- Must exist in the article\n"
    "- Must be the very first element\n- Must contain 3 to 5 items\n"
    "- Each item: informative, specific, declarative, 25 words or fewer\n- Items must not repeat each other\n\n"
    'FEW-SHOT EXAMPLES:\nBad: "The article discusses the importance of sleep."\n'
    'Good: "Prioritize 8 hours of sleep to improve cognitive function by 20%."\n\n\n',
    'Return ONLY valid JSON:\n{\n  "block_found": true,\n  "position_correct": true,\n  "position_note": "...",\n'
    '  "count": 0,\n  "count_ok": true,\n  "items_within_word_limit": true,\n  "items_are_specific": true,\n'
    '  "issues": [],\n  "current_items": [],\n  "rewritten_items": []\n}\n'
    "Limit issues to 3. Always provide rewritten_items (3-5 items).",
)
TAKEAWAYS_USER_SOURCE = "Title: ${d.title}\\n\\nRaw HTML:\\n${d.content_html}"
TAKEAWAYS_SYSTEM = TAKEAWAYS_SEGMENTS[0] + "" + TAKEAWAYS_SEGMENTS[1]


def review_system(website_context: str, brand_voice: str) -> str:
    """The `system` string of the template's Build Review Body node, no additional instructions."""
    s = REVIEW_SEGMENTS
    return s[0] + website_context + s[1] + brand_voice + s[2] + "" + s[3]


def check_template() -> None:
    """Stops when the transcribed prompt text is no longer in the template's node code (containment only)."""
    nodes = {n["name"]: n for n in json.loads(TEMPLATE.read_text(encoding="utf-8"))["nodes"]}
    for node, segments, user in (("Build Review Body", REVIEW_SEGMENTS, REVIEW_USER_SOURCE),
                                 ("Build Takeaways Body", TAKEAWAYS_SEGMENTS, TAKEAWAYS_USER_SOURCE)):
        if node not in nodes:
            raise SystemExit(f"{TEMPLATE.name}: no node named {node!r}; update this script to the template")
        code = nodes[node]["parameters"].get("jsCode", "")
        for seg in segments:
            escaped = json.dumps(seg)[1:-1]  # the segment as it is escaped inside the node's JS string literal
            if escaped not in code:
                raise SystemExit(f"{TEMPLATE.name}, node {node!r}: this transcribed prompt segment is no longer in "
                                 f"the node's code; update the script to the template, then re-measure:\n"
                                 f"  {seg[:70]!r}...")
        if user not in code:
            raise SystemExit(f"{TEMPLATE.name}, node {node!r}: the user message is no longer built as {user!r}; "
                             "update the script to the template, then re-measure")


def read_fixture(path: Path) -> dict:
    text = path.read_text(encoding="utf-8")
    m = re.match(r"---\n(.*?)\n---\n(.*)\Z", text, re.S)
    if not m:
        raise SystemExit(f"{path.name}: no front matter")
    meta = {}
    for line in m.group(1).splitlines():
        k, _, v = line.partition(":")
        meta[k.strip()] = v.strip().strip('"')
    body = m.group(2).strip("\n")
    return {"name": path.stem, "title": meta["title"], "excerpt": meta["excerpt"], "body": body}


def to_plain(md: str) -> str:
    """What the plugin sends as `content`: the post's text without tags (wp_strip_all_tags)."""
    out = []
    for line in md.splitlines():
        if line.startswith("```"):
            continue
        line = re.sub(r"^#{1,6}\s+", "", line)
        line = line.replace("**", "").replace("`", "")
        out.append(line)
    return "\n".join(out).strip()


def to_html(md: str) -> str:
    """What the plugin sends as `content_html`: a plain rendering of the fixture's Markdown."""
    html, para, in_code, in_list = [], [], False, None

    def flush():
        nonlocal para, in_list
        if para:
            html.append("<p>" + inline(" ".join(para)) + "</p>")
            para = []
        if in_list:
            html.append("</%s>" % in_list)
            in_list = None

    def inline(s: str) -> str:
        s = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", s)
        return re.sub(r"`([^`]+)`", r"<code>\1</code>", s)

    for line in md.splitlines():
        if line.startswith("```"):
            flush()
            html.append("<pre><code>" if not in_code else "</code></pre>")
            in_code = not in_code
            continue
        if in_code:
            html.append(line)
            continue
        h = re.match(r"^(#{1,6})\s+(.*)", line)
        if h:
            flush()
            html.append("<h%d>%s</h%d>" % (len(h.group(1)), inline(h.group(2)), len(h.group(1))))
            continue
        li = re.match(r"^(\d+\.|-)\s+(.*)", line)
        if li:
            tag = "ol" if li.group(1) != "-" else "ul"
            if in_list != tag:
                flush()
                in_list = tag
                html.append("<%s>" % tag)
            html.append("<li>" + inline(li.group(2)) + "</li>")
            continue
        if not line.strip():
            flush()
            continue
        para.append(line.strip())
    flush()
    return "\n".join(html)


def review_request(post: dict, model: str) -> dict:
    user = "Title: %s\n\nExcerpt: %s\n\nGuidelines:\n%s\n\nFull article:\n%s" % (
        post["title"], post["excerpt"], "", to_plain(post["body"]))
    return {"model": model, "system": review_system(WEBSITE_CONTEXT, BRAND_VOICE),
            "messages": [{"role": "user", "content": user}]}


def takeaways_request(post: dict, model: str) -> dict:
    user = "Title: %s\n\nRaw HTML:\n%s" % (post["title"], to_html(post["body"]))
    return {"model": model, "system": TAKEAWAYS_SYSTEM, "messages": [{"role": "user", "content": user}]}


def find_key() -> str | None:
    key = os.environ.get("ANTHROPIC_API_KEY", "").strip()
    if key:
        return key
    f = REPO.parent / ".secrets" / "anthropic-key.txt"
    if f.is_file():
        key = f.read_text(encoding="utf-8").strip()
        return key or None
    return None


def count(request: dict, key: str) -> int:
    req = urllib.request.Request(
        ENDPOINT, data=json.dumps(request).encode("utf-8"), method="POST",
        headers={"x-api-key": key, "anthropic-version": "2023-06-01", "content-type": "application/json"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return int(json.loads(resp.read().decode("utf-8"))["input_tokens"])


def estimate(request: dict) -> int:
    text = request["system"] + "".join(m["content"] for m in request["messages"])
    return len(text) // 4


def usd(tokens_in: int, cap_out: int, price_in: float, price_out: float) -> float:
    return tokens_in * price_in / 1e6 + cap_out * price_out / 1e6


def cents_up(x: float) -> str:
    """Rounded up to the cent, so a figure quoted "at most" holds (0.0417 -> 0.05)."""
    return f"{math.ceil(round(x * 100, 6)) / 100:.2f}"


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--prices", help="input,output USD per million tokens, e.g. 3,15")
    ap.add_argument("--model", default=DEFAULT_MODEL)
    ap.add_argument("--estimate", action="store_true", help="characters / 4 instead of the endpoint")
    ap.add_argument("--figures", metavar="DATE", help="print the cost-figures block, measured on DATE (needs --prices)")
    args = ap.parse_args()

    prices = None
    if args.prices:
        a, b = args.prices.split(",")
        prices = (float(a), float(b))
    if args.figures and not prices:
        ap.error("--figures needs --prices")

    check_template()
    posts = [read_fixture(FIXTURES / (n + ".md")) for n in ORDER]
    key = None if args.estimate else find_key()
    method = "measured" if key else "estimate (characters / 4)"
    if not key and not args.estimate:
        print("No key found ($ANTHROPIC_API_KEY or ../.secrets/anthropic-key.txt): estimating.", file=sys.stderr)

    rows = []
    for p in posts:
        words = len(p["body"].split())
        chars = len(to_plain(p["body"]))
        rev, tak = review_request(p, args.model), takeaways_request(p, args.model)
        if key:
            try:
                r_tok, t_tok = count(rev, key), count(tak, key)
            except (urllib.error.URLError, urllib.error.HTTPError, KeyError, ValueError) as e:
                msg = getattr(e, "reason", None) or e
                print(f"Token-count endpoint failed ({msg}): estimating.", file=sys.stderr)
                key, method = None, "estimate (characters / 4)"
                r_tok, t_tok = estimate(rev), estimate(tak)
        else:
            r_tok, t_tok = estimate(rev), estimate(tak)
        rows.append((p["name"], words, chars, r_tok, t_tok))
    del key

    print(f"Model {args.model}, counts {method}; output caps review {REVIEW_OUTPUT_CAP}, takeaways {TAKEAWAYS_OUTPUT_CAP}")
    head = ("post", "words", "chars", "review in", "takeaways in")
    if prices:
        head += ("review USD", "takeaways USD")
    print(" | ".join(h.rjust(13) for h in head))
    per_review = {}
    for name, words, chars, r_tok, t_tok in rows:
        cells = [name, f"{words:,}", f"{chars:,}", f"{r_tok:,}", f"{t_tok:,}"]
        if prices:
            r_usd = usd(r_tok, REVIEW_OUTPUT_CAP, *prices)
            t_usd = usd(t_tok, TAKEAWAYS_OUTPUT_CAP, *prices)
            per_review[name] = r_usd
            cells += [f"{r_usd:.4f}", f"{t_usd:.4f}"]
        print(" | ".join(c.rjust(13) for c in cells))
    if prices:
        lo, hi = min(per_review.values()), max(per_review.values())
        print(f"Prices: {prices[0]:g} in, {prices[1]:g} out, USD per million tokens. One review per post per month, "
              "rounded up to the cent:")
        for n in (20, 80):
            print(f"  {n} posts: {cents_up(n * lo)} to {cents_up(n * hi)} USD")
    if args.figures:
        print("\n```cost-figures")
        print(f"model: {args.model}")
        print(f"measured: {args.figures}")
        print(f"price_input: {prices[0]:g}")
        print(f"price_output: {prices[1]:g}")
        for name, words, chars, r_tok, t_tok in rows:
            print(f"{name}: {cents_up(per_review[name])}")
        for n in (20, 80):
            print(f"month_{n}_low: {cents_up(n * lo)}")
            print(f"month_{n}_high: {cents_up(n * hi)}")
        print("```")
    return 0 if method == "measured" or args.estimate else 2


if __name__ == "__main__":
    sys.exit(main())
