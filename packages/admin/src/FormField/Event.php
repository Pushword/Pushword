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
     * @param AdminInterface<T>                                                                                                                                                                                                                                                                                                                                                            $admin
     * @param array{ 0: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] , 1: class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array<string,  class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array{'fields': class-string<\Pushword\Admin\FormField\AbstractField<T>>[], 'expand': bool}>, 2: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] } $fields
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
     * @return array{ 0: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] , 1: class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array<string,  class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array{'fields': class-string<\Pushword\Admin\FormField\AbstractField<T>>[], 'expand': bool}>, 2: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] }
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @phpstan-param array{ 0: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] , 1: class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array<string,  class-string<\Pushword\Admin\FormField\AbstractField<T>>[]|array{'fields': class-string<\Pushword\Admin\FormField\AbstractField<T>>[], 'expand': bool}>, 2: class-string<\Pushword\Admin\FormField\AbstractField<T>>[] } $fields
     *
     * @return self<T>
     */
    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }
}
