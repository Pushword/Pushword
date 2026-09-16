<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Symfony\Component\HttpFoundation\Request;

#[Group('integration')]
final class MediaViewPreferenceTest extends AbstractAdminTestClass
{
    public function testMediaOpensInMosaicByDefault(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_media_list'));
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('.media-mosaic-wrapper'), 'Media is what you scan, so mosaic is the default');
        self::assertCount(0, $crawler->filter('#pw-media-table'));
    }

    public function testTheChosenViewSticksForTheSession(): void
    {
        $client = $this->loginUser();
        $indexUrl = $this->generateAdminUrl('admin_media_list');

        $crawler = $client->request(Request::METHOD_GET, $indexUrl.'?view=table');
        self::assertCount(1, $crawler->filter('#pw-media-table'));

        // Walk away and come back without a parameter: the choice must survive.
        $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_page_list'));
        $crawler = $client->request(Request::METHOD_GET, $indexUrl);
        self::assertCount(1, $crawler->filter('#pw-media-table'), 'The table choice must outlive the navigation');
        self::assertCount(0, $crawler->filter('.media-mosaic-wrapper'));

        // And switching back sticks too.
        $client->request(Request::METHOD_GET, $indexUrl.'?view=mosaic');
        $crawler = $client->request(Request::METHOD_GET, $indexUrl);
        self::assertCount(1, $crawler->filter('.media-mosaic-wrapper'));
    }

    public function testThePickerDoesNotOverwriteThePreference(): void
    {
        $client = $this->loginUser();
        $indexUrl = $this->generateAdminUrl('admin_media_list');

        $client->request(Request::METHOD_GET, $indexUrl.'?view=table');

        // The picker embeds this list and always asks for mosaic; that is its own need,
        // not a choice the editor made.
        $crawler = $client->request(Request::METHOD_GET, $indexUrl.'?pwMediaPicker=1&view=mosaic');
        self::assertCount(1, $crawler->filter('.media-mosaic-wrapper[data-pw-media-picker-embedded]'));

        $crawler = $client->request(Request::METHOD_GET, $indexUrl);
        self::assertCount(1, $crawler->filter('#pw-media-table'), 'Opening the picker must not reset the list preference');
    }

    public function testMosaicCardsWearTheAdminsOwnElevation(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_media_list'));

        $cards = $crawler->filter('.media-mosaic__card.card');
        self::assertGreaterThan(0, $cards->count(), 'A mosaic card is a card');

        // Bootstrap's .shadow-sm is !important, so carrying it means the admin's own
        // --pw-elevation-2 never reaches the card.
        self::assertCount(0, $crawler->filter('.media-mosaic__card.shadow-sm'));
    }

    public function testTheActiveHalfOfTheSwitchIsMarked(): void
    {
        $client = $this->loginUser();
        $crawler = $client->request(Request::METHOD_GET, $this->generateAdminUrl('admin_media_list').'?view=table');

        $active = $crawler->filter('.media-view-switch a.active');
        self::assertCount(1, $active, 'Exactly one half of the segmented control is current');
        self::assertSame('true', $active->attr('aria-pressed'));

        // "List" must ask for the table explicitly: no parameter now means mosaic.
        self::assertStringContainsString('view=table', (string) $active->attr('href'));
    }
}
