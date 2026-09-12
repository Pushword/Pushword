<?php

declare(strict_types=1);

use Knp\Menu\ItemInterface;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ContentSplitter;

require __DIR__.'/../../../../vendor/autoload.php';

if (count($argv) < 2 || count($argv) > 3) {
    throw new InvalidArgumentException('Usage: time-downstream.php <rendered.ndjson> [analyzer-binary]');
}

$source = $argv[1];
$binary = $argv[2] ?? __DIR__.'/../target/release/pushword-content-analyzer';

/**
 * @return array<int, ItemInterface|string|string[]>
 */
function result(SplitContent $split): array
{
    return [$split->getBody(true), $split->getContentParts(), $split->getToc(), $split->getParagraphs(), $split->getParagraphs(true)];
}

$native = new ContentSplitter($binary);
$stream = fopen($source, 'r');
$samples = ['toc' => ['php' => [], 'rust' => []], 'no_toc' => ['php' => [], 'rust' => []]];
$index = 0;
while (false !== $line = fgets($stream)) {
    $case = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
    $page = new Page();
    if ($case['toc']) {
        $page->setCustomProperty('toc', true);
    }

    $category = $case['toc'] ? 'toc' : 'no_toc';
    $operations = [
        'php' => static fn (): array => result(new SplitContent($case['html'], $page)),
        'rust' => static fn (): array => result($native->split($case['html'], $page)),
    ];
    if ($index++ % 2) {
        $operations = array_reverse($operations, true);
    }

    $outputs = [];
    foreach ($operations as $name => $operation) {
        $start = hrtime(true);
        $outputs[$name] = $operation();
        $samples[$category][$name][] = (hrtime(true) - $start) / 1e6;
    }

    if ($outputs['php'] !== $outputs['rust']) {
        throw new RuntimeException('Parity drift on '.$case['page']);
    }
}

fclose($stream);
$native->reset();

$report = [];
foreach ($samples as $category => $modes) {
    $report[$category] = [];
    foreach ($modes as $name => $values) {
        sort($values, \SORT_NUMERIC);
        $report[$category][$name] = [
            'documents' => count($values),
            'sum_ms' => array_sum($values),
            'p50_ms' => $values[intdiv(count($values), 2)],
            'p95_ms' => $values[(int) (count($values) * .95)],
        ];
    }
}

echo json_encode($report, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
