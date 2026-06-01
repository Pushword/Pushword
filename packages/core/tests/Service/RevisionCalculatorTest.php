<?php

namespace Pushword\Core\Tests\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\RevisionCalculator;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class RevisionCalculatorTest extends TestCase
{
    public function testRevisionIsStableForSamePage(): void
    {
        $page = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $calculator = $this->buildCalculator();

        self::assertSame($calculator->compute($page), $calculator->compute($page));
    }

    public function testRevisionChangesWhenUpdatedAtChanges(): void
    {
        $calculator = $this->buildCalculator();
        $earlier = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $later = $this->buildPage('example.com', 'about', '2026-05-28T10:00:01+00:00');

        self::assertNotSame($calculator->compute($earlier), $calculator->compute($later));
    }

    public function testRevisionChangesWhenSlugOrHostChanges(): void
    {
        $calculator = $this->buildCalculator();
        $a = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $b = $this->buildPage('example.com', 'contact', '2026-05-28T10:00:00+00:00');
        $c = $this->buildPage('other.example', 'about', '2026-05-28T10:00:00+00:00');

        self::assertNotSame($calculator->compute($a), $calculator->compute($b));
        self::assertNotSame($calculator->compute($a), $calculator->compute($c));
    }

    public function testRevisionChangesWhenAnyColumnChanges(): void
    {
        $calculator = $this->buildCalculator();
        $a = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $b = $this->buildPage('example.com', 'about', '2026-05-28T10:00:00+00:00');
        $b->setH1('Different headline');

        self::assertNotSame(
            $calculator->compute($a),
            $calculator->compute($b),
            'Changing any #[Column] property must shift the revision token'
        );
    }

    private function buildCalculator(): RevisionCalculator
    {
        return new RevisionCalculator(new Serializer(
            [new DateTimeNormalizer(), new ObjectNormalizer()],
            ['json' => new JsonEncoder()],
        ));
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
