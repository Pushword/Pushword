<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require __DIR__.'/../../../../vendor/autoload.php';

const MODES = ['php', 'tempest', 'rust'];
const DATE_PATTERNS = [
    'Réservation date(Y), été date(S), hiver date(W) pour l’étape {n}.',
    'Voir [le programme date(Y+1)](/programme) en date(M).',
    'Le code `date(Y)` reste littéral, texte date(Y) pour l’étape {n}.',
];
const SYNTHETIC = [
    'Parcours {n} dans les collines.',
    '## Étape {n} **facile**',
    'Voir [la carte](/carte) pour l’étape {n}.',
    'Un `code` simple pour le trajet {n}.',
    "- Départ {n}\n- Arrivée {n}",
    "1. Première étape {n}\n2. Deuxième étape {n}",
    "| Nom | Valeur |\n|---|---|\n| Étape {n} | {n} |",
    "> Une question {n}\n> Une réponse simple.",
    'Prix & transport pour l’étape {n}.',
    'Une _petite marche_ avant l’étape {n}.',
    'Le numéro 01 23 45 67 89 pour l’étape {n}.',
    "> [!faq] Question {n}\n>\n> Une réponse courte.",
    'Voir [la carte](/carte "Carte du trajet {n}").',
    "| Offre | Deux | Trois |\n| --- | --- | --- |\n| Location {n} | 25 € | -> |\n| Assurance | 10 € |",
    "- Parent {n}\n    - Enfant\n        - Détail\n- Retour",
    'Hôtel 3*/4* pour le trajet {n}.',
    'Lisez _\\[note\\]_ avant le départ {n}.',
    'Réduction ~30€~ pour le trajet {n}.',
    'Distance ~170 km et dénivelé ~10 000 m pour l’étape {n}.',
    'Voir [la carte](/carte ) pour l’étape {n}.',
    'Appelez **04 76 95 23 00** pour l’étape {n}.',
    "| A | B |\n    | --- | --- |\n    | {n} | 2 |",
    "Départ {n}  \nArrivée après une courte marche.",
    '#### Fin de l’étape {n}',
    '* * *',
    ...DATE_PATTERNS,
];

function rssKiB(int $pid): int
{
    $status = @file_get_contents('/proc/'.$pid.'/status');

    return false !== $status && 1 === preg_match('/^VmRSS:\s*(\d+)/m', $status, $matches) ? (int) $matches[1] : 0;
}

/** @return list<int> */
function descendants(int $pid): array
{
    $children = @file_get_contents('/proc/'.$pid.'/task/'.$pid.'/children');
    if (false === $children || '' === trim($children)) {
        return [];
    }

    $direct = array_map(intval(...), preg_split('/\s+/', trim($children)) ?: []);
    $all = $direct;
    foreach ($direct as $child) {
        array_push($all, ...descendants($child));
    }

    return $all;
}

/** @return list<string> */
function workerCommand(string $mode, string $site, string $snapshot, string $binary): array
{
    return ['php', __DIR__.'/three-way-markdown.php', $mode, $site, $snapshot, $binary];
}

/**
 * @param array<string, string> $env
 *
 * @return array{mode: string, blocks: int, accepted: int, fallback: int, different: int, byte_different: int, dates: array{blocks: int, accepted: int, fallback: int, expected_mismatches: int}, conversion_seconds: float, zend_peak_bytes: int, digest: string, parent_peak_kib: int, child_peak_kib: int, tree_peak_kib: int}
 */
function sample(string $mode, string $site, string $snapshot, string $binary, ?int $cpu, array $env = []): array
{
    $command = workerCommand($mode, $site, $snapshot, $binary);
    if (null !== $cpu) {
        array_unshift($command, 'taskset', '-c', (string) $cpu);
    }

    $process = new Process($command, dirname(__DIR__, 4), $env);
    $process->setTimeout(null);
    $process->start();

    $parentPeak = 0;
    $childPeak = 0;
    $treePeak = 0;
    while ($process->isRunning()) {
        $pid = $process->getPid();
        $parent = null === $pid ? 0 : rssKiB($pid);
        $child = 0;
        foreach (null === $pid ? [] : descendants($pid) as $descendant) {
            $child += rssKiB($descendant);
        }

        $parentPeak = max($parentPeak, $parent);
        $childPeak = max($childPeak, $child);
        $treePeak = max($treePeak, $parent + $child);
        usleep(2000);
    }

    if (! $process->isSuccessful()) {
        throw new RuntimeException($mode.': '.$process->getErrorOutput());
    }

    $decoded = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
    $dates = is_array($decoded) ? ($decoded['dates'] ?? null) : null;
    if (! is_array($decoded) || ! is_string($decoded['mode'] ?? null)
        || ! is_int($decoded['blocks'] ?? null) || ! is_int($decoded['accepted'] ?? null)
        || ! is_int($decoded['fallback'] ?? null) || ! is_int($decoded['different'] ?? null) || ! is_int($decoded['byte_different'] ?? null)
        || ! is_float($decoded['conversion_seconds'] ?? null) || ! is_int($decoded['zend_peak_bytes'] ?? null)
        || ! is_string($decoded['digest'] ?? null) || ! is_array($dates)
        || ! is_int($dates['blocks'] ?? null) || ! is_int($dates['accepted'] ?? null)
        || ! is_int($dates['fallback'] ?? null) || ! is_int($dates['expected_mismatches'] ?? null)) {
        throw new RuntimeException($mode.': invalid worker report.');
    }

    return [
        'mode' => $decoded['mode'],
        'blocks' => $decoded['blocks'],
        'accepted' => $decoded['accepted'],
        'fallback' => $decoded['fallback'],
        'different' => $decoded['different'],
        'byte_different' => $decoded['byte_different'],
        'dates' => [
            'blocks' => $dates['blocks'],
            'accepted' => $dates['accepted'],
            'fallback' => $dates['fallback'],
            'expected_mismatches' => $dates['expected_mismatches'],
        ],
        'conversion_seconds' => $decoded['conversion_seconds'],
        'zend_peak_bytes' => $decoded['zend_peak_bytes'],
        'digest' => $decoded['digest'],
        'parent_peak_kib' => $parentPeak,
        'child_peak_kib' => $childPeak,
        'tree_peak_kib' => $treePeak,
    ];
}

function sha256(string $path): string
{
    return hash_file('sha256', $path) ?: throw new RuntimeException('Unreadable file: '.$path);
}

function gitRevision(string $path): string
{
    $process = new Process(['git', '-C', $path, 'rev-parse', 'HEAD']);
    $process->mustRun();

    return trim($process->getOutput());
}

/** @param array<string, string> $env */
function syntheticSnapshot(string $path, int $blocks, string $site, string $binary, array $env): void
{
    $raw = dirname($path).'/raw.ndjson';
    $stream = fopen($raw, 'w');
    if (false === $stream) {
        throw new RuntimeException('Cannot write synthetic corpus.');
    }

    try {
        for ($index = 0; $index < $blocks; ++$index) {
            $pattern = SYNTHETIC[$index % count(SYNTHETIC)];
            $markdown = str_replace('{n}', (string) $index, $pattern);
            $encoded = json_encode($markdown, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
            $expected = in_array($pattern, DATE_PATTERNS, true) ? ', "native_expected": true' : '';
            // Keep the original corpus bytes so existing snapshot hashes remain comparable.
            fwrite($stream, sprintf('{"page": "localhost.dev/benchmark", "block": %d, "markdown": %s, "pre_class": ""%s}', $index, $encoded, $expected)."\n");
        }
    } finally {
        fclose($stream);
    }

    $process = new Process(workerCommand('prepare', $site, $raw, $binary), dirname(__DIR__, 4), $env);
    $process->setTimeout(null);
    $process->mustRun();
    if (false === file_put_contents($path, $process->getOutput())) {
        throw new RuntimeException('Cannot write prepared snapshot.');
    }
}

/**
 * @param list<int|float> $values
 */
function median(array $values): int|float
{
    sort($values, \SORT_NUMERIC);
    $middle = intdiv(count($values), 2);

    return 1 === count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
}

/**
 * @param array<string, string> $env
 *
 * @return array<string, mixed>
 */
function runBenchmark(string $site, string $snapshot, string $binary, ?int $cpu, int $runs, string $corpus, bool $requireNativeDates, array $env = []): array
{
    $samples = [];
    for ($round = 0; $round < $runs; ++$round) {
        $offset = $round % count(MODES);
        $modes = array_merge(array_slice(MODES, $offset), array_slice(MODES, 0, $offset));
        foreach ($modes as $mode) {
            $result = sample($mode, $site, $snapshot, $binary, $cpu, $env);
            $samples[] = $result;
            fwrite(\STDERR, sprintf("%s: %.3fs, %.1f MiB\n", $mode, $result['conversion_seconds'], $result['tree_peak_kib'] / 1024));
        }
    }

    if (1 !== count(array_unique(array_column($samples, 'digest')))
        || 1 !== count(array_unique(array_column($samples, 'blocks')))
        || [] !== array_filter($samples, static fn (array $result): bool => (bool) $result['different'])) {
        throw new RuntimeException('Output parity failed.');
    }

    $rustSamples = array_values(array_filter($samples, static fn (array $result): bool => 'rust' === $result['mode']));
    foreach ($rustSamples as $result) {
        if (0 !== $result['dates']['expected_mismatches']) {
            throw new RuntimeException('Native acceptance differs from the snapshot expectation.');
        }

        if ($requireNativeDates && (0 === $result['dates']['blocks'] || 0 !== $result['dates']['fallback'])) {
            throw new RuntimeException('A date block fell back to PHP (or the snapshot contains no dates).');
        }
    }

    $medians = [];
    foreach (MODES as $mode) {
        $modeSamples = array_values(array_filter($samples, static fn (array $result): bool => $mode === $result['mode']));
        foreach (['conversion_seconds', 'parent_peak_kib', 'child_peak_kib', 'tree_peak_kib', 'zend_peak_bytes'] as $key) {
            $medians[$mode][$key] = median(array_column($modeSamples, $key));
        }
    }

    return [
        'corpus' => $corpus,
        'site_revision' => gitRevision($site),
        'pushword_revision' => gitRevision(dirname(__DIR__, 4)),
        'snapshot_sha256' => sha256($snapshot),
        'binary_sha256' => sha256($binary),
        'blocks' => $samples[0]['blocks'],
        'cpu_affinity' => $cpu,
        'passes' => $runs,
        'output_sha256' => $samples[0]['digest'],
        'dates' => $rustSamples[0]['dates'],
        'samples' => $samples,
        'medians' => $medians,
    ];
}

$usage = 'Usage: php three-way-markdown-runner.php [--site PATH --snapshot PATH] [--blocks N] [--binary PATH] [--cpu N] [--runs N] [--require-native-dates]';
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

$siteOption = null;
$snapshotOption = null;
$blocksOption = '24000';
$runsOption = '3';
$cpuOption = null;
$binaryOption = dirname(__DIR__).'/target/release/pushword-content-analyzer';
$requireNativeDates = false;
$counter = count($arguments);
for ($index = 1; $index < $counter; ++$index) {
    $parts = explode('=', $arguments[$index], 2);
    $name = $parts[0];
    $value = $parts[1] ?? null;
    if ('--help' === $name && null === $value) {
        echo $usage."\n";
        exit;
    }

    if ('--require-native-dates' === $name && null === $value) {
        $requireNativeDates = true;

        continue;
    }

    if (! in_array($name, ['--site', '--snapshot', '--blocks', '--runs', '--cpu', '--binary'], true)) {
        throw new InvalidArgumentException($usage);
    }

    $value ??= $arguments[++$index] ?? null;
    if (null === $value || str_starts_with($value, '--')) {
        throw new InvalidArgumentException($usage);
    }

    switch ($name) {
        case '--site':
            $siteOption = $value;

            break;
        case '--snapshot':
            $snapshotOption = $value;

            break;
        case '--blocks':
            $blocksOption = $value;

            break;
        case '--runs':
            $runsOption = $value;

            break;
        case '--cpu':
            $cpuOption = $value;

            break;
        case '--binary':
            $binaryOption = $value;

            break;
    }
}

if (! ctype_digit($blocksOption) || 0 === (int) $blocksOption
    || ! ctype_digit($runsOption) || 0 === (int) $runsOption
    || (null !== $cpuOption && ! ctype_digit($cpuOption))
    || (null === $siteOption) !== (null === $snapshotOption)) {
    throw new InvalidArgumentException($usage);
}

$binary = realpath($binaryOption) ?: throw new RuntimeException('Analyzer binary does not exist: '.$binaryOption);
$cpu = null === $cpuOption ? null : (int) $cpuOption;
$runs = (int) $runsOption;
if (null !== $siteOption && null !== $snapshotOption) {
    $site = realpath($siteOption) ?: throw new RuntimeException('Site does not exist: '.$siteOption);
    $snapshot = realpath($snapshotOption) ?: throw new RuntimeException('Snapshot does not exist: '.$snapshotOption);
    $report = runBenchmark($site, $snapshot, $binary, $cpu, $runs, 'site snapshot', $requireNativeDates);
} else {
    $site = dirname(__DIR__, 4).'/packages/dev-app';
    $directory = sys_get_temp_dir().'/pushword-markdown-bench-'.bin2hex(random_bytes(8));
    $filesystem = new Filesystem();
    $filesystem->mkdir($directory);

    try {
        foreach (['var', 'media', 'media-cache', 'content'] as $name) {
            $filesystem->mkdir($directory.'/'.$name);
        }

        $database = $directory.'/benchmark.db';
        touch($database);
        $env = [
            'PUSHWORD_TEST_VAR_DIR' => $directory.'/var',
            'PUSHWORD_TEST_MEDIA_DIR' => $directory.'/media',
            'PUSHWORD_TEST_MEDIA_CACHE_DIR' => $directory.'/media-cache',
            'PUSHWORD_TEST_FLAT_CONTENT_DIR' => $directory.'/content',
            'PUSHWORD_TEST_DATABASE_URL' => 'sqlite:///'.$database,
        ];
        $snapshot = $directory.'/synthetic.ndjson';
        syntheticSnapshot($snapshot, (int) $blocksOption, $site, $binary, $env);
        $report = runBenchmark($site, $snapshot, $binary, $cpu, $runs, 'synthetic '.$blocksOption.' blocks / '.count(SYNTHETIC).' patterns', $requireNativeDates, $env);
    } finally {
        $filesystem->remove($directory);
    }
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR)."\n";
