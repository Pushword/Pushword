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
            'help' => 'adminMediaAltsHelp',
            'label' => 'adminMediaAltsLabel',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);
    }
}
