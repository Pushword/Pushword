<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageSearchExcreptField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('searchExcrept', TextareaType::class, [
            'required' => false,
            'label' => 'admin.page.searchExcrept.label',
            'help_html' => true,
            'help' => 'admin.page.searchExcrept.help',
            'attr' => ['class' => 'descToMeasure autosize textarea-no-newline'],
        ]);
    }
}
