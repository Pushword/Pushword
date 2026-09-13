<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Component\EntityFilter\Filter;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Component\EntityFilter\Filter\Markdown;
use Pushword\Core\Component\EntityFilter\FilterRegistry;
use Pushword\Core\Component\EntityFilter\Manager;
use Pushword\Core\Content\ContentPipeline;
use Pushword\Core\Content\ContentPipelineFactory;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class MarkdownPlainBlockTest extends KernelTestCase
{
    public function testPlainBlocksDoNotRequireTheTwigFilter(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = $container->get(ContentPipelineFactory::class);
        $filter = $container->get(FilterRegistry::class)->getFilter('markdown');
        self::assertInstanceOf(Markdown::class, $filter);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'en';

        $manager = new Manager(new ContentPipeline(
            $factory,
            $factory->eventDispatcher,
            new FilterRegistry([]),
            $page,
            $factory->apps,
        ));

        $html = $filter->apply("A **plain** paragraph.\n\n- First\n- Second", $page, $manager);

        self::assertIsString($html);
        self::assertStringContainsString('<strong>plain</strong>', $html);
        self::assertStringContainsString('<li>Second</li>', $html);
    }

    public function testTwigAndCodeBlocksStillUseTheProtectedPath(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $factory = $container->get(ContentPipelineFactory::class);
        $filter = $container->get(FilterRegistry::class)->getFilter('markdown');
        self::assertInstanceOf(Markdown::class, $filter);

        $page = new Page();
        $page->host = 'localhost';
        $page->locale = 'en';

        $manager = $factory->getLegacyManager($page);

        $html = $filter->apply("Value: {{ 1 + 1 }}.\n\n`{{ 2 + 2 }}`\n\n<pre>{{ 3 + 3 }}</pre>\n\n## Heading {#heading}", $page, $manager);

        self::assertIsString($html);
        self::assertStringContainsString('Value: 2.', $html);
        self::assertStringContainsString('<code>{{ 2 + 2 }}</code>', $html);
        self::assertStringContainsString('<pre>{{ 3 + 3 }}</pre>', $html);
        self::assertStringContainsString('id="heading"', $html);
    }
}
