<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgImageField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('ogImage', TextType::class, [
            'required' => false,
            'label' => 'admin.page.ogImage.label',
            'help_html' => true,
            'help' => 'admin.page.ogImage.help',
        ]);
    }
}
