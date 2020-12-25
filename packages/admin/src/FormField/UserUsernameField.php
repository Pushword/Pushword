<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\User;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<User>
 */
class UserUsernameField extends AbstractField
{
    /**
     * @param FormMapper<User> $form
     */
    public function formField(FormMapper $form): void
    {
        $form
                    ->add('username', null, ['label' => 'admin.user.username.label']);
    }
}
