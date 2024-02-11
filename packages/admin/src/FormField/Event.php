<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\AdminFormFieldManager;
use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Contracts\EventDispatcher\Event as SfEvent;

/**
 * @template T of object
 */
class Event extends SfEvent
{
    /** @var string */
    final public const NAME = 'pushword.admin.load_field';

    /**
     * @param AdminInterface<T>                                                                                                                                                                                                                      $admin
     * @param array{0: class-string<AbstractField<T>>[], 1: (class-string<AbstractField<T>>[]|array<string, (class-string<AbstractField<T>>[]|array{fields: class-string<AbstractField<T>>[], expand: bool})>), 2: class-string<AbstractField<T>>[]} $fields
     */
    public function __construct(
        private readonly AdminInterface $admin,
        private array $fields,
        private readonly AdminFormFieldManager $formFieldManager,
    ) {
    }

    public function getFormFieldManager(): AdminFormFieldManager
    {
        return $this->formFieldManager;
    }

    /**
     * @return AdminInterface<T>
     */
    public function getAdmin(): AdminInterface
    {
        return $this->admin;
    }

    /**
     * @return array{0: class-string<AbstractField<T>>[], 1: (class-string<AbstractField<T>>[]|array<string, (class-string<AbstractField<T>>[]|array{fields: class-string<AbstractField<T>>[], expand: bool})>), 2: class-string<AbstractField<T>>[]}
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @phpstan-param array{0: class-string<AbstractField<T>>[], 1: (class-string<AbstractField<T>>[] | array<string, (class-string<AbstractField<T>>[] | array{fields: class-string<AbstractField<T>>[], expand: bool})>), 2: class-string<AbstractField<T>>[]} $fields
     *
     * @return self<T>
     */
    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }
}
