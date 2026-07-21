<?php

namespace Pushword\Repurpose\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-mostly list of carousels: filter by status/host/network, jump to the
 * studio to preview and export. Carousels are authored by the drafter or the API,
 * not typed into a form, so creation is disabled here.
 *
 * @extends AbstractCrudController<SocialPost>
 */
class SocialPostCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SocialPost::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('repurpose.label.singular')
            ->setEntityLabelInPlural('repurpose.label.plural')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setSearchFields(['page', 'network', 'host']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('status')
            ->add('host')
            ->add('network')
            ->add('plannedAt');
    }

    /**
     * A carousel is edited in the studio, not an EasyAdmin form, so the "Edit"
     * action is relabelled "Studio" and {@see self::edit()} redirects there — the
     * `…/edit` URL lands straight on the live preview + spec editor.
     *
     * "New" doesn't open a blank EasyAdmin form: it is relabelled "New carousel" and
     * {@see self::new()} creates a page-less standalone draft and opens the studio.
     * (Carousels for a page are still drafted from that page's "Repurpose" action.)
     */
    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $toStudio = static fn (Action $action): Action => $action
            ->setLabel('repurpose.action.studio')
            ->setIcon('fas fa-wand-magic-sparkles');

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action
                ->setLabel('repurpose.action.new')
                ->setIcon('fas fa-wand-magic-sparkles'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, $toStudio)
            ->update(Crud::PAGE_DETAIL, Action::EDIT, $toStudio)
            ->setPermission(Action::DELETE, 'ROLE_PUSHWORD_ADMIN');
    }

    #[Override]
    public function edit(AdminContext $context): Response
    {
        return $this->redirectToRoute('repurpose_studio', ['id' => $context->getEntity()->getPrimaryKeyValueAsString()]);
    }

    #[Override]
    public function new(AdminContext $context): Response
    {
        return $this->redirectToRoute('repurpose_studio_new');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('page', 'repurpose.field.page');
        yield TextField::new('network', 'repurpose.field.network');
        yield TextField::new('format', 'repurpose.field.format')->hideOnIndex();
        yield TextField::new('host', 'repurpose.field.host');
        yield ChoiceField::new('status', 'repurpose.field.status')
            ->setChoices(['draft' => 'draft', 'planned' => 'planned', 'posted' => 'posted'])
            ->renderAsBadges(['draft' => 'warning', 'planned' => 'info', 'posted' => 'success']);
        yield DateTimeField::new('plannedAt', 'repurpose.field.plannedAt')->setRequired(false);
        yield DateTimeField::new('updatedAt', 'repurpose.field.updatedAt')->onlyOnIndex();
    }
}
