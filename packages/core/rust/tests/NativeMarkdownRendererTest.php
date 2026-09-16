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
use Pushword\Core\Tests\Support\HtmlEquivalence;
use Pushword\Core\Tests\Support\MarkdownCacheVersion;
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
            'Call +33 1 23 45 67 89',
            'Call 01&nbsp;23&nbsp;45&nbsp;67&nbsp;89',
            "Call 01\u{a0}23\u{a0}45\u{a0}67\u{a0}89",
            'contact@example.com',
            'Before <span>contact@example.com</span> after',
            'date(Y)',
        ];
        $result = $parser->renderNativeMany($sources);
        $php = $this->parser();
        foreach ($sources as $index => $source) {
            if (null !== $result[$index]) {
                self::assertSame(HtmlEquivalence::structure($php->transform($source)), HtmlEquivalence::structure($result[$index]), $source);
            }
        }

        self::assertSame([false, false, false, false, true, true, false, false, false, true, false, false, false], array_map(static fn (?string $html): bool => null === $html, $result));
        $parser->reset();
    }

    public function testDateShortcodesUsePhpValuesAndDeclineAmbiguousSyntax(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');
        $native = $this->parser(self::BINARY);
        $php = $this->parser();
        $sources = [
            'Copyright date(Y) / date(Y+1) / date(Y-1)',
            'Seasons date(S) and date(W)',
            'Today date(M), date(B), date(A), date(e)',
            'Quoted date("%Y") and unknown date(Q)',
            '[Article date(Y)](/archive)',
            '[Article date(Y)](/archive/date(Y))',
            '`date(Y)` and date(Y)',
            '<span title="date(Y)">date(Y)</span>',
            '\\date(Y)',
        ];

        $results = $native->renderNativeMany($sources);
        foreach ($sources as $index => $source) {
            if (null !== $results[$index]) {
                self::assertSame(HtmlEquivalence::structure($php->transform($source)), HtmlEquivalence::structure($results[$index]), $source);
            }
        }

        self::assertSame([false, false, false, false, false, true, false, true, true], array_map(static fn (?string $html): bool => null === $html, $results));
        $native->reset();
    }

    public function testPhoneLocaleUsesDistinctNativeCacheEntries(): void
    {
        self::bootKernel();
        $sites = self::getContainer()->get(SiteRegistry::class);
        $page = new Page();
        $page->host = 'localhost.dev';

        $sites->switchSite($page);
        $pool = new ArrayAdapter();
        $native = $this->parser(self::BINARY, $pool);
        $php = $this->parser();
        $source = 'Call +33 1 23 45 67 89';

        $page->locale = 'fr';
        $french = $native->renderNativeMany([$source]);
        self::assertSame([$php->transform($source)], $french);

        $page->locale = 'en';
        $english = $native->renderNativeMany([$source]);
        self::assertSame([$php->transform($source)], $english);
        self::assertNotSame($french, $english);
        foreach (['fr', 'en'] as $locale) {
            self::assertTrue($pool->getItem('pw_mdn3.'.hash('xxh3', MarkdownCacheVersion::get().'a1l'.$locale.'|'.$source))->isHit());
        }

        $native->reset();
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

    public function testDocsExportRegressionCasesMatchPhpOrDecline(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');

        $sources = [
            'namespaced class in list code' => '- **Events:** See `Pushword\\Core\\Event\\PushwordEvents`.',
            'wrapped link label in list' => "- The [ELTS\n  price](/pricing) increased.",
            'separate code spans with braces' => 'A `collection` of `{name, type}` values.',
            'table with code braces' => "| Key | Value |\n|---|---|\n| `source` | `{host}/{slug}` |",
            'indented closing fence' => "```bash\n  echo hello\n  ```",
            'paragraph before a fence' => "**Before:**\n```twig\n<div class=\"example\">\n```",
            'numbered item with a fence' => "5. **Unlock**: Release the lock\n   ```bash\n   echo hello\n   ```",
            'fence closed with more backticks' => "```twig\n{{ example }}\n````",
            'fenced item among numbered items' => "1. Check spam\n2. Configure mailer\n   ```bash\n   MAILER_DSN=smtp://example.com\n   ```\n3. Check logs",
            'indented paragraph continuation' => "A sentence\n   continued here.",
            'heading containing only code' => '### `media/index.html.twig`',
            'ordered list with child bullets' => "1. **Step 1**: Begin\n2. **Step 2**: Choose\n   - Yes\n   - No",
            'task list with a continued item' => "- [ ] First\n      Continued\n- [ ] Second",
        ];
        $declines = ['table with code braces', 'paragraph before a fence'];
        $native = $this->parser(self::BINARY);
        $php = $this->parser();
        $results = $native->renderNativeMany(array_values($sources));

        foreach (array_keys($sources) as $index => $name) {
            if (\in_array($name, $declines, true)) {
                self::assertNull($results[$index], $name);
            } else {
                self::assertSame($php->transform($sources[$name]), $results[$index], $name);
            }
        }

        $native->reset();
    }

    public function testNativeBatchUsesExistingMarkdownCache(): void
    {
        self::bootKernel();
        self::getContainer()->get(SiteRegistry::class)->switchSite('localhost.dev');
        $pool = new ArrayAdapter();
        $parser = $this->parser(self::BINARY, $pool);
        $source = 'A **cached** paragraph.';
        self::assertSame([$this->parser()->transform($source)], $parser->renderNativeMany([$source]));

        $declined = "| Key | Value |\n|---|---|\n| `source` | `{host}/{slug}` |";
        $oldItem = $pool->getItem('pw_mdn2.'.hash('xxh3', MarkdownCacheVersion::get().'|'.$declined));
        $oldItem->set('OLD INCORRECT HTML');

        $pool->save($oldItem);
        self::assertSame([null], $parser->renderNativeMany([$declined]));

        $key = 'pw_mdn3.'.hash('xxh3', MarkdownCacheVersion::get().'|'.$source);
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

    public function testOlderWorkerRequestsDeclineContactMarkup(): void
    {
        $documents = array_map(static fn (string $source): array => [
            'markdown' => $source,
            'fenced_code_pre_class' => '',
        ], ['contact@example.com', 'Call 01 23 45 67 89', '#[private](/path)', 'Plain text']);
        $frame = json_encode(['version' => 1, 'id' => 1, 'operation' => 'render_markdown', 'documents' => $documents], \JSON_THROW_ON_ERROR)."\n";
        $process = new Process([self::BINARY], input: $frame);
        $process->mustRun();

        $response = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($response);

        self::assertSame([null, null, null, "<p>Plain text</p>\n"], $response['documents']);
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
        self::assertTrue($pool->getItem('pw_mdn3.'.hash('xxh3', MarkdownCacheVersion::get().'|A **bold** paragraph.'))->isHit());
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
