<?php

namespace Pushword\Admin\Tests\Filter;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\PageHoldFilter;
use Pushword\Core\Entity\Page;

#[Group('integration')]
final class PageHoldFilterTest extends AbstractFilterTestCase
{
    public function testHeldValueFiltersNotNull(): void
    {
        self::assertStringContainsString('entity.holdPublicationAt IS NOT NULL', $this->applyDql('held'));
    }

    public function testLiveValueFiltersNull(): void
    {
        self::assertStringContainsString('entity.holdPublicationAt IS NULL', $this->applyDql('live'));
    }

    public function testEmptyValueLeavesQueryUntouched(): void
    {
        self::assertStringNotContainsString('holdPublicationAt', $this->applyDql(''));
    }

    private function applyDql(mixed $value): string
    {
        return $this->apply(
            PageHoldFilter::new('holdPublicationAt'),
            Page::class,
            'holdPublicationAt',
            ['comparison' => '=', 'value' => $value],
        )->getDQL();
    }
}
