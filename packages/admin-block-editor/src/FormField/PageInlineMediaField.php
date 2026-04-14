<?php

namespace Pushword\AdminBlockEditor\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Admin\FormField\AbstractMediaPickerField;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractMediaPickerField<Page>
 */
class PageInlineMediaField extends AbstractMediaPickerField
{
    public function getEasyAdminField(): iterable
    {
        return [
            $this->buildInlineImageField(),
            $this->buildInlineAttachField(),
        ];
    }

    private function buildInlineImageField(): FieldInterface
    {
        return $this->buildEasyAdminField('inline_image', ChoiceType::class, [
            'label' => false,
            'required' => false,
            'mapped' => false,
            'choices' => [],
            'placeholder' => ' ',
            'row_attr' => ['class' => 'd-none'],
            'attr' => $this->mediaPickerAttributes('inline_image', $this->imageFilters()),
        ]);
    }

    private function buildInlineAttachField(): FieldInterface
    {
        return $this->buildEasyAdminField('inline_attaches', ChoiceType::class, [
            'label' => false,
            'required' => false,
            'mapped' => false,
            'choices' => [],
            'placeholder' => ' ',
            'row_attr' => ['class' => 'd-none'],
            'attr' => $this->mediaPickerAttributes('inline_attaches'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function imageFilters(): array
    {
        return [
            'mimeType' => [
                'value' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ],
        ];
    }
}
