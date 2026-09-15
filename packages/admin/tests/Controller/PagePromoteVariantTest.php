<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PagePromoteVariantTest extends AbstractAdminTestClass
{
    public function testPromoteVariantFromTheActionsDropdown(): void
    {
        $client = $this->loginUser();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $master = $this->newPage('promote-master');
        $variant = $this->newPage('promote-variant');
        $variant->variantOf = $master;

        $em->persist($master);
        $em->persist($variant);
        $em->flush();

        $variantId = $variant->id;
        $masterId = $master->id;

        $path = self::getContainer()->get('router')->generate('admin_page_promote_variant', ['entityId' => $variantId]);

        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list', ['filters' => ['slug' => ['comparison' => 'like', 'value' => 'promote-']]]));
        self::assertResponseIsSuccessful();

        // Only the variant offers the action, and it posts with a CSRF token.
        $form = $crawler->filter('.pw-page-actions .dropdown-menu form[action$="'.$path.'"]');
        self::assertCount(1, $form);
        self::assertCount(1, $form->filter('button.dropdown-item.action-promoteVariant'));
        $token = $form->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        $client->request(Request::METHOD_POST, $path, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $client->request(Request::METHOD_POST, $path, ['_token' => $token]);
        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());

        // Container is rebuilt after the HTTP request — fetch fresh instances
        /** @var PageRepository $pageRepo */
        $pageRepo = self::getContainer()->get(PageRepository::class);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $promoted = $pageRepo->find($variantId);
        $demoted = $pageRepo->find($masterId);
        self::assertNotNull($promoted);
        self::assertNotNull($demoted);
        self::assertFalse($promoted->isVariant(), 'The promoted page becomes the master');
        self::assertSame($promoted->id, $demoted->variantOf?->id, 'The former master becomes its variant');

        $demoted->variantOf = null;
        $em->flush();
        $em->remove($demoted);
        $em->remove($promoted);
        $em->flush();
    }

    private function newPage(string $slug): Page
    {
        $page = new Page();
        $page->h1 = ucfirst($slug);
        $page->slug = $slug;
        $page->locale = 'en';
        $page->host = 'localhost.dev';
        $page->createdAt = new DateTime();
        $page->updatedAt = new DateTime();
        $page->mainContent = 'Content of '.$slug;

        return $page;
    }
}
