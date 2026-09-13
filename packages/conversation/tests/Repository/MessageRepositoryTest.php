<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Repository;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Throwable;

#[Group('integration')]
final class MessageRepositoryTest extends KernelTestCase
{
    use QueryCountingTrait;

    private EntityManagerInterface $entityManager;

    private MessageRepository $messageRepository;

    private string $testHost = 'localhost.dev';

    /** @var array<int> */
    private array $createdMessageIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->messageRepository = self::getContainer()->get(MessageRepository::class);
    }

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
        // Create reviews with same weight but different creation dates (both with content)
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

    public function testGetPublishedReviewsByTagPrioritizesReviewsWithContent(): void
    {
        // Create reviews with same weight: one with content, one without
        $reviewWithContent = $this->createTestReview('Review with text content', 5);
        $reviewWithoutContent = $this->createTestReview('', 5);

        $this->entityManager->persist($reviewWithContent);
        $this->entityManager->persist($reviewWithoutContent);
        $this->entityManager->flush();

        // Set same createdAt to ensure content is the deciding factor
        $reviewWithContent->createdAt = new DateTime('2024-01-01');
        $reviewWithoutContent->createdAt = new DateTime('2024-06-01'); // newer but no content
        $this->entityManager->flush();

        $this->trackCreatedMessage($reviewWithContent);
        $this->trackCreatedMessage($reviewWithoutContent);

        // Clear the entity manager to force fresh query
        $this->entityManager->clear();

        // Get published reviews
        $reviews = $this->messageRepository->getPublishedReviewsByTag([]);

        // Filter to only our test reviews (by weight 5)
        $testReviews = array_filter(
            $reviews,
            static fn (mixed $r): bool => $r instanceof Review && 5 === $r->weight
                && ('' === $r->getContent() || 'Review with text content' === $r->getContent())
        );
        $testReviews = array_values($testReviews);

        self::assertCount(2, $testReviews);

        // Assert ordering: review with content first, even though the empty one is newer
        self::assertSame('Review with text content', $testReviews[0]->getContent());
        self::assertSame('', $testReviews[1]->getContent());
    }

    public function testGetPublishedReviewsByTagFiltersMinRating(): void
    {
        $review3Stars = $this->createTestReview('3 star review', 0, 3);
        $review5Stars = $this->createTestReview('5 star review', 0, 5);

        $this->entityManager->persist($review3Stars);
        $this->entityManager->persist($review5Stars);
        $this->entityManager->flush();

        $this->trackCreatedMessage($review3Stars);
        $this->trackCreatedMessage($review5Stars);

        // Without minRating: both returned
        $all = $this->messageRepository->getPublishedReviewsByTag([], 0, 0);
        $allTest = array_filter(
            $all,
            static fn (mixed $r): bool => $r instanceof Review && str_ends_with($r->getContent(), 'star review'),
        );
        self::assertCount(2, $allTest);

        // With minRating 4: only the 5-star review
        $filtered = $this->messageRepository->getPublishedReviewsByTag([], 0, 4);
        $filteredTest = array_filter(
            $filtered,
            static fn (mixed $r): bool => $r instanceof Review && str_ends_with($r->getContent(), 'star review'),
        );
        $filteredTest = array_values($filteredTest);
        self::assertCount(1, $filteredTest);
        self::assertSame('5 star review', $filteredTest[0]->getContent());
    }

    public function testPublishedReviewSearchReusesQueriesAndInvalidatesAfterWritesOrClear(): void
    {
        $tag = 'review-search-cache-test';
        $first = $this->createTestReview('First cached review', 2);
        $first->referring = $tag;

        $this->entityManager->persist($first);
        $this->entityManager->flush();
        $this->trackCreatedMessage($first);

        $this->startCountingQueries($this->entityManager->getConnection());
        $search = fn (): array => $this->messageRepository->getPublishedReviewsByTag([$tag]);
        $missingSearch = fn (): array => $this->messageRepository->getPublishedReviewsByTag([$tag.'-missing']);

        try {
            self::assertSame([], $missingSearch());
            self::assertSame(0, $this->countQueries($missingSearch));

            self::assertSame(1, $this->countQueries($search));
            self::assertSame(0, $this->countQueries($search));

            $second = $this->createTestReview('Second cached review', 1, 3);
            $second->referring = $tag;
            $this->entityManager->persist($second);
            $this->entityManager->flush();
            $this->trackCreatedMessage($second);

            self::assertSame(1, $this->countQueries($search));
            self::assertCount(2, $search());
            self::assertCount(1, $this->messageRepository->getPublishedReviewsByTag([$tag], 1));
            self::assertCount(1, $this->messageRepository->getPublishedReviewsByTag([$tag], 0, 4));

            $first->setContent('Updated cached review');
            $this->entityManager->flush();
            self::assertSame(1, $this->countQueries($search));

            $this->entityManager->remove($second);
            $this->entityManager->flush();
            self::assertSame(1, $this->countQueries($search));
            self::assertCount(1, $search());

            $this->entityManager->clear();
            self::assertSame(1, $this->countQueries($search));

            $this->messageRepository->reset();
            self::assertSame(1, $this->countQueries($search));
        } finally {
            $this->stopCountingQueries();
        }
    }

    public function testPublishedQueriesExcludeTombstonedMessages(): void
    {
        $alive = $this->createTestReview('Tombstone check alive', 1);
        $deleted = $this->createTestReview('Tombstone check deleted', 1);
        $deleted->softDelete();

        $this->entityManager->persist($alive);
        $this->entityManager->persist($deleted);
        $this->entityManager->flush();

        $this->trackCreatedMessage($alive);
        $this->trackCreatedMessage($deleted);

        $reviewContents = array_map(
            static fn (Review $r): string => $r->getContent(),
            array_filter(
                $this->messageRepository->getPublishedReviewsByTag([]),
                static fn (mixed $r): bool => $r instanceof Review && str_starts_with($r->getContent(), 'Tombstone check')
            ),
        );
        self::assertSame(['Tombstone check alive'], array_values($reviewContents));

        /** @var Message[] $byReferring */
        $byReferring = $this->messageRepository->getMessagesPublishedByReferring('/test-page');
        $referringContents = array_map(
            static fn (Message $m): string => $m->getContent(),
            array_filter($byReferring, static fn (Message $m): bool => str_starts_with($m->getContent(), 'Tombstone check')),
        );
        self::assertSame(['Tombstone check alive'], array_values($referringContents));
    }

    private function createTestReview(string $content, int $weight, int $rating = 5): Review
    {
        $review = new Review();
        $review->host = $this->testHost;
        $review->setContent($content);
        $review->authorEmail = 'test@example.com';
        $review->authorName = 'Test User';
        $review->referring = '/test-page';
        $review->setRating($rating);
        $review->setWeight($weight);
        $review->publishedAt = new DateTime();

        return $review;
    }

    private function trackCreatedMessage(Review $review): void
    {
        if (null !== $review->id) {
            $this->createdMessageIds[] = $review->id;
        }
    }
}
