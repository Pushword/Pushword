<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Pushword\Core\Entity\Media;

/**
 * @extends AbstractField<Media>
 */
final class MediaNamesField extends AbstractField
{
    public function getEasyAdminField(): Field
    {
        return $this->buildEasyAdminField('alts', null, [
            'required' => false,
            'help_html' => true,
            'help' => 'admin.media.alts.help',
            'label' => 'admin.media.alts.label',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
