<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../../../../vendor/autoload.php';

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

if (! in_array(count($arguments), [3, 4], true) || (4 === count($arguments) && '--supported-only' !== $arguments[3])) {
    throw new InvalidArgumentException('Usage: php check-markdown-downstream.php <private.ndjson> <probe-binary> [--supported-only]');
}

/** @param array{markdown: string, php: string} $case
 * @return list<string>
 */
function features(array $case): array
{
    $source = $case['markdown'];
    $html = $case['php'];
    $checks = [
        'obfuscated_link' => 1 === preg_match('/#\[[^]]+\]\(/', $source),
        'notice' => str_contains($source, '> [!'),
        'image' => str_contains($source, '!['),
        'phone' => str_contains($html, 'data-rot="gry:'),
        'email' => str_contains($html, 'class=nojs'),
        'date' => str_contains($source, 'date('),
        'attributes' => 1 === preg_match('/\{[.#]|\{(?:id|class)=/', $source),
    ];

    $present = array_keys(array_filter($checks));

    return [] === $present ? ['unclassified'] : $present;
}

/**
 * @param list<array{page: string, markdown: string, pre_class: string, php: string}> $cases
 * @param array<string, int>                                                          $counts
 * @param array<string, bool>                                                         $pageEqual
 */
function compare(string $binary, array $cases, array &$counts, array &$pageEqual, bool $supportedOnly): void
{
    if ([] === $cases) {
        return;
    }

    $request = json_encode([
        'documents' => array_column($cases, 'markdown'),
        'fenced_code_pre_class' => $cases[0]['pre_class'],
        'supported_only' => $supportedOnly,
    ], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    $process = new Process([$binary]);
    $process->setInput($request);
    $process->setTimeout(null);
    $process->mustRun();

    $output = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
    if (! is_array($output) || count($output) !== count($cases)) {
        throw new RuntimeException('Probe returned a different document count.');
    }

    foreach ($cases as $index => $case) {
        $counts['blocks'] = ($counts['blocks'] ?? 0) + 1;
        $page = $case['page'];
        $pageEqual[$page] ??= true;
        $actual = $output[$index];
        if (null === $actual && $supportedOnly) {
            $counts['declined_to_php'] = ($counts['declined_to_php'] ?? 0) + 1;

            continue;
        }

        if ($actual === $case['php']) {
            $counts['exact'] = ($counts['exact'] ?? 0) + 1;
            if ($supportedOnly) {
                $counts['accepted_native'] = ($counts['accepted_native'] ?? 0) + 1;
            }
        } else {
            $counts['mismatch'] = ($counts['mismatch'] ?? 0) + 1;
            $pageEqual[$page] = false;
            $names = features($case);
            $siteFeatures = ['obfuscated_link', 'notice', 'image', 'phone', 'email', 'date'];
            $category = [] !== array_intersect($names, $siteFeatures) ? 'mismatch_with_site_features' : 'mismatch_without_site_features';
            $counts[$category] = ($counts[$category] ?? 0) + 1;
            foreach ($names as $name) {
                $key = 'mismatch_'.$name;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }
    }
}

$source = $arguments[1];
$binary = $arguments[2];
$supportedOnly = 4 === count($arguments);
$counts = [];
$pageEqual = [];
$batch = [];
$batchBytes = 0;
$stream = fopen($source, 'r');
if (false === $stream) {
    throw new RuntimeException('Cannot open snapshot: '.$source);
}

try {
    while (false !== $line = fgets($stream)) {
        $decoded = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || ! is_string($decoded['page'] ?? null)
            || ! is_string($decoded['markdown'] ?? null) || ! is_string($decoded['pre_class'] ?? null)
            || ! is_string($decoded['php'] ?? null)) {
            throw new RuntimeException('Invalid Markdown snapshot record.');
        }

        $case = [
            'page' => $decoded['page'],
            'markdown' => $decoded['markdown'],
            'pre_class' => $decoded['pre_class'],
            'php' => $decoded['php'],
        ];
        $size = strlen($case['markdown']);
        if ($size > 4_000_000) {
            throw new RuntimeException('A Markdown block exceeds the probe batch limit.');
        }

        if ([] !== $batch && (count($batch) >= 250 || $batchBytes + $size > 4_000_000 || $case['pre_class'] !== $batch[0]['pre_class'])) {
            compare($binary, $batch, $counts, $pageEqual, $supportedOnly);
            $batch = [];
            $batchBytes = 0;
        }

        $batch[] = $case;
        $batchBytes += $size;
    }

    compare($binary, $batch, $counts, $pageEqual, $supportedOnly);
} finally {
    fclose($stream);
}

ksort($counts);
echo json_encode([
    'snapshot_sha256' => hash_file('sha256', $source),
    'probe_sha256' => hash_file('sha256', $binary),
    'supported_only' => $supportedOnly,
    'pages' => count($pageEqual),
    'pages_exact' => count(array_filter($pageEqual)),
    'counts' => (object) $counts,
], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
