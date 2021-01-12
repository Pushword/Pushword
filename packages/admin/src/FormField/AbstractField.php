<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;

abstract class AbstractField
{
    protected AdminInterface $admin;

    public function __construct(AdminInterface $admin)
    {
        $this->admin = $admin;
    }

    abstract public function formField(FormMapper $formMapper): FormMapper;
}
