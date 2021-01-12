<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;

class UserEmailField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper
            ->add('email', null, ['label' => 'admin.user.email.label']);
    }
}
