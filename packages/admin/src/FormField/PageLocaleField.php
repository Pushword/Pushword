<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class PageLocaleField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('locale', TextType::class, [
            'label' => 'adminPageLocaleLabel',
            'help_html' => true,
            'help' => 'adminPageLocaleHelp',
        ]);
    }
}
