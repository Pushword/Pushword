<?php

namespace Pushword\Admin\FormField;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractField<Page>
 */
class PageTranslationsField extends AbstractField
{
    private function configureQueryBuilder(QueryBuilder $qb, Page $page): QueryBuilder
    {
        // TODO change isSaved by a js function wich retrieve value from input[name$="[locale]"]
        $isSaved = null !== $page->id;

        if (! $isSaved) {
            return $qb;
        }

        $alias = $qb->getRootAliases()[0] ?? 'entity';

        $qb
            ->andWhere(sprintf('%s.id != :currentPageId', $alias))
            ->andWhere(sprintf('%s.locale != :currentPageLocale', $alias))
            ->setParameter('currentPageId', (int) $page->id)
            ->setParameter('currentPageLocale', $page->locale);

        return $qb;
    }

    public function getEasyAdminField(): ?FieldInterface
    {
        /** @var Page $page */
        $page = $this->admin->getSubject();

        return AssociationField::new('translations', 'adminPageTranslationsLabel')
            ->onlyOnForms()
            ->setHelp('adminPageTranslationsHelp')
            ->setFormTypeOption('help_html', true)
            ->setFormTypeOption('multiple', true)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('choice_label', static fn (Page $entity): string => $entity->locale.' ('.$entity->getSlug().')')
            ->setQueryBuilder(fn (QueryBuilder $qb): QueryBuilder => $this->configureQueryBuilder($qb, $page));
    }
}
