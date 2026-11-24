<?php

declare(strict_types=1);

namespace Pushword\Flat\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Flat\Entity\AdminNotification;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class NotificationCrudControllerTest extends AbstractAdminTestClass
{
    public function testTheListKeepsDetailsVisibleAndMovesDeleteIntoTheMenu(): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $notification = new AdminNotification();
        $notification->type = AdminNotification::TYPE_SYNC_ERROR;
        $notification->message = 'Sync failed';

        $em->persist($notification);
        $em->flush();

        try {
            $crawler = $client->request(Request::METHOD_GET, '/admin/notification');

            self::assertResponseIsSuccessful();
            $actions = $crawler->filter('tr[data-id="'.$notification->id.'"] td.actions');
            $details = $actions->filter('a.action-detail:not(.dropdown-item)');
            $delete = $actions->filter('.pw-row-menu .dropdown-item[data-action-name="delete"][data-action-confirmation="true"]');
            self::assertCount(1, $delete);
            self::assertSame('/admin/notification/'.$notification->id.'/delete', parse_url($delete->attr('formaction') ?? '', \PHP_URL_PATH));
            self::assertCount(0, $actions->filter('.action-delete:not(.dropdown-item)'));

            $client->click($details->link());
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('.content-header-title h1', 'Notification #'.$notification->id);
        } finally {
            $em->getConnection()->delete('admin_notification', ['id' => $notification->id]);
        }
    }
}
