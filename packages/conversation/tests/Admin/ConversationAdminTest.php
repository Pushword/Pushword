<?php

declare(strict_types=1);

namespace Pushword\conversation\Tests\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Entity\Review;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class ConversationAdminTest extends AbstractAdminTestClass
{
    public function testAdmin(): void
    {
        $client = $this->loginUser();

        $client->catchExceptions(false);

        $actions = ['', '/new'];
        $controllers = ['conversation', 'review'];

        foreach ($controllers as $controller) {
            foreach ($actions as $action) {
                $client->request(Request::METHOD_GET, '/admin/'.$controller.$action);
                self::assertResponseIsSuccessful();
            }
        }
    }

    public function testReviewIndexExposesEmailAndTripCodeFilters(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/review/render-filters');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('filters[authorEmail][value]', $html);
        self::assertStringContainsString('filters[referring][value]', $html);
        self::assertStringContainsString('Email', $html);
        self::assertStringContainsString('Trip code', $html);
    }

    public function testReviewFiltersFindAnEmailAndTripCode(): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $persistedReviews = [];
        foreach ([
            ['filter-alice@example.test', 'FRAB1234', 'Alice review filter marker'],
            ['filter-alice@example.test', 'DECD5678', 'Other trip filter marker'],
            ['filter-bob@example.test', 'FRAB1234', 'Other email filter marker'],
        ] as [$email, $referring, $content]) {
            $review = new Review();
            $review->host = 'localhost.dev';
            $review->authorEmail = $email;
            $review->referring = $referring;
            $review->setContent($content);
            $review->setRating(4);
            $entityManager->persist($review);
            $persistedReviews[] = $review;
        }

        $entityManager->flush();

        try {
            $client->request(Request::METHOD_GET, '/admin/review?'.http_build_query([
                'filters' => [
                    'authorEmail' => ['comparison' => 'like', 'value' => 'filter-alice@example.test'],
                    'referring' => ['comparison' => 'like', 'value' => 'FRAB1234'],
                ],
            ]));
            self::assertResponseIsSuccessful();

            $html = (string) $client->getResponse()->getContent();
            self::assertStringContainsString('Alice review filter marker', $html);
            self::assertStringNotContainsString('Other trip filter marker', $html);
            self::assertStringNotContainsString('Other email filter marker', $html);
        } finally {
            foreach ($persistedReviews as $review) {
                $stored = $entityManager->find(Review::class, $review->id);
                if (null !== $stored) {
                    $entityManager->remove($stored);
                }
            }

            $entityManager->flush();
        }
    }

    public function testAdminDeleteCreatesTombstone(): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $message = new Review();
        $message->host = 'localhost.dev';
        $message->setContent('Admin delete tombstone check');
        $message->setRating(4);

        $entityManager->persist($message);
        $entityManager->flush();

        $id = $message->id;

        $crawler = $client->request(Request::METHOD_GET, '/admin/review/'.$id.'/edit');
        self::assertResponseIsSuccessful();

        $formaction = (string) $crawler->filter('.action-delete')->attr('formaction');
        $token = (string) $crawler->filter('#action-confirmation-form input[name=token]')->attr('value');
        self::assertNotSame('', $formaction);
        $client->request(Request::METHOD_POST, $formaction, ['token' => $token]);

        // The row must survive as a tombstone: it carries the deletion through
        // the flat CSV to other databases.
        $entityManager->clear();
        $reloaded = $entityManager->find(Message::class, $id);
        self::assertInstanceOf(Message::class, $reloaded);
        self::assertNotNull($reloaded->deletedAt);

        $entityManager->remove($reloaded);
        $entityManager->flush();
    }

    public function testIndexHidesTombstonedMessages(): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $alive = new Message();
        $alive->host = 'localhost.dev';
        $alive->setContent('Admin tombstone check alive');

        $deleted = new Message();
        $deleted->host = 'localhost.dev';
        $deleted->setContent('Admin tombstone check deleted');
        $deleted->softDelete();

        $entityManager->persist($alive);
        $entityManager->persist($deleted);
        $entityManager->flush();

        $client->request(Request::METHOD_GET, '/admin/conversation');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Admin tombstone check alive', $html);
        self::assertStringNotContainsString('Admin tombstone check deleted', $html);

        foreach ([$alive->id, $deleted->id] as $id) {
            $message = $entityManager->find(Message::class, $id);
            if (null !== $message) {
                $entityManager->remove($message);
            }
        }

        $entityManager->flush();
    }
}
