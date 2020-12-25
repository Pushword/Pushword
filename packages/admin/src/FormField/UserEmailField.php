<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\User;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<User>
 */
class UserEmailField extends AbstractField
{
    /**
     * @param FormMapper<User> $form
     */
    public function formField(FormMapper $form): void
    {
        $form
                ->add('email', null, ['label' => 'admin.user.email.label']);
    }
}
