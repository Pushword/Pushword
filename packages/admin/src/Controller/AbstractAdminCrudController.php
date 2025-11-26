<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
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

    /**
     * @param T $subject
     */
    public function setSubject(object $subject): void
    {
        $modelClass = $this->getModelClass();

        if (! $subject instanceof $modelClass) {
            throw new InvalidArgumentException(sprintf('Expected subject of type "%s", "%s" given.', $modelClass, $subject::class));
        }

        $this->subject = $subject;
    }
}
