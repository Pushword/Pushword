<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\User;
use Pushword\Core\Service\ImageManager;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class AdminFormFieldManager
{
    public ?User $user;

    public function __construct(
        public readonly AppPool $apps,
        public readonly EntityManagerInterface $em,
        public readonly RouterInterface $router,
        public readonly Environment $twig,
        public readonly ImageManager $imageManager,
        // TokenStorageInterface $securityTokenStorage,
        Security $security,
        public readonly EventDispatcherInterface $eventDispatcher,
    ) {
        /** @var ?User */
        $user = $security->getUser();
        $this->user = $user; // null === $securityTokenStorage->getToken() || ! ($user = $securityTokenStorage->getToken()->getUser()) instanceof User ? null : $user; // $security->getUser();
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    private string $messagePrefix = '';

    public function getMessagePrefix(): string
    {
        return $this->messagePrefix;
    }

    public function setMessagePrefix(string $messagePrefix): self
    {
        $this->messagePrefix = $messagePrefix;

        return $this;
    }

    /**
     * @template T of object
     *
     * @param AdminInterface<T> $admin
     *
     * @return array{0: class-string<AbstractField<T>>[], 1: (class-string<AbstractField<T>>[]|array<string, (class-string<AbstractField<T>>[]|array{fields: class-string<AbstractField<T>>[], expand: bool})>), 2: class-string<AbstractField<T>>[]}
     */
    public function getFormFields(AdminInterface $admin, string $formFieldKey): array
    {
        /** @var array{0: class-string<AbstractField<T>>[], 1: (class-string<AbstractField<T>>[]|array<string, (class-string<AbstractField<T>>[]|array{fields: class-string<AbstractField<T>>[], expand: bool})>), 2: class-string<AbstractField<T>>[]} */
        $fields = $this->apps->get()->get($formFieldKey);

        $event = new FormEvent($admin, $fields, $this);
        $this->eventDispatcher->dispatch($event, FormEvent::NAME);

        return $event->getFields();
    }

    /**
     * @template T of object
     *
     * @param class-string<AbstractField<T>> $field
     * @param FormMapper<T>                  $form
     * @param AdminInterface<T>              $admin
     */
    public function addFormField(string $field, FormMapper $form, AdminInterface $admin): void
    {
        (new $field($this, $admin))->formField($form);
    }
}
