<?php

namespace Pushword\Admin\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Implemented by bundles that need to extend the PageCrudController with
 * actions, filters or fields without subclassing it. Tagged services are
 * collected and iterated in order.
 */
#[AutoconfigureTag('pushword.admin.page_crud_extension')]
interface PageCrudExtensionInterface
{
    public function configureActions(Actions $actions): void;

    public function configureFilters(Filters $filters): void;

    /**
     * Yield additional EasyAdmin fields appended after the controller's own
     * fields for the given pageName.
     *
     * @return iterable<FieldInterface|string>
     */
    public function configureFields(string $pageName): iterable;
}
