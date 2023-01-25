<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\MediaInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractField<MediaInterface>
 */
final class MediaNameField extends AbstractField
{
    /**
     * @param FormMapper<MediaInterface> $form
     *
     * @return FormMapper<MediaInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form->add('name', TextType::class, [
            'required' => null !== $this->admin->getSubject()->getId(),
            'help_html' => true,
            'help' => 'admin.media.name.help',
            'label' => 'admin.media.name.label',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
