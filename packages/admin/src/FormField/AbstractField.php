<?php

namespace Pushword\Admin\FormField;

use Pushword\Admin\AdminInterface;
use Pushword\Core\Entity\PageInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\Form\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

abstract class AbstractField
{
    protected AdminInterface $admin;

    public function __construct(AdminInterface $admin)
    {
        $this->admin = $admin;
    }

    abstract public function formField(FormMapper $formMapper): FormMapper;
}
