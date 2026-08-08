<?php

namespace Pushword\Conversation\Tests\Twig;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Review;
use Pushword\Conversation\Twig\AppExtension;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Twig\ShowMore;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The review list folds itself past the second review, and `{{ reviews() }}` is
 * written in page bodies — so its HTML is baked into the block text the markdown
 * fragment pool is keyed on. Anything random in it therefore means a brand new
 * cache entry on every render, of every page holding the call: the pool grows
 * without bound and never hits, and a static build rewrites files nothing
 * changed in. It used to draw its id from `random(1000, 9999)`.
 */
#[Group('integration')]
final class ReviewListDeterminismTest extends KernelTestCase
{
    /** @var int[] */
    private array $createdIds = [];

    protected function tearDown(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        foreach ($this->createdIds as $id) {
            $review = $em->find(Review::class, $id);
            if (null !== $review) {
                $em->remove($review);
            }
        }

        $em->flush();
        $this->createdIds = [];

        parent::tearDown();
    }

    private function render(): string
    {
        $siteRegistry = self::getContainer()->get(SiteRegistry::class);

        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = 'review-determinism';
        $page->locale = 'en';

        $siteRegistry->setCurrentPage($page);

        // Each render is a fresh request as far as the numbering is concerned.
        self::getContainer()->get(ShowMore::class)->reset();

        return self::getContainer()->get(AppExtension::class)->renderReviewList(
            self::getContainer()->get('twig'),
            '#',
        );
    }

    private function createReviews(int $count): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);

        for ($i = 0; $i < $count; ++$i) {
            $review = new Review();
            $review->host = 'localhost.dev';
            $review->setContent('Determinism probe '.$i);
            $review->authorEmail = 'probe@example.com';
            $review->authorName = 'Probe';
            $review->referring = '/review-determinism';
            $review->setRating(5);
            $review->setWeight(100 - $i);
            $review->publishedAt = new DateTime();

            $em->persist($review);
            $em->flush();
            if (null !== $review->id) {
                $this->createdIds[] = $review->id;
            }
        }
    }

    public function testRenderingTheSameListTwiceGivesTheSameBytes(): void
    {
        self::bootKernel();
        $this->createReviews(4);

        $first = $this->render();

        self::assertStringContainsString('class="show-more ', $first, 'more than two reviews must fold');
        self::assertSame($first, $this->render());
    }

    /** The wrapper must come from the site's template, not from a copy of it. */
    public function testTheFoldIsTheSiteShowMoreComponent(): void
    {
        self::bootKernel();
        $this->createReviews(4);

        $html = $this->render();

        self::assertStringContainsString('window.ShowMore.open(this)', $html);
        self::assertStringContainsString('window.ShowMore.close(this)', $html);
        self::assertMatchesRegularExpression(
            '/<input type="checkbox" id="([^"]+)"[^>]*>\s*<div[^>]*id="csm_\1"/',
            $html,
            'the toggle and the box it opens must carry the same id',
        );
    }
}
