<?php

namespace Pushword\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\Admin\Filter\MediaDimensionIntFilter;
use Pushword\Admin\Filter\MediaSearchFilter;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Admin\Utils\Thumb;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Service\ImageManager;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** @extends AbstractAdminCrudController<Media> */
class MediaCrudController extends AbstractAdminCrudController
{
    public const string MESSAGE_PREFIX = 'admin.media';

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly MediaRepository $mediaRepo,
        private readonly PageRepository $pageRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield from $this->getIndexFields();

            return;
        }

        $instance = $this->getContext()?->getEntity()?->getInstance();
        $this->setSubject($instance instanceof Media ? $instance : new Media());
        $this->adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);

        $fields = array_replace(
            [[], [], []],
            $this->adminFormFieldManager->getFormFields($this, 'admin_media_form_fields'),
        );
        [$mediaFields, $paramBlocks, $previewBlocks] = $fields;

        yield FormField::addColumn('col-12 col-md-8 mainFields');
        yield FormField::addFieldset();
        yield from $this->adminFormFieldManager->getEasyAdminFields($mediaFields, $this);

        if ([] !== $paramBlocks) {
            yield FormField::addColumn('col-12 col-md-4 columnFields');
            yield FormField::addFieldset();
            foreach ($paramBlocks as $block) {
                $classes = $this->normalizeBlock($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($classes, $this);
            }
        }

        if ([] !== $previewBlocks) {
            yield FormField::addColumn('col-12 extraFields');
            yield FormField::addFieldset('admin.media.preview.label');
            foreach ($previewBlocks as $block) {
                $classes = $this->normalizeBlock($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($classes, $this);
            }
        }
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.label.media')
            ->setEntityLabelInPlural('admin.label.medias')
            ->setSearchFields(['alt', 'fileName', 'altSearch', 'tags'])
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->overrideTemplates([
                'crud/index' => '@pwAdmin/media/index.html.twig',
                'crud/edit' => '@pwAdmin/media/edit.html.twig',
            ])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);
        $context = $this->getContext();

        if (null === $context) {
            return $responseParameters;
        }

        if (Crud::PAGE_EDIT !== $context->getCrud()?->getCurrentPage()) {
            return $responseParameters;
        }

        $entity = $context->getEntity()->getInstance();
        if (! $entity instanceof Media) {
            return $responseParameters;
        }

        if ('' === $entity->getFileName()) {
            return $responseParameters;
        }

        $responseParameters->set('media_preview_html', $this->renderMediaPreview($entity));

        $relatedPages = $this->pageRepository->getPagesUsingMedia($entity);
        if ([] !== $relatedPages) {
            $responseParameters->set(
                'media_related_pages_html',
                $this->renderView('@pwAdmin/media/media_show.relatedPages.html.twig', [
                    'related_pages' => $relatedPages,
                ]),
            );
        }

        return $responseParameters;
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        $filters->add(MediaSearchFilter::new($this->mediaRepo, 'admin.media.alt.label'));

        $available = $this->getAvailableMimeTypes();
        $mimeChoices = $this->formatChoiceList($available['mimeType']);
        if ([] !== $mimeChoices) {
            $filters->add(
                ChoiceFilter::new('mimeType', 'admin.media.filetype.label')
                    ->setChoices($mimeChoices)
                    ->canSelectMultiple(),
            );
        }

        $ratioChoices = $this->formatChoiceList($available['ratioLabel']);
        if ([] !== $ratioChoices) {
            $filters->add(
                ChoiceFilter::new('ratioLabel', 'admin.media.ratioLabel.label')
                    ->setChoices($ratioChoices)
                    ->canSelectMultiple(),
            );
        }

        $filters->add(MediaDimensionIntFilter::new('admin.media.dimensions.int_filter_label'));

        return $filters;
    }

    /**
     * @param array<int|string, mixed>|class-string<AbstractField<Media>> $block
     *
     * @return list<class-string<AbstractField<Media>>>
     */
    private function normalizeBlock(array|string $block): array
    {
        if (\is_array($block)) {
            if (isset($block['fields']) && \is_array($block['fields'])) {
                /** @var list<class-string<AbstractField<Media>>> $fields */
                $fields = $block['fields'];

                return $fields;
            }

            return $this->filterFieldClassList($block);
        }

        /** @var class-string<AbstractField<Media>> $block */
        return [$block];
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return list<class-string<AbstractField<Media>>>
     */
    private function filterFieldClassList(array $values): array
    {
        $classes = [];
        foreach ($values as $value) {
            if (\is_string($value) && is_subclass_of($value, AbstractField::class)) {
                /** @var class-string<AbstractField<Media>> $value */
                $classes[] = $value;
            }
        }

        /** @var list<class-string<AbstractField<Media>>> $classes */
        return $classes;
    }

    public function getThumbnailUrl(?Media $media): string
    {
        if (null === $media) {
            return Thumb::$thumb;
        }

        if (! $this->imageManager->isImage($media)) {
            return Thumb::$thumb;
        }

        return $this->imageManager->getBrowserPath($media, 'md');
    }

    /**
     * @return array{mimeType: string[], ratioLabel: string[], dimensions: string[]}
     */
    public function getAvailableMimeTypes(): array
    {
        return $this->mediaRepo->getMimeTypesAndRatio();
    }

    private function renderMediaPreview(Media $media): string
    {
        $template = $this->imageManager->isImage($media)
            ? '@pwAdmin/media/media_show.preview_image.html.twig'
            : '@pwAdmin/media/media_show.preview.html.twig';

        return $this->renderView($template, [
            'media' => $media,
        ]);
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    private function getIndexFields(): iterable
    {
        yield TextField::new('alt', 'admin.media.alt.label')
            ->setSortable(true);
        yield TextField::new('mimeType', 'admin.media.filetype.label')
            ->setSortable(true);
        yield DateTimeField::new('updatedAt', 'admin.page.updatedAt.label')
            ->setSortable(true);
    }

    /**
     * @param string[] $values
     *
     * @return array<string, string>
     */
    private function formatChoiceList(array $values): array
    {
        if ([] === $values) {
            return [];
        }

        $values = array_values(array_unique($values));

        /** @var array<string, string> $choices */
        $choices = array_combine($values, $values);

        return $choices;
    }

    #[Override]
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $request = $context->getRequest();
        $isPickerContext = $request->query->getBoolean('pwMediaPicker');
        $entity = $context->getEntity()->getInstance();

        if (! $isPickerContext || ! $entity instanceof Media || Action::NEW !== $action) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        $adminUrlGenerator = clone $this->adminUrlGenerator;
        $adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->set('view', 'mosaic')
            ->set('pwMediaPicker', '1')
            ->set('pwMediaPickerSelect', (string) $entity->getId());

        $fieldId = $request->query->get('pwMediaPickerFieldId');
        if (\is_string($fieldId) && '' !== $fieldId) {
            $adminUrlGenerator->set('pwMediaPickerFieldId', $fieldId);
        }

        $filters = $request->query->all('filters');
        if ([] !== $filters) {
            $adminUrlGenerator->set('filters', $filters);
        }

        return new RedirectResponse($adminUrlGenerator->generateUrl());
    }
}
