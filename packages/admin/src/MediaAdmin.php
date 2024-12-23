<?php

namespace Pushword\Admin;

use Exception;
use Override;
use Pushword\Admin\Utils\Thumb;
use Pushword\Core\Entity\Media;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Core\Service\ImageManager;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper; // use Sonata\BlockBundle\Meta\Metadata;
use Sonata\AdminBundle\Object\Metadata;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractAdmin<Media>
 */
#[AutoconfigureTag('sonata.admin', [
    'model_class' => '%pw.entity_media%',
    'manager_type' => 'orm',
    'label' => 'admin.label.media',
    'persist_filters' => true,
])]
final class MediaAdmin extends AbstractAdmin
{
    public const string MESSAGE_PREFIX = 'admin.media';

    public function __construct(
        private readonly AdminFormFieldManager $adminFormFieldManager,
        private readonly ImageManager $imageManager,
        private readonly MediaRepository $mediaRepo,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return 'admin_media';
    }

    #[Override]
    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'media';
    }

    protected function configure(): void
    {
        $this->setTemplate('list', '@pwAdmin/CRUD/mosaic.html.twig');
        $this->setTemplate('short_object_description', '@pwAdmin/media/short_object_description.html.twig');
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues = [
            '_page' => 1,
            '_sort_order' => 'DESC',
            '_sort_by' => 'updatedAt',
        ];
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $this->adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);

        $fields = $this->adminFormFieldManager->getFormFields($this, 'admin_media_form_fields');
        // if (! isset($fields[0]) || ! \is_array($fields[0]) || ! isset($fields[1]) || ! \is_array($fields[1]) || ! isset($fields[2]) || ! \is_array($fields[2])) {  throw new \LogicException(); }

        $form->with('Media', ['class' => 'col-md-8']);
        foreach ($fields[0] as $field) {
            $this->adminFormFieldManager->addFormField($field, $form, $this);
        }

        $form->end();

        $form->with('Params', ['class' => 'col-md-4']);
        foreach ($fields[1] as $field) {
            $field = \is_string($field) ? $field : throw new Exception('');
            $this->adminFormFieldManager->addFormField($field, $form, $this);
        }

        $form->end();

        // preview
        foreach ($fields[2] as $field) {
            $this->adminFormFieldManager->addFormField($field, $form, $this);
        }
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter->add('name', null, [
            'label' => 'admin.media.name.label',
        ]);
        $filter->add('media', null, [
            'label' => 'admin.media.mediaFile.label',
        ]);

        /*
        $filter->add('mimeType', ModelAutocompleteFilter::class, [
            'field_options' => [
                'property' => 'mimeType',
                'multiple' => true
        ],
            'label' => 'admin.media.filetype.label',
        ]);* */

        $mimeTypes = $this->mediaRepo->getMimeTypes();
        if ([] !== $mimeTypes) {
            $filter->add('mimeType', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => array_combine($mimeTypes, $mimeTypes),
                    'multiple' => true,
                ],
                'label' => 'admin.media.filetype.label',
            ]);
        }

        $filter->add('names', null, [
            'label' => 'admin.media.names.label',
        ]);
    }

    /**
     * Must be a cookie to check before to do that
     * If you click one time to list, stay in liste mode.
     * Yes it's in the session
     * TODO.
     * */
    protected function setMosaicDefaultListMode(): self
    {
        if ($this->hasRequest() && ($mode = (string) $this->getRequest()->query->get('_list_mode')) !== '') {
            $this->setListMode($mode);
        } else {
            $this->setListMode('mosaic');
        }

        return $this;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $this->setMosaicDefaultListMode();

        $list->add('name', null, [
            'label' => 'admin.media.name.label',
        ]);
        $list->add('createdAt', null, [
            'label' => 'admin.media.createdAt.label',
            'format' => 'd/m/y',
        ]);
        $list->add('mainColor', null, [
            'label' => 'admin.media.mainColor.label',
        ]);
        $list->add('_actions', null, [
            'actions' => [
                'edit' => [],
                'delete' => [],
            ],
        ]);
    }

    #[Override]
    public function getObjectMetadata(object $object): Metadata
    {
        $thumb = $this->imageManager->isImage($object) ? $this->imageManager->getBrowserPath($object, 'thumb') : Thumb::$thumb;

        return new Metadata($object->getName(), null, $thumb);
    }
}
