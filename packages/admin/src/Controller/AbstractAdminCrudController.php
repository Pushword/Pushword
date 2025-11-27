<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use Override;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
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
    #[Override]
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $response = parent::getRedirectResponseAfterSave($context, $action);

        $request = $context->getRequest();
        if (null === $request || ! $request->query->has('pwInline')) {
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
}
