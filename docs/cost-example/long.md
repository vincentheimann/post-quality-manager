---
title: "Moving a nightly batch job to a queue, without a rewrite"
excerpt: "Our 2 a.m. report job had grown into a four-hour monolith that failed twice a month. Here is how we moved it to a work queue one slice at a time, kept the old job running throughout, and what we would do differently."
length: long
---

Every company of a certain age has a nightly job. Ours generated the daily settlement report for about nine hundred merchant accounts. It started in year one as a script that took four minutes. By year six it took four hours, held a long transaction open on the main database for most of that time, and failed often enough that "did the report run" was the first question in the morning stand-up.

This is the story of how we moved it to a work queue over about ten weeks, without a rewrite and without a night where the report did not arrive. It is not a clever story. The interesting part is the order we did things in, and the two decisions that made the rest easy.

## What the job did, and why it was slow

The job had three phases, all in one process:

1. **Collect.** For each merchant, pull the day's transactions, refunds and fee adjustments from the main database.
2. **Compute.** Apply the merchant's fee schedule, currency conversions and any manual corrections, producing a settlement figure and a line-item breakdown.
3. **Deliver.** Render a PDF and a CSV, upload both to the merchant's folder, and send a notification email.

Each phase ran for all merchants before the next phase started. That meant phase one held a read transaction open for an hour and a half while it paged through transactions, phase two kept everything in memory, and phase three failed if a single merchant's email bounced hard enough to throw.

A failure anywhere meant re-running from the top. A re-run took another four hours. On a bad night the report arrived at ten in the morning, and the merchants whose finance teams started at eight had already written in.

## The decision that made everything else possible

Before touching any code, we agreed on one thing: **the unit of work is one merchant**, not one night.

That sounds obvious written down. It was not obvious in the code, where every loop was `for merchant in merchants`, and every data structure was keyed by merchant inside a night-wide dictionary. But once we said it out loud, the whole plan fell out of it. A queue holds jobs. A job is "settle merchant 4411 for 2026-03-14". Nine hundred jobs a night. Each one small, independent and re-runnable on its own.

The second decision followed from the first: **a job must be safe to run twice**. If a worker crashes halfway through merchant 4411, another worker will pick the job up again. The second run has to produce the same files and must not send a second email. We had to make each phase idempotent before we could move it.

Everything else was mechanics.

## Week one and two: make the old job idempotent in place

We did not start with the queue. We started by making the existing monolith safe to re-run per merchant, while it still ran as one process at 2 a.m.

For **collect**, nothing needed to change: reads are idempotent.

For **compute**, the settlement figures were written to a `settlements` table with an auto-increment id, so a re-run inserted a second row. We added a unique constraint on `(merchant_id, settlement_date)` and changed the insert to an upsert. Then we re-ran the job for one merchant by hand, twice, and checked there was one row.

For **deliver**, two problems. The PDF and CSV uploads overwrote the previous files, which was fine. The email was the issue. We added a `notifications` table with the same `(merchant_id, settlement_date)` key, written in the same transaction as the settlement row, and the email step checked it before sending. If the row said sent, skip.

This took two weeks because the job was six years old and every phase had a special case for some merchant. We found four places where a merchant id was hard-coded. We left them in; this was not a cleanup project.

At the end of week two, the job was still a monolith, still took four hours, but any merchant could be re-run alone with a command-line flag, and running it twice did no harm. That alone cut our morning firefighting in half, because a failure no longer meant "re-run everything".

## Week three: extract the per-merchant function

With idempotency in place, we pulled the body of the three loops into one function:

```
def settle_merchant(merchant_id, settlement_date):
    txns = collect(merchant_id, settlement_date)
    result = compute(merchant_id, settlement_date, txns)
    deliver(merchant_id, settlement_date, result)
```

The nightly job became a loop that called this function nine hundred times. Behaviour unchanged. The important thing is that `settle_merchant` had no dependency on anything outside its arguments: no night-wide dictionary, no shared transaction, no accumulated state.

Finding the hidden shared state took most of the week. The currency rates were loaded once at the top of the job and reused; we moved the load inside `compute`, with a short-lived in-process cache so nine hundred calls did not mean nine hundred rate lookups. The fee schedules were the same. A counter that tracked "merchants processed so far" for the log line was replaced by a log line per merchant.

We shipped this to production on a Wednesday. The job ran that night in four hours and ten minutes, slightly slower because of the per-merchant rate loads. Nobody noticed.

## Week four: the queue, in shadow mode

Now the queue. We used the work-queue library that already shipped with our framework, backed by the same database, because adding a new piece of infrastructure to this project would have doubled its risk. A dedicated broker can come later if the database-backed queue becomes the bottleneck. Six months on, it has not.

The job definition was `settle_merchant` with its two arguments. A worker process picked jobs and called the function. Retries: three, with a backoff of one, five and twenty-five minutes. A job that failed three times went to a dead-letter table and raised an alert.

But we did not switch the nightly job over yet. Instead, we ran both:

- At 2 a.m. the old monolith ran as before, and produced the real report.
- At 2:05 a.m. a new scheduler enqueued nine hundred jobs with a flag `shadow=True`. In shadow mode, `deliver` wrote the files to a separate folder and did not send email.

Every morning for a week, a script compared the shadow output with the real output, file by file. Day one, six merchants differed. Four were rounding: the monolith computed the fee total as a running sum, the function computed it per line and summed at the end. We changed the function to match the monolith, not the other way round, because the monolith's figures were what the merchants had been reconciling against for six years. The other two were a timezone edge case for merchants whose day boundary was not midnight UTC. That one was a real bug in the old code, which we fixed in both.

By the end of the week the outputs matched to the byte for seven nights running.

## Week five: cut over, with the old job as the fallback

The cutover was one configuration change: the 2 a.m. schedule now enqueued the jobs for real, and the monolith's schedule was moved to 6 a.m. with a guard: it ran only if the `settlements` table had fewer than nine hundred rows for the day.

So if the queue finished, the monolith woke up at six, saw the work was done, and exited. If the queue had failed, the monolith ran as it always had, and the report still arrived by ten.

First night: the queue finished in forty-one minutes with four workers. The monolith woke, counted, and exited. We left the fallback in place for a month. It ran for real once, on a night when a database failover killed the workers mid-run and the dead-letter table filled up. The fallback caught it; we read the dead letters in the morning, found the cause, and re-enqueued.

## Weeks six to ten: the parts nobody writes about

The queue was live after five weeks. The remaining five went on things that do not make a diagram but decide whether the change sticks.

**Observability.** The monolith had one log file and one metric: did it finish. The queue had nine hundred jobs, four workers and a retry policy, and the first morning we had no way to answer "how far along are we". We added three numbers to the dashboard: jobs queued, jobs done, jobs in the dead-letter table, each for the current settlement date. Then a fourth, the age of the oldest unfinished job, which turned out to be the one that predicted trouble.

**Alerting.** The old alert fired if the monolith had not finished by 7 a.m. We kept that one, pointed at the `settlements` row count instead of the process, and added one more: any dead letter older than thirty minutes. We did not alert on individual retries. A retry is the system working, not failing.

**The runbook.** Half a page. How to see the queue's state, how to re-run one merchant, how to re-run a whole day, how to turn the fallback back on if the queue is unhealthy. Every item is one command. We rehearsed it once with the person who had not written any of the code, and rewrote three steps she could not follow.

**Removing the fallback.** After a month with one real fallback run, we turned the 6 a.m. monolith schedule off. We did not delete the code for another quarter, because the cost of keeping it was zero and the cost of needing it and not having it was a very bad morning.

**Telling finance.** The merchants' finance teams had learned over six years that the report arrived "sometime after eight". We wrote to them once, said it now arrived before three, and asked them to tell us if a figure ever changed between the first delivery and a later one. Nobody has.

## What changed for the people

Forty-one minutes instead of four hours is the number everyone quotes, but it is not the change that mattered.

The change that mattered was the shape of a failure. Before, a failure was "the report did not run", a night-sized problem that one person owned until it was fixed. After, a failure was "merchant 4411 is in the dead-letter table", a merchant-sized problem with a known cause, retried automatically three times before anyone was woken, and re-runnable with one command.

Morning stand-up stopped starting with the report. The on-call engineer stopped dreading Sunday nights, when the weekly fee adjustments landed and the monolith was most likely to time out.

And the long transaction on the main database went away. Each job held a transaction for a few hundred milliseconds. The database team noticed before we told them.

## What we would do differently

**Add the comparison script on day one.** We wrote the shadow comparison in week four. If we had written it in week one, against the monolith's own output from two consecutive runs, we would have found the running-sum rounding difference before it became a question of which side was right.

**Put a budget on the per-job time.** A few merchants had two hundred thousand transactions a day, and their jobs took eight minutes while the median took two seconds. The queue handled it fine, but the workers' timeouts had been set with the median in mind and the first large-merchant job hit the timeout. We raised it to fifteen minutes. Better: measure the distribution before choosing the timeout.

**Decide earlier who owns the dead-letter table.** For the first two weeks, dead letters were everyone's problem and therefore nobody's. We now have a rule: whoever is on call looks at the table at nine, and a dead letter older than a day is a bug ticket.

**Do not try to fix the special cases.** We were tempted to clean up the four hard-coded merchant ids while we were in there. We did not, and that was right. Two of them turned out to be load-bearing in ways nobody remembered. They are still there, with a comment.

## The pattern, if you want to reuse it

If you have a batch job that has grown past its comfortable size, the sequence that worked for us was:

1. Name the unit of work. It is almost never "the whole night".
2. Make one unit safe to run twice, while the batch still runs as before.
3. Extract one unit into a function with no outside state.
4. Run the queue in shadow and compare outputs until they match for a week.
5. Cut over, with the old batch as a guarded fallback, and keep the fallback for a month.

Each step shipped on its own. None of them broke the nightly report. At no point did we have a branch that was weeks away from mergeable. If we had been told to stop after any step, what we had shipped would still have been an improvement.

That is the real argument against a rewrite. Not that rewrites fail, though they often do, but that a rewrite has no step three. There is nothing to ship until the end, and the end is always further than it looks.

## Questions we were asked when we told this story

**Why not a stream instead of a nightly queue?** Because the report is defined by a day boundary. Settlement is "everything that happened on the fourteenth", and the merchants reconcile against that. A stream would have moved the hard part, deciding when a day is complete, into every consumer. The queue kept it in one place.

**Why a database-backed queue when a proper broker exists?** Because we already operated the database, and the queue's load was nine hundred jobs a night, not nine hundred a second. The simplest thing that worked was the thing we already knew how to back up, monitor and restore. If the volume grows a hundredfold the answer changes. It has not.

**Did the shadow week really need to be a week?** Yes. Two of the six differences on day one only appeared on specific days: one on a day with a manual correction, one on the day a merchant changed their timezone. A single matching night would have told us nothing about either.

**What did it cost?** Two engineers, part time, for ten weeks, on top of their other work. Against that: a four-hour database transaction gone, a monthly incident gone, and an on-call rota that no longer has a bad night built into it. We did not try to put a figure on the second column. Nobody asked us to.

## A note on the numbers

Nine hundred merchants, four hours down to forty-one minutes, four workers, three retries, one month of fallback. None of those numbers transfer to your job. The sequence does. Measure your own, pick your own timeouts, and run the comparison longer than feels necessary. A week of matching output is cheap. A merchant's finance team finding a one-cent difference is not.
