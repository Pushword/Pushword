<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class PageNameField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('name', TextareaType::class, [
            'label' => 'adminPageNameLabel',
            'required' => false,
            'help_html' => true,
            'help' => 'adminPageNameHelp',
            'attr' => ['class' => 'autosize'],
        ]);
    }
}
