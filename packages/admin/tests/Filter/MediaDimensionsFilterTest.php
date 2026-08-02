<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\MediaDimensionsFilter;
use Pushword\Core\Entity\Media;

#[Group('integration')]
final class MediaDimensionsFilterTest extends AbstractFilterTestCase
{
    public function testNewExposesTheDimensionsProperty(): void
    {
        $dto = MediaDimensionsFilter::new(['1200 × 630' => '1200×630'])->getAsDto();

        self::assertSame('dimensions', $dto->getProperty());
        self::assertTrue($dto->getFormTypeOption('multiple'));
    }

    public function testSingleDimensionMatchesWidthAndHeight(): void
    {
        $queryBuilder = $this->applyValue(['1200×630']);

        self::assertStringContainsString(
            '(entity.imageData.width = :dimensions_0_width_0 AND entity.imageData.height = :dimensions_0_height_0)',
            $queryBuilder->getDQL(),
        );
        self::assertSame(
            ['dimensions_0_width_0' => 1200, 'dimensions_0_height_0' => 630],
            $this->parameters($queryBuilder),
        );
    }

    public function testSeveralDimensionsAreOred(): void
    {
        $queryBuilder = $this->applyValue(['1200×630', '800×600']);

        self::assertStringContainsString('OR', $queryBuilder->getDQL());
        self::assertSame([
            'dimensions_0_width_0' => 1200,
            'dimensions_0_height_0' => 630,
            'dimensions_0_width_1' => 800,
            'dimensions_0_height_1' => 600,
        ], $this->parameters($queryBuilder));
    }

    /** A single choice arrives unwrapped when `multiple` is off on the submitted form. */
    public function testScalarValueIsAcceptedAsWellAsAnArray(): void
    {
        self::assertStringContainsString('entity.imageData.width', $this->applyValue('1200×630')->getDQL());
    }

    /** @return iterable<string, array{mixed}> */
    public static function provideIgnoredValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty array' => [[]];
        yield 'empty string' => [''];
        yield 'no separator' => [['1200x630']];
        yield 'non-numeric width' => [['w×630']];
        yield 'non-numeric height' => [['1200×h']];
        yield 'not a string' => [[1200]];
    }

    #[DataProvider('provideIgnoredValues')]
    public function testUnusableValuesLeaveTheQueryUntouched(mixed $value): void
    {
        $queryBuilder = $this->applyValue($value);

        self::assertStringNotContainsString('imageData', $queryBuilder->getDQL());
        self::assertSame([], $this->parameters($queryBuilder));
    }

    /** Padding around the separator comes from the choice labels, and must not defeat the match. */
    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        self::assertSame(
            ['dimensions_0_width_0' => 1200, 'dimensions_0_height_0' => 630],
            $this->parameters($this->applyValue([' 1200 × 630 '])),
        );
    }

    private function applyValue(mixed $value): QueryBuilder
    {
        return $this->apply(
            MediaDimensionsFilter::new(['1200 × 630' => '1200×630']),
            Media::class,
            'dimensions',
            ['comparison' => '=', 'value' => $value],
        );
    }
}
