<?php

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use LogicException;
use Pushword\Admin\AdminInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
    private ?object $subject = null;

    /**
     * @param class-string<T> $modelClass
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly string $modelClass,
    ) {
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function hasRequest(): bool
    {
        return null !== $this->requestStack->getCurrentRequest();
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
        return $this->modelClass;
    }

    /**
     * @return T
     */
    public function getSubject(): object
    {
        if (null === $this->subject) {
            throw new LogicException(sprintf('No subject defined for admin "%s".', static::class));
        }

        /** @var T $subject */
        $subject = $this->subject;

        return $subject;
    }

    /**
     * @param T $subject
     */
    public function setSubject(object $subject): void
    {
        if (! $subject instanceof $this->modelClass) {
            throw new InvalidArgumentException(sprintf('Expected subject of type "%s", "%s" given.', $this->modelClass, $subject::class));
        }

        $this->subject = $subject;
    }
}
