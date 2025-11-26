<?php

namespace Pushword\Admin\Controller;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Exception;
use Override;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\Filter\PageSearchFilter;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\FlashBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/** @extends AbstractAdminCrudController<Page> */
class PageCrudController extends AbstractAdminCrudController
{
    protected const FORM_FIELD_KEY = 'admin_page_form_fields';

    protected const MESSAGE_PREFIX = 'admin.page';

    public function __construct(
        protected readonly AdminFormFieldManager $adminFormFieldManager,
        EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        TranslatorInterface $translator,
        private readonly AppPool $apps,
        private readonly PushwordRouteGenerator $routeGenerator,
        private readonly PageController $pageController,
        private readonly PageRepository $pageRepo,
    ) {
        parent::__construct($entityManager, $requestStack, $translator, Page::class);

        $this->syncAppContext();
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->addFormTheme('@pwAdmin/form/page_form_theme.html.twig')
            ->addFormTheme('@PushwordAdminBlockEditor/editorjs_widget.html.twig')
            ->overrideTemplates([
                'crud/edit' => '@pwAdmin/page/edit.html.twig',
            ]);
    }

    #[Override]
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->hideRedirectionsFromIndex()) {
            $alias = $queryBuilder->getRootAliases()[0] ?? 'entity';

            $queryBuilder
                ->andWhere(sprintf('%s.mainContent NOT LIKE :redirectionPrefix', $alias))
                ->setParameter('redirectionPrefix', 'Location:%');
        }

        $queryBuilder->andWhere('entity.slug != :cheatsheetSlug')->setParameter('cheatsheetSlug', PageCheatSheetCrudController::CHEATSHEET_SLUG);

        return $queryBuilder;
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        if ($this->hasMultipleHosts()) {
            $filters->add(
                ChoiceFilter::new('host', 'admin.page.host.label')
                    ->setChoices($this->getHostChoices()),
            );
        }

        $filters
            ->add(PageSearchFilter::new(['h1', 'title', 'slug'], 'admin.page.h1.label'))
            ->add(TextFilter::new('tags', 'admin.page.tags.search_label'))
            ->add(TextFilter::new('slug', 'admin.page.slug.label'))
            ->add(TextFilter::new('mainContent', 'admin.page.mainContent.label'));

        $localeChoices = $this->getLocaleChoices();
        if ([] !== $localeChoices) {
            $filters->add(
                ChoiceFilter::new('locale', 'admin.page.locale.label')
                    ->setChoices($localeChoices),
            );
        } else {
            $filters->add(TextFilter::new('locale', 'admin.page.locale.label'));
        }

        $filters
            ->add(TextFilter::new('name', 'admin.page.name.label'))
            ->add(EntityFilter::new('parentPage', 'admin.page.parentPage.label'))
            ->add(
                ChoiceFilter::new('metaRobots', 'admin.page.metaRobots.label')
                    ->setChoices($this->getMetaRobotsChoices()),
            )
            ->add(TextFilter::new('customProperties', 'admin.page.customProperties.label'));

        return $filters;
    }

    #[Override]
    public function createEntity(string $entityFqcn): Page
    {
        $page = new Page();
        $this->setSubject($page);
        $this->initializeNewPage($page);

        return $page;
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->getIndexFields();
        }

        return $this->getFormFieldsDefinition();
    }

    /**
     * @param Page $entityInstance
     */
    #[Override]
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->setSubject($entityInstance);
        $this->syncAppContext($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);

        $this->refreshPreview($entityInstance);
    }

    /**
     * @param Page $entityInstance
     */
    #[Override]
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->setSubject($entityInstance);
        $this->syncAppContext($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);

        $this->refreshPreview($entityInstance);
    }

    private function getAction(): string
    {
        $currentRequest = $this->requestStack->getCurrentRequest();
        $ea = $currentRequest?->request->all('ea') ?? [];

        $newForm = $ea['newForm'] ?? null;
        $editForm = $ea['editForm'] ?? null;

        $newFormAction = \is_array($newForm) ? ($newForm['btn'] ?? null) : null;
        $editFormAction = \is_array($editForm) ? ($editForm['btn'] ?? null) : null;
        $action = $newFormAction ?? $editFormAction ?? null;

        return \is_string($action) ? $action : '';
    }

    #[Override]
    protected function getRedirectResponseAfterSave(
        AdminContext $context,
        string $action
    ): RedirectResponse {
        $action = $this->getAction(); // fix because $action always return 'edit'

        if (Action::SAVE_AND_RETURN !== $action) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        return $this->redirectToPage($context) ?? parent::getRedirectResponseAfterSave($context, $action);
    }

    public function getPageUrl(Page $page): string
    {
        return $this->routeGenerator->generate(
            $page->getSlug(),
            false,
            null,
            $page->getHost(),
            ! $this->apps->isDefaultHost($page->getHost())
        );
    }

    private function redirectToPage(AdminContext $context): ?RedirectResponse
    {
        $page = $context->getEntity()->getInstance();
        if (! $page instanceof Page) {
            return null;
        }

        $redirectUrl = $this->getPageUrl($page);

        if ('' === $redirectUrl) {
            return null;
        }

        return new RedirectResponse($redirectUrl);
    }

    #[Override]
    public function detail(AdminContext $context): Response
    {
        return $this->redirectToPage($context) ?? throw new Exception('Page not found');
    }

    /**
     * @param array<int|string, mixed>|class-string<AbstractField<Page>> $block
     *
     * @return list<class-string<AbstractField<Page>>>
     */
    private function extractFieldClasses(array|string $block): array
    {
        if (\is_array($block)) {
            if (isset($block['fields']) && \is_array($block['fields'])) {
                /** @var list<class-string<AbstractField<Page>>> $fields */
                $fields = $block['fields'];

                return $fields;
            }

            return $this->filterFieldClassList($block);
        }

        /** @var class-string<AbstractField<Page>> $block */
        return [$block];
    }

    /**
     * @param array<int|string, mixed> $values
     *
     * @return list<class-string<AbstractField<Page>>>
     */
    private function filterFieldClassList(array $values): array
    {
        $classes = [];
        foreach ($values as $value) {
            if (\is_string($value) && is_subclass_of($value, AbstractField::class)) {
                /** @var class-string<AbstractField<Page>> $value */
                $classes[] = $value;
            }
        }

        /** @var list<class-string<AbstractField<Page>>> $classes */
        return $classes;
    }

    private function getHostFromFilter(): ?string
    {
        // based on filter : /edit?filter[host][value][0]=localhost.dev
        $currentRequest = $this->requestStack->getCurrentRequest();

        if (null === $currentRequest) {
            return null;
        }

        $query = $currentRequest->query->all();

        $hostFromFilter = $query['filters']['host']['value'] ?? null; // @phpstan-ignore-line

        if (null !== $hostFromFilter) {
            assert(is_string($hostFromFilter));
        }

        return $hostFromFilter;
    }

    private function syncAppContext(?Page $page = null): void
    {
        $hostFromFilter = $this->getHostFromFilter();
        if (null !== $hostFromFilter) {
            $this->apps->switchCurrentApp($hostFromFilter);

            return;
        }

        if (null === $page || '' === $page->getHost()) {
            return;
        }

        $this->apps->switchCurrentApp($page->getHost());
    }

    private function initializeNewPage(Page $page): void
    {
        if ('' === $page->getLocale()) {
            $page->setLocale($this->apps->get()->getLocale());
        }

        if ('' === $page->getHost()) {
            $page->setHost($this->apps->get()->getMainHost());
        }
    }

    private function refreshPreview(Page $page): void
    {
        $page->setUpdatedAt(new DateTime());

        $flashBag = FlashBag::get($this->getRequest());

        if (null === $flashBag) {
            return;
        }

        try {
            $response = $this->pageController->showPage($page);
            if (Response::HTTP_OK !== $response->getStatusCode()) {
                $flashBag->add('warning', $this->getTranslator()->trans('admin.page.error.generation_failed'));
            }
        } catch (RuntimeError|SyntaxError $runtimeError) {
            $flashBag->add(
                'warning',
                $this->getTranslator()->trans('admin.page.error.generation_failed_with_details', [
                    '%error%' => $runtimeError->getRawMessage(),
                    '%excerpt%' => htmlentities($this->getErrorExcerpt($runtimeError)),
                ])
            );
        }
    }

    private function getErrorExcerpt(RuntimeError|SyntaxError $exception, int $context = 1): string
    {
        $sourceContext = $exception->getSourceContext();
        if (null === $sourceContext) {
            return '';
        }

        $code = $sourceContext->getCode();
        $lines = explode("\n", $code);
        $line = $exception->getTemplateLine();

        $start = max(0, $line - $context - 1);
        $end = min(count($lines) - 1, $line + $context - 1);

        $excerpt = array_slice($lines, $start, $end - $start + 1, true);

        return trim(implode("\n", $excerpt));
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    private function getIndexFields(): iterable
    {
        yield TextField::new('h1', 'admin.page.h1.label')
            ->setTemplatePath('@pwAdmin/page/pageListTitleField.html.twig')
            ->setSortable(false);
        yield DateTimeField::new('updatedAt', 'admin.page.updatedAt.label')
            ->setSortable(true);
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    protected function getFormFieldsDefinition(): iterable
    {
        $instance = $this->getContext()?->getEntity()?->getInstance();
        $page = $instance instanceof Page ? $instance
            : $this->pageRepo->create($this->apps->getMainHost() ?? '');
        $this->setSubject($page);

        $this->adminFormFieldManager->setMessagePrefix(self::MESSAGE_PREFIX);

        /** @var string $formFieldKey */
        $formFieldKey = static::FORM_FIELD_KEY;
        $fields = array_replace(
            [[], [], []],
            $this->adminFormFieldManager->getFormFields($this, $formFieldKey),
        );
        [$mainFields, $sidebarBlocks, $extraBlocks] = $fields;

        yield FormField::addColumn('col-12 col-xl-9 mainFields');
        yield FormField::addFieldset();
        yield from $this->adminFormFieldManager->getEasyAdminFields($mainFields, $this);

        if ([] !== $sidebarBlocks) {
            yield FormField::addColumn('col-12 col-xl-3 columnFields');
            foreach ($sidebarBlocks as $groupName => $block) {
                if (\is_string($groupName)) {
                    yield $this->buildSettingsFieldset($groupName, $block);
                }

                $blockFields = $this->extractFieldClasses($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($blockFields, $this);
            }
        }

        if ([] !== $extraBlocks) {
            yield FormField::addColumn('col-12 extraFields');
            yield FormField::addFieldset('admin.page.extra.label');
            foreach ($extraBlocks as $block) {
                $blockFields = $this->extractFieldClasses($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($blockFields, $this);
            }
        }
    }

    private function hasMultipleHosts(): bool
    {
        return \count($this->apps->getHosts()) > 1;
    }

    /**
     * @return array<string, string>
     */
    private function getHostChoices(): array
    {
        $hosts = $this->apps->getHosts();

        return [] === $hosts ? [] : array_combine($hosts, $hosts);
    }

    /**
     * @return array<string, string>
     */
    private function getLocaleChoices(): array
    {
        $choices = [];
        foreach ($this->apps->getApps() as $app) {
            foreach ($app->getLocales() as $locale) {
                $choices[$locale] = $locale;
            }
        }

        ksort($choices);

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function getMetaRobotsChoices(): array
    {
        return [
            'admin.page.metaRobots.choice.noIndex' => 'noindex',
        ];
    }

    protected function hideRedirectionsFromIndex(): bool
    {
        return true;
    }

    /**
     * @param array<int|string, mixed>|class-string<AbstractField<Page>> $block
     */
    private function buildSettingsFieldset(string $groupName, array|string $block): FormField
    {
        $cssClasses = ['pw-settings-accordion'];
        $cssClasses[] = $this->shouldExpandBlock($block) ? 'pw-settings-open' : 'pw-settings-collapsed';

        return FormField::addFieldset($groupName)
            ->setCssClass(implode(' ', $cssClasses))
            ->setFormTypeOption('attr', [
                'data-pw-panel-key' => $this->buildPanelKey($groupName),
            ]);
    }

    /**
     * @param array<int|string, mixed>|class-string<AbstractField<Page>> $block
     */
    private function shouldExpandBlock(array|string $block): bool
    {
        if (! \is_array($block)) {
            return false;
        }

        if (! \array_key_exists('expand', $block)) {
            return false;
        }

        return (bool) $block['expand'];
    }

    private function buildPanelKey(string $groupName): string
    {
        $normalized = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $groupName), '-'));

        if ('' === $normalized) {
            return 'pw-panel-'.substr(md5($groupName), 0, 12);
        }

        return 'pw-panel-'.$normalized;
    }
}
