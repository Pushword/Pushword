<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use Pushword\Core\Entity\Media;
use Symfony\Component\Form\Extension\Core\Type\FileType;

/**
 * @extends AbstractField<Media>
 */
final class MediaMediaFileField extends AbstractField
{
    public function getEasyAdminField(): Field
    {
        return $this->buildEasyAdminField('mediaFile', FileType::class, [
            'label' => 'adminMediaMediaFileLabel',
            'required' => null === $this->admin->getSubject()->getId(),
        ]);
    }
}
