<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class AdminEmptyStateTest extends AbstractAdminTestClass
{
    public function testASearchWithNoMatchOffersToClearItself(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(
            Request::METHOD_GET,
            $this->generateAdminUrl('admin_page_list').'?query=zzzznothingmatchesthis'
        );
        self::assertResponseIsSuccessful();

        $emptyState = $crawler->filter('.pw-empty-state');
        self::assertCount(1, $emptyState);
        self::assertSame('No result', trim($emptyState->filter('.pw-empty-state__title')->text()));

        // The way out of a dead end is clearing the search, not creating a page.
        $cta = $emptyState->filter('a');
        self::assertCount(1, $cta);
        self::assertSame('Clear search and filters', trim($cta->text()));
        $href = (string) $cta->attr('href');
        self::assertStringNotContainsString('zzzznothingmatchesthis', $href);
        self::assertStringNotContainsString('query=', $href);

        // EasyAdmin padded the empty table with 14 skeleton rows that CSS then hid.
        self::assertCount(0, $crawler->filter('.datagrid .empty-row'));
    }

    public function testAListWithNothingInItOffersItsOwnCreateAction(): void
    {
        $client = $this->loginUser();
        // Snippets are the one CRUD the dev-app fixtures leave empty, which is what
        // this branch needs: no rows and no search to clear.
        $crawler = $client->request(Request::METHOD_GET, '/admin/snippet');
        self::assertResponseIsSuccessful();

        $emptyState = $crawler->filter('.pw-empty-state');
        self::assertCount(1, $emptyState);
        self::assertSame('Nothing here yet', trim($emptyState->filter('.pw-empty-state__title')->text()));

        // Here the way forward is creating the first entry, using the CRUD's own action.
        $cta = $emptyState->filter('a.btn-primary');
        self::assertCount(1, $cta);
        self::assertStringContainsString('/new', (string) $cta->attr('href'));
    }
}
