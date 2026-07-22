<?php

namespace Pushword\AdminBlockEditor\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Pushword\Admin\Controller\MediaCrudController;
use Pushword\Admin\Utils\Thumb;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Builds the media-picker data attributes for the hidden <select> elements the
 * EditorJS image / gallery / attaches tools reach for (they look them up
 * globally through `select[id*="inline_image"]` / `[id*="inline_attaches"]`).
 *
 * Rendering these selects inside the editor widget keeps the block editor
 * self-contained, so its media picker works on any EasyAdmin form (Page,
 * Snippet, downstream entities) without per-CRUD wiring.
 *
 * The attribute set mirrors {@see \Pushword\Admin\FormField\AbstractMediaPickerField::mediaPickerAttributes()};
 * client-side, admin.mediaPicker.js enhances any `[data-pw-media-picker]` select into a full picker.
 */
final readonly class InlineMediaPickerExtension
{
    public function __construct(
        private AdminUrlGenerator $adminUrlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, string>
     */
    #[AsTwigFunction('editorjs_media_picker_attributes', needsEnvironment: false)]
    public function attributes(string $fieldName, array $filters = []): array
    {
        return [
            'data-pw-media-picker' => '1',
            'data-pw-media-picker-field' => $fieldName,
            'data-pw-media-picker-placeholder' => Thumb::PLACEHOLDER_DATA_URI,
            'data-pw-media-picker-modal-url' => $this->buildAdminUrl($this->buildPickerQuery('index', $filters)),
            'data-pw-media-picker-upload-url' => $this->buildAdminUrl($this->buildPickerQuery('new', $filters)),
            'data-pw-media-picker-edit-url' => $this->buildAdminUrl([
                'crudControllerFqcn' => MediaCrudController::class,
                'crudAction' => 'edit',
                'entityId' => '__MEDIA_ID__',
            ]),
            'data-pw-media-picker-modal-title' => $this->translator->trans('adminMediaPickerTitle'),
            'data-pw-media-picker-choose-label' => $this->translator->trans('adminMediaPickerChoose'),
            'data-pw-media-picker-upload-label' => $this->translator->trans('adminMediaPickerUpload'),
            'data-pw-media-picker-remove-label' => $this->translator->trans('adminMediaPickerRemove'),
            'data-pw-media-picker-empty-label' => $this->translator->trans('adminMediaPickerEmpty'),
            'data-pw-media-picker-edit-label' => $this->translator->trans('adminMediaPickerEdit'),
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
        $adminUrlGenerator = clone $this->adminUrlGenerator;

        // Drop the edited entity's context (sort/filters/entityId): they belong
        // to the host form (e.g. the Page), not to the Media picker. Keeping
        // entityId would make Media new/index try to load a Media with the
        // host entity's id, throwing EntityNotFoundException.
        $adminUrlGenerator->unset('sort');
        $adminUrlGenerator->unset('filters');
        $adminUrlGenerator->unset('entityId');

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
}
