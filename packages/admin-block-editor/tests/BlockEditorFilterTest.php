<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use DateTime;
use Pushword\AdminBlockEditor\BlockEditorFilter;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BlockEditorFilterTest extends KernelTestCase
{
    public function testIt()
    {
        $filter = $this->getEditorFilterTest();
        $mainContentFiltered = $filter->apply($filter->getEntity()->getMainContent());

        $this->assertStringContainsString('</div>', $mainContentFiltered);
        $this->assertStringContainsString('&test&', $mainContentFiltered);
    }

    private function getEditorFilterTest()
    {
        self::bootKernel();
        $filter = new BlockEditorFilter();
        $filter->setApp(self::$kernel->getContainer()->get('pushword.apps')->get());
        $filter->setTwig(self::$kernel->getContainer()->get('twig'));
        $filter->setEntity($this->getPage());

        return $filter;
    }

    private function getPage($content = null)
    {
        return (new Page())
                ->setH1('Demo Page - Kitchen Sink  Markdown + Twig')
                ->setSlug('kitchen-sink')
                ->setLocale('en')
                ->setCustomProperty('toc', true)
                ->setCreatedAt(new DateTime('1 day ago'))
                ->setUpdatedAt(new DateTime('1 day ago'))
                ->setMainContent(file_get_contents(__DIR__.'/content/content.json'));
    }
}
