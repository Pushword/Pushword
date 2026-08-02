<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\MediaTagFilter;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;

#[Group('integration')]
final class MediaTagFilterTest extends AbstractFilterTestCase
{
    public function testNewOffersTheStoredTagsAsSortedChoices(): void
    {
        $dto = $this->makeFilter()->getAsDto();

        self::assertSame('tags', $dto->getProperty());

        $choices = $dto->getFormTypeOption('choices');
        self::assertIsArray($choices);
        self::assertSame(array_keys($choices), array_values($choices), 'a tag is its own label');

        $sorted = array_values($choices);
        sort($sorted);
        self::assertSame($sorted, array_values($choices));
    }

    /** Tags share one column, so each selected tag adds its own LIKE — the filter is an AND. */
    public function testEachTagAddsItsOwnLikeClause(): void
    {
        $queryBuilder = $this->applyValue(['logo', 'banner']);

        self::assertStringContainsString('entity.tags LIKE :tag_0', $queryBuilder->getDQL());
        self::assertStringContainsString('entity.tags LIKE :tag_1', $queryBuilder->getDQL());
        self::assertSame(['tag_0' => '%logo%', 'tag_1' => '%banner%'], $this->parameters($queryBuilder));
    }

    public function testEmptySelectionLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('tags', $this->applyValue([])->getDQL());
    }

    public function testNonArrayValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('tags', $this->applyValue('logo')->getDQL());
    }

    private function makeFilter(): MediaTagFilter
    {
        self::bootKernel();

        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);

        return MediaTagFilter::new($mediaRepository);
    }

    private function applyValue(mixed $value): QueryBuilder
    {
        return $this->apply(
            $this->makeFilter(),
            Media::class,
            'tags',
            ['comparison' => '=', 'value' => $value],
        );
    }
}
