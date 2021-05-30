<?php

namespace Pushword\Admin;

use Pushword\Admin\FormField\HostField;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Object\Metadata;
use Sonata\AdminBundle\Object\MetadataInterface;

class PageAdmin extends AbstractAdmin implements PageAdminInterface
{
    use AdminTrait;

    public $supportsPreviewMode = true;

    protected $messagePrefix = 'admin.page';

    protected array $fields = [];

    protected $perPageOptions = [16, 250, 1000];

    protected $maxPerPage = 1000;

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues = [
            '_page' => 1,
            '_sort_order' => 'DESC',
            '_sort_by' => 'updatedAt',
            '_per_page' => 100,
        ];
    }

    public function __construct($code, $class, $baseControllerName)
    {
        parent::__construct($code, $class, $baseControllerName);
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setListModes(array_merge($this->getListModes(), ['tree' => ['class' => 'fa fa-sitemap']]));
    }

    /**
     * Check if page entity's item $name exist.
     */
    protected function exists(string $name): bool
    {
        return method_exists($this->pageClass, 'get'.$name);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $fields = $this->getFormFields();

        $form->with('admin.page.mainContent.label', ['class' => 'col-md-9 mainFields']);
        foreach ($fields[0] as $field) {
            $this->addFormField($field, $form);
        }
        $form->end();

        foreach ($fields[1] as $k => $block) {
            $fields = $block['fields'] ?? $block;
            $class = isset($block['expand']) ? 'expand' : '';
            $form->with($k, ['class' => 'col-md-3 columnFields '.$class, 'label' => $k]);
            foreach ($fields as $field) {
                $this->addFormField($field, $form);
            }

            $form->end();
        }
    }

    protected function alterNewInstance(object $object): void
    {
        if (! $object instanceof PageInterface) {
            return;
        }

        $object->setLocale($this->apps->get()->getDefaultLocale()); // always use first app params...
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter->add('locale', null, ['label' => 'admin.page.locale.label']);

        if (\count($this->getApps()->getHosts()) > 1) {
            //$filter->add('host', null, ['label' => 'admin.page.host.label']);
            (new HostField($this))->datagridMapper($filter);
        }

        $filter->add('h1', null, ['label' => 'admin.page.h1.label']);

        $filter->add('mainContent', null, ['label' => 'admin.page.mainContent.label']);

        $filter->add('slug', null, ['label' => 'admin.page.slug.label']);

        $filter->add('title', null, ['label' => 'admin.page.title.label']);

        if ($this->exists('name')) {
            $filter->add('name', null, ['label' => 'admin.page.name.label']);
        }

        if ($this->exists('parentPage')) {
            $filter->add('parentPage', null, ['label' => 'admin.page.parentPage.label']);
        }

        if ($this->exists('metaRobots')) {
            $filter->add('metaRobots', null, [
                'choices' => [
                    'admin.page.metaRobots.choice.noIndex' => 'noindex',
                ],
                'label' => 'admin.page.metaRobots.label',
            ]);
        }
    }

    public function preUpdate(object $object): void
    {
        $object->setUpdatedAt(new \Datetime());
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list->addIdentifier('h1', 'html', [
            'label' => 'admin.page.title.label',
            'template' => '@pwAdmin/page/page_list_titleField.html.twig',
        ]);
        $list->add('updatedAt', null, [
            'format' => 'd/m à H:m',
            'label' => 'admin.page.updatedAt.label',
        ]);
        $list->add('_actions', null, [
            'actions' => [
                'edit' => [],
                'show' => [],
                'delete' => [],
            ],
            'row_align' => 'right',
            'header_class' => 'text-right',
            'label' => 'admin.action',
        ]);
    }

    public function getObjectMetadata(object $object): MetadataInterface
    {
        $media = $object->getMainImage();
        if ($media && $this->imageManager->isImage($media)) {
            $thumb = $this->imageManager->getBrowserPath($media, 'thumb');
        } else {
            $thumb = self::$thumb;
        }

        return new Metadata(strip_tags($object->getName(true)), null, $thumb);
    }
}
