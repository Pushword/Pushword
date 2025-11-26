<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;

/**
 * @extends AbstractMediaPickerField<Page>
 */
class PageMainImageField extends AbstractMediaPickerField
{
    public function getEasyAdminField(): FieldInterface|iterable|null
    {
        /** @var Page $page */
        $page = $this->admin->getSubject();

        return AssociationField::new('mainImage', ' ')
            ->onlyOnForms()
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('choice_label', static fn (Media $media): string => $media->getAlt() ?: $media->getFileName())
            ->setFormTypeOption('placeholder', 'admin.page.mainImage.label')
            ->setFormTypeOption('attr', $this->mediaPickerAttributes('mainImage', $this->defaultFilters(), $page->getMainImage()));
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFilters(): array
    {
        return [
            'mimeType' => [
                'value' => $this->imageMimeTypes(),
            ],
            'dimensionIntFilter' => [
                'comparison' => ComparisonType::GTE,
                'value' => 1200,
            ],
        ];
    }

    /**
     * @return string[]
     */
    private function imageMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/gif'];
    }
}
