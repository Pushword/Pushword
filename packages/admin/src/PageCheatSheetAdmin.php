<?php

namespace Pushword\Admin;

use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

class PageCheatSheetAdmin extends PageAdmin
{
    public const CHEATSHEET_SLUG = 'pushword-cheatsheet';

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
