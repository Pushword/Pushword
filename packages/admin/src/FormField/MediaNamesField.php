<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\MediaInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<MediaInterface>
 */
final class MediaNamesField extends AbstractField
{
    /**
     * @param FormMapper<MediaInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form->add('names', null, [
            'required' => false,
            'help_html' => true, 'help' => 'admin.media.names.help',
            'label' => 'admin.media.names.label',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
