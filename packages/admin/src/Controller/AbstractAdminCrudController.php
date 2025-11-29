<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use Override;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\SharedTrait\IdInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @template T of object
 *
 * @extends AbstractCrudController<T>
 *
 * @implements AdminInterface<T>
 */
abstract class AbstractAdminCrudController extends AbstractCrudController implements AdminInterface
{
    /** @var int[] */
    protected array $pageSizeOptions = [25, 50, 100, 250, 500];

    protected int $defaultPageSize = 100;

    protected ?object $subject = null;

    protected EntityManagerInterface $entityManager;

    protected RequestStack $requestStack;

    protected TranslatorInterface $translator;

    protected AdminFormFieldManager $adminFormFieldManager;

    protected AppPool $apps;

    #[Required]
    public function injectBaseServices(
        AppPool $apps,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        AdminFormFieldManager $adminFormFieldManager,
    ): void {
        $this->entityManager = $entityManager;
        $this->requestStack = $requestStack;
        $this->translator = $translator;
        $this->adminFormFieldManager = $adminFormFieldManager;
        $this->apps = $apps;

        $this->syncAppContext();
    }

    private function getHostFromFilter(): ?string
    {
        // based on filter : /edit?filter[host][value][0]=localhost.dev
        $currentRequest = $this->getRequest();

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

    protected function syncAppContext(?Page $page = null): void
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

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function hasRequest(): bool
    {
        return null !== $this->getRequest();
    }

    public function getRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }

    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    public function getModelClass(): string
    {
        return static::getEntityFqcn();
    }

    /**
     * @return T
     */
    public function getSubject(): object
    {
        /** @var T $subject */
        $subject = $this->subject;

        return $subject;
    }

    protected function normalizePublishedState(string $value): bool
    {
        return \in_array(strtolower($value), ['1', 'true', 'on'], true);
    }

    #[Override]
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $response = parent::getRedirectResponseAfterSave($context, $action);

        $request = $context->getRequest();
        if (! $request->query->has('pwInline')) {
            return $response;
        }

        $targetUrl = $this->withInlineQueryParameters($response->getTargetUrl());
        if ($targetUrl === $response->getTargetUrl()) {
            return $response;
        }

        return new RedirectResponse($targetUrl, $response->getStatusCode(), $response->headers->all());
    }

    private function withInlineQueryParameters(string $url): string
    {
        $originalUrl = $url;
        $fragment = '';

        $hashPos = strpos($url, '#');
        if (false !== $hashPos) {
            $fragment = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        $queryString = '';
        $basePath = $url;

        $queryPos = strpos($url, '?');
        if (false !== $queryPos) {
            $basePath = substr($url, 0, $queryPos);
            $queryString = substr($url, $queryPos + 1);
        }

        $params = [];
        if ('' !== $queryString) {
            parse_str($queryString, $params);
        }

        $hasChanges = false;

        if (! isset($params['pwInline'])) {
            $params['pwInline'] = 1;
            $hasChanges = true;
        }

        if (($params['pwInlineSaved'] ?? null) !== '1') {
            $params['pwInlineSaved'] = 1;
            $hasChanges = true;
        }

        if (! $hasChanges) {
            return $originalUrl;
        }

        $query = http_build_query($params);

        return sprintf('%s?%s%s', $basePath, $query, $fragment);
    }

    /**
     * @param T|null $subject
     *
     * @return T
     */
    public function setSubject(?object $subject = null): object
    {
        $modelClass = $this->getModelClass();

        if (null === $subject) {
            $subject = new $modelClass();
        }

        if (! $subject instanceof $modelClass) {
            throw new InvalidArgumentException(sprintf('Expected subject of type "%s", "%s" given.', $modelClass, $subject::class));
        }

        $this->subject = $subject;

        return $this->subject;
    }

    /**
     * Normalize a block definition to a list of field class names.
     *
     * @param array<int|string, mixed>|class-string<AbstractField<T>> $block
     *
     * @return list<class-string<AbstractField<T>>>
     */
    protected function normalizeBlock(array|string $block): array
    {
        if (\is_array($block)) {
            if (isset($block['fields']) && \is_array($block['fields'])) {
                /** @var list<class-string<AbstractField<T>>> $fields */
                $fields = $block['fields'];

                return $fields;
            }

            return $this->filterFieldClassList($block);
        }

        /** @var class-string<AbstractField<T>> $block */
        return [$block];
    }

    /**
     * Filter an array to only include valid field class names.
     *
     * @param array<int|string, mixed> $values
     *
     * @return list<class-string<AbstractField<T>>>
     */
    protected function filterFieldClassList(array $values): array
    {
        $classes = [];
        foreach ($values as $value) {
            if (\is_string($value) && is_subclass_of($value, AbstractField::class)) {
                /** @var class-string<AbstractField<T>> $value */
                $classes[] = $value;
            }
        }

        /** @var list<class-string<AbstractField<T>>> $classes */
        return $classes;
    }

    // ========== Pagination PageSize ==========

    #[Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters = parent::configureResponseParameters($responseParameters);
        $context = $this->getContext();

        if (null === $context || Crud::PAGE_INDEX !== $context->getCrud()?->getCurrentPage()) {
            return $responseParameters;
        }

        $responseParameters->set('pageSizeOptions', $this->getPageSizeOptions());
        $responseParameters->set('currentPageSize', $this->getCurrentPageSize());

        return $responseParameters;
    }

    protected function getRequestedPageSize(): int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return $this->defaultPageSize;
        }

        $session = $request->getSession();
        $sessionKey = $this->getPageSizeSessionKey();

        // Priority 1: Query parameter
        if ($request->query->has('pageSize')) {
            $size = $request->query->getInt('pageSize');

            if (\in_array($size, $this->pageSizeOptions, true)) {
                $session->set($sessionKey, $size);

                return $size;
            }
        }

        // Priority 2: Session
        if ($session->has($sessionKey)) {
            $size = $session->get($sessionKey);
            if (\is_int($size) && \in_array($size, $this->pageSizeOptions, true)) {
                return $size;
            }
        }

        // Priority 3: Default
        return $this->defaultPageSize;
    }

    /**
     * @return int[]
     */
    public function getPageSizeOptions(): array
    {
        return $this->pageSizeOptions;
    }

    public function getCurrentPageSize(): int
    {
        return $this->getRequestedPageSize();
    }

    private function getPageSizeSessionKey(): string
    {
        return 'admin_page_size_'.static::class;
    }

    // ========== Multi-host support ==========

    protected function hasMultipleHosts(): bool
    {
        return \count($this->apps->getHosts()) > 1;
    }

    /**
     * @return array<string, string>
     */
    protected function getHostChoices(): array
    {
        $hosts = $this->apps->getHosts();

        return [] === $hosts ? [] : array_combine($hosts, $hosts);
    }

    // ========== Inline editing support ==========

    /**
     * Get the entity type identifier for CSRF tokens.
     * Override in child controllers if needed.
     */
    protected function getEntityTypeIdentifier(): string
    {
        return 'entity';
    }

    protected function getPublishedToggleTokenId(object $entity): string
    {
        return sprintf('%s_toggle_published_%d', $this->getEntityTypeIdentifier(), $this->getEntityId($entity));
    }

    protected function getInlineTokenId(object $entity): string
    {
        return sprintf('%s_inline_%d', $this->getEntityTypeIdentifier(), $this->getEntityId($entity));
    }

    private function getEntityId(object $entity): int
    {
        return $entity instanceof IdInterface ? ($entity->getId() ?? 0) : 0;
    }
}
