<?php

namespace Pushword\Admin\FormField;

use Pushword\Core\Entity\UserInterface;
use Sonata\AdminBundle\Form\FormMapper;

/**
 * @extends AbstractField<UserInterface>
 */
class UserUsernameField extends AbstractField
{
    /**
     * @param FormMapper<UserInterface> $form
     */
    public function formField(FormMapper $form): void
    {
        $form
                    ->add('username', null, ['label' => 'admin.user.username.label']);
    }
}
