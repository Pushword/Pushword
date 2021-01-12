<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserPasswordField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper
        ->add('plainPassword', TextType::class, [
            'required' => (! $this->admin->getSubject() || null === $this->admin->getSubject()->getId()),
            'label' => 'admin.user.password.label',
        ]);
    }
}
