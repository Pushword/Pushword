<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractField<Page>
 */
class PageVariantOfField extends AbstractField
{
    use PageFormSubjectTrait;

    private function configureQueryBuilder(QueryBuilder $qb, Page $page): QueryBuilder
    {
        $alias = $qb->getRootAliases()[0] ?? 'entity';

        $qb->andWhere(sprintf('%s.id != :currentPageId', $alias))
            ->setParameter('currentPageId', (int) $page->id)
            // Only a master (non-variant) can itself be a master: flat hierarchy.
            ->andWhere(sprintf('%s.variantOf IS NULL', $alias));

        if ('' !== $page->host) { // HostField must be called before
            $qb->andWhere(sprintf('%s.host = :host', $alias))
                ->setParameter('host', $page->host);
        }

        return $qb;
    }

    public function getEasyAdminField(): ?FieldInterface
    {
        $page = $this->pageFormSubject();

        return AssociationField::new('variantOf', 'adminPageVariantOfLabel')
            ->onlyOnForms()
            ->setCrudController(PageCrudController::class)
            ->autocomplete()
            ->setFormTypeOption('required', false)
            ->setQueryBuilder(fn (QueryBuilder $qb): QueryBuilder => $this->configureQueryBuilder($qb, $page));
    }
}
