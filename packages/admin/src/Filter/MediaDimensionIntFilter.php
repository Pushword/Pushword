<?php

namespace Pushword\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class MediaDimensionIntFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(TranslatableInterface|string|false|null $label = null): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty('dimensionIntFilter')
            ->setLabel($label)
            ->setFormType(NumericFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $comparison = $filterDataDto->getComparison();
        $parameterName = $filterDataDto->getParameterName();
        $parameter2Name = $filterDataDto->getParameter2Name();
        $value = $filterDataDto->getValue();
        $value2 = $filterDataDto->getValue2();

        if (! \is_numeric($value)) {
            return;
        }

        if (ComparisonType::BETWEEN === $comparison) {
            if (! \is_numeric($value2)) {
                return;
            }

            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->orX(
                        sprintf('%s.width BETWEEN :%s AND :%s', $alias, $parameterName, $parameter2Name),
                        sprintf('%s.height BETWEEN :%s AND :%s', $alias, $parameterName, $parameter2Name),
                    ),
                )
                ->setParameter($parameterName, $value)
                ->setParameter($parameter2Name, $value2);

            return;
        }

        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->orX(
                    sprintf('%s.width %s :%s', $alias, $comparison, $parameterName),
                    sprintf('%s.height %s :%s', $alias, $comparison, $parameterName),
                ),
            )
            ->setParameter($parameterName, $value);
    }
}
