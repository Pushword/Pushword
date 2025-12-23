<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\Controller\MediaCrudController;
use Pushword\Admin\Utils\Thumb;
use Pushword\Core\Entity\Media;

/**
 * @template T of object
 *
 * @extends AbstractField<T>
 */
abstract class AbstractMediaPickerField extends AbstractField
{
    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, string>
     *
     * @phpstan-return array<string, string>
     */
    protected function mediaPickerAttributes(string $fieldName, array $filters = [], ?Media $selected = null): array
    {
        $attr = [
            'data-pw-media-picker' => '1',
            'data-pw-media-picker-field' => $fieldName,
            'data-pw-media-picker-placeholder' => Thumb::PLACEHOLDER_DATA_URI,
            'data-pw-media-picker-modal-url' => $this->buildAdminUrl($this->buildPickerQuery('index', $filters)),
            'data-pw-media-picker-upload-url' => $this->buildAdminUrl($this->buildPickerQuery('new', $filters)),
            'data-pw-media-picker-modal-title' => $this->translate('adminMediaPickerTitle'),
            'data-pw-media-picker-choose-label' => $this->translate('adminMediaPickerChoose'),
            'data-pw-media-picker-upload-label' => $this->translate('adminMediaPickerUpload'),
            'data-pw-media-picker-remove-label' => $this->translate('adminMediaPickerRemove'),
            'data-pw-media-picker-empty-label' => $this->translate('adminMediaPickerEmpty'),
        ];

        if ($selected instanceof Media && null !== $selected->id) {
            return array_merge($attr, $this->formatSelectedMediaAttributes($selected));
        }

        return $attr;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function buildPickerQuery(string $crudAction, array $filters = []): array
    {
        $query = [
            'crudControllerFqcn' => MediaCrudController::class,
            'crudAction' => $crudAction,
            'pwMediaPicker' => '1',
        ];

        if ('index' === $crudAction) {
            $query['view'] = 'mosaic';
        }

        if ([] !== $filters) {
            $query['filters'] = $filters;
        }

        return $query;
    }

    private function translate(string $key): string
    {
        return $this->admin->getTranslator()->trans($key);
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function buildAdminUrl(array $query): string
    {
        $adminUrlGenerator = clone $this->formFieldManager->adminUrlGenerator;

        // Remove sort and filters from the current page context
        // as they may not be valid for the Media entity
        $adminUrlGenerator->unset('sort');
        $adminUrlGenerator->unset('filters');

        if (isset($query['crudControllerFqcn']) && \is_string($query['crudControllerFqcn'])) {
            $adminUrlGenerator->setController($query['crudControllerFqcn']);
            unset($query['crudControllerFqcn']);
        }

        if (isset($query['crudAction']) && \is_string($query['crudAction'])) {
            $adminUrlGenerator->setAction($query['crudAction']);
            unset($query['crudAction']);
        }

        foreach ($query as $key => $value) {
            $adminUrlGenerator->set($key, $value);
        }

        return $adminUrlGenerator->generateUrl();
    }

    /**
     * @return array<string, string>
     */
    protected function formatSelectedMediaAttributes(Media $media): array
    {
        $label = $media->getAlt() ?: $media->getFileName();
        $dimensions = $media->getDimensions();
        $meta = null === $dimensions ? '' : sprintf('%dx%d', $dimensions[0], $dimensions[1]);
        $width = null === $dimensions ? null : (string) $dimensions[0];
        $height = null === $dimensions ? null : (string) $dimensions[1];
        $ratioLabel = $media->getRatioLabel();

        return [
            'data-pw-media-picker-selected-id' => (string) $media->id,
            'data-pw-media-picker-selected-name' => $label,
            'data-pw-media-picker-selected-filename' => $media->getFileName(),
            'data-pw-media-picker-selected-thumb' => $this->formFieldManager->imageManager->getBrowserPath($media, 'md'),
            'data-pw-media-picker-selected-meta' => $meta,
            'data-pw-media-picker-selected-width' => $width ?? '',
            'data-pw-media-picker-selected-height' => $height ?? '',
            'data-pw-media-picker-selected-ratio' => (string) $ratioLabel,
        ];
    }
}
