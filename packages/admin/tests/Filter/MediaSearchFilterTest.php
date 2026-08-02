<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\MediaSearchFilter;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;

#[Group('integration')]
final class MediaSearchFilterTest extends AbstractFilterTestCase
{
    public function testNewSearchesOnAlt(): void
    {
        self::assertSame('alt', $this->makeFilter()->getAsDto()->getProperty());
    }

    /** The repository decides which columns a media search spans; the filter only delegates. */
    public function testSearchSpansEveryNamingColumn(): void
    {
        $dql = $this->applyValue('logo')->getDQL();

        foreach (['fileName', 'fileNameHistory', 'alt', 'altSearch', 'alts', 'tags'] as $column) {
            self::assertStringContainsString('entity.'.$column.' LIKE', $dql);
        }
    }

    public function testEmptyValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('LIKE', $this->applyValue('')->getDQL());
    }

    public function testNonStringValueLeavesTheQueryUntouched(): void
    {
        self::assertStringNotContainsString('LIKE', $this->applyValue(null)->getDQL());
    }

    private function makeFilter(): MediaSearchFilter
    {
        self::bootKernel();

        /** @var MediaRepository $mediaRepository */
        $mediaRepository = self::getContainer()->get(MediaRepository::class);

        return MediaSearchFilter::new($mediaRepository);
    }

    private function applyValue(mixed $value): QueryBuilder
    {
        return $this->apply(
            $this->makeFilter(),
            Media::class,
            'alt',
            ['comparison' => 'LIKE', 'value' => $value],
        );
    }
}
