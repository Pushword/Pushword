<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\AdminFormFieldManager;
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
    public function __construct(
        protected AdminFormFieldManager $formFieldManager,
        protected AdminInterface $admin
    ) {
    }

    /**
     * @param FormMapper<T> $form
     */
    abstract public function formField(FormMapper $form): void;
}
