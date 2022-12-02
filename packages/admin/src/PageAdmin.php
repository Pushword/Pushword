<?php

namespace Pushword\Admin;

use Pushword\Admin\FormField\HostField;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Object\Metadata;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;

/**
 * @extends AbstractAdmin<PageInterface>
 */
class PageAdmin extends AbstractAdmin implements PageAdminInterface
{
    /**
     * @use AdminTrait<PageInterface>
     */
    use AdminTrait;

    /**
     * @var bool
     */
    public $supportsPreviewMode = true;

    protected string $messagePrefix = 'admin.page';

    /**
     * @var string[]
     */
    protected array $fields = [];

    /**
     * @var int[]
     */
    protected array $perPageOptions = [16, 250, 1000];

    protected int $maxPerPage = 1000;

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues = [
            '_page' => 1,
            '_sort_order' => 'DESC',
            '_sort_by' => 'updatedAt',
            '_per_page' => 100,
        ];
    }

    /**
     * @param class-string<PageInterface> $class
     */
    public function __construct(string $code, string $class, string $baseControllerName)
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

    /**
     * @psalm-suppress InvalidArgument
     */
    protected function configureFormFields(FormMapper $form): void
    {
        $this->apps->switchCurrentApp($this->getSubject());

        $fields = $this->getFormFields();
        if (! isset($fields[0]) || ! \is_array($fields[0]) || ! isset($fields[1]) || ! \is_array($fields[1])) {
            throw new \LogicException();
        }

        $form->with('admin.page.mainContent.label', ['class' => 'col-md-9 mainFields']);
        foreach ($fields[0] as $field) {
            $this->addFormField($field, $form);
        }

        $form->end();

        foreach ($fields[1] as $k => $block) {
            if (null === $this->getSubject()->getId() && 'admin.page.revisions' == $k) {
                continue;
            }

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
        $object->setLocale($this->apps->get()->getDefaultLocale()); // always use first app params...
    }

    /**
     * @param ProxyQuery<PageInterface> $queryBuilder
     *
     * @psalm-suppress TooManyArguments
     */
    public function getSearchFilterForTitle(ProxyQuery $queryBuilder, string $alias, string $field, FilterData $filterData): ?bool
    {
        if (! $filterData->hasValue()) {
            return null;
        }

        $exp = new \Doctrine\ORM\Query\Expr();
        $queryBuilder->andWhere(
            (string) $exp->like(
                (string) $exp->concat($alias.'.h1', $alias.'.title', $alias.'.slug'),
                (string) $exp->literal('%'.$filterData->getValue().'%')
            )
        );

        return true;
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        if (\count($this->getApps()->getHosts()) > 1) {
            // $filter->add('host', null, ['label' => 'admin.page.host.label']);
            (new HostField($this))->datagridMapper($filter); // @phpstan-ignore-line
        }

        $filter
            ->add('h1', CallbackFilter::class, [
                'callback' => [$this, 'getSearchFilterForTitle'],
                'label' => 'admin.page.h1.label',
            ]);
        // $filter->add('slug', null, ['label' => 'admin.page.slug.label']);

        $filter->add('mainContent', null, ['label' => 'admin.page.mainContent.label']);

        $filter->add('locale', null, ['label' => 'admin.page.locale.label']);

        if ($this->exists('name')) {
            $filter->add('name', null, ['label' => 'admin.page.name.label']);
        }

        if ($this->exists('parentPage')) {
            $filter->add('parentPage', null, ['label' => 'admin.page.parentPage.label']);
        }

        $filter->add('metaRobots', null, [
            'choices' => [
                'admin.page.metaRobots.choice.noIndex' => 'noindex',
            ],
            'label' => 'admin.page.metaRobots.label',
        ]);

        $filter->add('customProperties', null, ['label' => 'admin.page.customProperties.label']);
    }

    protected function preUpdate(object $object): void
    {
        $object->setUpdatedAt(new \DateTime());
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list->addIdentifier('h1', 'html', [
            'label' => 'admin.page.title.label',
            'template' => '@pwAdmin/page/page_list_titleField.html.twig',
        ]);
        $list->add('updatedAt', 'datetime', [
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

    /**
     * @param PageInterface $object
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     */
    public function getObjectMetadata(object $object): Metadata
    {
        $media = $object->getMainImage();
        if (null !== $media && $this->imageManager->isImage($media)) {
            $thumb = $this->imageManager->getBrowserPath($media, 'thumb');
        } else {
            $thumb = self::$thumb;
        }

        $name = \in_array($object->getName(), ['', null], true) ? $object->getH1() : $object->getName();

        return new Metadata(strip_tags((string) $name), null, $thumb);
    }
}
