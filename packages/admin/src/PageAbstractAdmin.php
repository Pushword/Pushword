<?php

namespace Pushword\Admin;

use Doctrine\ORM\QueryBuilder;
use Pushword\Admin\Controller\PageCRUDController;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Admin\FormField\HostField;
use Pushword\Admin\Utils\Thumb;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Service\ImageManager;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Object\Metadata;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @extends AbstractAdmin<PageInterface>
 *
 * @implements AdminInterface<PageInterface>
 */
abstract class PageAbstractAdmin extends AbstractAdmin implements AdminInterface
{
    final public const FORM_FIELD_KEY = 'admin_page_form_fields';

    /** @var bool */
    public $supportsPreviewMode = true;

    final public const MESSAGE_PREFIX = 'admin.page';

    /** @var string[] */
    protected array $fields = [];

    /** @var int[] */
    protected array $perPageOptions = [16, 250, 1000];

    protected int $maxPerPage = 1000;

    protected ?string $mainColClass = null;

    protected ?string $secondColClass = null;

    protected string $formFieldKey = 'admin_page_form_fields';

    public function __construct(
        private readonly AdminFormFieldManager $adminFormFieldManager,
        private readonly AppPool $apps,
        private readonly ImageManager $imageManager,
        RequestStack $requestStack,
    ) {
        $adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);

        // dd($requestStack->getCurrentRequest()->query->get('host'));
        if (($r = $requestStack->getCurrentRequest()) !== null && ($host = $r->query->get('host')) !== null) {
            $this->apps->switchCurrentApp($host);
        }

        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();

        // $this->setListModes([...$this->getListModes(), ...['tree' => ['class' => 'fa fa-sitemap']]]);
        $this->setBaseControllerName(PageCRUDController::class);
        $this->setTemplate('list', '@pwAdmin/CRUD/mosaic.html.twig');
        $this->setTemplate('show', '@pwAdmin/page/page_show.html.twig');
        $this->setTemplate('edit', '@pwAdmin/page/page_edit.html.twig');
        $this->setTemplate('preview', '@pwAdmin/page/preview.html.twig');
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues = [
            '_page' => 1,
            '_sort_order' => 'DESC',
            '_sort_by' => 'updatedAt',
            '_per_page' => 100,
        ];
    }

    protected function generateBaseRouteName(bool $isChildAdmin = false): string
    {
        return 'admin_page';
    }

    protected function generateBaseRoutePattern(bool $isChildAdmin = false): string
    {
        return 'app/page';
    }

    /**
     * @param ProxyQueryInterface<PageInterface> $query
     */
    protected function getQueryBuilderFrom(ProxyQueryInterface $query): QueryBuilder
    {
        if (! method_exists($query, 'getQueryBuilder')) {
            throw new \Exception();
        }

        $qb = $query->getQueryBuilder();

        return $qb instanceof QueryBuilder ? $qb : throw new \Exception();
    }

    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
        $query = parent::configureQuery($query);

        $qb = $this->getQueryBuilderFrom($query);

        $rootAlias = current($qb->getRootAliases());

        $qb->andWhere($qb->expr()->notLike($rootAlias.'.mainContent', ':mcf'))->setParameter('mcf', 'Location:%');

        $qb->andWhere($qb->expr()->neq($rootAlias.'.slug', ':slug'))->setParameter('slug', 'pushword-cheatsheet');

        return $query;
    }

    /**
     * Check if page entity's item $name exist.
     */
    protected function exists(string $name): bool
    {
        return method_exists($this->getModelClass(), 'get'.$name);
    }

    /**
     * @psalm-suppress InvalidArgument
     */
    protected function configureFormFields(FormMapper $form): void
    {
        $this->adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);

        $this->apps->switchCurrentApp($this->getSubject());
        $fields = $this->adminFormFieldManager->getFormFields($this, $this->formFieldKey);
        // if (! isset($fields[0]) || ! \is_array($fields[0]) || ! isset($fields[1]) || ! \is_array($fields[1])) { throw new \LogicException(); }

        $form->with('admin.page.mainContent.label', ['class' => $this->mainColClass ?? 'col-md-9 mainFields']);
        foreach ($fields[0] as $field) {
            $this->adminFormFieldManager->addFormField($field, $form, $this);
        }

        $form->end();

        foreach ($fields[1] as $k => $block) {
            if (null === $this->getSubject()->getId() && 'admin.page.revisions' == $k) {
                continue;
            }

            /** @var class-string<AbstractField<PageInterface>>[] */
            $blockFields = $block['fields'] ?? $block;
            $class = isset($block['expand']) ? 'expand' : '';
            $form->with($k, ['class' => $this->secondColClass ?? 'col-md-3 columnFields '.$class, 'label' => $k]);
            foreach ($blockFields as $field) {
                $this->adminFormFieldManager->addFormField($field, $form, $this);
            }

            $form->end();
        }
    }

    /**
     * @phpstan-param PageInterface $object
     */
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
        if (\count($this->apps->getHosts()) > 1) {
            // $filter->add('host', null, ['label' => 'admin.page.host.label']);
            (new HostField($this->adminFormFieldManager, $this))->datagridMapper($filter); // @phpstan-ignore-line
        }

        $filter->add('slug', null, ['label' => 'admin.page.slug.label']);

        $filter
            ->add('h1', CallbackFilter::class, [
                'callback' => fn (\Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery $queryBuilder, string $alias, string $field, \Sonata\AdminBundle\Filter\Model\FilterData $filterData): ?bool => $this->getSearchFilterForTitle($queryBuilder, $alias, $field, $filterData),
                'label' => 'admin.page.h1.label',
            ]);

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
            $thumb = Thumb::$thumb;
        }

        $name = \in_array($object->getName(), ['', null], true) ? $object->getH1() : $object->getName();

        return new Metadata(strip_tags((string) $name), null, $thumb);
    }
}
