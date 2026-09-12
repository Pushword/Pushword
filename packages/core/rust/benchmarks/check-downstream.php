<?php

declare(strict_types=1);

use Knp\Menu\ItemInterface;
use Pushword\Core\Component\EntityFilter\ValueObject\PreparedSplitContent;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\NativeWorker;

require __DIR__.'/../../../../vendor/autoload.php';

if (count($argv) < 2 || count($argv) > 3) {
    throw new InvalidArgumentException('Usage: check-downstream.php <rendered.ndjson> [analyzer-binary]');
}

$source = $argv[1];
$binary = $argv[2] ?? __DIR__.'/../target/release/pushword-content-analyzer';

function menu(ItemInterface|string $item): array|string
{
    if (is_string($item)) {
        return $item;
    }

    $children = [];
    foreach ($item->getChildren() as $key => $child) {
        $children[$key] = menu($child);
    }

    return [$item->getName(), $item->getLabel(), $item->getUri(), $item->getLevel(), $children];
}

/**
 * @return array<string, ItemInterface|string|mixed[]>
 */
function snapshot(SplitContent $split): array
{
    return [
        'chapeau' => $split->getChapeau(),
        'intro' => $split->getIntro(),
        'content' => $split->getContent(),
        'parts' => $split->getContentParts(),
        'body' => $split->getBody(true),
        'toc' => $split->getToc(),
        'menu' => menu($split->getToc(false)),
        'paragraphs' => $split->getParagraphs(),
        'paragraphs_all' => $split->getParagraphs(true),
    ];
}

$worker = new NativeWorker($binary);
$stream = fopen($source, 'r');
$counts = ['pages' => 0, 'accepted' => 0, 'declined' => 0, 'equal' => 0, 'mismatches' => [], 'exceptions' => []];
$examples = [];
while (true) {
    $cases = [];
    for ($i = 0; $i < 10; ++$i) {
        $line = fgets($stream);
        if (false === $line) {
            break;
        }

        $cases[] = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
    }

    if ([] === $cases) {
        break;
    }

    $response = $worker->request('split_content', array_map(static fn (array $case): array => ['html' => $case['html'], 'toc' => $case['toc']], $cases));
    foreach ($cases as $index => $case) {
        ++$counts['pages'];
        if (null === $response[$index]) {
            ++$counts['declined'];

            continue;
        }

        ++$counts['accepted'];

        try {
            $page = new Page();
            if ($case['toc']) {
                $page->setCustomProperty('toc', true);
            }

            $expected = snapshot(new SplitContent($case['html'], $page));
            $prepared = new PreparedSplitContent($response[$index]);
            $actual = snapshot(new SplitContent($case['html'], $page, prepared: $prepared));
            if ($actual === $expected) {
                ++$counts['equal'];
            } else {
                $fields = array_keys(array_filter($expected, static fn ($value, $key): bool => $value !== $actual[$key], \ARRAY_FILTER_USE_BOTH));
                foreach ($fields as $field) {
                    $counts['mismatches'][$field] = ($counts['mismatches'][$field] ?? 0) + 1;
                }

                if (count($examples) < 25) {
                    $examples[] = [$case['page'], $fields];
                }
            }
        } catch (Throwable $error) {
            $name = $error::class;
            $counts['exceptions'][$name] = ($counts['exceptions'][$name] ?? 0) + 1;
            if (count($examples) < 25) {
                $examples[] = [$case['page'], $name];
            }
        }
    }
}

$worker->reset();
fclose($stream);
echo json_encode(['counts' => $counts, 'examples' => $examples], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n";
if (0 !== $counts['declined'] || 0 !== array_sum($counts['mismatches']) || 0 !== array_sum($counts['exceptions'])) {
    exit(1);
}
