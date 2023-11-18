<?php

namespace Pushword\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Admin\FormField\AbstractField;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\MediaInterface;
use Pushword\Core\Entity\PageInterface;
use Pushword\Core\Entity\UserInterface;
use Pushword\Core\Service\ImageManager;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AdminFormFieldManager
{
    public ?UserInterface $user;

    /**
     * @param class-string<MediaInterface> $mediaClass
     * @param class-string<UserInterface>  $userClass
     * @param class-string<PageInterface>  $pageClass
     */
    public function __construct(
        public readonly AppPool $apps,
        public readonly string $pageClass,
        public readonly string $mediaClass,
        public readonly string $userClass,
        public readonly EntityManagerInterface $em,
        public readonly RouterInterface $router,
        public readonly \Twig\Environment $twig,
        public readonly ImageManager $imageManager,
        // TokenStorageInterface $securityTokenStorage,
        Security $security,
        public readonly EventDispatcherInterface $eventDispatcher,
    ) {
        /** @var ?UserInterface */
        $user = $security->getUser();
        $this->user = $user; // null === $securityTokenStorage->getToken() || ! ($user = $securityTokenStorage->getToken()->getUser()) instanceof UserInterface ? null : $user; // $security->getUser();
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->em;
    }

    private string $messagePrefix;

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
     * @psalm-suppress  InvalidReturnType // use only phpstan
     * @psalm-suppress  InvalidReturnStatement // use only phpstan
     *
     * @template T of object
     *
     * @param AdminInterface<T> $admin
     *
     * @return array{ 0: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] , 1: class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array<string,  class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array{'fields': class-string<\Pushword\Admin\FormField\AbstractField<T>>[], 'expand': bool}>, 2: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] }
     */
    public function getFormFields(AdminInterface $admin, string $formFieldKey): array
    {
        /** @var array{ 0: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] , 1: class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array<string,  class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array{'fields': class-string<\Pushword\Admin\FormField\AbstractField<T>>[], 'expand': bool}>, 2: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] } */
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
