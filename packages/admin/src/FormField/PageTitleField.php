<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageTitleField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('title', TextareaType::class, [
            'label' => 'admin.page.title.label',
            'required' => false,
            'help_html' => true,
            'help' => 'admin.page.title.help',
            'attr' => ['class' => 'titleToMeasure autosize textarea-no-newline'],
        ]);
    }
}
