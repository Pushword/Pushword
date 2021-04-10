<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\AdminInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;

abstract class AbstractField
{
    protected AdminInterface $admin;

    public function __construct(AdminInterface $admin)
    {
        $this->admin = $admin;
    }

    /**
     * Undocumented function.
     *
     * @param object|DatagridMapper $formMapper
     *
     * @return object|DatagridMapper
     */
    abstract public function formField(FormMapper $formMapper): FormMapper;
}
