---
title: 'The Free-build check reads an empty close/open tag pair as Freemius prints it (story 58)'
type: 'bugfix'
ticket: ''
created: '2026-11-02'
status: 'done'
baseline_revision: '65c32f98f68297b2bebb38df8e54aeaef0c5c57f'
route: 'oneshot'
route_source: 'auto'
review: 'quick'
review_source: 'pinned'
lenses_ran: ['quick']
review_loop_iteration: 0
context: []
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The 3.10.0 release run (41484767680, package job) failed its Free-build check. `bin/compare-php-trees.php` reported `admin/class-pqm-admin.php: differs from token 4359`: Freemius's Free build reads `… return (string) ob_get_clean ; }`, while the local strip reads `… ?> <?php return (string) ob_get_clean ; }`. The source at `class-pqm-admin.php` L1743–1745 ends a template with `<?php endif; ?>` followed directly by `<?php` on the next line, with no inline HTML between the two tags. Freemius's re-print drops that empty pair, and the code runs the same either way. This is the first empty pair in Free code: story 67 added it after 3.9.0. The only other one, at L1181, sits in `render_overrides__premium_only()`, which the Free build strips. So 3.10.0's Free build went unverified, its Free artifact was not uploaded, and story 58's AC3 re-run cannot pass on 3.10.0.

**Approach (owner, 2026-11-02: "Comparer tolerates it"):** the comparer drops a close tag directly followed by an open tag, once whitespace-only inline HTML is gone, on both sides. This applies the 2026-10-14 lesson: tolerate a cosmetic re-print in the comparison instead of mimicking it. A pair with real HTML between the tags still counts, and `<?=` is untouched. Add harness rows: the source and printed forms compare identical, and HTML between the tags still differs. Record the Freemius behaviour in `memory/delivery-pipeline.md`. Ship it as `fix(release): … (story 58)`, so release-please proposes 3.10.1, whose run is AC3's next try.

</frozen-after-approval>

## Implementation Notes

Oneshot: about 25 lines in `bin/compare-php-trees.php` and `tests/harness/free-build-harness.php`, plus one sentence in `memory/delivery-pipeline.md`. No plugin code, schema, REST or template change. Run in-session on Opus 5.5 (session rule). `bmad-build` was rendered by hand from the workspace root; the default route, routed `oneshot` by step 2.

- Evidence before the fix: the source has two empty pairs, `class-pqm-admin.php` L1181 (inside `render_overrides__premium_only()`, stripped from Free) and L1743 (`render_workflow_body()`). v3.9.0 had only the premium one, which is why 3.9.0 passed. The run's Pro artifact matches the v3.10.0 source as PHP, except the CI-built `admin/build/index.asset.php`, so Freemius does not re-print the Pro build.
- Verified: on the baseline comparer, a local Free build against a copy with L1743–1744 in Freemius's form reproduces CI's message exactly (token 4359, the same two lines). The new comparer reads it as `PHP identical: 11 files`. Without the comparer change, the new harness row fails (92/1). With it: free-build 95/0, the full sweep is green with the build, and the CI loop runs all 22 files without it. `php -l` is clean; every file is LF.
- Twice a `//` comment holding a literal close tag ended PHP mode (a scratch script, then the first harness comment). Comments here never quote the tag.

## Plan Change Log

## Review Triage Log

Pass 1, 2026-11-02, Quick lens (Opus): 4 findings, all low, all patched.
- low, patch: dropping the pair also dropped the `;` the close tag stands for. Reproduced: `$a = 1 ?>`, newline, `<?php -$b;` compared identical to `$a = 1 - $b;` (exit 0, both pass `php -l`), and `foo()` plus a pair plus `bar();` differed from `foo(); bar();`. There was no instance in today's source (every close tag follows `;`, `{`, `}` or `:`). Patched: the pair now reads as `;` unless `;`, `{`, `}` or `:` precedes it, with two harness rows for both directions. The 3.10.0 replay still reads identical (`}` precedes it there).
- low, patch: the docblock and memory described Freemius's drop more broadly than observed. 3.10.0's Free build kept the 24 indentation-only pairs before token 4359 and dropped only the pair with nothing at all between. Patched: both now state the observed rule, and say the comparer's whitespace-only reading is wider on purpose.
- low, patch: `docs/distribution.md` L103 lists Freemius's re-print changes and lacked this one. Patched with the 3.10.0 line.
- low, patch: the new harness comment restated the Freemius behaviour without naming memory (lesson 2026-10-16). Patched: it names `memory/delivery-pipeline.md`.

## Verification

**Commands:**
- `php tests/harness/free-build-harness.php` -- expected: `0 failed`, including the new rows. On the baseline comparer, the empty-pair row fails.
- `php bin/build-free.php <tmp>` then `php bin/compare-php-trees.php <tmp>/post-quality-manager <copy with L1743–1744's empty pair removed>` -- expected: `PHP identical`. The same against an unmodified copy also passes.
- the full harness sweep, built and unbuilt -- expected: every file `0 failed`.
- `php -l` on the 2 touched PHP files.
