<?php

declare(strict_types=1);

use Psr\Log\AbstractLogger;
use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\HtmlMinifier;

require __DIR__.'/../../../vendor/autoload.php';

// Pass already rendered HTML files; render/file publication/sidecars are not timed.
$pages = array_map(static fn (string $file): string => file_get_contents($file) ?: throw new RuntimeException('Unreadable or empty HTML file'), \array_slice($argv ?? [], 1));
if ([] === $pages) {
    throw new RuntimeException('Usage: php bench.php page.html [another.html ...]');
}

$logger = new class extends AbstractLogger {
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        throw new RuntimeException('Benchmark refused PHP fallback: '.$message);
    }
};
$binary = __DIR__.'/target/release/pushword-html-minifier';
$report = [
    'php' => \PHP_VERSION,
    'libxml' => \LIBXML_DOTTED_VERSION,
    'binary_sha256' => hash_file('sha256', $binary),
    'input_bytes' => array_map(strlen(...), $pages),
    'input_sha256' => array_map(static fn (string $html): string => hash('sha256', $html), $pages),
    'samples' => [],
];
foreach ([1, 100, 1000] as $count) {
    $documents = [];
    for ($i = 0; $i < $count; ++$i) {
        $documents[] = $pages[$i % \count($pages)];
    }

    $expected = array_map(HtmlMinifier::compress(...), $documents);
    for ($repeat = 0; $repeat < 5; ++$repeat) {
        $modes = ['php', 'rust-single', 'rust-batch'];
        shuffle($modes);
        foreach ($modes as $mode) {
            $minifier = new HtmlMinification('php' === $mode ? null : $binary, logger: $logger);
            $start = hrtime(true);

            try {
                $actual = 'rust-batch' === $mode
                    ? $minifier->compressMany($documents)
                    : array_map($minifier->compress(...), $documents);
                $elapsed = (hrtime(true) - $start) / 1e6;
                if ($actual !== $expected) {
                    throw new RuntimeException('PHP/Rust byte mismatch');
                }

                $report['samples'][] = ['mode' => $mode, 'pages' => $count, 'repeat' => $repeat, 'ms' => $elapsed];
            } finally {
                $minifier->reset();
            }
        }
    }
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
