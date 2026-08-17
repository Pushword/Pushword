---
title: 'Database and pipeline benchmarks'
h1: 'Database and pipeline benchmarks'
publishedAt: '2026-08-17 12:00'
toc: true
---

Pushword's database benchmark compares SQLite, MariaDB and PostgreSQL at three
levels: isolated database operations, representative application reads and real
end-to-end commands or HTTP requests. The fixtures are deterministic and synthetic;
no production content or configuration is read.

This page records a reference run from **17 August 2026**. It is useful for spotting
scaling trends and large regressions, but it is not a production capacity guarantee.
Absolute timings depend on the machine, database configuration, filesystem cache and
background activity.

## Run the benchmark

With MariaDB and PostgreSQL available on the configured test URLs, run the default
volume ladder from the repository root:

```shell
composer bench-databases
```

The default run measures database and application workloads at 100, 1,000 and 10,000
synthetic pages. The longer end-to-end pipelines default to 100 and 1,000 pages. Run
their 10,000-page variants explicitly when needed:

```shell
PUSHWORD_BENCH_PIPELINE_VOLUMES=100,1000,10000 composer bench-databases
```

To reproduce only the 10,000-page static generation measurement:

```shell
PUSHWORD_BENCH_PIPELINE_VOLUMES=10000 \
  ./.scripts/test --benchmark StaticGeneratorBenchmarkTest
```

The runner prints Markdown tables and removes its temporary databases, generated
files and result files after the run. DSN and volume overrides are documented in
[Contribute > Database volume benchmark](/contribute#database-volume-benchmark).

## What is measured

| Layer | Workload | Repetitions |
|---|---|---:|
| Database operations | Batched inserts, count, 200 indexed slug lookups, tag filtering, numeric JSON filtering and a sorted 50-row list | Median of 5 reads |
| Application workload | Multisite editorial navigation, internal-link resolution, catalogue filters, facets and a content-export pass | Median of 3 reads |
| `pw:page-scan` | The real command scans a deterministic internal-link graph with external checks disabled | 1 command run |
| `pw:static` | The real command renders HTML and writes it to an isolated directory with one worker | 1 command run |
| EasyAdmin | Authenticated list, pagination, search, host filter and sort requests | Sum of the median of 3 runs per request |

The pipeline volume is the number of synthetic pages added to the normal test
fixtures. Consequently, operation and SQL-query totals also include the small set of
pages already present in the development application. For `pw:static`, the query
counter covers the command's coordinating entity manager, not the separate render
kernel connection. Instrumenting both connections is useful for profiling but adds
enough overhead to invalidate cross-engine duration comparisons. Xdebug is disabled,
database engines run locally, pipelines are sequential, and timings use a monotonic
clock.

## Reference environment

| Component | Value |
|---|---|
| Operating system | Linux 7.1.6, x86-64 |
| Processor | AMD Ryzen AI 9 HX 370, 12 cores / 24 threads |
| Memory | 125 GiB |
| PHP | 8.5.9 NTS |
| SQLite | 3.51.2, local file |
| MariaDB | 11.8.8, local TCP connection |
| PostgreSQL | 17.11, disposable local TCP container |

All three engines used the same application code and test environment. The server
databases were reached over loopback, so these results do not include production
network latency.

## Database operation results

Times are milliseconds; lower is better. `Write` covers fixture insertion. `Lookups`
is the complete batch of 200 indexed slug queries.

| Pages | Engine | Write | Count | Lookups | Tag filter | JSON number | List 50 |
|---:|---|---:|---:|---:|---:|---:|---:|
| 100 | SQLite | 29.3 | 0.05 | 2.38 | 0.05 | 0.06 | 0.07 |
| 100 | MariaDB | 62.2 | 0.10 | 4.98 | 0.09 | 0.11 | 0.11 |
| 100 | PostgreSQL | 65.6 | 0.19 | 13.07 | 0.12 | 0.15 | 0.12 |
| 1,000 | SQLite | 321.3 | 0.10 | 2.36 | 0.14 | 0.24 | 0.14 |
| 1,000 | MariaDB | 416.5 | 0.53 | 6.67 | 0.49 | 0.61 | 0.64 |
| 1,000 | PostgreSQL | 564.3 | 0.18 | 22.59 | 0.31 | 0.55 | 0.30 |
| 10,000 | SQLite | 3,123.8 | 0.76 | 2.58 | 1.09 | 2.15 | 0.93 |
| 10,000 | MariaDB | 4,306.8 | 4.59 | 5.41 | 3.24 | 5.17 | 5.50 |
| 10,000 | PostgreSQL | 6,168.1 | 1.02 | 12.17 | 1.62 | 3.42 | 1.31 |

SQLite has the lowest write and point-lookup cost on this local, single-process
workload. PostgreSQL's larger fixed cost is visible at low volume, while its count,
tag, JSON and list timings remain closer to SQLite than MariaDB at 10,000 pages.

## Application workload results

Times are milliseconds; lower is better. `Rich seed` creates the related editorial
and catalogue corpus and is not included in `Read total`.

| Pages | Engine | Rich seed | Editorial | Catalogue | Facets | Export | Read total |
|---:|---|---:|---:|---:|---:|---:|---:|
| 100 | SQLite | 75.9 | 7.47 | 1.37 | 0.54 | 0.49 | 9.87 |
| 100 | MariaDB | 104.3 | 13.08 | 3.11 | 1.20 | 1.07 | 18.46 |
| 100 | PostgreSQL | 103.8 | 22.59 | 5.70 | 1.39 | 1.75 | 31.43 |
| 1,000 | SQLite | 707.4 | 22.35 | 5.86 | 6.89 | 4.21 | 39.31 |
| 1,000 | MariaDB | 968.1 | 25.83 | 6.72 | 5.64 | 5.99 | 44.19 |
| 1,000 | PostgreSQL | 1,088.1 | 25.48 | 4.74 | 3.17 | 10.72 | 44.11 |
| 10,000 | SQLite | 6,970.1 | 135.33 | 46.36 | 115.35 | 47.59 | 344.63 |
| 10,000 | MariaDB | 9,028.9 | 105.19 | 43.98 | 60.51 | 314.37 | 524.05 |
| 10,000 | PostgreSQL | 10,523.9 | 66.05 | 19.41 | 22.13 | 125.05 | 232.64 |

The small-volume result is dominated by fixed round-trip cost. At 10,000 pages,
PostgreSQL completes this particular read mix fastest, especially the catalogue and
facet queries. MariaDB's export pass is the largest outlier in this snapshot. These
are workload-specific results, not a ranking of the databases in general.

## Initial end-to-end pipeline results

`Throughput` includes the development fixtures as well as the requested synthetic
volume. Peak memory is the PHP process peak, not total database or operating-system
memory.

| Scenario | Pages | Engine | Duration ms | Throughput | SQL queries | Peak memory MiB |
|---|---:|---|---:|---:|---:|---:|
| `pw:page-scan` | 100 | SQLite | 245 | 473.5 pages/s | 244 | 135.0 |
| `pw:page-scan` | 100 | MariaDB | 258 | 449.6 pages/s | 244 | 176.3 |
| `pw:page-scan` | 100 | PostgreSQL | 285 | 407.0 pages/s | 244 | 166.3 |
| `pw:page-scan` | 1,000 | SQLite | 1,589 | 639.4 pages/s | 2,044 | 166.0 |
| `pw:page-scan` | 1,000 | MariaDB | 1,880 | 540.4 pages/s | 2,044 | 201.3 |
| `pw:page-scan` | 1,000 | PostgreSQL | 1,976 | 514.2 pages/s | 2,044 | 199.3 |
| `pw:static` | 100 | SQLite | 1,049 | 111.5 pages/s | 249 | 190.9 |
| `pw:static` | 100 | MariaDB | 1,280 | 91.4 pages/s | 249 | 208.3 |
| `pw:static` | 100 | PostgreSQL | 1,395 | 83.9 pages/s | 249 | 206.3 |
| `pw:static` | 1,000 | SQLite | 8,896 | 114.3 pages/s | 2,049 | 203.8 |
| `pw:static` | 1,000 | MariaDB | 11,410 | 89.1 pages/s | 2,049 | 216.8 |
| `pw:static` | 1,000 | PostgreSQL | 11,122 | 91.4 pages/s | 2,049 | 212.8 |
| EasyAdmin | 100 | SQLite | 255.7 | 19.6 requests/s | 25 | 110.0 |
| EasyAdmin | 100 | MariaDB | 281.0 | 17.8 requests/s | 25 | 147.3 |
| EasyAdmin | 100 | PostgreSQL | 249.8 | 20.0 requests/s | 23 | 141.3 |
| EasyAdmin | 1,000 | SQLite | 242.1 | 20.6 requests/s | 25 | 116.0 |
| EasyAdmin | 1,000 | MariaDB | 254.2 | 19.7 requests/s | 25 | 151.3 |
| EasyAdmin | 1,000 | PostgreSQL | 272.5 | 18.4 requests/s | 23 | 145.3 |

EasyAdmin stays effectively flat from 100 to 1,000 pages, and its constant query
count is the more durable signal than the small timing differences. Page scan issues
approximately two observed queries per processed page. The initial static-generation
result showed the same shape on its coordinating connection, but profiling the render
kernel exposed additional corpus-wide work hidden behind that count.

## Static generation at 10,000 pages

The 10,000-page static scenario was run separately because it takes several minutes
per engine. Each run successfully rendered and validated exactly 10,000 synthetic
HTML files; the operation count also includes 17 development-fixture pages.

| Engine | Initial | Optimized | Change | Optimized throughput | Coordinator queries | Peak memory MiB |
|---|---:|---:|---:|---:|---:|---:|
| SQLite | 203.8 s | 76.7 s | -62.4% | 130.60 pages/s | 10,035 | 540.9 |
| MariaDB | 242.5 s | 107.2 s | -55.8% | 93.47 pages/s | 10,035 | 561.8 |
| PostgreSQL | 254.9 s | 113.1 s | -55.6% | 88.54 pages/s | 10,035 | 561.8 |

The initial run rebuilt two complete page maps for every rendered page: redirect
lookup data in Core and internal-link source data in Link Improver. Both caches were
cleared by the render kernel's service reset after every request, turning otherwise
linear work into two quadratic paths. Static generation now pins those read-only maps
for one build. A render epoch still invalidates Link Improver data when the page corpus
changes in a long-lived process. Feed generation also preloads the set of parent pages
once instead of issuing one child-count query per page.

At 5,000 pages, the render-kernel phase fell from 39.5 s to 11.0 s and the complete
run from 64.4 s to 36.2 s. The optimized 1,000-page runs took 6.9 s on SQLite, 8.2 s
on MariaDB and 8.1 s on PostgreSQL. From 1,000 to 10,000 pages, duration now grows by
11.1x, 13.0x and 13.9x respectively, instead of 21–23x in the initial snapshot.

The benchmark also records generated HTML size and the `kernel.handle`, compression,
file-write and page-generation phases in its JSON result. The 10,000-page corpus
produced about 86.9 MiB of HTML on every engine. These diagnostics are deliberately
reported rather than enforced as CI thresholds; shared-runner timing noise would make
such thresholds unreliable.

## Interpreting and updating the results

- Compare query counts and scaling curves before comparing sub-millisecond timings.
- Repeat pipeline runs when investigating a small difference; their published values
  are single snapshots, unlike the median database and application reads.
- Keep the same machine, engine versions and volume ladder when comparing a change.
- Do not infer concurrent request capacity: this benchmark is deliberately sequential
  and uses local databases.
- Update the date, environment and complete affected table after a representative
  rerun. Label code revisions explicitly when a table is intended as a before/after
  comparison.

The structural query-count regression tests under `packages/core/tests/Perf/` and
`packages/admin/tests/Perf/` run in normal CI. Timing benchmarks remain opt-in because
shared CI runners are too noisy for stable latency thresholds.
