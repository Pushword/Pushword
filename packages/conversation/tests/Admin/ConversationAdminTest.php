<?php

declare(strict_types=1);

namespace Pushword\conversation\Tests\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testReviewIndexExposesEmailAndReferringFilters(): void
    {
        $client = $this->loginUser();

        $client->request(Request::METHOD_GET, '/admin/review/render-filters');
        self::assertResponseIsSuccessful();

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('filters[authorEmail][value]', $html);
        self::assertStringContainsString('filters[referring][value]', $html);
        self::assertStringContainsString('Email', $html);
        self::assertStringContainsString('Referring', $html);
    }

    /**
     * @return iterable<string, array{array<string, array{comparison: string, value: string}>, list<string>, list<string>}>
     */
    public static function provideReviewFilters(): iterable
    {
        $aliceFrab1234 = 'Alice FRAB1234 filter marker';
        $aliceDecd5678 = 'Alice DECD5678 filter marker';
        $bobFrab1234 = 'Bob FRAB1234 filter marker';
        $bobFrab = 'Bob FRAB filter marker';
        $anonymous = 'Anonymous filter marker';

        yield 'email and trip code combine' => [
            [
                'authorEmail' => ['comparison' => 'like', 'value' => 'filter-alice@example.test'],
                'referring' => ['comparison' => 'like', 'value' => 'FRAB1234'],
            ],
            [$aliceFrab1234],
            [$aliceDecd5678, $bobFrab1234, $bobFrab, $anonymous],
        ];

        // "contains" is the default comparison; a review without email never matches it.
        yield 'email contains a fragment' => [
            ['authorEmail' => ['comparison' => 'like', 'value' => 'alice']],
            [$aliceFrab1234, $aliceDecd5678],
            [$bobFrab1234, $bobFrab, $anonymous],
        ];

        yield 'trip code contains a fragment' => [
            ['referring' => ['comparison' => 'like', 'value' => 'FRAB']],
            [$aliceFrab1234, $bobFrab1234, $bobFrab],
            [$aliceDecd5678, $anonymous],
        ];

        yield 'trip code equals the whole code only' => [
            ['referring' => ['comparison' => '=', 'value' => 'FRAB']],
            [$bobFrab],
            [$aliceFrab1234, $aliceDecd5678, $bobFrab1234, $anonymous],
        ];

        // SQLite compares `=` case-sensitively, so this case fails without the lowering.
        yield 'filters ignore case' => [
            [
                'authorEmail' => ['comparison' => '=', 'value' => 'FILTER-BOB@EXAMPLE.TEST'],
                'referring' => ['comparison' => '=', 'value' => 'frab'],
            ],
            [$bobFrab],
            [$aliceFrab1234, $aliceDecd5678, $bobFrab1234, $anonymous],
        ];

        // Fails on SQLite too without the lowering: 'FRAB1234' != 'frab1234' holds.
        yield 'not exactly ignores case' => [
            ['referring' => ['comparison' => '!=', 'value' => 'frab1234']],
            [$aliceDecd5678, $bobFrab, $anonymous],
            [$aliceFrab1234, $bobFrab1234],
        ];

        yield 'not contains ignores case' => [
            ['referring' => ['comparison' => 'not like', 'value' => 'frab']],
            [$aliceDecd5678, $anonymous],
            [$aliceFrab1234, $bobFrab1234, $bobFrab],
        ];
    }

    /**
     * @param array<string, array{comparison: string, value: string}> $filters
     * @param list<string>                                            $shown
     * @param list<string>                                            $hidden
     */
    #[DataProvider('provideReviewFilters')]
    public function testReviewFiltersNarrowTheList(array $filters, array $shown, array $hidden): void
    {
        $client = $this->loginUser();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $persistedReviews = [];
        foreach ([
            ['filter-alice@example.test', 'FRAB1234', 'Alice FRAB1234 filter marker'],
            ['filter-alice@example.test', 'DECD5678', 'Alice DECD5678 filter marker'],
            ['filter-bob@example.test', 'FRAB1234', 'Bob FRAB1234 filter marker'],
            ['filter-bob@example.test', 'FRAB', 'Bob FRAB filter marker'],
            [null, '', 'Anonymous filter marker'],
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
            $client->request(Request::METHOD_GET, '/admin/review?'.http_build_query(['filters' => $filters]));
            self::assertResponseIsSuccessful();

            $html = (string) $client->getResponse()->getContent();
            foreach ($shown as $marker) {
                self::assertStringContainsString($marker, $html);
            }

            foreach ($hidden as $marker) {
                self::assertStringNotContainsString($marker, $html);
            }
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
