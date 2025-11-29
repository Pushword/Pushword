<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Page>
 */
class OgTwitterCreatorField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        return $this->buildEasyAdminField('twitterCreator', TextType::class, [
            'required' => false,
            'label' => 'adminPageTwitterCreatorLabel',
            'help_html' => true,
            'help' => 'adminPageTwitterCreatorHelp',
        ]);
    }
}
