<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class RedirectionFormTest extends AbstractAdminTestClass
{
    public function testDestinationFormCreatesAWorkingRedirection(): void
    {
        $client = $this->loginUser();
        $client->disableReboot();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $crawler = $client->request(Request::METHOD_GET, '/admin/page-redirection/new');
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('.h1Field, [data-editor="markdown"]'));
            self::assertCount(1, $crawler->filter('input[name="Page[mainContent]"]'));
            self::assertStringContainsString('From', $crawler->filter('.columnFields')->text());

            $form = $crawler->selectButton('Create')->form();
            $form['Page[slug]'] = '__audit_test_redirection';
            $form['Page[mainContent]'] = '/destination 302';
            $client->submit($form);
            self::assertResponseRedirects();

            $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => '__audit_test_redirection']);
            self::assertInstanceOf(Page::class, $page);
            self::assertSame('Location: /destination 302', $page->mainContent);
            self::assertSame('/destination', $page->getRedirectionUrl());
            self::assertSame(302, $page->getRedirectionCode());

            $crawler = $client->request(Request::METHOD_GET, '/admin/page-redirection/'.$page->id.'/edit');
            self::assertResponseIsSuccessful();
            self::assertSame('/destination 302', $crawler->filter('input[name="Page[mainContent]"]')->attr('value'));
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
