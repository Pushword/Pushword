<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgTitleField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('ogTitle', TextType::class, [
            'label' => 'adminPageOgTitleLabel',
            'required' => false,
            'help_html' => true,
            'help' => 'adminPageOgTitleHelp',
        ]);
    }
}
