<?php

namespace Pushword\Core\Tests;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\VariantManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class VariantPageTest extends KernelTestCase
{
    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function newPage(string $slug, ?Page $variantOf = null): Page
    {
        $page = new Page();
        $page->setH1(ucfirst($slug));
        $page->setSlug($slug);
        $page->locale = 'en';
        $page->host = 'localhost.dev';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->setMainContent('Content of '.$slug);

        if (null !== $variantOf) {
            $page->setVariantOf($variantOf);
        }

        return $page;
    }

    public function testVariantCanonicalPointsToMasterAndEmitsNoHreflang(): void
    {
        $em = $this->em();
        $master = $this->newPage('variant-master-page');
        $variant = $this->newPage('variant-child-page', $master);
        $em->persist($master);
        $em->persist($variant);
        $em->flush();

        try {
            $content = (string) self::getContainer()->get(PageController::class)
                ->show(Request::create('/variant-child-page'), 'variant-child-page')
                ->getContent();

            self::assertStringContainsString(
                'rel="canonical" href="https://localhost.dev/variant-master-page"',
                $content,
            );
            self::assertStringNotContainsString(
                'rel="canonical" href="https://localhost.dev/variant-child-page"',
                $content,
            );
            // A variant is not self-canonical, so it emits no hreflang cluster.
            self::assertStringNotContainsString('hreflang=', $content);
        } finally {
            $em->remove($variant);
            $em->remove($master);
            $em->flush();
        }
    }

    public function testInternalLinkToVariantRewrittenToMaster(): void
    {
        $em = $this->em();
        $master = $this->newPage('link-master-page');
        $variant = $this->newPage('link-variant-page', $master);
        $linking = $this->newPage('link-source-page');
        $linking->setMainContent('See [the offer](/link-variant-page) now.');

        $em->persist($master);
        $em->persist($variant);
        $em->persist($linking);
        $em->flush();

        try {
            $content = (string) self::getContainer()->get(PageController::class)
                ->show(Request::create('/link-source-page'), 'link-source-page')
                ->getContent();

            // The variant URL is moved to the data-variant hook...
            self::assertStringContainsString('data-variant="/link-variant-page"', $content);
            // ...and the href now points to the master.
            self::assertMatchesRegularExpression(
                '/href="[^"]*link-master-page[^"]*"[^>]*data-variant="\/link-variant-page"/',
                $content,
            );
        } finally {
            $em->remove($linking);
            $em->remove($variant);
            $em->remove($master);
            $em->flush();
        }
    }

    public function testNonVariantLinkIsLeftUntouched(): void
    {
        $em = $this->em();
        $target = $this->newPage('plain-target-page');
        $linking = $this->newPage('plain-source-page');
        $linking->setMainContent('See [the page](/plain-target-page) now.');

        $em->persist($target);
        $em->persist($linking);
        $em->flush();

        try {
            $content = (string) self::getContainer()->get(PageController::class)
                ->show(Request::create('/plain-source-page'), 'plain-source-page')
                ->getContent();

            // The link keeps its own href and gains no data-variant hook
            // (data-variant-zone is the unrelated content-wrapper attribute).
            self::assertStringNotContainsString('data-variant="', $content);
            self::assertStringContainsString('href="/plain-target-page"', $content);
        } finally {
            $em->remove($linking);
            $em->remove($target);
            $em->flush();
        }
    }

    public function testIndexableQueryExcludesVariants(): void
    {
        $em = $this->em();
        $master = $this->newPage('indexable-master-page');
        $variant = $this->newPage('indexable-variant-page', $master);
        $em->persist($master);
        $em->persist($variant);
        $em->flush();

        try {
            $repo = self::getContainer()->get(PageRepository::class);
            $result = $repo->getIndexablePagesQuery('localhost.dev', 'en')->getQuery()->getResult();
            self::assertIsIterable($result);

            $slugs = [];
            foreach ($result as $page) {
                self::assertInstanceOf(Page::class, $page);
                $slugs[] = $page->getSlug();
            }

            self::assertContains('indexable-master-page', $slugs);
            self::assertNotContains('indexable-variant-page', $slugs);
        } finally {
            $em->remove($variant);
            $em->remove($master);
            $em->flush();
        }
    }

    public function testHasVariantShortCircuitsHostsWithoutVariants(): void
    {
        $em = $this->em();
        $master = $this->newPage('hv-master');
        $variant = $this->newPage('hv-variant', $master);
        $em->persist($master);
        $em->persist($variant);
        $em->flush();

        $repo = self::getContainer()->get(PageRepository::class);

        try {
            self::assertTrue($repo->hasVariant('localhost.dev'));
            self::assertFalse($repo->hasVariant('host-without-variant.invalid'));
        } finally {
            $em->remove($variant);
            $em->remove($master);
            $em->flush();
        }
    }

    public function testPromoteSwapsMasterAndVariants(): void
    {
        $em = $this->em();
        $master = $this->newPage('promote-master');
        $variantA = $this->newPage('promote-a', $master);
        $variantB = $this->newPage('promote-b', $master);
        $em->persist($master);
        $em->persist($variantA);
        $em->persist($variantB);
        $em->flush();

        try {
            self::getContainer()->get(VariantManager::class)->promote($variantA);
            $em->flush();
            $em->clear();

            $repo = $em->getRepository(Page::class);
            $a = $repo->findOneBy(['slug' => 'promote-a']);
            $b = $repo->findOneBy(['slug' => 'promote-b']);
            $m = $repo->findOneBy(['slug' => 'promote-master']);

            self::assertNotNull($a);
            self::assertNotNull($b);
            self::assertNotNull($m);
            self::assertFalse($a->isVariant(), 'Promoted page is now the master');
            self::assertSame('promote-a', $m->getVariantOf()?->getSlug());
            self::assertSame('promote-a', $b->getVariantOf()?->getSlug());
        } finally {
            $repo = $em->getRepository(Page::class);
            foreach (['promote-a', 'promote-b', 'promote-master'] as $slug) {
                $page = $repo->findOneBy(['slug' => $slug]);
                if (null !== $page) {
                    $page->setVariantOf(null);
                }
            }

            $em->flush();
            foreach (['promote-a', 'promote-b', 'promote-master'] as $slug) {
                $page = $repo->findOneBy(['slug' => $slug]);
                if (null !== $page) {
                    $em->remove($page);
                }
            }

            $em->flush();
        }
    }

    public function testRemovingMasterPromotesAVariant(): void
    {
        $em = $this->em();
        $master = $this->newPage('remove-master');
        $variant = $this->newPage('remove-variant', $master);
        $em->persist($master);
        $em->persist($variant);
        $em->flush();

        $em->remove($master);
        $em->flush();
        $em->clear();

        $repo = $em->getRepository(Page::class);
        $survivor = $repo->findOneBy(['slug' => 'remove-variant']);

        try {
            self::assertNotNull($survivor, 'The variant survives its master removal');
            self::assertFalse($survivor->isVariant(), 'The surviving variant was promoted to master');
        } finally {
            if (null !== $survivor) {
                $em->remove($survivor);
                $em->flush();
            }
        }
    }
}
