<?php

namespace Pushword\Admin\FormField;

use Sonata\AdminBundle\Form\FormMapper;

class UserUsernameField extends AbstractField
{
    public function formField(FormMapper $formMapper): FormMapper
    {
        return $formMapper
                ->add('username', null, ['label' => 'admin.user.username.label']);
    }
}
