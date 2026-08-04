<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\MediaUsageFilter;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\MediaUsage;
use Pushword\Core\Repository\MediaRepository;

#[Group('integration')]
final class MediaUsageFilterTest extends AbstractFilterTestCase
{
    public function testNotReferencedAsksForTheAbsenceOfAnyUsageRow(): void
    {
        $dql = $this->applyValue(MediaUsageFilter::NOT_REFERENCED)->getDQL();

        self::assertStringContainsString('NOT EXISTS', $dql);
        self::assertStringContainsString(MediaUsage::class, $dql);
        self::assertStringNotContainsString('NOT (NOT EXISTS', $dql);
    }

    public function testReferencedIsTheSameSubqueryNegated(): void
    {
        self::assertStringContainsString('NOT (NOT EXISTS', $this->applyValue(MediaUsageFilter::REFERENCED)->getDQL());
    }

    /** A subquery, never a join: the index paginator counts rows and must not see duplicates. */
    public function testItNeverJoinsTheUsageTable(): void
    {
        self::assertStringNotContainsString('JOIN', $this->applyValue(MediaUsageFilter::NOT_REFERENCED)->getDQL());
    }

    public function testNonStringValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('EXISTS', $this->applyValue(null)->getDQL());
    }

    private function applyValue(mixed $value): QueryBuilder
    {
        self::bootKernel();

        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);

        return $this->apply(
            MediaUsageFilter::new($mediaRepository),
            Media::class,
            'mediaUsageFilter',
            ['comparison' => '=', 'value' => $value],
        );
    }
}
