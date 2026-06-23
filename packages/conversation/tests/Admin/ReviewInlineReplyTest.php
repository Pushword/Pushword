<?php

namespace Pushword\Conversation\Tests\Admin;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Conversation\Entity\Review;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers inline editing of a review reply on the review index
 * (pushword_conversation_inline_update with field=reply).
 */
final class ReviewInlineReplyTest extends AbstractAdminTestClass
{
    public function testInlineUpdateSetsAndClearsReply(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $reviewId = $this->createReview();

        $crawler = $client->request(Request::METHOD_GET, '/admin/review');
        self::assertResponseIsSuccessful();

        $replyField = $crawler->filter('#pw-message-inline-'.$reviewId.' [hx-vals*="reply"]');
        self::assertCount(1, $replyField, 'The review row should expose an inline reply field.');
        $token = $this->extractToken((string) $replyField->attr('hx-vals'));

        // Setting a reply persists it and the re-rendered row reflects the new value.
        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/inline-update', [
            'field' => 'reply',
            'value' => 'Thank you for your feedback!',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertStringContainsString('Thank you for your feedback!', (string) $client->getResponse()->getContent());
        self::assertSame('Thank you for your feedback!', $this->reloadReview($reviewId)->getReply());

        // Clearing it removes the custom property.
        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/inline-update', [
            'field' => 'reply',
            'value' => '',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $review = $this->reloadReview($reviewId);
        self::assertSame('', $review->getReply());
        self::assertFalse($review->hasCustomProperty('reply'));
    }

    public function testInlineReplyFillsConfiguredDefaultAuthorWhenEmpty(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $expectedDefault = self::getContainer()->get(SiteRegistry::class)
            ->get()->getStr('conversation_review_default_reply_author');
        self::assertNotSame('', $expectedDefault, 'A default reply author must be configured for the default site.');

        $reviewId = $this->createReview();

        $crawler = $client->request(Request::METHOD_GET, '/admin/review');
        self::assertResponseIsSuccessful();
        $replyField = $crawler->filter('#pw-message-inline-'.$reviewId.' [hx-vals*="reply"]');
        $token = $this->extractToken((string) $replyField->attr('hx-vals'));

        // Setting a reply with no explicit author falls back to the configured default.
        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/inline-update', [
            'field' => 'reply',
            'value' => 'Glad you liked it!',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $review = $this->reloadReview($reviewId);
        self::assertSame('Glad you liked it!', $review->getReply());
        self::assertSame($expectedDefault, $review->getReplyAuthor());
    }

    public function testInlineUpdateRejectsInvalidCsrf(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $reviewId = $this->createReview();

        $client->request(Request::METHOD_POST, '/admin/conversation/'.$reviewId.'/inline-update', [
            'field' => 'reply',
            'value' => 'Should not persist',
            '_token' => 'invalid-token',
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        self::assertSame('', $this->reloadReview($reviewId)->getReply());
    }

    private function createReview(): int
    {
        $review = new Review();
        $review->setContent('Inline reply test review');
        $review->setRating(5);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($review);
        $entityManager->flush();

        self::assertNotNull($review->id);

        return $review->id;
    }

    private function reloadReview(int $id): Review
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $review = $entityManager->getRepository(Review::class)->find($id);
        self::assertInstanceOf(Review::class, $review);

        return $review;
    }

    /**
     * Reverse Twig's `e('js')` escaping applied to the token in hx-vals.
     */
    private function extractToken(string $hxVals): string
    {
        self::assertSame(1, preg_match('/_token:\s*"([^"]*)"/', $hxVals, $matches));

        $token = preg_replace_callback(
            '/\\\\u([0-9A-Fa-f]{4})/',
            static fn (array $match): string => mb_chr((int) hexdec($match[1]), 'UTF-8'),
            $matches[1],
        );

        self::assertNotNull($token);

        return $token;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManager $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $entityManager;
    }
}
