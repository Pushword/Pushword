<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\MediaInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\FileType;

/**
 * @extends AbstractField<MediaInterface>
 */
final class MediaMediaFileField extends AbstractField
{
    /**
     * @param FormMapper<MediaInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('mediaFile', FileType::class, [
            'label' => 'admin.media.mediaFile.label',
            'required' => null === $this->admin->getSubject()->getId(),
        ]);
    }
}
