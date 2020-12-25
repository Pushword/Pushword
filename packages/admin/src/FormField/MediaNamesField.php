<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\Media;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<Media>
 */
final class MediaNamesField extends AbstractField
{
    /**
     * @param FormMapper<Media> $form
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
