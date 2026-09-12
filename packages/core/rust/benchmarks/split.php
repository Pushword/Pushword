<?php

declare(strict_types=1);

use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ContentSplitter;
use Pushword\Core\Service\NativeWorker;
use Pushword\Core\Service\Toc\IndexedUniqueSlugger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use TOC\UniqueSlugger;

require __DIR__.'/../../../../vendor/autoload.php';

$binary = __DIR__.'/../target/release/pushword-content-analyzer';
if (! is_executable($binary)) {
    throw new RuntimeException('Build packages/core/rust first');
}

/** @return array<mixed> */
function readSplit(SplitContent $split): array
{
    return [$split->getChapeau(), $split->getIntro(), $split->getContent(), $split->getContentParts(), $split->getBody(true), $split->getToc(), $split->getParagraphs(), $split->getParagraphs(true)];
}

$page = new Page();
$page->setCustomProperty('toc', true);
$native = new ContentSplitter($binary);
$cachedNative = new ContentSplitter($binary, cache: new ArrayAdapter());
$eligibility = new NativeWorker($binary);
$phpCache = new ArrayAdapter();
$report = [
    'measured_at' => gmdate(\DATE_ATOM),
    'cpu_affinity' => 1 === preg_match('/^Cpus_allowed_list:\s*(.+)$/m', (string) file_get_contents('/proc/self/status'), $affinity) ? trim($affinity[1]) : 'unknown',
    'php' => \PHP_VERSION,
    'icu' => \INTL_ICU_VERSION,
    'binary_sha256' => hash_file('sha256', $binary),
    'cargo_lock_sha256' => hash_file('sha256', __DIR__.'/../Cargo.lock'),
    'methodology' => 'Five shuffled samples of five documents after warmup. All SplitContent string/list accessors, including HTML TOC and both paragraph views. Rust includes reused-worker IPC, PHP slugging/menu rendering and validation. Caches are in-memory ArrayAdapter. No Markdown/Twig, SQL, HTTP or file publication is timed.',
    'workloads' => [],
];

foreach (['short' => 2, 'article' => 80, 'long' => 800, 'duplicate_headings' => 800] as $name => $count) {
    $html = '<p>Opening paragraph.</p>';
    for ($i = 0; $i < $count; ++$i) {
        $title = 'duplicate_headings' === $name ? 'Same heading' : 'Heading '.$i;
        $html .= '<h2>'.$title.'</h2><p>A paragraph with <strong>bold</strong>, <em>emphasis</em>, <a href="/docs">a link</a>, café 🦀 and <code>code</code>.</p>'."\n<ul>\n<li>First item</li>\n<li>Second item</li>\n</ul>\n";
    }

    if (null === $eligibility->request('split_content', [['html' => $html, 'toc' => true]])[0]) {
        throw new RuntimeException('Benchmark must exercise native analysis');
    }

    $expected = readSplit(new SplitContent($html, $page));
    $operations = [
        'php_uncached' => static fn (): array => readSplit(new SplitContent($html, $page)),
        'php_toc_cache_hit' => static fn (): array => readSplit(new SplitContent($html, $page, $phpCache)),
        'rust_uncached' => static fn (): array => readSplit($native->split($html, $page)),
        'rust_cache_hit' => static fn (): array => readSplit($cachedNative->split($html, $page)),
    ];
    foreach ($operations as $operation) {
        if ($expected !== $operation()) {
            throw new RuntimeException('Benchmark parity failure');
        }
    }

    $samples = [];
    for ($sample = 0; $sample < 5; ++$sample) {
        $modes = [...array_keys($operations), 'rust_batch'];
        shuffle($modes);
        foreach ($modes as $mode) {
            $start = hrtime(true);
            if ('rust_batch' === $mode) {
                $actual = array_map(readSplit(...), $native->splitMany(array_fill(0, 5, ['html' => $html, 'page' => $page])));
            } else {
                $actual = [];
                for ($i = 0; $i < 5; ++$i) {
                    $actual[] = $operations[$mode]();
                }
            }

            $samples[$mode][] = (hrtime(true) - $start) / 1e6 / 5;
            if ($actual !== array_fill(0, 5, $expected)) {
                throw new RuntimeException('Timed output differs from PHP');
            }
        }
    }

    $medians = [];
    foreach ($samples as $mode => $values) {
        sort($values, \SORT_NUMERIC);
        $medians[$mode] = $values[2];
    }

    $report['workloads'][$name] = ['html_bytes' => \strlen($html), 'html_sha256' => hash('sha256', $html), 'median_ms_per_document' => $medians, 'samples_ms_per_document' => $samples];
}

foreach (['upstream' => new UniqueSlugger(), 'indexed' => new IndexedUniqueSlugger()] as $name => $slugger) {
    $samples = [];
    for ($sample = 0; $sample < 5; ++$sample) {
        $slugger->reset();
        $start = hrtime(true);
        for ($i = 0; $i < 800; ++$i) {
            $slugger->makeSlug('Same heading');
        }

        $samples[] = (hrtime(true) - $start) / 1e6;
    }

    $report['slugging_800_duplicates_ms'][$name] = $samples;
}

$native->reset();
$cachedNative->reset();
$eligibility->reset();
echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
