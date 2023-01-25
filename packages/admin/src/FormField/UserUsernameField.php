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
     *
     * @return FormMapper<UserInterface>
     */
    public function formField(FormMapper $form): FormMapper
    {
        return $form
                ->add('username', null, ['label' => 'admin.user.username.label']);
    }
}
