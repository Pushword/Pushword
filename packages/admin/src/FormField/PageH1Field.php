<?php

declare(strict_types=1);

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
        return TextareaField::new('h1', 'adminPageTitleLabel')
            ->setNumOfRows(1)
            ->setLabel(false)
            ->onlyOnForms()
            ->setFormTypeOption('attr', [
                'class' => 'autosize textarea-no-newline h1Field',
                'placeholder' => 'adminPageTitleLabel',
                'rows' => 1,
            ]);
    }
}
