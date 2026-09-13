<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Rust;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\LinkProvider;
use Pushword\Core\Service\Markdown\MarkdownParser;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\MediaExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\Process;
use Twig\Environment;

final class NativeMarkdownRendererTest extends KernelTestCase
{
    private const string BINARY = __DIR__.'/../target/release/pushword-content-analyzer';

    private function parser(?string $binary = null, ?ArrayAdapter $cache = null, ?LoggerInterface $logger = null): MarkdownParser
    {
        $container = self::getContainer();

        return new MarkdownParser(
            $container->get(LinkProvider::class),
            $container->get(MediaExtension::class),
            $container->get(SiteRegistry::class),
            $container->get(Environment::class),
            $cache,
            nativeBinary: $binary,
            logger: $logger ?? new NullLogger(),
        );
    }

    public function testBatchPreservesOrderAndDeclinesSiteFeatures(): void
    {
        self::bootKernel();
        $sites = self::getContainer()->get(SiteRegistry::class);
        $sites->switchSite('localhost.dev');

        $parser = $this->parser(self::BINARY);
        self::assertSame([], $parser->renderNativeMany([]));
        self::assertTrue($parser->hasNativeMarkdown());

        $sources = [
            'First **bold** paragraph.',
            '#[private](/path)',
            '#[*Café*](https://example.com/café){.button target="_blank"}',
            'A [normal link](/path).',
            '![alt](/image.jpg)',
            '> [!note] Notice',
            'Call 01 23 45 67 89',
            'contact@example.com',
            'date(Y)',
        ];
        $result = $parser->renderNativeMany($sources);
        $php = $this->parser();
        foreach ($sources as $index => $source) {
            if (null !== $result[$index]) {
                self::assertSame($php->transform($source), $result[$index], $source);
            }
        }

        self::assertSame([false, false, false, false, true, true, true, true, true], array_map(static fn (?string $html): bool => null === $html, $result));
        $parser->reset();
    }

    public function testSharedMarkdownCorpusUsesNativeOnlyForExactResults(): void
    {
        self::bootKernel();
        $sites = self::getContainer()->get(SiteRegistry::class);
        $sites->switchSite('localhost.dev');

        $json = file_get_contents(__DIR__.'/markdown.json');
        self::assertIsString($json);
        $cases = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);
        $groups = [];
        foreach ($cases as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['name']);
            self::assertIsString($case['markdown']);
            self::assertIsString($case['pre_class']);
            $groups[$case['pre_class']][] = $case;
        }

        $parser = $this->parser(self::BINARY);
        $accepted = 0;
        $declined = 0;
        $original = $sites->get()->getStr(SiteConfig::FENCED_CODE_PRE_CLASS);
        foreach ($groups as $preClass => $group) {
            $sites->get()->setCustomProperty(SiteConfig::FENCED_CODE_PRE_CLASS, $preClass);
            $sources = array_column($group, 'markdown');
            $result = $parser->renderNativeMany($sources);
            foreach ($group as $index => $case) {
                if (null === $result[$index]) {
                    ++$declined;

                    continue;
                }

                self::assertSame($case['php'], $result[$index], $case['name']);
                ++$accepted;
            }
        }

        $sites->get()->setCustomProperty(SiteConfig::FENCED_CODE_PRE_CLASS, $original);
        self::assertGreaterThan(0, $accepted);
        self::assertGreaterThan(0, $declined);
        $parser->reset();
    }

    public function testNativeBatchUsesExistingMarkdownCache(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');
        $pool = new ArrayAdapter();
        $parser = $this->parser(self::BINARY, $pool);
        $source = 'A **cached** paragraph.';
        self::assertSame([$this->parser()->transform($source)], $parser->renderNativeMany([$source]));

        $key = 'pw_mdn1.'.hash('xxh3', '9|'.$source);
        $item = $pool->getItem($key);
        self::assertTrue($item->isHit());
        $item->set('FROM CACHE');
        $pool->save($item);
        self::assertSame(['FROM CACHE'], $parser->renderNativeMany([$source]));
        self::assertSame($this->parser()->transform($source), $this->parser(cache: $pool)->transform($source));
        $parser->reset();
    }

    public function testMissingWorkerFallsBackAndCanReset(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $parser = $this->parser(__DIR__.'/missing-worker', logger: $logger);
        self::assertSame([null, null], $parser->renderNativeMany(['First', 'Second']));
        self::assertFalse($parser->hasNativeMarkdown());
        self::assertSame([null], $parser->renderNativeMany(['Third']));
        $parser->reset();
        self::assertTrue($parser->hasNativeMarkdown());
    }

    public function testMarkdownWorkerRejectsInvalidDocuments(): void
    {
        foreach ([
            ['markdown' => null, 'fenced_code_pre_class' => ''],
            ['markdown' => 'Text'],
            ['markdown' => 'Text', 'fenced_code_pre_class' => '', 'unexpected' => true],
        ] as $document) {
            $frame = json_encode(['version' => 1, 'id' => 1, 'operation' => 'render_markdown', 'documents' => [$document]], \JSON_THROW_ON_ERROR)."\n";
            $process = new Process([self::BINARY], input: $frame);
            $process->run();
            self::assertFalse($process->isSuccessful());
            self::assertSame('', $process->getOutput());
        }
    }

    public function testFilterBatchesAndMatchesPhpForMixedContent(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->get(SiteRegistry::class)->switchSite('localhost.dev');
        $page = new Page();
        $page->host = 'localhost.dev';

        $manager = $container->get(ContentPipelineFactory::class)->getLegacyManager($page);
        $source = "## A title\n\nA **bold** paragraph.\n\n#[private](/path)\n\n![alt](/missing.jpg)\n\n> [!note] Notice\n\nA [normal link](/path).";
        $php = new Markdown($this->parser());
        $pool = new ArrayAdapter();
        $nativeParser = $this->parser(self::BINARY, $pool);
        $linkProvider = $container->get(LinkProvider::class);
        self::assertTrue($linkProvider->canRenderObfuscatedMarkdownLinkNatively());
        $native = new Markdown($nativeParser, $linkProvider);

        self::assertSame($php->apply($source, $page, $manager), $native->apply($source, $page, $manager));
        self::assertTrue($pool->getItem('pw_mdn1.'.hash('xxh3', '9|A **bold** paragraph.'))->isHit());
        $nativeParser->reset();
    }

    public function testFilterUsesPhpWhenWorkerIsUnavailable(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->get(SiteRegistry::class)->switchSite('localhost.dev');
        $page = new Page();
        $page->host = 'localhost.dev';

        $manager = $container->get(ContentPipelineFactory::class)->getLegacyManager($page);
        $source = "## Title\n\nA **paragraph**.";
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $parser = $this->parser(__DIR__.'/missing-worker', logger: $logger);

        self::assertSame(new Markdown($this->parser())->apply($source, $page, $manager), new Markdown($parser)->apply($source, $page, $manager));
        self::assertFalse($parser->hasNativeMarkdown());
    }
}
