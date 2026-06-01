<?php

namespace Pushword\PageWorkflow\Tests\Pending;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Pending\PendingPayload;

final class PendingPayloadTest extends TestCase
{
    public function testSnapshotFromPageReturnsEveryEditorialField(): void
    {
        $page = new Page();
        $page->setH1('Live H1');
        $page->setMainContent('Live body');
        $page->title = 'Live title';
        $page->name = 'Live name';
        $page->metaRobots = 'noindex';

        $snapshot = PendingPayload::snapshotFromPage($page);

        self::assertSame(
            ['h1', 'mainContent', 'title', 'name', 'metaRobots'],
            array_keys($snapshot),
        );
        self::assertSame('Live H1', $snapshot['h1']);
        self::assertSame('Live body', $snapshot['mainContent']);
        self::assertSame('Live title', $snapshot['title']);
        self::assertSame('Live name', $snapshot['name']);
        self::assertSame('noindex', $snapshot['metaRobots']);
    }

    public function testApplyOnPageOverwritesEachField(): void
    {
        $page = new Page();
        $page->setH1('Before');
        $page->setMainContent('Before body');
        $page->title = 'Before title';
        $page->name = 'Before name';
        $page->metaRobots = '';

        PendingPayload::applyOnPage($page, [
            'h1' => 'After',
            'mainContent' => 'After body',
            'title' => 'After title',
            'name' => 'After name',
            'metaRobots' => 'noindex,nofollow',
        ]);

        self::assertSame('After', $page->getH1());
        self::assertSame('After body', $page->getMainContent());
        self::assertSame('After title', $page->title);
        self::assertSame('After name', $page->name);
        self::assertSame('noindex,nofollow', $page->metaRobots);
    }

    public function testApplyOnPageIgnoresMissingKeysAndNonStringValues(): void
    {
        $page = new Page();
        $page->setH1('Kept');
        $page->setMainContent('Kept body');
        $page->title = 'Kept title';

        PendingPayload::applyOnPage($page, [
            'mainContent' => 'Replaced',
            'title' => 123, // non-string: must be ignored
        ]);

        self::assertSame('Kept', $page->getH1(), 'missing key leaves field untouched');
        self::assertSame('Replaced', $page->getMainContent());
        self::assertSame('Kept title', $page->title, 'non-string value leaves field untouched');
    }
}
