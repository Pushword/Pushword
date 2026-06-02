<?php

namespace Pushword\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;

use function sprintf;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filters pages by their "hold publication" state. The held flag is stored as a
 * nullable datetime, so the query relies on IS NULL / IS NOT NULL rather than a
 * value comparison.
 */
final class PageHoldFilter implements FilterInterface
{
    use FilterTrait;

    private const string HELD = 'held';

    private const string LIVE = 'live';

    public static function new(string $propertyName, TranslatableInterface|string|false|null $label = null): self
    {
        $filter = new self();

        return $filter
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('value_type_options.choices', [
                'adminPageHoldOnlyHeld' => self::HELD,
                'adminPageHoldOnlyLive' => self::LIVE,
            ])
            ->setFormTypeOption('value_type_options.multiple', false);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (\is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (self::HELD !== $value && self::LIVE !== $value) {
            return;
        }

        $field = sprintf('%s.%s', $filterDataDto->getEntityAlias(), $filterDataDto->getProperty());

        $queryBuilder->andWhere(sprintf('%s IS %s', $field, self::HELD === $value ? 'NOT NULL' : 'NULL'));
    }
}
