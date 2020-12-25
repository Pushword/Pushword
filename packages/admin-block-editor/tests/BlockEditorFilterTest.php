<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use DateTime;
use Pushword\AdminBlockEditor\BlockEditorFilter;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;

use function Safe\file_get_contents;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BlockEditorFilterTest extends KernelTestCase
{
    public function testIt(): void
    {
        $filter = $this->getEditorFilterTest();
        $mainContentFiltered = $filter->apply($filter->page->getMainContent());

        self::assertStringContainsString('</div>', $mainContentFiltered);
        self::assertStringContainsString('&test&', $mainContentFiltered);
    }

    private function getEditorFilterTest(): BlockEditorFilter
    {
        $filter = new BlockEditorFilter();
        /** @var AppPool */
        $apps = static::getContainer()->get(AppPool::class);
        $filter->app = $apps->get();
        $filter->twig = static::getContainer()->get('test.service_container')->get('twig'); // @phpstan-ignore-line
        $filter->page = $this->getPage();

        return $filter;
    }

    private function getPage(?string $content = null): Page
    {
        $page = (new Page())
                ->setH1('Demo Page - Kitchen Sink  Markdown + Twig')
                ->setSlug('kitchen-sink')
                ->setLocale('en')
                ->setCreatedAt(new DateTime('1 day ago'))
                ->setUpdatedAt(new DateTime('1 day ago'))
                ->setMainContent(file_get_contents(__DIR__.'/content/content.json'));

        $page->setCustomProperty('toc', true);

        return $page;
    }
}
