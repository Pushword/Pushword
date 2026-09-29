<?php

declare(strict_types=1);

namespace Pushword\Conversation\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;

use function sprintf;

/**
 * EasyAdmin's TextFilter compares the raw column, so case sensitivity depends on the
 * database: MariaDB ignores case, SQLite only for LIKE, PostgreSQL never. Lowering
 * both sides gives every database the same result.
 */
final class CaseInsensitiveTextFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, string $label): self
    {
        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TextFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $parameterName = $filterDataDto->getParameterName();

        $queryBuilder->andWhere(sprintf(
            'LOWER(%s.%s) %s LOWER(:%s)',
            $filterDataDto->getEntityAlias(),
            $filterDataDto->getProperty(),
            $filterDataDto->getComparison(),
            $parameterName,
        ))->setParameter($parameterName, $filterDataDto->getValue());
    }
}
