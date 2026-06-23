<?php

namespace Pushword\Conversation\Tests\Admin;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Conversation\Entity\Review;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers the review edit page extras added on top of the EasyAdmin defaults:
 * the DELETE action (configureActions) and the publish toggle script (configureAssets).
 */
final class ReviewEditPageTest extends AbstractAdminTestClass
{
    public function testEditPageExposesDeleteAction(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $reviewId = $this->createReview();

        $crawler = $client->request(Request::METHOD_GET, '/admin/review/'.$reviewId.'/edit');
        self::assertResponseIsSuccessful();

        // EasyAdmin adds DELETE only to the index/detail pages by default;
        // configureActions() adds it to the edit page too.
        self::assertGreaterThan(
            0,
            $crawler->filter('.action-delete')->count(),
            'The review edit page should expose a delete action.',
        );
    }

    public function testEditPageRendersPublishToggle(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $reviewId = $this->createReview();

        $crawler = $client->request(Request::METHOD_GET, '/admin/review/'.$reviewId.'/edit');
        self::assertResponseIsSuccessful();

        // The publish toggle drives this input client-side.
        self::assertGreaterThan(
            0,
            $crawler->filter('input[name$="[publishedAt]"]')->count(),
            'The edit form must expose the publishedAt input the toggle drives.',
        );

        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('pw-publish-switch', $content, 'The publish toggle script should be injected on the edit page.');
        self::assertStringContainsString('var labels = {', $content, 'The toggle labels should be injected into the script.');
        self::assertStringNotContainsString('__PW_PUBLISH_LABELS__', $content, 'The labels placeholder should be replaced.');
    }

    private function createReview(): int
    {
        $review = new Review();
        $review->setContent('Edit page test review');
        $review->setRating(5);

        $entityManager = $this->getEntityManager();
        $entityManager->persist($review);
        $entityManager->flush();

        self::assertNotNull($review->id);

        return $review->id;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManager $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $entityManager;
    }
}
