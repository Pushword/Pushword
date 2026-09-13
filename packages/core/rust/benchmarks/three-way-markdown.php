<?php

declare(strict_types=1);

use App\Kernel;
use League\CommonMark\MarkdownConverter;
use Pushword\Core\Component\EntityFilter\Filter\Date;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Service\Markdown\TempestMarkdownRenderer;
use Pushword\Core\Service\NativeWorker;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Component\Dotenv\Dotenv;
use Twig\Environment;

if (5 !== $argc || ! in_array($argv[1], ['prepare', 'php', 'tempest', 'rust'], true)) {
    throw new InvalidArgumentException('Usage: three-way-markdown.php <prepare|php|tempest|rust> <site-root> <snapshot.ndjson> <analyzer-binary>');
}

[, $mode, $siteRoot, $snapshot, $binary] = $argv;
$siteRoot = realpath($siteRoot);
$repoRoot = dirname(__DIR__, 4);
$autoload = $siteRoot === realpath($repoRoot.'/packages/dev-app') ? $repoRoot.'/vendor/autoload.php' : $siteRoot.'/vendor/autoload.php';
if (false === $siteRoot || ! is_file($autoload)) {
    throw new InvalidArgumentException('The site needs vendor/autoload.php.');
}

chdir($siteRoot);
require_once $autoload;
new Dotenv()->bootEnv('.env');
$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$sites = $container->get(SiteRegistry::class);
$links = $container->get(LinkProvider::class);
$media = $container->get(MediaExtension::class);
$twig = $container->get(Environment::class);
$parser = new MarkdownParser($links, $media, $sites, $twig);
$converter = new ReflectionProperty(MarkdownParser::class, 'converter')->getValue($parser);
require_once $repoRoot.'/vendor/autoload.php';
$tempest = 'tempest' === $mode ? new TempestMarkdownRenderer($links, $sites, $twig, $media) : null;
$worker = 'rust' === $mode ? new NativeWorker($binary) : null;
$dateFilter = new Date($sites);
$stream = fopen($snapshot, 'r');
if (false === $stream) {
    throw new RuntimeException('Cannot open snapshot.');
}

if ('prepare' === $mode) {
    $currentHost = null;
    while (false !== $line = fgets($stream)) {
        $record = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        $host = explode('/', $record['page'], 2)[0];
        if ($currentHost !== $host) {
            $sites->switchSite($host);
            $currentHost = $host;
        }

        $record['php'] = $converter->convert($record['markdown'])->__toString();
        echo json_encode($record, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)."\n";
    }

    fclose($stream);
    $kernel->shutdown();

    exit;
}

$digest = hash_init('sha256');
$nanoseconds = 0;
$blocks = 0;
$accepted = 0;
$fallback = 0;
$different = 0;
$dateStats = ['blocks' => 0, 'accepted' => 0, 'fallback' => 0, 'expected_mismatches' => 0];
$currentHost = null;

/**
 * @param array<string, mixed> $record
 */
function checkOutput(array $record, string $output, HashContext $digest, int &$blocks, int &$different): void
{
    ++$blocks;
    if ($output !== $record['php']) {
        ++$different;
    }

    hash_update($digest, $output);
}

/**
 * @param mixed[][]                                                                  $batch
 * @param array{blocks: int, accepted: int, fallback: int, expected_mismatches: int} $dateStats
 */
function runRustBatch(array $batch, NativeWorker $worker, SiteRegistry $sites, Date $dateFilter, MarkdownConverter $converter, HashContext $digest, int &$nanoseconds, int &$blocks, int &$accepted, int &$fallback, int &$different, array &$dateStats, ?string &$currentHost): void
{
    $start = hrtime(true);
    $request = array_map(static function (array $record) use ($sites, $dateFilter): array {
        $locale = $sites->get(explode('/', $record['page'], 2)[0])->locale;
        $document = [
            'markdown' => $record['markdown'],
            'fenced_code_pre_class' => $record['pre_class'],
            'locale' => $locale,
            'allow_obfuscated_links' => true,
        ];
        if (str_contains($record['markdown'], 'date(')) {
            preg_match_all('/date\([^)]+\)/', $record['markdown'], $matches);
            $values = [];
            foreach (array_unique($matches[0]) as $shortcode) {
                $values[$shortcode] = $dateFilter->convertDateShortCode($shortcode, $locale);
            }

            if ([] !== $values) {
                $document['date_values'] = $values;
            }
        }

        return $document;
    }, $batch);
    $responses = $worker->request('render_markdown', $request);
    $nanoseconds += hrtime(true) - $start;
    foreach ($batch as $index => $record) {
        $output = $responses[$index];
        if (str_contains($record['markdown'], 'date(')) {
            ++$dateStats['blocks'];
            ++$dateStats[null === $output ? 'fallback' : 'accepted'];
            if (isset($record['native_expected']) && $record['native_expected'] !== (null !== $output)) {
                ++$dateStats['expected_mismatches'];
            }
        }

        if (null === $output) {
            $host = explode('/', $record['page'], 2)[0];
            if ($currentHost !== $host) {
                $sites->switchSite($host);
                $currentHost = $host;
            }

            $start = hrtime(true);
            $output = $converter->convert($record['markdown'])->__toString();
            $nanoseconds += hrtime(true) - $start;
            ++$fallback;
        } else {
            ++$accepted;
        }

        checkOutput($record, $output, $digest, $blocks, $different);
    }
}

if ('rust' === $mode) {
    $batch = [];
    $batchBytes = 0;
    while (false !== $line = fgets($stream)) {
        $record = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        $size = strlen($record['markdown']);
        if ([] !== $batch && (count($batch) >= 250 || $batchBytes + $size > 4_000_000 || $record['pre_class'] !== $batch[0]['pre_class'])) {
            runRustBatch($batch, $worker, $sites, $dateFilter, $converter, $digest, $nanoseconds, $blocks, $accepted, $fallback, $different, $dateStats, $currentHost);
            $batch = [];
            $batchBytes = 0;
        }

        $batch[] = $record;
        $batchBytes += $size;
    }

    if ([] !== $batch) {
        runRustBatch($batch, $worker, $sites, $dateFilter, $converter, $digest, $nanoseconds, $blocks, $accepted, $fallback, $different, $dateStats, $currentHost);
    }

    $worker->reset();
} else {
    while (false !== $line = fgets($stream)) {
        $record = json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        $host = explode('/', $record['page'], 2)[0];
        if ($currentHost !== $host) {
            $sites->switchSite($host);
            $currentHost = $host;
        }

        $start = hrtime(true);
        $output = 'tempest' === $mode ? $tempest->render($record['markdown']) : $converter->convert($record['markdown'])->__toString();
        $nanoseconds += hrtime(true) - $start;
        if (null === $output) {
            ++$fallback;
            $start = hrtime(true);
            $output = $converter->convert($record['markdown'])->__toString();
            $nanoseconds += hrtime(true) - $start;
        } else {
            ++$accepted;
        }

        checkOutput($record, $output, $digest, $blocks, $different);
    }
}

fclose($stream);
$kernel->shutdown();
echo json_encode([
    'mode' => $mode,
    'blocks' => $blocks,
    'accepted' => $accepted,
    'fallback' => $fallback,
    'different' => $different,
    'dates' => $dateStats,
    'conversion_seconds' => $nanoseconds / 1e9,
    'zend_peak_bytes' => memory_get_peak_usage(true),
    'digest' => hash_final($digest),
], \JSON_THROW_ON_ERROR)."\n";
