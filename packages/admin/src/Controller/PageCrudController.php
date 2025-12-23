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
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Exception;
use Override;
use Pushword\Admin\Filter\PageSearchFilter;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Controller\PageController;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\Core\Router\PushwordRouteGenerator;
use Pushword\Core\Utils\FlashBag;
use Pushword\Core\Utils\TwigErrorExtractor;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/** @extends AbstractAdminCrudController<Page> */
class PageCrudController extends AbstractAdminCrudController
{
    protected const FORM_FIELD_KEY = 'admin_page_form_fields';

    protected const MESSAGE_PREFIX = 'admin.page';

    public function __construct(
        private readonly PushwordRouteGenerator $routeGenerator,
        private readonly PageController $pageController,
        private readonly PageRepository $pageRepo,
        private readonly TwigErrorExtractor $errorExtractor,
    ) {
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
            ->setPaginatorPageSize($this->getRequestedPageSize())
            ->addFormTheme('@pwAdmin/form/page_form_theme.html.twig')
            ->addFormTheme('@PushwordAdminBlockEditor/editorjs_widget.html.twig')
            ->overrideTemplates([
                'crud/index' => '@pwAdmin/page/index.html.twig',
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
                ChoiceFilter::new('host', 'adminPageHostLabel')
                    ->setChoices($this->getHostChoices()),
            );
        }

        $filters
            ->add(PageSearchFilter::new(['h1', 'title', 'slug'], 'adminPageH1Label'))
            ->add(TextFilter::new('tags', 'adminPageTagsSearchLabel'))
            ->add(TextFilter::new('slug', 'adminPageSlugLabel'))
            ->add(TextFilter::new('mainContent', 'adminPageMainContentLabel'));

        $localeChoices = $this->getLocaleChoices();
        if ([] !== $localeChoices) {
            $filters->add(
                ChoiceFilter::new('locale', 'adminPageLocaleLabel')
                    ->setChoices($localeChoices),
            );
        } else {
            $filters->add(TextFilter::new('locale', 'adminPageLocaleLabel'));
        }

        $filters
            ->add(TextFilter::new('name', 'adminPageNameLabel'))
            ->add(EntityFilter::new('parentPage', 'adminPageParentPageLabel'))
            ->add(
                ChoiceFilter::new('metaRobots', 'adminPageMetaRobotsLabel')
                    ->setChoices($this->getMetaRobotsChoices()),
            )
            ->add(TextFilter::new('customProperties', 'adminPageCustomPropertiesLabel'));

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
        $currentRequest = $this->getRequest();
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
                $flashBag->add('warning', $this->getTranslator()->trans('adminPageErrorGenerationFailed'));
            }
        } catch (RuntimeError|SyntaxError $runtimeError) {
            $flashBag->add(
                'warning',
                $this->getTranslator()->trans('adminPageErrorGenerationFailedWithDetails', [
                    '%error%' => $runtimeError->getRawMessage(),
                    '%excerpt%' => htmlentities($this->errorExtractor->getErrorExcerpt($runtimeError)),
                ]),
            );
        }
    }

    /**
     * @return iterable<FieldInterface|string>
     */
    private function getIndexFields(): iterable
    {
        yield DateTimeField::new('publishedAt', 'adminPagePublishedAtLabel')
            ->setSortable(true)
            ->setTemplatePath('@pwAdmin/components/published_toggle.html.twig');

        yield IntegerField::new('weight', 'adminPageWeightLabel')
            ->setSortable(true)
            ->setTemplatePath('@pwAdmin/components/weight_inline_field.html.twig');

        yield TextField::new('h1', 'adminPageH1Label')
            ->setTemplatePath('@pwAdmin/page/pageListTitleField.html.twig')
            ->setSortable(false);
        yield DateTimeField::new('updatedAt', 'adminPageUpdatedAtLabel')
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

                $blockFields = $this->normalizeBlock($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($blockFields, $this);
            }
        }

        if ([] !== $extraBlocks) {
            yield FormField::addColumn('col-12 extraFields');
            yield FormField::addFieldset('adminPageExtraLabel');
            foreach ($extraBlocks as $block) {
                $blockFields = $this->normalizeBlock($block);
                yield from $this->adminFormFieldManager->getEasyAdminFields($blockFields, $this);
            }
        }
    }

    #[Override]
    protected function getEntityTypeIdentifier(): string
    {
        return 'page';
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
            'adminPageMetaRobotsChoiceNoIndex' => 'noindex',
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

    #[Route(path: '/admin/page/{id}/toggle-published', name: 'pushword_page_toggle_publish', methods: ['POST'])]
    public function togglePublished(Request $request, Page $page): Response
    {
        $token = (string) $request->request->get('_token');

        if (! $this->isCsrfTokenValid($this->getPublishedToggleTokenId($page), $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $shouldPublish = $this->normalizePublishedState((string) $request->request->get('published'));

        $page->setPublishedAt($shouldPublish ? new DateTime() : null);

        $this->getEntityManager()->flush();

        return new Response($this->adminFormFieldManager->twig->render('@pwAdmin/components/published_toggle.html.twig', [
            'entity' => ['instance' => $page],
            'value' => $page->getPublishedAt(),
            'field' => null,
        ]));
    }

    #[Route(path: '/admin/page/{id}/inline-update', name: 'pushword_page_inline_update', methods: ['POST'])]
    public function inlineUpdate(Request $request, Page $page): Response
    {
        $token = (string) $request->request->get('_token');

        if (! $this->isCsrfTokenValid($this->getInlineTokenId($page), $token)) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $field = trim((string) $request->request->get('field', ''));
        $value = (string) $request->request->get('value', '');

        if ('' === $field || ! $this->applyInlineUpdate($page, $field, $value)) {
            return new Response('Field not editable.', Response::HTTP_BAD_REQUEST);
        }

        $this->getEntityManager()->flush();

        $template = 'weight' === $field
            ? '@pwAdmin/components/weight_inline_field.html.twig'
            : '@pwAdmin/page/pageListTitleField.html.twig';

        return new Response($this->adminFormFieldManager->twig->render($template, [
            'entity' => ['instance' => $page],
            'value' => 'weight' === $field ? $page->getWeight() : null,
            'field' => null,
        ]));
    }

    private function applyInlineUpdate(Page $page, string $field, string $value): bool
    {
        return match ($field) {
            'tags' => $this->updateTags($page, $value),
            'weight' => $page->setWeight($value),
            default => false,
        };
    }

    private function updateTags(Page $page, string $value): bool
    {
        $page->setTags($value);

        return true;
    }
}
