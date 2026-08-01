<?php

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
