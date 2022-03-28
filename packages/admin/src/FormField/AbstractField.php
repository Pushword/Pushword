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
     * @param AdminInterface<T> $admin
     */
    public function __construct(protected AdminInterface $admin)
    {
    }

    /**
     * @param FormMapper<T> $form
     *
     * @return FormMapper<T>
     */
    abstract public function formField(FormMapper $form): FormMapper;
}
