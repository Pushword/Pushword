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
 * The tags a media inherits from the pages using it — deliberately its own filter,
 * and its own column, so that "I tagged this image" and "the pages showing it are
 * tagged" stay two different questions. {@see MediaTagFilter} answers the first.
 */
final class MediaPageTagFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(MediaRepository $mediaRepository, TranslatableInterface|string|false|null $label = null): self
    {
        $tags = $mediaRepository->getMediaPageTags();
        sort($tags);
        $choices = array_combine($tags, $tags);

        return new self()
            ->setFilterFqcn(self::class)
            ->setProperty('pageTags')
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
            $param = 'pageTag_'.$i;
            $queryBuilder
                ->andWhere(\sprintf('%s.pageTags LIKE :%s', $alias, $param))
                ->setParameter($param, '%"'.$tag.'"%');
        }
    }
}
