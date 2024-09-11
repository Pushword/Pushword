<?php

namespace Pushword\Admin;

use Pushword\Core\Entity\Page;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
#[AutoconfigureTag('sonata.admin', [
    'model_class' => Page::class,
    'manager_type' => 'orm',
    'label' => 'admin.label.page',
    'default' => false,
])]
class PageCheatSheetAdmin extends PageAbstractAdmin
{
    final public const CHEATSHEET_SLUG = 'pushword-cheatsheet';

    protected ?string $mainColClass = 'col-md-12 mainFields';

    protected ?string $secondColClass = 'hidden';

    protected function configureFormFields(FormMapper $form): void
    {
        $this->formFieldKey = 'admin_redirection_form_fields';
        parent::configureFormFields($form);
    }

    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return 'admin_cheatsheet';
    }

    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'cheatsheet';
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('list');
    }
}
