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
            'required' => null !== $this->admin->getSubject()->getId(),
            'help_html' => true,
            'help' => 'admin.media.alt.help',
            'label' => 'admin.media.alt.label',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
