<?php

namespace Pushword\Admin\Tests\Filter;

use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Filter\PageHoldFilter;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageHoldFilterTest extends KernelTestCase
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
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $queryBuilder = $em->createQueryBuilder()
            ->select('entity')
            ->from(Page::class, 'entity');

        $filterDto = new FilterDto();
        $filterDto->setProperty('holdPublicationAt');

        $filterDataDto = FilterDataDto::new(0, $filterDto, 'entity', ['comparison' => '=', 'value' => $value]);
        $entityDto = new EntityDto(Page::class, $em->getMetadataFactory()->getMetadataFor(Page::class));

        PageHoldFilter::new('holdPublicationAt')->apply($queryBuilder, $filterDataDto, null, $entityDto);

        return $queryBuilder->getDQL();
    }
}
