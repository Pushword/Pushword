<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Media;
use Symfony\Component\Form\Extension\Core\Type\FileType;

/**
 * @extends AbstractField<Media>
 */
final class MediaMediaFileField extends AbstractField
{
    public function getEasyAdminField(): FieldInterface
    {
        return $this->buildEasyAdminField('mediaFile', FileType::class, [
            'label' => 'admin.media.mediaFile.label',
            'required' => null === $this->admin->getSubject()->getId(),
        ]);
    }
}
