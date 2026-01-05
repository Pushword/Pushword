<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Repository;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Pushword\Conversation\Entity\Review;
use Pushword\Conversation\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

final class MessageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private MessageRepository $messageRepository;

    private string $testHost = 'localhost.dev';

    /** @var array<int> */
    private array $createdMessageIds = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->messageRepository = self::getContainer()->get(MessageRepository::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        if ([] !== $this->createdMessageIds) {
            try {
                foreach ($this->createdMessageIds as $id) {
                    $message = $this->messageRepository->find($id);
                    if (null !== $message) {
                        $this->entityManager->remove($message);
                    }
                }

                $this->entityManager->flush();
            } catch (Throwable) {
                // Ignore errors during cleanup
            }
        }

        parent::tearDown();
    }

    public function testGetPublishedReviewsByTagOrdersByWeightDescending(): void
    {
        // Create reviews with different weights
        $review1 = $this->createTestReview('Review with weight 6', 6);
        $review2 = $this->createTestReview('Review with weight 12', 12);
        $review3 = $this->createTestReview('Review with weight 0', 0);

        $this->entityManager->persist($review1);
        $this->entityManager->persist($review2);
        $this->entityManager->persist($review3);
        $this->entityManager->flush();

        $this->trackCreatedMessage($review1);
        $this->trackCreatedMessage($review2);
        $this->trackCreatedMessage($review3);

        // Get published reviews
        $reviews = $this->messageRepository->getPublishedReviewsByTag([]);

        // Filter to only our test reviews
        $testReviews = array_filter(
            $reviews,
            static fn (mixed $r): bool => $r instanceof Review && str_starts_with($r->getContent(), 'Review with weight')
        );
        $testReviews = array_values($testReviews);

        self::assertCount(3, $testReviews);

        // Assert ordering: weight 12 first, then weight 6, then weight 0
        self::assertSame('Review with weight 12', $testReviews[0]->getContent());
        self::assertSame('Review with weight 6', $testReviews[1]->getContent());
        self::assertSame('Review with weight 0', $testReviews[2]->getContent());
    }

    public function testGetPublishedReviewsByTagOrdersByCreatedAtWhenSameWeight(): void
    {
        // Create reviews with same weight but different creation dates
        $review1 = $this->createTestReview('Older review', 5);
        $review2 = $this->createTestReview('Newer review', 5);

        $this->entityManager->persist($review1);
        $this->entityManager->persist($review2);
        $this->entityManager->flush();

        // Update createdAt after persist to override PrePersist hook
        $review1->createdAt = new DateTime('2024-01-01');
        $review2->createdAt = new DateTime('2024-06-01');
        $this->entityManager->flush();

        $this->trackCreatedMessage($review1);
        $this->trackCreatedMessage($review2);

        // Clear the entity manager to force fresh query
        $this->entityManager->clear();

        // Get published reviews
        $reviews = $this->messageRepository->getPublishedReviewsByTag([]);

        // Filter to only our test reviews
        $testReviews = array_filter(
            $reviews,
            static fn (mixed $r): bool => $r instanceof Review && str_ends_with($r->getContent(), 'review')
        );
        $testReviews = array_values($testReviews);

        self::assertCount(2, $testReviews);

        // Assert ordering: newer first (createdAt DESC)
        self::assertSame('Newer review', $testReviews[0]->getContent());
        self::assertSame('Older review', $testReviews[1]->getContent());
    }

    private function createTestReview(string $content, int $weight): Review
    {
        $review = new Review();
        $review->host = $this->testHost;
        $review->setContent($content);
        $review->setAuthorEmail('test@example.com');
        $review->setAuthorName('Test User');
        $review->setReferring('/test-page');
        $review->setRating(5);
        $review->setWeight($weight);
        $review->setPublishedAt(new DateTime());

        return $review;
    }

    private function trackCreatedMessage(Review $review): void
    {
        if (null !== $review->id) {
            $this->createdMessageIds[] = $review->id;
        }
    }
}
