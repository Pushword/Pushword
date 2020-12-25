<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Media;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<Media>
 */
final class MediaNameField extends AbstractField
{
    /**
     * @param FormMapper<Media> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('name', TextType::class, [
            'required' => null !== $this->admin->getSubject()->getId(),
            'help_html' => true,
            'help' => 'admin.media.name.help',
            'label' => 'admin.media.name.label',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
