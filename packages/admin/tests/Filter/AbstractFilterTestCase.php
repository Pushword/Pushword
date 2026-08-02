<?php

namespace Pushword\Admin\Tests\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * `apply()` is pure query building, so a filter is exercised through the DQL and
 * the parameters it leaves on the builder — no rows needed in the database.
 */
abstract class AbstractFilterTestCase extends KernelTestCase
{
    /**
     * @param class-string                                            $entityClass
     * @param array{comparison: string, value: mixed, value2?: mixed} $formData    as the filter form submits it
     */
    protected function apply(FilterInterface $filter, string $entityClass, string $property, array $formData): QueryBuilder
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('entity')
            ->from($entityClass, 'entity');

        $filterDto = new FilterDto();
        $filterDto->setProperty($property);

        $filter->apply(
            $queryBuilder,
            FilterDataDto::new(0, $filterDto, 'entity', $formData),
            null,
            new EntityDto($entityClass, $entityManager->getMetadataFactory()->getMetadataFor($entityClass)),
        );

        return $queryBuilder;
    }

    /** @return array<string, mixed> */
    protected function parameters(QueryBuilder $queryBuilder): array
    {
        $parameters = [];
        foreach ($queryBuilder->getParameters() as $parameter) {
            $parameters[$parameter->getName()] = $parameter->getValue();
        }

        return $parameters;
    }
}
