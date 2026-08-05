<?php

namespace Pushword\LinkImprover\Tests;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\LinkImprover\DependencyInjection\AddLinkImproverToFilterChainPass;
use Pushword\LinkImprover\LinkImprover;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AddLinkImproverToFilterChainPassTest extends TestCase
{
    /**
     * @param list<string>|string $mainContent
     *
     * @return list<string> the main_content chain the pass produced
     */
    private function chainAfterProcess(array|string $mainContent): array
    {
        return $this->mainContentOf($this->process([
            'example.tld' => ['filters' => ['main_content' => $mainContent]],
        ]));
    }

    /**
     * @param array<array-key, mixed> $apps
     *
     * @return array<array-key, mixed>
     */
    private function process(array $apps): array
    {
        $container = new ContainerBuilder();
        $container->setParameter('pw.apps', $apps);

        new AddLinkImproverToFilterChainPass()->process($container);

        $processed = $container->getParameterBag()->all()['pw.apps'] ?? null;
        self::assertIsArray($processed);

        return $processed;
    }

    /**
     * @param array<array-key, mixed> $apps
     *
     * @return list<string>
     */
    private function mainContentOf(array $apps): array
    {
        $app = $apps['example.tld'] ?? null;
        self::assertIsArray($app);

        $filters = $app['filters'] ?? null;
        self::assertIsArray($filters);

        $mainContent = $filters['main_content'] ?? null;
        self::assertIsArray($mainContent);

        $chain = [];
        foreach ($mainContent as $filter) {
            self::assertIsString($filter);
            $chain[] = $filter;
        }

        return $chain;
    }

    public function testItInsertsRightAfterMarkdown(): void
    {
        self::assertSame(
            ['ShowMore', Markdown::class, LinkImprover::class, 'HtmlObfuscateLink'],
            $this->chainAfterProcess(['ShowMore', Markdown::class, 'HtmlObfuscateLink'])
        );
    }

    public function testItReadsTheCommaSeparatedForm(): void
    {
        self::assertSame(
            ['ShowMore', Markdown::class, LinkImprover::class, 'HtmlObfuscateLink'],
            $this->chainAfterProcess('ShowMore,'.Markdown::class.',HtmlObfuscateLink')
        );
    }

    public function testItAppendsWhenTheChainHasNoMarkdown(): void
    {
        self::assertSame(['ShowMore', LinkImprover::class], $this->chainAfterProcess(['ShowMore']));
    }

    public function testItNeverInsertsTwice(): void
    {
        $once = $this->process(['example.tld' => ['filters' => ['main_content' => [Markdown::class]]]]);

        self::assertSame([Markdown::class, LinkImprover::class], $this->mainContentOf($this->process($once)));
    }

    public function testItSkipsAnAppWithoutAFilterChain(): void
    {
        $apps = ['example.tld' => ['locale' => 'en']];

        self::assertSame($apps, $this->process($apps));
    }
}
