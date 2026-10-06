<?php

declare(strict_types=1);

namespace Pushword\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use Override;
use Pushword\Core\Entity\Page;

class PageCheatSheetCrudController extends PageCrudController
{
    final public const string CHEATSHEET_SLUG = 'pushword-cheatsheet';

    protected const string FORM_FIELD_KEY = 'admin_redirection_form_fields';

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('adminLabelCheatsheet')
            ->setEntityLabelInPlural('adminLabelCheatsheet')
            ->showEntityActionsInlined();
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    #[Override]
    protected function getFormFieldsDefinition(): iterable
    {
        $instance = $this->getContext()?->getEntity()?->getInstance();
        $this->setSubject($instance instanceof Page ? $instance : new Page());

        $formFieldKey = static::FORM_FIELD_KEY;
        $fields = array_replace(
            [[], [], []],
            $this->adminFormFieldManager->getFormFields($this, $formFieldKey),
        );
        [$mainFields] = $fields;

        yield FormField::addColumn('col-12 mainFields');
        yield FormField::addFieldset();
        yield from $this->adminFormFieldManager->getEasyAdminFields($mainFields, $this);
    }
}
