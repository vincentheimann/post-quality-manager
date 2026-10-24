---
title: "Why your build cache keeps missing, and the three-line fix"
excerpt: "A build cache that misses on every run is usually keyed on something that changes on every run. Here is how to find the culprit in ten minutes."
length: short
---

Every team I have worked with has had the same conversation at some point. Someone notices that the pipeline takes twelve minutes when it should take three. Someone else says "but we have a cache". And then nobody looks at it for another month.

A build cache that never hits is worse than no cache at all: you pay for the upload and the download and still do the full build. The good news is that the cause is almost always the same, and it takes about ten minutes to find.

## The cache key is the whole story

A cache entry is stored under a key. If the key you compute on this run does not equal a key stored by an earlier run, you miss. That is the entire mechanism. So when the cache misses on every run, the key changes on every run.

The question is not "why is the cache broken" but "what in my key changes every time". Print the key. Most pipeline tools let you echo the resolved key as a step. Run the pipeline twice without changing anything and compare the two values.

In my experience the culprit is one of three things.

## Culprit one: a timestamp or a run number

Someone wanted "fresh" caches and put the date in the key. Or the key template includes the run id by default and nobody removed it. Either way, the key is unique per run by construction, so it never matches.

The fix is to key on the thing that decides the cache contents, not on when the run happened. For a dependency cache, that is a hash of the lock file. For a compiler cache, that is a hash of the source tree plus the compiler version.

## Culprit two: a file that is regenerated before the key is computed

This one is sneaky. The key hashes the lock file, which sounds right. But an earlier step runs an install that rewrites the lock file with a slightly different ordering or a new header line. The file content changes, so the hash changes, so the key misses.

Move the cache restore step above anything that writes to the hashed files. If the install step must run first, hash the manifest instead of the lock file, and accept that a manifest change invalidates the cache a little more often than needed.

## Culprit three: the restore path and the save path differ

The cache saves the dependency folder from the project root, but the restore step runs in a subdirectory, or the other way round. The key matches, the archive downloads, and it is unpacked where nothing reads it. From the outside it looks like a miss.

Check that the restore and save steps name the same path, relative to the same working directory. Print the working directory in both steps if you are not sure.

## The three-line fix

Once you know which culprit you have, the change is tiny. For the most common case, a dependency cache keyed on a lock file:

```
key: deps-{{ hashFiles('lockfile') }}
restore-keys: deps-
path: dependencies/
```

Three lines. The `restore-keys` prefix gives you a partial hit when the lock file changes, which is usually most of the work.

## How to know it worked

Do not trust the green check mark. Look at the cache step's log for the words "cache hit" or "restored from key", then look at the install step: it should finish in seconds, not minutes. If the restore says hit but the install still takes four minutes, you have culprit three.

Then watch the total pipeline time for a week. A cache that works shows up as a flat line where there used to be a sawtooth.
