<?php

namespace Pushword\conversation\Tests\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Conversation\Entity\Message;
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
