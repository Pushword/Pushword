<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgTwitterCardField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('twitterCard', TextType::class, [
            'required' => false,
            'label' => 'admin.page.twitterCard.label',
            'help_html' => true,
            'help' => 'admin.page.twitterCard.help',
        ]);
    }
}
