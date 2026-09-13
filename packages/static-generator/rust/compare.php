<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../../../vendor/autoload.php';

$rawArguments = $_SERVER['argv'] ?? [];
if (! is_array($rawArguments)) {
    throw new InvalidArgumentException('Invalid command-line arguments.');
}

$arguments = [];
foreach ($rawArguments as $argument) {
    if (! is_string($argument)) {
        throw new InvalidArgumentException('Invalid command-line argument.');
    }

    $arguments[] = $argument;
}

if (count($arguments) < 4) {
    throw new InvalidArgumentException('Usage: php compare.php baseline-binary candidate-binary page.html [another.html ...]');
}

$status = file_get_contents('/proc/self/status');
if (false === $status || 1 !== preg_match('/^Cpus_allowed_list:\s*(\d+)/m', $status, $allowed)) {
    throw new RuntimeException('Cannot determine the first allowed Linux CPU.');
}

$affinity = new Process(['taskset', '-pc', $allowed[1], (string) getmypid()]);
$affinity->mustRun();

$binaries = ['before' => $arguments[1], 'candidate' => $arguments[2]];
$pages = array_map(static function (string $path): string {
    $html = file_get_contents($path);

    return false === $html ? throw new RuntimeException('Unreadable HTML file: '.$path) : $html;
}, array_slice($arguments, 3));
$documents = [];
for ($i = 0; $i < 1000; ++$i) {
    $documents[] = $pages[$i % count($pages)];
}

$encoded = array_map(static fn (string $html): string => json_encode($html, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR), $documents);
// Preserve the request bytes used by the earlier native-only measurements.
$request = '{"version": 1, "id": 1, "operation": "minify_html", "documents": ['.implode(', ', $encoded)."]}\n";
$report = [
    'methodology' => 'One CPU, 1000 documents repeating supplied HTML; 9 interleaved samples. Wall time includes startup, stdin, native work and stdout collection. No PHP adapter. Every output compared byte for byte.',
    'binary_sha256' => array_map(static fn (string $path): string => hash_file('sha256', $path) ?: throw new RuntimeException('Unreadable binary: '.$path), $binaries),
    'input_sha256' => array_map(static fn (string $html): string => hash('sha256', $html), $pages),
    'request_bytes' => strlen($request),
    'samples' => [],
];

mt_srand(290912);
$expected = null;
for ($repeat = 0; $repeat < 9; ++$repeat) {
    $modes = array_keys($binaries);
    shuffle($modes);
    foreach ($modes as $mode) {
        $process = new Process([$binaries[$mode]]);
        $process->setInput($request);
        $process->setTimeout(30);
        $start = hrtime(true);
        $process->mustRun();
        $elapsed = (hrtime(true) - $start) / 1e6;
        $output = $process->getOutput();
        if (null === $expected) {
            $response = json_decode($output, true, flags: \JSON_THROW_ON_ERROR);
            if (! is_array($response) || 1 !== ($response['version'] ?? null) || 1 !== ($response['id'] ?? null)
                || ! isset($response['documents']) || ! is_array($response['documents']) || 1000 !== count($response['documents'])
                || 1000 !== count(array_filter($response['documents'], is_string(...)))) {
                throw new RuntimeException('The first binary did not return a valid minification response.');
            }

            $expected = $output;
        }

        if ($output !== $expected) {
            throw new RuntimeException('Baseline and candidate outputs differ.');
        }

        $report['samples'][] = ['mode' => $mode, 'repeat' => $repeat, 'ms' => $elapsed];
    }
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
