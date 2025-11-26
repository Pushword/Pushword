<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use LogicException;
use Override;
use Pushword\Core\Entity\Page;

class PageRedirectionCrudController extends PageCrudController
{
    protected const FORM_FIELD_KEY = 'admin_redirection_form_fields';

    private ?AdminUrlGenerator $adminUrlGenerator = null;

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        $crud = parent::configureCrud($crud);

        return $crud
            ->setEntityLabelInSingular('admin.label.redirection')
            ->setEntityLabelInPlural('admin.label.redirection')
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return [
                TextField::new('slug', 'From')
                    ->setSortable(false)
                    ->renderAsHtml()
                    ->formatValue(fn (?string $value, Page $page): string => $this->formatFromColumn($page)),
                TextField::new('redirection', 'To')
                    ->setSortable(false)
                    ->renderAsHtml()
                    ->formatValue(fn (?string $value, Page $page): string => $this->formatToColumn($page)),
            ];
        }

        return parent::configureFields($pageName);
    }

    #[Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $queryBuilder->getRootAliases()[0] ?? 'entity';

        return $queryBuilder
            ->andWhere(sprintf('%s.mainContent LIKE :redirectionPrefix', $alias))
            ->setParameter('redirectionPrefix', 'Location:%');
    }

    #[Override]
    protected function hideRedirectionsFromIndex(): bool
    {
        return false;
    }

    private function formatFromColumn(Page $page): string
    {
        $path = trim(sprintf('%s/%s', $page->getHost(), $page->getSlug()), '/');
        $editUrl = $this->buildEditUrl($page);

        return sprintf(
            '<a href="%s" class="text-muted d-flex justify-content-between align-items-center w-100 ms-2" style="gap: 8px;">'
            .'<span class="text-truncate">%s</span>'
            .'<i class="fa fa-edit me-1 opacity-50"></i>'
            .'</a>',
            htmlspecialchars($editUrl, \ENT_QUOTES),
            htmlspecialchars($path, \ENT_QUOTES),
        );
    }

    private function formatToColumn(Page $page): string
    {
        if (! $page->hasRedirection()) {
            return '';
        }

        $target = $page->getRedirection();

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            htmlspecialchars($target, \ENT_QUOTES),
            htmlspecialchars($target, \ENT_QUOTES),
        );
    }

    private function buildEditUrl(Page $page): string
    {
        $generator = clone $this->getAdminUrlGenerator();

        return $generator
            ->setController(static::class)
            ->setAction(Action::EDIT)
            ->setEntityId($page->getId())
            ->generateUrl();
    }

    private function getAdminUrlGenerator(): AdminUrlGenerator
    {
        if (null !== $this->adminUrlGenerator) {
            return $this->adminUrlGenerator;
        }

        if (! isset($this->container)) {
            throw new LogicException('Container not available to generate admin URLs.');
        }

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        $this->adminUrlGenerator = $adminUrlGenerator;

        return $this->adminUrlGenerator;
    }
}
