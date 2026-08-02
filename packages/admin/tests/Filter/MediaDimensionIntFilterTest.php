<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\MediaDimensionIntFilter;
use Pushword\Core\Entity\Media;

#[Group('integration')]
final class MediaDimensionIntFilterTest extends AbstractFilterTestCase
{
    public function testNewExposesTheDimensionProperty(): void
    {
        self::assertSame('dimensionIntFilter', MediaDimensionIntFilter::new()->getAsDto()->getProperty());
    }

    /** The filter is a single input matching either side, so both sides carry the same parameter. */
    public function testComparisonMatchesEitherWidthOrHeight(): void
    {
        $queryBuilder = $this->applyValue('>=', 800);

        self::assertStringContainsString('entity.imageData.width >= :dimensionIntFilter_0', $queryBuilder->getDQL());
        self::assertStringContainsString('entity.imageData.height >= :dimensionIntFilter_0', $queryBuilder->getDQL());
        self::assertSame(['dimensionIntFilter_0' => 800], $this->parameters($queryBuilder));
    }

    public function testBetweenUsesBothBounds(): void
    {
        $queryBuilder = $this->applyValue(ComparisonType::BETWEEN, 800, 1600);

        self::assertStringContainsString(
            'entity.imageData.width BETWEEN :dimensionIntFilter_0 AND :dimensionIntFilter_1',
            $queryBuilder->getDQL(),
        );
        self::assertSame(
            ['dimensionIntFilter_0' => 800, 'dimensionIntFilter_1' => 1600],
            $this->parameters($queryBuilder),
        );
    }

    public function testBetweenWithoutAnUpperBoundLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('imageData', $this->applyValue(ComparisonType::BETWEEN, 800)->getDQL());
    }

    public function testNonNumericValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('imageData', $this->applyValue('>=', 'wide')->getDQL());
    }

    private function applyValue(string $comparison, mixed $value, mixed $value2 = null): QueryBuilder
    {
        return $this->apply(
            MediaDimensionIntFilter::new(),
            Media::class,
            'dimensionIntFilter',
            ['comparison' => $comparison, 'value' => $value, 'value2' => $value2],
        );
    }
}
