<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Tempest\Markdown\Markdown;

$autoload = getenv('PUSHWORD_TEMPEST_AUTOLOAD') ?: __DIR__.'/../target/tempest/vendor/autoload.php';
if (! is_file($autoload)) {
    fwrite(\STDERR, "Tempest is not installed. Create an isolated Composer project and run: composer require tempest/markdown:1.2.2\n");
    exit(2);
}

require $autoload;

$markdown = new Markdown(null);
$corpus = json_decode((string) file_get_contents(__DIR__.'/../tests/markdown.json'), true, flags: \JSON_THROW_ON_ERROR);
$equal = [];
foreach ($corpus as $case) {
    $html = $markdown->parse($case['markdown'])->html;
    $equal[$case['name']] = $html === $case['php'];
}

$paragraph = "## A heading\n\nA paragraph with **bold**, *emphasis*, [a link](/docs), café 🦀 and `code`.\n\n- First item\n- Second item\n\n";
$workloads = [];
foreach (['short' => 2, 'article' => 80, 'long' => 800, 'duplicate_headings' => 800] as $name => $repetitions) {
    $source = '';
    for ($heading = 0; $heading < $repetitions; ++$heading) {
        $source .= 'duplicate_headings' === $name ? $paragraph : str_replace('A heading', 'Heading '.$heading, $paragraph);
    }

    $markdown->parse($source);
    $samples = [];
    for ($sample = 0; $sample < 9; ++$sample) {
        $start = hrtime(true);
        for ($document = 0; $document < 50; ++$document) {
            $markdown->parse($source);
        }

        $samples[] = (hrtime(true) - $start) / 1e6 / 50;
    }

    sort($samples, \SORT_NUMERIC);
    $workloads[$name] = [
        'markdown_bytes' => strlen($source),
        'median_ms_per_document' => $samples[4],
        'samples_ms_per_document' => $samples,
    ];
}

$parseManyAvailable = method_exists($markdown, 'parseMany');
$parseMany = [
    'available' => $parseManyAvailable,
    'marker' => '<!-- next -->',
    'pr' => 'https://github.com/tempestphp/markdown/pull/24',
];
if ($parseManyAvailable) {
    $source = implode("\n<!-- next -->\n", array_fill(0, 20, $paragraph));
    $markdown->parseMany($source);
    $samples = [];
    for ($sample = 0; $sample < 9; ++$sample) {
        $start = hrtime(true);
        for ($iteration = 0; $iteration < 20; ++$iteration) {
            $collection = $markdown->parseMany($source);
        }

        $samples[] = (hrtime(true) - $start) / 1e6 / 20;
    }

    sort($samples, \SORT_NUMERIC);
    $parseMany['chunks'] = count($collection);
    $parseMany['median_ms_per_call'] = $samples[4];
    $parseMany['samples_ms_per_call'] = $samples;
}

$version = null;
if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('tempest/markdown')) {
    $version = InstalledVersions::getPrettyVersion('tempest/markdown');
}

$equalNames = array_keys(array_filter($equal));
$differentNames = array_keys(array_filter($equal, static fn (bool $value): bool => ! $value));

echo json_encode([
    'engine' => 'tempest/markdown',
    'version' => $version,
    'php' => \PHP_VERSION,
    'corpus' => [
        'cases' => count($corpus),
        'equal_to_pushword_php' => count($equalNames),
        'equal_case_names' => $equalNames,
        'different_case_names' => $differentNames,
    ],
    'workloads' => $workloads,
    'parse_many' => $parseMany,
], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
