<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @template T of object
 */
abstract class AbstractField
{
    /**
     * @var AdminInterface<T>
     */
    protected AdminInterface $admin;

    /**
     * @param AdminInterface<T> $admin
     */
    public function __construct(AdminInterface $admin)
    {
        $this->admin = $admin;
    }

    /**
     * @param FormMapper<T> $form
     *
     * @return FormMapper<T>
     */
    abstract public function formField(FormMapper $form): FormMapper;
}
