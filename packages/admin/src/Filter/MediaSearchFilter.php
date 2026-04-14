<?php

namespace Pushword\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Contracts\Translation\TranslatableInterface;

final class MediaSearchFilter implements FilterInterface
{
    use FilterTrait;

    private MediaRepository $mediaRepository;

    public static function new(MediaRepository $mediaRepository, TranslatableInterface|string|false|null $label = null): self
    {
        $filter = new self();
        $filter->mediaRepository = $mediaRepository;

        return $filter
            ->setFilterFqcn(self::class)
            ->setProperty('alt')
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

        $expr = $this->mediaRepository->getExprToFilterMedia($filterDataDto->getEntityAlias(), $value);

        $queryBuilder->andWhere($expr);
    }
}
