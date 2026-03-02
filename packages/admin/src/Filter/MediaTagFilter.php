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

final class MediaTagFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(MediaRepository $mediaRepository, TranslatableInterface|string|false|null $label = null): self
    {
        $tags = $mediaRepository->getMediaTags();
        sort($tags);
        $choices = array_combine($tags, $tags);

        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty('tags')
            ->setLabel($label)
            ->setFormType(ChoiceType::class)
            ->setFormTypeOption('choices', $choices)
            ->setFormTypeOption('multiple', true)
            ->setFormTypeOption('attr', ['data-ea-widget' => 'ea-autocomplete']);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (! \is_array($value) || [] === $value) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        /** @var string[] $value */
        foreach ($value as $i => $tag) {
            $param = 'tag_'.$i;
            $queryBuilder
                ->andWhere(\sprintf('%s.tags LIKE :%s', $alias, $param))
                ->setParameter($param, '%'.$tag.'%');
        }
    }
}
