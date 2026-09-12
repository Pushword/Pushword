<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Rust;

use Knp\Menu\ItemInterface;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\EntityFilter\ValueObject\SplitContent;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\ContentSplitter;
use Pushword\Core\Service\NativeWorker;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\ContentExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\Process;

final class NativeContentSplitterTest extends KernelTestCase
{
    private const string BINARY = __DIR__.'/../target/release/pushword-content-analyzer';

    public function testCompleteResultParity(): void
    {
        $json = file_get_contents(__DIR__.'/split.json');
        self::assertIsString($json);
        $cases = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);
        $worker = new NativeWorker(self::BINARY);
        $pool = new ArrayAdapter();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $splitter = new ContentSplitter(self::BINARY, cache: $pool, logger: $logger);
        foreach ([true, false] as $toc) {
            $page = new Page();
            if ($toc) {
                $page->setCustomProperty('toc', true);
            }

            foreach ($cases as $case) {
                self::assertIsArray($case);
                self::assertIsString($case['html']);
                self::assertIsString($case['name']);
                self::assertIsBool($case['native']);
                $raw = $worker->request('split_content', [['html' => $case['html'], 'toc' => $toc]])[0];
                if ($toc) {
                    self::assertSame($case['native'], null !== $raw, $case['name'].' native eligibility');
                } elseif ('crlf' === $case['name']) {
                    self::assertNotNull($raw, 'Line endings without TOC retain the original HTML');
                }

                $expected = $this->snapshot(new SplitContent($case['html'], $page));
                self::assertSame($expected, $this->snapshot($splitter->split($case['html'], $page)), $case['name'].' toc='.(int) $toc);
                self::assertSame($expected, $this->snapshot($splitter->split($case['html'], $page)), $case['name'].' cache hit');
            }
        }

        $splitter->reset();
        $worker->reset();
    }

    public function testBatchOrderAndIndependentHeadingScopes(): void
    {
        $toc = new Page();
        $toc->setCustomProperty('tocTitle', 'Contents');

        $plain = new Page();
        $splitter = new ContentSplitter(self::BINARY);
        self::assertSame([], $splitter->splitMany([]));
        $documents = [
            ['html' => '<h2>Same</h2><h2>Same</h2>', 'page' => $toc],
            ['html' => '<h2>Same</h2>', 'page' => $plain],
            ['html' => '<h2>Same</h2>', 'page' => $toc],
            ['html' => '<section><h2>Fallback</h2></section>', 'page' => $toc],
        ];
        foreach ($splitter->splitMany($documents) as $index => $split) {
            self::assertSame($this->snapshot(new SplitContent($documents[$index]['html'], $documents[$index]['page'])), $this->snapshot($split));
        }

        $splitter->reset();
    }

    public function testPushwordMarkdownCorpusAfterRendering(): void
    {
        $json = file_get_contents(__DIR__.'/markdown.json');
        self::assertIsString($json);
        $cases = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($cases);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $splitter = new ContentSplitter(self::BINARY, logger: $logger);
        $worker = new NativeWorker(self::BINARY);
        $documents = [];
        $names = [];
        foreach ($cases as $case) {
            self::assertIsArray($case);
            self::assertIsString($case['php']);
            self::assertIsString($case['name']);
            $documents[] = ['html' => $case['php'], 'toc' => true];
            $names[] = $case['name'];
        }

        foreach ($worker->request('split_content', $documents) as $index => $result) {
            self::assertNotNull($result, $names[$index].' must run in Rust');
        }

        foreach ([true, false] as $toc) {
            $page = new Page();
            if ($toc) {
                $page->setCustomProperty('toc', true);
            }

            foreach ($documents as $index => $document) {
                $html = $document['html'];
                self::assertSame($this->snapshot(new SplitContent($html, $page)), $this->snapshot($splitter->split($html, $page)), $names[$index].' toc='.(int) $toc);
            }
        }

        $splitter->reset();
        $worker->reset();
    }

    public function testGeneratedSupportedDocumentsMatchEveryAccessor(): void
    {
        $page = new Page();
        $page->setCustomProperty('toc', true);

        $texts = ['Same', 'Same-1', '中文', 'Café', '0', '01', '1', '', '🦀', 'A &amp; B'];
        $documents = [];
        for ($case = 0; $case < 160; ++$case) {
            $html = 0 === $case % 3 ? '<p>Lede.</p><!--break-->' : '';
            $html .= '<p>Intro.</p>';
            for ($i = 0; $i < 12; ++$i) {
                $level = 1 + ($case + $i * 3) % 6;
                $attributes = 0 === $i % 4 ? ' class="example" title="Title '.$i.'"' : '';
                $html .= '<h'.$level.$attributes.'>'.$texts[($case + $i) % \count($texts)].'</h'.$level.'><p>Text <em>é</em>.</p>';
                if (5 === $i) {
                    $html .= 0 === $case % 2 ? '<!--stop-toc-->' : '<!--break-->';
                }
            }

            $documents[] = ['html' => $html, 'page' => $page];
        }

        $worker = new NativeWorker(self::BINARY);
        foreach ($worker->request('split_content', array_map(static fn (array $document): array => ['html' => $document['html'], 'toc' => true], $documents)) as $raw) {
            self::assertNotNull($raw, 'Generated corpus must exercise Rust, not fallback');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $splitter = new ContentSplitter(self::BINARY, logger: $logger);
        foreach ($splitter->splitMany($documents) as $i => $actual) {
            self::assertSame($this->snapshot(new SplitContent($documents[$i]['html'], $page)), $this->snapshot($actual), 'Generated case '.$i);
        }

        $worker->reset();
        $splitter->reset();
    }

    public function testCachedMenuMutationsStayLocal(): void
    {
        $page = new Page();
        $page->setCustomProperty('toc', true);

        $html = '<h2>Title</h2><h3>Child</h3>';
        $splitter = new ContentSplitter(self::BINARY, cache: new ArrayAdapter());
        $split = $splitter->split($html, $page);
        $expected = $this->snapshot(new SplitContent($html, $page));
        $menu = $split->getToc(false);
        self::assertInstanceOf(ItemInterface::class, $menu);
        $menu->setChildren([]);
        $menu->setLabel('Changed by a template');
        self::assertSame($expected, $this->snapshot($split));
        self::assertSame($expected, $this->snapshot($splitter->split($html, $page)));
        $splitter->reset();
    }

    public function testProtocolBoundaries(): void
    {
        $valid = ['version' => 1, 'id' => 1, 'operation' => 'split_content', 'documents' => []];
        $frames = ["invalid\n", "\xff\n", '{"version":1}', str_repeat('x', 16 * 1024 * 1024 + 1)."\n"];
        foreach ([['version' => 2], ['operation' => 'wrong'], ['extra' => true], ['documents' => [['html' => null, 'toc' => true]]], ['documents' => [['html' => '', 'toc' => 'true']]], ['documents' => [['html' => '', 'toc' => true, 'extra' => true]]]] as $invalid) {
            $frames[] = json_encode(array_replace($valid, $invalid), \JSON_THROW_ON_ERROR)."\n";
        }

        foreach ($frames as $frame) {
            $process = new Process([self::BINARY], input: $frame);
            $process->run();
            self::assertFalse($process->isSuccessful());
            self::assertSame('', $process->getOutput());
            self::assertNotSame('', $process->getErrorOutput());
        }

        $oversized = array_replace($valid, ['documents' => [['html' => '<p>'.str_repeat('x', 6 * 1024 * 1024).'</p>', 'toc' => false]]]);
        $process = new Process([self::BINARY], input: json_encode($oversized, \JSON_THROW_ON_ERROR)."\n");
        $process->run();
        self::assertFalse($process->isSuccessful());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('oversized response', $process->getErrorOutput());
        $worker = new NativeWorker(self::BINARY);
        self::assertSame([], $worker->request('split_content', []));
        self::assertSame([null, 'ambiguous_heading', 'unsupported_attribute', 'normalized_control'], $worker->request('diagnose_split', [
            ['html' => '<h2>Title</h2>', 'toc' => true],
            ['html' => '<p data-html="<h2>">Text.</p>', 'toc' => true],
            ['html' => '<svg><use xlink:href="#a"></use></svg>', 'toc' => true],
            ['html' => "<h2>Title</h2>\r\n", 'toc' => true],
        ]));
        $html = str_repeat('<p>é 🦀</p>', 10000);
        self::assertNotNull($worker->request('split_content', [['html' => $html, 'toc' => false]])[0]);
        self::assertSame([], $worker->request('split_content', []));
        $worker->reset();
    }

    public function testSharedHostingWithProcessExecutionDisabled(): void
    {
        $process = new Process([\PHP_BINARY, '-d', 'disable_functions=proc_open', __DIR__.'/shared-hosting.php']);
        $process->mustRun();
        self::assertSame("PHP-only split verified\n", $process->getOutput());
        self::assertSame('', $process->getErrorOutput());
    }

    public function testRenderedMarkdownAndTwigUseTheSameSplitResult(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->get(SiteRegistry::class)->switchSite('localhost.dev');
        $factory = $container->get(ContentPipelineFactory::class);
        $reference = new ContentExtension($factory);
        $splitter = new ContentSplitter(self::BINARY);
        $native = new ContentExtension($factory, splitter: $splitter);
        foreach (["Opening.\n\n## {{ 'Café' }}\n\nText **bold**.\n\n<!--break-->\n\n## Extra\n\nOther.", "## SVG\n\n<svg viewBox=\"0 0 4 4\"><path d=\"M0 0\"/></svg>"] as $markdown) {
            $page = new Page();
            $page->host = 'localhost.dev';
            $page->locale = 'en';
            $page->slug = 'native-split';
            $page->mainContent = $markdown;
            $page->setCustomProperty('toc', true);
            self::assertSame($this->snapshot($reference->mainContentSplit($page)), $this->snapshot($native->mainContentSplit($page)));
            self::assertSame($native->mainContentSplit($page), $native->mainContentSplit($page));
        }

        $splitter->reset();
    }

    /** @return array<string, mixed> */
    private function snapshot(SplitContent $split): array
    {
        return [
            'chapeau' => $split->getChapeau(), 'intro' => $split->getIntro(), 'content' => $split->getContent(),
            'body' => $split->getBody(), 'full' => (string) $split, 'parts' => $split->getContentParts(),
            'paragraphs' => $split->getParagraphs(), 'allParagraphs' => $split->getParagraphs(true),
            'first' => $split->getFirstParagraph(), 'toc' => $split->getToc(),
            'menu' => $this->menu($split->getToc(false)),
        ];
    }

    /** @return array<string, mixed>|string */
    private function menu(ItemInterface|string $menu): array|string
    {
        if (\is_string($menu)) {
            return $menu;
        }

        $children = [];
        foreach ($menu->getChildren() as $key => $child) {
            $children[$key] = $this->menu($child);
        }

        return ['name' => $menu->getName(), 'label' => $menu->getLabel(), 'uri' => $menu->getUri(), 'level' => $menu->getLevel(), 'children' => $children];
    }
}
