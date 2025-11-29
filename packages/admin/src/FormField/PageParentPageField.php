<?php

namespace Pushword\Admin\FormField;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractField<Page>
 */
class PageParentPageField extends AbstractField
{
    private function configureQueryBuilder(QueryBuilder $qb, Page $page): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0] ?? 'entity';

        $qb->andWhere(sprintf('%s.id != :currentPageId', $alias))
            ->setParameter('currentPageId', (int) $page->getId());

        if ('' !== $page->getHost()) { // HostField must be call before
            $qb->andWhere(sprintf('%s.host = :host', $alias))
                ->setParameter('host', $page->getHost());
        }

        return $qb;
    }

    public function getEasyAdminField(): ?FieldInterface
    {
        /** @var Page $page */
        $page = $this->admin->getSubject();

        return AssociationField::new('parentPage', 'adminPageParentPageLabel')
            ->onlyOnForms()
            ->setFormTypeOption('required', false)
            ->setQueryBuilder(fn (QueryBuilder $qb): QueryBuilder => $this->configureQueryBuilder($qb, $page));
    }
}
