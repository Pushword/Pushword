<?php

namespace Pushword\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Contracts\Translation\TranslatableInterface;

final class MediaDimensionsFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param array<string, string> $choices
     */
    public static function new(array $choices, TranslatableInterface|string|false|null $label = null): self
    {
        $filter = new self();

        return $filter
            ->setFilterFqcn(self::class)
            ->setProperty('dimensions')
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('choices', $choices)
            ->setFormTypeOption('multiple', true)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (null === $value || [] === $value || '' === $value) {
            return;
        }

        $selectedDimensions = \is_array($value) ? $value : [$value];

        $constraints = [];
        foreach ($selectedDimensions as $dimension) {
            if (! \is_string($dimension)) {
                continue;
            }

            $dimension = trim($dimension);
            if (! str_contains($dimension, '×')) {
                continue;
            }

            [$width, $height] = array_map(trim(...), explode('×', $dimension));
            if (! ctype_digit($width)) {
                continue;
            }

            if (! ctype_digit($height)) {
                continue;
            }

            $constraints[] = ['width' => (int) $width, 'height' => (int) $height];
        }

        if ([] === $constraints) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $expr = $queryBuilder->expr()->orX();

        foreach ($constraints as $index => $constraint) {
            $widthParameter = sprintf('%s_width_%d', $filterDataDto->getParameterName(), $index);
            $heightParameter = sprintf('%s_height_%d', $filterDataDto->getParameterName(), $index);
            $expr->add(sprintf('(%1$s.width = :%2$s AND %1$s.height = :%3$s)', $alias, $widthParameter, $heightParameter));

            $queryBuilder
                ->setParameter($widthParameter, $constraint['width'])
                ->setParameter($heightParameter, $constraint['height']);
        }

        $queryBuilder->andWhere($expr);
    }
}

