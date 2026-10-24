---
title: "Structured logging for a small team: what to log, what to drop"
excerpt: "Logs are the first thing you read at 2 a.m. and the last thing anyone designs. A practical set of rules for a service run by three people."
length: average
---

Most logging advice is written for companies with a platform team, a log pipeline and a budget line for both. This post is for the rest of us: a service maintained by two or three engineers, deployed a few times a week, with an incident every month or two. You do not need a logging strategy. You need about eight rules you can keep in your head.

I have run a small payments-adjacent service on these rules for three years. They are not original. They are the ones that survived.

## Rule 1: every line is a key-value record, no exceptions

The single biggest change you can make is to stop writing sentences into the log and start writing records. Not because sentences are bad, but because you will search the logs with a tool, and tools search fields.

```
level=info event=order.created order_id=81723 customer_id=4411 total_cents=12900 duration_ms=41
```

That line answers more questions than "Created order 81723 for customer 4411 in 41 ms". It is also one search away from any question about order 81723, which the sentence form only sometimes is, depending on who wrote the sentence.

Pick one format and make the logging helper the only way to write a line. If a line can be written by hand, one will be, and it will be the one you need during an incident.

## Rule 2: one event name per thing that happens

The `event` field is the backbone. Name it like a path: `order.created`, `order.payment_failed`, `cache.miss`. Keep a list of the names in one file in the repository. When you add an event, add it to the list.

The list does two things. It stops two people from logging the same thing under two names. And it becomes the table of contents for your logs: when a new teammate asks "what does this service record", you point at the file.

## Rule 3: carry a request id through everything

Every incoming request gets an id, generated at the edge or taken from an upstream header. Every log line written while handling that request carries it. Every outgoing call passes it on.

This is the rule that pays for itself in the first incident. "Show me everything that happened to request `c91a…`" is the question you will ask most often, and it is only answerable if the id is on every line.

In practice this means your logging helper reads the id from the request context, not from a parameter. A line that requires the caller to pass the id will, sooner or later, be written without it.

## Rule 4: log at the boundaries, not in the middle

Log when a request arrives and when it leaves. Log when you call another service and when it answers. Log when a background job starts and when it ends. Do not log every function call in between.

Boundary logs give you the shape of what happened: how long each hop took and which one failed. Middle logs give you volume. If you need to see inside a function, that is a debugger or a test, not a log line.

The one exception is a decision that changes the outcome: a feature flag read, a retry, a fallback. Log those, with the value that was decided and why.

## Rule 5: three levels are enough

`error` means a human should look at this, today. `warn` means something degraded but the request succeeded, and a human should look at the trend this week. `info` is everything else.

`debug` exists, but it is off in production, so treat it as a development tool. If you find yourself wanting `debug` lines in production to understand an incident, the fix is a better `info` line at a boundary, not turning `debug` on.

Resist `fatal`, `critical`, `notice` and friends. Each additional level is a judgement call somebody has to make under pressure, and the alerting rules get longer.

## Rule 6: an error line carries the error, the input and the id

When something fails, the line should let you reproduce it. That means the error message and type, the identifiers of the thing being processed, and the request id. It does not mean the whole payload.

```
level=error event=payment.capture_failed request_id=c91a2f order_id=81723 provider=north-pay error_type=Timeout error="deadline exceeded after 8000ms" attempt=2
```

With that line, I can find the order, see the provider timed out twice, and go read the provider's status page. Without `attempt=2`, I would wonder whether retries are working. Without `provider`, I would have to look it up.

## Rule 7: never log a secret, a token or a full card number

This sounds obvious until you log a request header map "for debugging" and ship it. Put the redaction in the logging helper, not in each call site. Maintain a short list of field names that are always masked: `authorization`, `password`, `token`, `card_number`, `cvv`. Mask them wherever they appear, including nested.

Then write one test that logs a record containing each of those fields and asserts the output does not contain the value. It is a five-minute test and it will catch the one time somebody adds a new helper.

## Rule 8: decide what to drop before you decide what to keep

Log volume is the cost you feel last and pay longest. Before adding a line, ask what question it answers. If the answer is "none, but it might be useful", do not add it.

Three categories of lines go first when the bill arrives:

- Health check requests. Log them once a minute at most, or not at all.
- Successful cache hits. Count them in a metric instead.
- Per-item lines inside a loop. Log the loop once, with the count and the duration.

A good test: for each `info` event in your list, name the incident or question in which you read it. If you cannot, demote it to `debug` or delete it.

## A word on the format itself

People spend a surprising amount of time choosing between key-value pairs and a JSON object per line. It matters less than it feels. Key-value lines are easier to read in a terminal and easier to write by hand when you must; JSON lines are easier to parse when a value contains spaces or nesting. Pick the one your search tool ingests without a custom parser, and move on.

Two things matter more than the format. Timestamps in UTC, always, with the offset written out, so a line from a server in one region sorts correctly next to a line from another. And a fixed field order, with the level, the event and the request id first, so the eye lands on the same place in every line.

## What this looks like after a year

The service I mentioned writes about forty distinct events. The list fits on one screen. During an incident, the first command anyone runs is a search for a request id, and the second is a search for `level=error` in the last hour, grouped by `event`. Those two searches have found the cause of every incident but one.

The one they did not find was a slow memory leak, which no log line would have caught. That was a metric's job. Logs tell you what happened; metrics tell you how much. Keep them separate and let each do the thing it is good at.

## Where to start if you have none of this

Do not rewrite every log line. Do three things this week:

1. Add the request id to the logging context and to every outgoing call.
2. Replace the five log lines you read most often during the last incident with structured records.
3. Delete the health check lines.

Next incident, notice which questions were hard to answer, and add the boundary lines that would have answered them. Within a quarter you will have the forty events without ever having planned them.
