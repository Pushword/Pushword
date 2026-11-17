# CI performance budget

The `CI performance` job measures every successful `Tests` workload. It excludes
queue time and its own runtime. Two global metrics are guarded: the longest job
and cumulative runner time. Individual job and test-file timings are diagnostic;
a single noisy shard does not block a commit.

## Observation and thresholds

Each profile starts with **20 independent full runs in observation mode**. The
first 20 measurements form a fixed median reference, carried forward in every
performance artifact. Later slow runs never raise that reference automatically.

The policy lives in `ci-performance-budget.json`:

- Above **10%**: a warning, once at least five calibration samples exist.
- Above **5%** for the median of the last five independent runs: a trend warning
  after calibration. This catches gradual drift across commits.
- Above **20% and 30 seconds**: a failure only after calibration and confirmation
  by three independent full runs of the **same commit**. The latest run must
  still exceed the limit, and the median of the three must exceed it too.

The 30-second floor applies separately to each metric, including cumulative runner
seconds. These are initial thresholds; inspect the observed variation before
tightening them. Changing thresholds preserves the reference. Change `reference`
explicitly to restart calibration after an intentional budget change.

## Comparable runs and retained history

Coverage on/off, Monaco cache warm/cold/mixed, and the complete matrix job names
identify separate profiles. A cache miss does not become a warm-cache regression.
Each profile therefore needs its own calibration. Workload changes that alter job
names start a new profile and are shown as observation, not as a passed comparison.

Only artifacts from branches in this repository can supply history. Fork runs can
read that history but cannot contribute a reference to repository branches.
Histories are shared between repository branches with the same profile. The guard
only needs `actions: read` and `contents: read`; it never dispatches workflows or
executes downloaded artifacts.

Every run retains `ci-performance-<profile hash>` for 90 days. It contains the
calibration reference, the last 20 measurements, current step and test-file
timings, and actual passed-case counts. A subsequent run carries the reference
forward even after its original artifacts expire. If all history for a profile
expires or is deleted, calibration restarts visibly.

## Investigating a warning

Open the job summary. It shows both global deltas, the recent median, the ten
largest step and test-file increases since the previous comparable run, and test
counts. File durations sum their cases, including cases split across shards; they
are not wall-clock estimates. Full JUnit and Playwright reports remain available
in the existing test artifacts.

If the slowdown looks real, re-run **all jobs** twice on the same commit, for
example with `gh run rerun <run-id>`. There are no automatic reruns. Re-running only
the guard or a subset of upstream jobs cannot confirm a regression or add a
calibration sample. Source artifacts are overwritten on full reruns so the guard
reads the latest reports.

Once this workflow is on the default branch, make `CI performance` a required
status check to prevent merging a confirmed regression. A failed upstream test
also fails this job instead of producing a performance comparison.
The guard runs even when upstream jobs fail or are cancelled: a skipped required
check would otherwise be accepted by branch protection without a measurement.

## Local verification

```bash
python3 -m unittest discover -s .github/scripts -p 'ci_performance_test.py' -v
python3 .github/scripts/ci_performance.py --repository Pushword/Pushword --run-id <run-id> --output /tmp/ci-performance.json
```

The second command uses the authenticated GitHub CLI to read an existing run and
its artifacts. It creates a local report only; it does not publish an artifact or
change a check result.
