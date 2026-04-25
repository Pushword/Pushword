<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

/**
 * @extends AbstractField<Page>
 */
class OgDescriptionField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('ogDescription', TextareaType::class, [
            'required' => false,
            'label' => 'adminPageOgDescriptionLabel',
            'help_html' => true,
            'help' => 'adminPageOgDescriptionHelp',
        ]);
    }
}
