<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Pushword\Core\Entity\Media;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Media>
 */
final class MediaNameField extends AbstractField
{
    public function getEasyAdminField(): Field
    {
        return $this->buildEasyAdminField('alt', TextType::class, [
            'required' => null !== $this->admin->getSubject()->id,
            'help_html' => true,
            'help' => 'adminMediaAltHelp',
            'label' => 'adminMediaAltLabel',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
