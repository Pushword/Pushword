<?php

namespace Pushword\Api\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pushword\Api\Service\RevisionCalculator;
use Pushword\Core\Entity\Page;

final class RevisionCalculatorTest extends TestCase
{
    public function testRevisionIsStableForSamePage(): void
    {
        $page = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $calculator = new RevisionCalculator();

        self::assertSame($calculator->compute($page), $calculator->compute($page));
    }

    public function testRevisionChangesWhenUpdatedAtChanges(): void
    {
        $calculator = new RevisionCalculator();
        $earlier = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $later = $this->buildPage('example.com', 'about', '2026-05-28T10:00:01+00:00');

        self::assertNotSame($calculator->compute($earlier), $calculator->compute($later));
    }

    public function testRevisionChangesWhenSlugOrHostChanges(): void
    {
        $calculator = new RevisionCalculator();
        $a = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $b = $this->buildPage('example.com', 'contact', '2026-05-28T10:00:00+00:00');
        $c = $this->buildPage('other.example', 'about', '2026-05-28T10:00:00+00:00');

        self::assertNotSame($calculator->compute($a), $calculator->compute($b));
        self::assertNotSame($calculator->compute($a), $calculator->compute($c));
    }

    private function buildPage(string $host, string $slug, string $updatedAt): Page
    {
        $page = new Page(false);
        $page->host = $host;
        $page->setSlug($slug);
        $page->updatedAt = new DateTimeImmutable($updatedAt);

        return $page;
    }
}
