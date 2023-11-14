<?php

namespace Pushword\Admin;

use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper; // use Sonata\BlockBundle\Meta\Metadata;
use Sonata\AdminBundle\Object\Metadata;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ModelAutocompleteFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractAdmin<MediaInterface>
 */
final class MediaAdmin extends AbstractAdmin implements MediaAdminInterface
{
    /**
     * @use AdminTrait<MediaInterface>
     */
    use AdminTrait;

    private string $messagePrefix = 'admin.media';

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues = [
            '_page' => 1,
            '_sort_order' => 'DESC',
            '_sort_by' => 'updatedAt',
        ];
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    protected function configureFormFields(FormMapper $form): void
    {
        $this->formFieldKey = 'admin_media_form_fields';
        $fields = $this->getFormFields();
        if (! isset($fields[0]) || ! \is_array($fields[0]) || ! isset($fields[1]) || ! \is_array($fields[1]) || ! isset($fields[2]) || ! \is_array($fields[2])) {
            throw new \LogicException();
        }

        $form->with('Media', ['class' => 'col-md-8']);
        foreach ($fields[0] as $field) {
            $this->addFormField($field, $form);
        }

        $form->end();

        $form->with('Params', ['class' => 'col-md-4']);
        foreach ($fields[1] as $field) {
            $this->addFormField($field, $form);
        }

        $form->end();

        // preview
        foreach ($fields[2] as $field) {
            $this->addFormField($field, $form);
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
                'class' => $this->mediaClass,
                'multiple' => true
        ],
            'label' => 'admin.media.filetype.label',
        ]);* */

        $mimeTypes = Repository::getMediaRepository($this->getEntityManager(), $this->mediaClass)->getMimeTypes();
        if ([] !== $mimeTypes) {
            $filter->add('mimeType', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => \Safe\array_combine($mimeTypes, $mimeTypes),
                    'multiple' => true,
                ],
                'label' => 'admin.media.filetype.label',
            ]);
        }

        $filter->add('names', null, [
            'label' => 'admin.media.names.label',
        ]);
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

    public function getObjectMetadata(object $object): Metadata
    {
        $thumb = $this->imageManager->isImage($object) ? $this->imageManager->getBrowserPath($object, 'thumb') : self::$thumb;

        return new Metadata($object->getName(), null, $thumb);
    }
}
