<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Pushword\Core\Entity\Page;

final class PageListTitleConfigurator implements FieldConfiguratorInterface
{
    /** @param EntityDto<object> $entityDto */
    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return $entityDto->getInstance() instanceof Page && 'h1' === $field->getProperty();
    }

    /**
     * @param EntityDto<object>    $entityDto
     * @param AdminContext<object> $context
     */
    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        if (Crud::PAGE_INDEX === $context->getCrud()?->getCurrentPage()
            && '@pwAdmin/page/pageListTitleField.html.twig' === $field->getTemplatePath()) {
            $field->setTemplatePath('@PushwordRepurpose/admin/page_title.html.twig');
        }
    }
}
