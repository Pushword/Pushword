<?php

namespace Pushword\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use InvalidArgumentException;

use function sprintf;

use Symfony\Contracts\Translation\TranslatableInterface;

final class PageSearchFilter implements FilterInterface
{
    use FilterTrait;

    /** @var string[] */
    private array $fieldNames = [];

    /**
     * @param string[] $fieldNames
     */
    public static function new(array $fieldNames, TranslatableInterface|string|false|null $label = null): self
    {
        if ([] === $fieldNames) {
            throw new InvalidArgumentException('PageSearchFilter requires at least one field name.');
        }

        $filter = new self();
        $filter->fieldNames = $fieldNames;

        return $filter
            ->setFilterFqcn(self::class)
            ->setProperty($fieldNames[0])
            ->setLabel($label)
            ->setFormType(TextFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();

        if (! \is_string($value) || '' === $value) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $parameterName = $filterDataDto->getParameterName();
        $comparison = $filterDataDto->getComparison();
        $expr = $queryBuilder->expr();

        $fields = array_map(
            static fn (string $field): string => sprintf('%s.%s', $alias, $field),
            $this->fieldNames,
        );

        $concat = $expr->concat(...$fields);

        $queryBuilder->andWhere(sprintf('%s %s :%s', (string) $concat, $comparison, $parameterName))
            ->setParameter($parameterName, $value);
    }
}
