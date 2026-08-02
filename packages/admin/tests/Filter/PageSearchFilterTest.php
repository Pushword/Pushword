<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\PageSearchFilter;
use Pushword\Core\Entity\Page;

#[Group('integration')]
final class PageSearchFilterTest extends AbstractFilterTestCase
{
    public function testNewLabelsTheFilterAfterItsFirstField(): void
    {
        self::assertSame('title', PageSearchFilter::new(['title', 'h1'])->getAsDto()->getProperty());
    }

    public function testNewRejectsAnEmptyFieldList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PageSearchFilter::new([]);
    }

    /** One input searches several columns at once, so they are concatenated before comparison. */
    public function testFieldsAreSearchedAsOneConcatenatedValue(): void
    {
        $queryBuilder = $this->applyValue('%contact%');

        self::assertStringContainsString('CONCAT(entity.title, entity.h1) LIKE :title_0', $queryBuilder->getDQL());
        self::assertSame(['title_0' => '%contact%'], $this->parameters($queryBuilder));
    }

    public function testEmptyValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('CONCAT', $this->applyValue('')->getDQL());
    }

    public function testNonStringValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('CONCAT', $this->applyValue(null)->getDQL());
    }

    private function applyValue(mixed $value): QueryBuilder
    {
        return $this->apply(
            PageSearchFilter::new(['title', 'h1']),
            Page::class,
            'title',
            ['comparison' => 'LIKE', 'value' => $value],
        );
    }
}
