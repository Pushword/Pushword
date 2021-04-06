<?php

namespace Pushword\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Object\Metadata; //use Sonata\BlockBundle\Meta\Metadata;
use Sonata\AdminBundle\Object\MetadataInterface;

final class MediaAdmin extends AbstractAdmin implements MediaAdminInterface
{
    use AdminTrait;

    private $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'updatedAt',
    ];

    private $relatedPages;

    private $messagePrefix = 'admin.media';

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $fields = $this->getFormFields('admin_media_form_fields');

        $formMapper->with('Media', ['class' => 'col-md-8']);
        foreach ($fields[0] as $field) {
            $this->addFormField($field, $formMapper);
        }
        $formMapper->end();

        $formMapper->with('Params', ['class' => 'col-md-4']);
        foreach ($fields[1] as $field) {
            $this->addFormField($field, $formMapper);
        }
        $formMapper->end();

        // preview
        foreach ($fields[2] as $field) {
            $this->addFormField($field, $formMapper);
        }
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper): void
    {
        $datagridMapper->add('name', null, [
            'label' => 'admin.media.name.label',
        ]);
        $datagridMapper->add('names', null, [
            'label' => 'admin.media.names.label',
        ]);
    }

    protected function configureListFields(ListMapper $listMapper): void
    {
        $this->setMosaicDefaultListMode();

        $listMapper->add('name', null, [
            'label' => 'admin.media.name.label',
        ]);
        $listMapper->add('createdAt', null, [
            'label' => 'admin.media.createdAt.label',
            'format' => 'd/m/y',
        ]);
        $listMapper->add('mainColor', null, [
            'label' => 'admin.media.mainColor.label',
        ]);
        $listMapper->add('_actions', null, [
            'actions' => [
                'edit' => [],
                'delete' => [],
            ],
        ]);
    }

    public function getObjectMetadata($media): MetadataInterface
    {
        if (false !== strpos($media->getMimeType(), 'image/')) {
            $thumb = $this->imageManager->getBrowserPath($media, 'thumb');
        } else {
            $thumb = self::$thumb;
        }

        return new Metadata($media->getName(), null, $thumb);
    }
}
