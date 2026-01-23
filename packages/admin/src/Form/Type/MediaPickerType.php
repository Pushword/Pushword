<?php

namespace Pushword\Admin\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\Admin\Controller\MediaCrudController;
use Pushword\Admin\Utils\Thumb;
use Pushword\Core\Entity\Media;
use Pushword\Core\Service\ImageManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Media picker form type usable outside custom EasyAdmin fields.
 *
 * @extends AbstractType<Media|null>
 */
final class MediaPickerType extends AbstractType
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ImageManager $imageManager,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $attr = \is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];

        $rawFieldId = $view->vars['id'] ?? $view->vars['name'] ?? uniqid('pw_media_picker_', true);
        $fieldId = \is_string($rawFieldId) ? $rawFieldId : uniqid('pw_media_picker_', true);
        /** @var array<string, mixed> $filters */
        $filters = $options['media_picker_filters'];
        $attr = array_merge($attr, $this->baseAttributes($fieldId, $filters));

        $media = $form->getData();
        if ($media instanceof Media) {
            $attr = array_merge($attr, $this->formatSelectedMediaAttributes($media));
        }

        $view->vars['attr'] = $attr;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Media::class,
            'choice_label' => static fn (?Media $media): string => $media?->getFileName() ?? ($media?->getAlt() ?? ''),
            'placeholder' => ' ',
            'required' => false,
            'media_picker_filters' => [],
        ]);
        $resolver->setAllowedTypes('media_picker_filters', 'array');
    }

    #[Override]
    public function getParent(): string
    {
        return EntityType::class;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, string>
     */
    private function baseAttributes(string $fieldName, array $filters = []): array
    {
        return [
            'data-pw-media-picker' => '1',
            'data-pw-media-picker-field' => $fieldName,
            'data-pw-media-picker-placeholder' => Thumb::PLACEHOLDER_DATA_URI,
            'data-pw-media-picker-modal-url' => $this->buildAdminUrl($this->buildPickerQuery('index', $filters)),
            'data-pw-media-picker-upload-url' => $this->buildAdminUrl($this->buildPickerQuery('new', $filters)),
            'data-pw-media-picker-modal-title' => $this->translator->trans('adminMediaPickerTitle'),
            'data-pw-media-picker-choose-label' => $this->translator->trans('adminMediaPickerChoose'),
            'data-pw-media-picker-upload-label' => $this->translator->trans('adminMediaPickerUpload'),
            'data-pw-media-picker-remove-label' => $this->translator->trans('adminMediaPickerRemove'),
            'data-pw-media-picker-empty-label' => $this->translator->trans('adminMediaPickerEmpty'),
        ];
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

    /**
     * @param array<string, mixed> $query
     */
    private function buildAdminUrl(array $query): string
    {
        $urlGenerator = clone $this->adminUrlGenerator;

        // Remove sort and filters from the current page context
        // as they may not be valid for the Media entity
        $urlGenerator->unset('sort');
        $urlGenerator->unset('filters');

        if (isset($query['crudControllerFqcn']) && \is_string($query['crudControllerFqcn'])) {
            $urlGenerator->setController($query['crudControllerFqcn']);
            unset($query['crudControllerFqcn']);
        }

        if (isset($query['crudAction']) && \is_string($query['crudAction'])) {
            $urlGenerator->setAction($query['crudAction']);
            unset($query['crudAction']);
        }

        foreach ($query as $key => $value) {
            $urlGenerator->set($key, $value);
        }

        return $urlGenerator->generateUrl();
    }

    /**
     * @return array<string, string>
     */
    private function formatSelectedMediaAttributes(Media $media): array
    {
        $label = $media->getAlt() ?: $media->getFileName();
        $dimensions = $media->getDimensions();
        $meta = null === $dimensions ? '' : sprintf('%dx%d', $dimensions->width, $dimensions->height);
        $width = null === $dimensions ? null : (string) $dimensions->width;
        $height = null === $dimensions ? null : (string) $dimensions->height;
        $ratioLabel = $media->getRatioLabel();

        return [
            'data-pw-media-picker-selected-id' => (string) $media->id,
            'data-pw-media-picker-selected-name' => $label,
            'data-pw-media-picker-selected-filename' => $media->getFileName(),
            'data-pw-media-picker-selected-thumb' => $this->imageManager->getBrowserPath($media, 'md'),
            'data-pw-media-picker-selected-meta' => $meta,
            'data-pw-media-picker-selected-width' => $width ?? '',
            'data-pw-media-picker-selected-height' => $height ?? '',
            'data-pw-media-picker-selected-ratio' => (string) $ratioLabel,
        ];
    }
}
