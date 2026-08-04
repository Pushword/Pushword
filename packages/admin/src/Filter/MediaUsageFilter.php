<?php

namespace Pushword\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filters on whether any page references the media.
 *
 * Worded "referenced by a page", never "unused": nothing scans Twig templates, so a
 * navbar logo lands in the same bucket as a forgotten upload.
 */
final class MediaUsageFilter implements FilterInterface
{
    use FilterTrait;

    public const string NOT_REFERENCED = 'notReferenced';

    public const string REFERENCED = 'referenced';

    private MediaRepository $mediaRepository;

    public static function new(MediaRepository $mediaRepository, TranslatableInterface|string|false|null $label = null): self
    {
        $filter = new self();
        $filter->mediaRepository = $mediaRepository;

        return $filter
            ->setFilterFqcn(self::class)
            ->setProperty('mediaUsageFilter')
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('choices', [
                'adminMediaUsageNotReferencedChoice' => self::NOT_REFERENCED,
                'adminMediaUsageReferencedChoice' => self::REFERENCED,
            ]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (! \is_string($value)) {
            return;
        }

        $notReferenced = $this->mediaRepository->getNotReferencedByAPageDql($filterDataDto->getEntityAlias());

        $queryBuilder->andWhere(self::NOT_REFERENCED === $value ? $notReferenced : 'NOT ('.$notReferenced.')');
    }
}
