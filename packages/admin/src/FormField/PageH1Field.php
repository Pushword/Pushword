<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractField<Page>
 */
class PageH1Field extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return TextareaField::new('h1', 'admin.page.title.label')
            ->setNumOfRows(1)
            ->setLabel(false)
            ->onlyOnForms()
            ->setFormTypeOption('attr', [
                'class' => 'autosize textarea-no-newline h1Field',
                'placeholder' => 'admin.page.title.label',
                'rows' => 1,
                'style' => '
                  --form-input-hover-shadow: 0;
                  font-weight: 700;
                  border: 0px;
                  color: rgb(17, 24, 39);
                  padding: 10px 10px 0px;
                  margin-top: -23px;
                  margin-bottom: -13px;
                  font-size: 22px !important; ',
            ]);
    }
}
