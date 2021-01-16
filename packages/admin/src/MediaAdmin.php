<?php

namespace Pushword\Admin;

use Pushword\Admin\FormField\CustomPropertiesField;
use Pushword\Core\Repository\Repository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Object\Metadata; //use Sonata\BlockBundle\Meta\Metadata;
use Sonata\AdminBundle\Object\MetadataInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class MediaAdmin extends AbstractAdmin implements MediaAdminInterface
{
    use AdminTrait;

    protected $datagridValues = [
        '_page' => 1,
        '_sort_order' => 'DESC',
        '_sort_by' => 'updatedAt',
    ];

    private $relatedPages;

    private $messagePrefix = 'admin.media';

    protected function configureFormFields(FormMapper $formMapper): void
    {
        $media = $this->getSubject();

        $formMapper->with('Media', ['class' => 'col-md-8'])

            ->add('mediaFile', FileType::class, [
                'label' => 'admin.media.mediaFile.label',
                'required' => $this->getSubject() && $this->getSubject()->getMedia() ? false : true,
            ])
            ->add('name', TextType::class, [
                'required' => $this->getSubject() && $this->getSubject()->getMedia() ? true : false,
                'help_html' => true,
                'help' => 'admin.media.name.help',
                'label' => 'admin.media.name.label',
                'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
            ])
            ->add('slugForce', TextType::class, [
                'label' => 'admin.page.slug.label',
                'help_html' => true,
                'required' => false,
                'help' => $this->getSubject() && $this->getSubject()->getSlug()
                    ? '<span class="btn btn-link" onclick="toggleDisabled()" id="disabledLinkSlug">
                        <i class="fa fa-unlock"></i></span>
                        <script>function toggleDisabled() {
                            $(".slug_disabled").first().removeAttr("disabled");
                            $(".slug_disabled").first().focus();
                            $("#disabledLinkSlug").first().remove();
                        }</script>'
                        .'<small>Changer le slug change l\'URL de l\'image et peut créer des erreurs.</small>'
                    : 'admin.page.slug.help',
                'attr' => [
                    'class' => 'slug_disabled',
                    ($this->getSubject() ? ($this->getSubject()->getSlug() ? 'disabled' : 't') : 't') => '',
                ],
            ])
            ->end();

        $formMapper->with('Params', ['class' => 'col-md-4']);

        (new CustomPropertiesField($this))->formField($formMapper);

        $formMapper->add('names', null, [
            'required' => false,
            'help_html' => true, 'help' => 'admin.media.names.help',
            'label' => 'admin.media.names.label',
            'attr' => ['ismedia' => 1, 'class' => 'col-md-6'],
        ]);

        $formMapper->end();

        if ($media->getMedia()) {
            $formMapper->with('admin.media.preview.label', [
                'class' => 'col-md-12',
                'description' => $this->showMediaPreview(),
                //'empty_message' => false, // to uncomment when sonataAdmin 3.62 is released
            ])->end();

            if ($this->issetRelatedPages()) {
                $formMapper->with('admin.media.related.label', [
                    'class' => 'col-md-12',
                    'description' => $this->showRelatedPages(),
                    //'empty_message' => false, /// to uncomment when sonataAdmin 3.62 is released
                ])->end();
            }
        }
    }

    protected function showMediaPreview(): string
    {
        $media = $this->getSubject();

        $template = false !== strpos($media->getMimeType(), 'image/') ?
            '@pwAdmin/media/media_show.preview_image.html.twig'
            : '@pwAdmin/media/media_show.preview.html.twig';

        return $this->twig->render(
            $template,
            [
                'media' => $media,
            ]
        );
    }

    protected function issetRelatedPages(): bool
    {
        $relatedPages = $this->getRelatedPages();

        if (! empty($relatedPages['content'])
            || $relatedPages['gallery']->count() > 0
            || $relatedPages['mainImage']->count() > 0
        ) {
            return true;
        } else {
            return false;
        }
    }

    protected function getRelatedPages(): ?array
    {
        if (null !== $this->relatedPages) {
            return $this->relatedPages;
        }

        $media = $this->getSubject();

        $pages = Repository::getPageRepository($this->em, $this->pageClass)
            ->getPagesUsingMedia($this->liipImage->getBrowserPath($media->getFullPath(), 'default'));

        $this->relatedPages = [
            'content' => $pages,
            'gallery' => $media->getPageHasMedias(),
            'mainImage' => $media->getMainImagePages(),
        ];

        return $this->relatedPages;
    }

    protected function showRelatedPages(): string
    {
        return $this->twig->render(
            '@pwAdmin/media/media_show.relatedPages.html.twig',
            $this->getRelatedPages()
        );
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
        $listMapper->add('_action', null, [
            'actions' => [
                'edit' => [],
                'delete' => [],
            ],
        ]);
    }

    public function getObjectMetadata($media): MetadataInterface
    {
        if (false !== strpos($media->getMimeType(), 'image/')) {
            $fullPath = '/'.$media->getRelativeDir().'/'.$media->getMedia();
            $thumb = $this->liipImage->getBrowserPath($fullPath, 'thumb');
        } else {
            $thumb = self::$thumb;
        }

        return new Metadata($media->getName(), null, $thumb);
    }
}
