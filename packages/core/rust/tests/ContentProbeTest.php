<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Rust;

use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Service\Typographer;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Tests\Support\HtmlEquivalence;
use Pushword\Core\Twig\MediaExtension;
use Pushword\Search\Service\TextExtractor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\Process;
use Twig\Environment;

/** Reproducible research; no runtime service uses this prototype. */
final class ContentProbeTest extends KernelTestCase
{
    public function testProbeProtocolBoundaries(): void
    {
        self::assertSame([], $this->native([]));
        foreach (['not JSON', "\xff", '{"documents":[null]}', '{"documents":[],"unknown":true}', '{"documents":[],"fenced_code_pre_class":null}', '{"documents":[],"fenced_code_pre_class":[]}', str_repeat('x', 16 * 1024 * 1024 + 1)] as $input) {
            $process = new Process([__DIR__.'/../target/release/pushword-content-probe'], input: $input);
            $process->run();
            self::assertFalse($process->isSuccessful());
            self::assertSame('', $process->getOutput());
            self::assertNotSame('', $process->getErrorOutput());
        }
    }

    public function testCompatibilityAndMeasureCandidateBoundaries(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $sites = $container->get(SiteRegistry::class);
        $sites->switchSite('localhost.dev');

        $parser = new MarkdownParser($container->get(LinkProvider::class), $container->get(MediaExtension::class), $sites, $container->get(Environment::class));
        $cached = new MarkdownParser($container->get(LinkProvider::class), $container->get(MediaExtension::class), $sites, $container->get(Environment::class), new ArrayAdapter());
        $typographer = new Typographer();
        $structure = static fn (string $html): array => HtmlEquivalence::structure($typographer->fix($html, $sites->getLocale()));

        $json = file_get_contents(__DIR__.'/markdown.json');
        self::assertIsString($json);
        $cases = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);
        $report = ['engine' => 'comrak', 'php' => \PHP_VERSION, 'libxml' => \LIBXML_DOTTED_VERSION, 'compatibility' => [], 'samples' => []];
        $referenceDrift = [];
        $compatibilityDrift = [];
        $preClass = $sites->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS);
        foreach ($cases as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['name']);
            self::assertIsString($case['markdown']);
            self::assertIsString($case['php']);
            self::assertIsString($case['pre_class']);
            self::assertIsBool($case['compatible']);
            $sites->get()->setCustomProperty(SiteConfig::FENCED_CODE_PRE_CLASS, $case['pre_class']);
            $php = $parser->transform($case['markdown']);
            $rendered = $structure($php);
            if ($structure($case['php']) !== $rendered) {
                $referenceDrift[] = $case['name'];
            }

            $native = $this->native([$case['markdown']], $case['pre_class'])[0];
            $equal = $rendered === $structure($native);
            if ($case['compatible'] && ! $equal) {
                $compatibilityDrift[] = $case['name'];
            }

            $report['compatibility'][$case['name']] = ['equal' => $equal, 'pre_class' => $case['pre_class'], 'php' => $php, 'rust' => $native];
        }

        self::assertSame([], $referenceDrift, 'PHP reference drift');
        self::assertSame([], $compatibilityDrift, 'Compatibility changed');

        $sites->get()->setCustomProperty(SiteConfig::FENCED_CODE_PRE_CLASS, $preClass);

        $paragraph = "## A heading\n\nA paragraph with **bold**, *emphasis*, [a link](/docs), café 🦀 and `code`.\n\n- First item\n- Second item\n\n";
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->setCustomProperty('toc', true);
        foreach (['short' => 2, 'article' => 80, 'long' => 800, 'duplicate_headings' => 800] as $size => $repeatParagraph) {
            $markdown = '';
            for ($heading = 0; $heading < $repeatParagraph; ++$heading) {
                $markdown .= 'duplicate_headings' === $size ? $paragraph : str_replace('A heading', 'Heading '.$heading, $paragraph);
            }

            $html = $parser->transform($markdown);
            $expected = $structure($html);
            self::assertSame($expected, $structure($this->native([$markdown])[0]), 'Timed Markdown subset must have equivalent HTML');
            $cached->transform($markdown);
            $tocCache = new ArrayAdapter();
            $fixed = new SplitContent($html, $page)->getBody();
            self::assertSame($fixed, new SplitContent($html, $page, $tocCache)->getBody());
            $operations = [
                'markdown_php_uncached' => static fn (): string => $parser->transform($markdown),
                'markdown_php_cache_hit' => static fn (): string => $cached->transform($markdown),
                'toc_php_uncached' => static fn (): string => new SplitContent($html, $page)->getBody(),
                'toc_php_cache_hit' => static fn (): string => new SplitContent($html, $page, $tocCache)->getBody(),
                'search_text_php' => static fn (): string => TextExtractor::toPlainText($html),
            ];
            $report['inputs'][$size] = ['markdown_bytes' => \strlen($markdown), 'html_bytes' => \strlen($html), 'markdown_sha256' => hash('sha256', $markdown)];
            for ($repeat = 0; $repeat < 5; ++$repeat) {
                $modes = array_keys($operations);
                $modes[] = 'markdown_rust_batch';
                shuffle($modes);
                foreach ($modes as $mode) {
                    $start = hrtime(true);
                    if ('markdown_rust_batch' === $mode) {
                        $actual = $this->native(array_fill(0, 20, $markdown));
                    } else {
                        $actual = [];
                        for ($i = 0; $i < 20; ++$i) {
                            $actual[] = $operations[$mode]();
                        }
                    }

                    $elapsed = (hrtime(true) - $start) / 1e6;
                    if (str_starts_with($mode, 'markdown_')) {
                        foreach ($actual as $rendered) {
                            self::assertSame($expected, $structure($rendered));
                        }
                    }

                    $report['samples'][] = ['size' => $size, 'mode' => $mode, 'repeat' => $repeat, 'documents' => 20, 'ms' => $elapsed];
                }
            }
        }

        file_put_contents(__DIR__.'/../target/content-probe-comrak.json', json_encode($report, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR)."\n");
    }

    /**
     * @param list<string> $documents
     *
     * @return list<string>
     */
    private function native(array $documents, string $preClass = ''): array
    {
        $process = new Process([__DIR__.'/../target/release/pushword-content-probe'], input: json_encode(['documents' => $documents, 'fenced_code_pre_class' => $preClass], \JSON_THROW_ON_ERROR));
        $process->mustRun();

        $output = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($output);
        self::assertTrue(array_is_list($output));
        self::assertCount(\count($documents), $output);
        $validated = [];
        foreach ($output as $html) {
            self::assertIsString($html);
            $validated[] = $html;
        }

        return $validated;
    }
}
