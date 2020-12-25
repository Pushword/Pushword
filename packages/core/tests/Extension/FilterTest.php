<?php

namespace Pushword\Core\Tests\Extension;

use DateTime;
use Pushword\Core\Component\Filter\Raw;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class FilterTest extends KernelTestCase
{
    public function testIt()
    {
        self::bootKernel();

        $filterManager = new Raw(
            self::$kernel->getContainer()->get('pushword.apps'),
            self::$kernel->getContainer()->get('twig'),
            self::$kernel->getContainer()->get('markdown.parser'), //pushword.markdown_parser
            $this->getPage(),
        );

        $this->assertSame('Demo Page - Kitchen Sink  Markdown + Twig', $filterManager->title());
        $this->assertSame('',  $filterManager->getChapeau());
        $this->assertSame('<p>',  substr(trim($filterManager->getBody()), 0, 3));
    }

    public function testToc()
    {
        self::bootKernel();
        $filterManager = new Raw(
            self::$kernel->getContainer()->get('pushword.apps'),
            self::$kernel->getContainer()->get('twig'),
            self::$kernel->getContainer()->get('markdown.parser'), //pushword.markdown_parser
            $this->getPage($this->getContentReadyForToc()),
        );

        $this->assertSame('<p>my intro...</p>',  trim($filterManager->getIntro()));
        $toCheck = '<h2 id="fist-title">Fist Title</h2>';
        $this->assertSame($toCheck,  substr(trim($filterManager->getContent()), 0, \strlen($toCheck)));
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
            ->setMainContent($content ?? file_get_contents(__DIR__.'/../../../skeleton/src/DataFixtures/WelcomePage.md'));
    }

    private function getContentReadyForToc()
    {
        return 'my intro...'
            .\chr(10).'## Fist Title'
            .\chr(10).'first paragraph'
            .\chr(10).'## Second Title'
            .\chr(10).'second paragraph';
    }
}
