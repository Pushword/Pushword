<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\MediaPageTagFilter;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;

#[Group('integration')]
final class MediaPageTagFilterTest extends AbstractFilterTestCase
{
    /** The point of the whole column: it never reaches the tags somebody set by hand. */
    public function testItFiltersOnTheInheritedColumnNotTheOwnOne(): void
    {
        $dto = $this->makeFilter()->getAsDto();

        self::assertSame('pageTags', $dto->getProperty());

        $queryBuilder = $this->applyValue(['mountain']);

        self::assertStringContainsString('entity.pageTags LIKE :pageTag_0', $queryBuilder->getDQL());
        self::assertStringNotContainsString('entity.tags LIKE', $queryBuilder->getDQL());
    }

    /** Whole JSON values, so `mountain` cannot answer for `mountain-lodge`. */
    public function testEachTagMatchesAWholeJsonValue(): void
    {
        $queryBuilder = $this->applyValue(['mountain', 'lodge']);

        self::assertSame(
            ['pageTag_0' => '%"mountain"%', 'pageTag_1' => '%"lodge"%'],
            $this->parameters($queryBuilder),
        );
    }

    public function testEmptySelectionLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('pageTags', $this->applyValue([])->getDQL());
    }

    public function testNonArrayValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('pageTags', $this->applyValue('mountain')->getDQL());
    }

    private function makeFilter(): MediaPageTagFilter
    {
        self::bootKernel();

        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);

        return MediaPageTagFilter::new($mediaRepository);
    }

    private function applyValue(mixed $value): QueryBuilder
    {
        return $this->apply(
            $this->makeFilter(),
            Media::class,
            'pageTags',
            ['comparison' => '=', 'value' => $value],
        );
    }
}
