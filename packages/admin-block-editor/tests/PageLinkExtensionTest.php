<?php

declare(strict_types=1);

namespace Pushword\AdminBlockEditor\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\AdminBlockEditor\Twig\PageLinkExtension;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PageLinkExtensionTest extends KernelTestCase
{
    public function testPageLinksContainPlainTitlesAndCompletePathsOnTheRequestedHost(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $pages = [];
        foreach (['homepage', 'blog/a-long-path', 'untitled'] as $slug) {
            $page = new Page();
            $page->host = 'page-links.test';
            $page->slug = $slug;
            $page->h1 = 'untitled' === $slug ? '' : '<b>Mountains</b> & travel';
            $em->persist($page);
            $pages[] = $page;
        }

        $em->flush();

        try {
            $extension = self::getContainer()->get(PageLinkExtension::class);
            self::assertSame([
                ['uri' => '/blog/a-long-path', 'title' => 'Mountains & travel'],
                ['uri' => '/', 'title' => 'Mountains & travel'],
                ['uri' => '/untitled', 'title' => ''],
            ], $extension->pageLinks('page-links.test'));
            self::assertSame([], $extension->pageLinks('no-page-links.test'));
        } finally {
            foreach ($pages as $page) {
                $em->remove($page);
            }

            $em->flush();
        }
    }
}
