<?php

namespace Pushword\Conversation\Tests\EventListener;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Cache\RenderEpoch;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against the real EntityManager on purpose: Review inherits the entity
 * listener through single-table inheritance from Message, which a unit test
 * with hand-called hooks would not prove.
 */
#[Group('integration')]
final class ReviewCacheInvalidationListenerTest extends KernelTestCase
{
    private const string HOST = 'localhost.dev';

    /** @var Review[] */
    private array $reviews = [];

    protected function tearDown(): void
    {
        $em = $this->getEm();
        foreach ($this->reviews as $review) {
            $managed = $em->find(Review::class, $review->id);
            if (null !== $managed) {
                $em->remove($managed);
            }
        }

        $em->flush();
        parent::tearDown();
    }

    public function testOnlyPublishedMessageWritesBumpTheEpoch(): void
    {
        self::bootKernel();
        $em = $this->getEm();
        $renderEpoch = self::getContainer()->get(RenderEpoch::class);

        // Unpublished visitor submission: nothing lists it, no bump.
        $epoch = $renderEpoch->get(self::HOST);
        $pending = $this->makeReview('awaiting moderation');
        $em->persist($pending);
        $em->flush();
        self::assertSame($epoch, $renderEpoch->get(self::HOST));

        // Approving it is the transition every listing reacts to.
        $pending->publishedAt = new DateTime('-1 minute');
        $em->flush();
        $afterApproval = $renderEpoch->get(self::HOST);
        self::assertNotSame($epoch, $afterApproval);

        // Editing a published review changes rendered output.
        $pending->setContent('now says something else');
        $em->flush();
        $afterEdit = $renderEpoch->get(self::HOST);
        self::assertNotSame($afterApproval, $afterEdit);

        // Soft-deleting a published review removes it from listings.
        $pending->softDelete();
        $em->flush();
        self::assertNotSame($afterEdit, $renderEpoch->get(self::HOST));

        // Editing the now-tombstoned review is invisible: no bump.
        $silent = $renderEpoch->get(self::HOST);
        $pending->setContent('edited while deleted');
        $em->flush();
        self::assertSame($silent, $renderEpoch->get(self::HOST));
    }

    private function makeReview(string $content): Review
    {
        $review = new Review();
        $review->host = self::HOST;
        $review->authorName = 'Epoch Tester';
        $review->setContent($content);
        $review->referring = 'epoch-listener-test';

        $this->reviews[] = $review;

        return $review;
    }

    private function getEm(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
